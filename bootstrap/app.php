<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'check-token-expiry' => \App\Http\Middleware\CheckTokenExpiry::class,
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'campaign_manager' => \App\Http\Middleware\EnsureUserIsCampaignManager::class,
            'can_see_logs' => \App\Http\Middleware\EnsureUserCanSeeLogs::class,
            'two_factor' => \App\Http\Middleware\RequireTwoFactor::class,
        ]);

        // Gate every authenticated web request behind 2FA.
        // The middleware itself fast-paths unauthenticated users and exempt routes.
        $middleware->appendToGroup('web', [
            \App\Http\Middleware\RequireTwoFactor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Convert 429 throttle errors to a redirect-back with a readable error message
        // so the 2FA challenge view (and any other throttled web form) shows inline feedback.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->expectsJson()) {
                $retryAfter = (int) ($e->getHeaders()['Retry-After'] ?? 60);
                $minutes    = max(1, (int) ceil($retryAfter / 60));
                $label      = $minutes === 1 ? 'minute' : 'minutes';

                return redirect()->back()
                    ->withErrors(['throttle' => "Too many failed attempts. Please wait {$minutes} {$label} before trying again."])
                    ->withInput();
            }
        });
    })->create();
