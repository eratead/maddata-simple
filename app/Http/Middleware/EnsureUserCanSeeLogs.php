<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanSeeLogs
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user || (! $user->hasPermission('is_admin') && ! $user->hasPermission('can_see_logs'))) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
