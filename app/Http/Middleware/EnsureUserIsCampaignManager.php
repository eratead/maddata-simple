<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsCampaignManager
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Unauthorized action.');
        }

        $isAdmin = $user->hasPermission('is_admin');
        $isCampaignManager = $user->userRole && $user->userRole->name === 'Campaign Manager';

        if (!$isAdmin && !$isCampaignManager) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
