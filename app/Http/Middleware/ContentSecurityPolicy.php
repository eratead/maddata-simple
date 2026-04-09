<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicy
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Skip CSP in local dev — Vite HMR uses dynamic ports/IPv6 that CSP can't handle
        if (app()->environment('local')) {
            return $response;
        }

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdn.datatables.net https://code.jquery.com https://unpkg.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net https://cdn.datatables.net https://unpkg.com",
            "img-src 'self' data: blob: https://*.tile.openstreetmap.org",
            "font-src 'self' https://fonts.gstatic.com https://fonts.bunny.net",
            "connect-src 'self' https://nominatim.openstreetmap.org https://cdn.jsdelivr.net https://cdn.datatables.net https://unpkg.com",
            "frame-ancestors 'none'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
