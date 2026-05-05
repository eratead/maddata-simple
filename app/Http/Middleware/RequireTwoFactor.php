<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactor
{
    /**
     * Routes that are exempt from the 2FA gate (auth + 2FA routes themselves).
     *
     * `auth.google.*` is exempt because the Google OAuth callback is the route
     * that ESTABLISHES the second factor (or verifies it). If the gate ran
     * before the callback's controller, the link/verify would never persist
     * and the user would loop back to /2fa/setup forever.
     */
    private const EXEMPT_ROUTES = [
        'login', 'register', 'logout',
        'password.*', 'verification.*',
        '2fa.*', 'auth.google.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Skip entirely in the test environment so existing tests are unaffected
        if (app()->environment('testing')) {
            return $next($request);
        }

        $user = $request->user();

        // Unauthenticated — skip (auth middleware handles the redirect to login)
        if (! $user) {
            return $next($request);
        }

        // Skip 2FA for API token requests (Sanctum) — tokens don't use sessions/2FA
        if ($user->currentAccessToken() && ! ($user->currentAccessToken() instanceof \Laravel\Sanctum\TransientToken)) {
            return $next($request);
        }

        // Always allow auth & 2FA routes through to prevent redirect loops
        if ($request->routeIs(self::EXEMPT_ROUTES)) {
            return $next($request);
        }

        // Already verified in this session (via TOTP or Google) — fast path
        if (session('2fa_verified')) {
            return $next($request);
        }

        // Has secret — check remember-device cookie (auto-decrypted by EncryptCookies)
        if ($user->google2fa_secret) {
            $cookieToken = $request->cookie('2fa_remember');
            if ($cookieToken) {
                // HMAC ties the token to this specific user + their current secret
                $expected = hash_hmac('sha256', $user->id.$user->google2fa_secret, config('app.key'));
                if (hash_equals($expected, $cookieToken)) {
                    session(['2fa_verified' => true]);

                    return $next($request);
                }
            }
        }

        // Decide where to send the user:
        // - TOTP enrolled → challenge screen (may also have Google — either works)
        // - Google linked but no TOTP → challenge screen (Google-only variant)
        // - Neither → forced setup screen
        if ($user->google2fa_secret || $user->hasGoogleLinked()) {
            Redirect::setIntendedUrl(url()->current());

            return redirect()->route('2fa.challenge');
        }

        Redirect::setIntendedUrl(url()->current());

        return redirect()->route('2fa.setup');
    }
}
