<?php

namespace App\Http\Controllers\Auth;

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
     * Handle the OAuth callback from Google.
     *
     * Recognises three state prefixes:
     * - "link:{userId}:{hmac}"       → settings-page link flow (existing)
     * - "2fa_setup:{userId}:{hmac}"  → first-time Google-as-2FA setup
     * - "2fa_verify:{userId}:{hmac}" → Google second-factor verification
     *
     * Any other state (including the old Socialite CSRF token used by the
     * removed anonymous login button) is rejected with a flash error.
     */
    public function callback(Request $request): RedirectResponse
    {
        $state = (string) $request->query('state', '');

        if (str_starts_with($state, 'link:')) {
            return $this->handleLinkCallback($state);
        }

        if (str_starts_with($state, '2fa_setup:')) {
            return $this->handleSetupCallback($state);
        }

        if (str_starts_with($state, '2fa_verify:')) {
            return $this->handleVerifyCallback($state);
        }

        // Unknown state — could be an old bookmark or a forged request.
        return redirect()->route('login')
            ->with('error', 'Invalid or expired Google request. Please sign in with email and password.');
    }

    // -------------------------------------------------------------------------
    // 2FA setup: link Google and immediately mark 2fa_verified
    // -------------------------------------------------------------------------

    private function handleSetupCallback(string $state): RedirectResponse
    {
        [$userId, $hmac] = $this->parseHmacState($state, '2fa_setup');

        if ($userId === null || ! $this->verifyHmac('2fa_setup:'.$userId, $hmac)) {
            return redirect()->route('2fa.setup')
                ->with('error', 'Invalid or expired request. Please try again.');
        }

        $user = $this->resolveAuthenticatedUser((int) $userId);
        if ($user instanceof RedirectResponse) {
            return $user;
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('2fa.setup')
                ->with('error', 'Google authorization failed. Please try again.');
        }

        // Prevent hijacking: ensure this Google sub isn't linked to a different account
        if (User::where('google_sub', $googleUser->getId())->where('id', '!=', $user->id)->exists()) {
            return redirect()->route('2fa.setup')
                ->with('error', 'This Google account is already linked to another MadData account.');
        }

        $this->ssoLinkService->link($user, $googleUser);

        session(['2fa_verified' => true]);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    // -------------------------------------------------------------------------
    // 2FA verify: assert Google sub matches, then grant access
    // -------------------------------------------------------------------------

    private function handleVerifyCallback(string $state): RedirectResponse
    {
        [$userId, $hmac] = $this->parseHmacState($state, '2fa_verify');

        if ($userId === null || ! $this->verifyHmac('2fa_verify:'.$userId, $hmac)) {
            return redirect()->route('2fa.challenge')
                ->with('error', 'Invalid or expired request. Please try again.');
        }

        $user = $this->resolveAuthenticatedUser((int) $userId);
        if ($user instanceof RedirectResponse) {
            return $user;
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('2fa.challenge')
                ->with('error', 'Google authorization failed. Please try again.');
        }

        // Critical: the Google sub MUST match what we stored — reject if the user
        // authenticated to Google with a different account.
        if ($googleUser->getId() !== $user->google_sub) {
            return redirect()->route('2fa.challenge')
                ->with('error', 'The Google account used does not match the one linked to your MadData account.');
        }

        session(['2fa_verified' => true]);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    // -------------------------------------------------------------------------
    // Settings-page link flow (unchanged)
    // -------------------------------------------------------------------------

    private function handleLinkCallback(string $state): RedirectResponse
    {
        $parts = explode(':', $state, 3);

        if (count($parts) !== 3 || $parts[0] !== 'link') {
            return redirect()->route('settings.sign-in-methods.index')
                ->with('error', 'Invalid link request. Please try again.');
        }

        [, $userId, $hmac] = $parts;

        if (! $this->verifyHmac('link:'.$userId, $hmac)) {
            return redirect()->route('settings.sign-in-methods.index')
                ->with('error', 'Invalid or expired link request. Please try again.');
        }

        $user = User::find((int) $userId);

        if (! $user) {
            return redirect()->route('settings.sign-in-methods.index')
                ->with('error', 'User not found.');
        }

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

        if (User::where('google_sub', $googleUser->getId())->where('id', '!=', $user->id)->exists()) {
            return redirect()->route('settings.sign-in-methods.index')
                ->with('error', 'This Google account is already linked to another MadData account.');
        }

        $this->ssoLinkService->link($user, $googleUser);

        return redirect()->route('settings.sign-in-methods.index')
            ->with('success', 'Google account connected successfully.');
    }

    // -------------------------------------------------------------------------
    // Shared helpers
    // -------------------------------------------------------------------------

    /**
     * Parse a "{prefix}:{userId}:{hmac}" state string.
     * Returns [userId, hmac] or [null, null] on malformed input.
     *
     * @return array{string|null, string|null}
     */
    private function parseHmacState(string $state, string $prefix): array
    {
        $parts = explode(':', $state, 3);

        if (count($parts) !== 3 || $parts[0] !== $prefix) {
            return [null, null];
        }

        return [$parts[1], $parts[2]];
    }

    private function verifyHmac(string $payload, string $hmac): bool
    {
        $expected = hash_hmac('sha256', $payload, config('app.key'));

        return hash_equals($expected, $hmac);
    }

    /**
     * Resolve the authenticated user from a userId embedded in the state token.
     * Returns the User on success, or a RedirectResponse on failure.
     */
    private function resolveAuthenticatedUser(int $userId): User|RedirectResponse
    {
        if (! Auth::check() || Auth::id() !== $userId) {
            return redirect()->route('login')
                ->with('error', 'Session expired. Please sign in again.');
        }

        $user = User::find($userId);

        if (! $user) {
            return redirect()->route('login')
                ->with('error', 'User not found.');
        }

        return $user;
    }
}
