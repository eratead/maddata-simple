<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmPasswordRequest;
use App\Services\ActivityLogger;
use App\Services\Auth\SsoLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SignInMethodsController extends Controller
{
    public function __construct(
        private readonly SsoLinkService $ssoLinkService,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * Display the Sign-in Methods settings page.
     */
    public function index(): View
    {
        return view('settings.sign-in-methods', [
            'user' => Auth::user(),
        ]);
    }

    /**
     * Verify password, then redirect to Socialite with a signed link-state token.
     */
    public function startConnectGoogle(ConfirmPasswordRequest $request): RedirectResponse
    {
        $request->verifyPassword();

        $user = Auth::user();

        // Build a signed state: "link:{userId}:{hmac}" so the callback can safely
        // identify who initiated the link without relying on session state alone.
        $hmac = hash_hmac('sha256', 'link:'.$user->id, config('app.key'));
        $state = 'link:'.$user->id.':'.$hmac;

        return \Laravel\Socialite\Facades\Socialite::driver('google')
            ->with(['state' => $state])
            ->redirect();
    }

    /**
     * Verify password + lockout invariant, then remove the Google link.
     *
     * Invariant: a user must have TOTP enrolled before disconnecting Google,
     * otherwise they would be locked out entirely.
     */
    public function disconnectGoogle(ConfirmPasswordRequest $request): RedirectResponse
    {
        $request->verifyPassword();

        $user = Auth::user();

        if (! $user->hasTotpEnrolled()) {
            return back()->withErrors([
                'google' => 'Set up your Authenticator app first so you don\'t lock yourself out.',
            ])->setStatusCode(422);
        }

        $this->ssoLinkService->unlink($user);

        return redirect()->route('settings.sign-in-methods.index')
            ->with('success', 'Google account disconnected.');
    }

    /**
     * Verify password + lockout invariant, then clear the TOTP secret.
     *
     * Invariant: a user must have Google linked before disabling TOTP,
     * otherwise they would be locked out entirely.
     */
    public function disableTotp(ConfirmPasswordRequest $request): RedirectResponse
    {
        $request->verifyPassword();

        $user = Auth::user();

        if (! $user->hasGoogleLinked()) {
            return back()->withErrors([
                'totp' => 'Connect Google first so you don\'t lock yourself out.',
            ])->setStatusCode(422);
        }

        $user->update(['google2fa_secret' => null]);

        // Clear the 2FA session flag so the middleware re-evaluates on next request
        session()->forget('2fa_verified');

        $this->logger->log('totp.disabled', $user, 'TOTP (Authenticator app) disabled.');

        return redirect()->route('settings.sign-in-methods.index')
            ->with('success', 'Authenticator app disabled.');
    }
}
