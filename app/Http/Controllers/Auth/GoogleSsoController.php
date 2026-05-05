<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\SsoLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

class GoogleSsoController extends Controller
{
    public function __construct(
        private readonly SsoLinkService $ssoLinkService,
    ) {}

    /**
     * Handle the OAuth callback from Google.
     *
     * Intent is read from the session (written by the three start-* methods).
     * Socialite's own CSRF state token is left untouched so its built-in
     * InvalidStateException check works normally.
     */
    public function callback(Request $request): RedirectResponse
    {
        // Pull intent + userId atomically — each OAuth round-trip consumes both.
        $intent = $request->session()->pull('google_oauth_intent');
        $userId = $request->session()->pull('google_oauth_user');

        if (! $intent || ! $userId) {
            return redirect()->route('login')
                ->with('error', 'Invalid Google request. Please sign in with email and password.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            $fallbackRoute = $intent === 'link' ? 'settings.sign-in-methods.index' : '2fa.setup';

            return redirect()->route($fallbackRoute)
                ->with('error', 'Google authorization failed. Please try again.');
        }

        return match ($intent) {
            'link' => $this->doLink((int) $userId, $googleUser),
            '2fa_setup' => $this->doSetup((int) $userId, $googleUser),
            '2fa_verify' => $this->doVerify((int) $userId, $googleUser),
            default => redirect()->route('login')->with('error', 'Unknown Google flow.'),
        };
    }

    // -------------------------------------------------------------------------
    // Settings-page link flow
    // -------------------------------------------------------------------------

    private function doLink(int $userId, SocialiteUser $googleUser): RedirectResponse
    {
        $user = User::find($userId);

        if (! $user) {
            return redirect()->route('settings.sign-in-methods.index')
                ->with('error', 'User not found.');
        }

        if (! Auth::check() || Auth::id() !== $user->id) {
            return redirect()->route('login')
                ->with('error', 'Please sign in before connecting a Google account.');
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
    // 2FA setup: link Google and immediately mark 2fa_verified
    // -------------------------------------------------------------------------

    private function doSetup(int $userId, SocialiteUser $googleUser): RedirectResponse
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

    private function doVerify(int $userId, SocialiteUser $googleUser): RedirectResponse
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

        // Critical: the Google sub MUST match what we stored — reject if the user
        // authenticated to Google with a different account.
        if ($googleUser->getId() !== $user->google_sub) {
            return redirect()->route('2fa.challenge')
                ->with('error', 'The Google account used does not match the one linked to your MadData account.');
        }

        session(['2fa_verified' => true]);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
