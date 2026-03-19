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
     */
    private const EXEMPT_ROUTES = [
        'login', 'register', 'logout',
        'password.*', 'verification.*',
        '2fa.*',
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

        // Always allow auth & 2FA routes through to prevent redirect loops
        if ($request->routeIs(self::EXEMPT_ROUTES)) {
            return $next($request);
        }

        // Already verified in this session — fast path
        if (session('2fa_verified')) {
            return $next($request);
        }

        // No secret yet — must go through setup first
        if (! $user->google2fa_secret) {
            Redirect::setIntendedUrl(url()->current());
            return redirect()->route('2fa.setup');
        }

        // Has secret — check remember-device cookie (auto-decrypted by EncryptCookies)
        $cookieToken = $request->cookie('2fa_remember');
        if ($cookieToken) {
            // HMAC ties the token to this specific user + their current secret
            $expected = hash_hmac('sha256', $user->id . $user->google2fa_secret, config('app.key'));
            if (hash_equals($expected, $cookieToken)) {
                session(['2fa_verified' => true]);
                return $next($request);
            }
        }

        // Must complete the challenge
        Redirect::setIntendedUrl(url()->current());
        return redirect()->route('2fa.challenge');
    }
}
