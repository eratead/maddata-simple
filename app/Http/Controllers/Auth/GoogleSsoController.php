<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\SsoLoginException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\SsoLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleSsoController extends Controller
{
    public function __construct(
        private readonly SsoLinkService $ssoLinkService,
    ) {}

    /**
     * Redirect the user to Google's OAuth authorization page.
     */
    public function redirect(Request $request): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the OAuth callback from Google.
     *
     * Distinguishes two flows via the `state` parameter:
     * - Login flow:  state is the CSRF token set by Socialite (no "link:" prefix)
     * - Link flow:   state = "link:{userId}:{hmac}" — set by SignInMethodsController
     */
    public function callback(Request $request): RedirectResponse
    {
        // Detect the link flow via our custom state prefix
        $state = $request->query('state', '');

        if ($this->isLinkState($state)) {
            return $this->handleLinkCallback($request, $state);
        }

        return $this->handleLoginCallback();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function handleLoginCallback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('login')
                ->with('error', 'Google sign-in failed. Please try again.');
        }

        try {
            $user = $this->ssoLinkService->resolveLogin($googleUser);
        } catch (SsoLoginException $e) {
            return redirect()->route('login')->with('error', $e->getMessage());
        }

        Auth::login($user, remember: false);

        session()->regenerate();

        // Mark this session as SSO-authenticated. The RequireTwoFactor middleware
        // fast-paths any request with login_method=sso, skipping the TOTP gate.
        session([
            'login_method' => 'sso',
            '2fa_verified' => true,
        ]);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function handleLinkCallback(Request $request, string $state): RedirectResponse
    {
        // Parse "link:{userId}:{hmac}"
        $parts = explode(':', $state, 3);

        if (count($parts) !== 3 || $parts[0] !== 'link') {
            return redirect()->route('settings.sign-in-methods.index')
                ->with('error', 'Invalid link request. Please try again.');
        }

        [, $userId, $hmac] = $parts;

        // Verify HMAC: prevents forged link-state parameters
        $expected = hash_hmac('sha256', 'link:'.$userId, config('app.key'));

        if (! hash_equals($expected, $hmac)) {
            return redirect()->route('settings.sign-in-methods.index')
                ->with('error', 'Invalid or expired link request. Please try again.');
        }

        $user = User::find((int) $userId);

        if (! $user) {
            return redirect()->route('settings.sign-in-methods.index')
                ->with('error', 'User not found.');
        }

        // The user must be logged in and match the state userId (prevents CSRF-style hijack)
        if (! Auth::check() || Auth::id() !== $user->id) {
            return redirect()->route('login')
                ->with('error', 'Please sign in before connecting a Google account.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('settings.sign-in-methods.index')
                ->with('error', 'Google authorization failed. Please try again.');
        }

        // Check the Google sub is not already used by a different user
        if (User::where('google_sub', $googleUser->getId())->where('id', '!=', $user->id)->exists()) {
            return redirect()->route('settings.sign-in-methods.index')
                ->with('error', 'This Google account is already linked to another MadData account.');
        }

        $this->ssoLinkService->link($user, $googleUser);

        return redirect()->route('settings.sign-in-methods.index')
            ->with('success', 'Google account connected successfully.');
    }

    /**
     * Determine whether the OAuth state parameter carries our custom link marker.
     */
    private function isLinkState(string $state): bool
    {
        return str_starts_with($state, 'link:');
    }
}
