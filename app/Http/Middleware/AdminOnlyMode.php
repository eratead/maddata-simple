<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AdminOnlyMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Cache::get('admin_only_login') && Auth::check()) {
            $user = Auth::user();

            if (! $user->hasPermission('is_admin')) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('error', 'System is in maintenance mode. Only administrators can log in.');
            }
        }

        return $next($request);
    }
}
