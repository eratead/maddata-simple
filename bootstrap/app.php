<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'check-token-expiry' => \App\Http\Middleware\CheckTokenExpiry::class,
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'campaign_manager' => \App\Http\Middleware\EnsureUserIsCampaignManager::class,
            'can_see_logs' => \App\Http\Middleware\EnsureUserCanSeeLogs::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'two_factor' => \App\Http\Middleware\RequireTwoFactor::class,
        ]);

        // Gate every authenticated web request behind 2FA.
        // The middleware itself fast-paths unauthenticated users and exempt routes.
        $middleware->appendToGroup('web', [
            \App\Http\Middleware\ContentSecurityPolicy::class,
            \App\Http\Middleware\RequireTwoFactor::class,
            \App\Http\Middleware\AdminOnlyMode::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Belt-and-suspenders: always render JSON for any api/* path, regardless of the
        // Accept header the client sends (fixes Postman Accept: */* case).
        // Also preserve the default expectsJson() behavior for non-api paths so that web
        // controllers using getJson/postJson in tests (or real XHR) still get JSON errors.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        // 401 — Authentication failures (missing/invalid/rejected Sanctum token).
        // Returns null on web paths so the default login redirect still fires.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return null;
        });

        // 403 — Authorization failures.
        // NOTE: prepareException() converts AuthorizationException → AccessDeniedHttpException
        // before render callbacks run, so we must handle both types.
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'This action is unauthorized.'], 403);
            }

            return null;
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'This action is unauthorized.'], 403);
            }

            return null;
        });

        // 422 — Validation failures with field-level errors.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $e->errors(),
                ], 422);
            }

            return null;
        });

        // 404 — Route model binding (ModelNotFoundException) and explicit 404s (NotFoundHttpException).
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }

            return null;
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }

            return null;
        });

        // 429 — Throttle. Adds JSON branch for api/* paths; preserves web redirect-back
        // with readable message for the 2FA challenge view and other throttled web forms.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Too many requests.'], 429);
            }

            if (! $request->expectsJson()) {
                $retryAfter = (int) ($e->getHeaders()['Retry-After'] ?? 60);
                $minutes = max(1, (int) ceil($retryAfter / 60));
                $label = $minutes === 1 ? 'minute' : 'minutes';

                return redirect()->back()
                    ->withErrors(['throttle' => "Too many failed attempts. Please wait {$minutes} {$label} before trying again."])
                    ->withInput();
            }

            return null;
        });

        // HTTP exceptions not matched above (e.g. 405 Method Not Allowed).
        // Use the exception's own status code so we don't swallow legitimate HTTP errors
        // as 500. This also acts as a safety net for any HttpException subclass we did
        // not register a dedicated handler for.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*')) {
                $status = $e->getStatusCode();
                $message = match ($status) {
                    403 => 'This action is unauthorized.',
                    404 => 'Resource not found.',
                    405 => 'Method not allowed.',
                    429 => 'Too many requests.',
                    default => 'Server error.',
                };

                return response()->json(['message' => $message], $status);
            }

            return null;
        });

        // 500 — Fallback for any uncaught non-HTTP exception on api/* paths.
        // Deliberately omits exception message and stack trace to avoid information leakage.
        // Registered last so specific type matches above take priority.
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Server error.'], 500);
            }

            return null;
        });
    })->create();
