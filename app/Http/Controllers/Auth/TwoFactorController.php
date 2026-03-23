<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    // ── Setup ─────────────────────────────────────────────────────────────

    /**
     * Show the QR-code setup screen.
     * Generates a temp secret (stored in session, NOT DB yet) so the user
     * can scan and confirm before we persist anything.
     */
    public function showSetup(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        // Already set up — go to challenge instead
        if ($user->google2fa_secret) {
            return redirect()->route('2fa.challenge');
        }

        $google2fa = new Google2FA;

        // Reuse the temp secret if the user refreshes the page
        $secret = session('2fa_setup_secret') ?? $google2fa->generateSecretKey();
        session(['2fa_setup_secret' => $secret]);

        $otpauthUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        $qrCodeSvg = $this->renderQrCode($otpauthUrl);

        return view('auth.2fa-setup', compact('secret', 'qrCodeSvg'));
    }

    /**
     * Confirm setup: verify the first TOTP code and persist the secret.
     */
    public function confirmSetup(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'digits:6']]);

        $secret = session('2fa_setup_secret');

        if (! $secret) {
            return redirect()->route('2fa.setup');
        }

        $google2fa = new Google2FA;

        if (! $google2fa->verifyKey($secret, $request->code)) {
            return back()->withErrors(['code' => 'That code is incorrect. Please try again.']);
        }

        // Persist — the `encrypted` cast on User handles encrypt/decrypt
        $request->user()->update(['google2fa_secret' => $secret]);

        session()->forget('2fa_setup_secret');
        session(['2fa_verified' => true]);

        return redirect()->intended(route('dashboard'));
    }

    // ── Challenge ─────────────────────────────────────────────────────────

    /**
     * Show the 6-digit challenge screen.
     */
    public function showChallenge(Request $request): View|RedirectResponse
    {
        if (session('2fa_verified')) {
            return redirect()->intended(route('dashboard'));
        }

        // No secret set up yet — redirect to setup
        if (! $request->user()->google2fa_secret) {
            return redirect()->route('2fa.setup');
        }

        return view('auth.2fa-challenge');
    }

    /**
     * Verify the TOTP code. On success, optionally set the 30-day remember cookie.
     */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'digits:6']]);

        $user = $request->user();
        // google2fa_secret is auto-decrypted by the encrypted cast
        $secret = $user->google2fa_secret;

        $google2fa = new Google2FA;

        if (! $google2fa->verifyKey($secret, $request->code)) {
            return back()->withErrors(['code' => 'That code is incorrect. Please try again.']);
        }

        session(['2fa_verified' => true]);

        if ($request->boolean('remember_device')) {
            $token = hash_hmac('sha256', $user->id.$secret, config('app.key'));

            cookie()->queue(
                cookie(
                    name: '2fa_remember',
                    value: $token,
                    minutes: 60 * 24 * 30,      // 30 days
                    secure: $request->isSecure(), // HTTPS-only in production
                    httpOnly: true,
                    sameSite: 'strict',
                )
            );
        }

        return redirect()->intended(route('dashboard'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function renderQrCode(string $url): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(192),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($url);
    }
}
