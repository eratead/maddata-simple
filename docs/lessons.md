# Lessons Learned

## Laravel 12 Exception Handling

**Laravel 12 does NOT auto-load `app/Exceptions/Handler.php`.** Exception customization lives exclusively in `bootstrap/app.php` via `->withExceptions(...)`. Subclassing the base `Handler` in `app/Exceptions/Handler.php` is dead code — it is never referenced or loaded. Verify with `grep -rn "App\\\\Exceptions\\\\Handler" . --exclude-dir=vendor` before assuming it is wired up.

### `prepareException()` transforms exceptions before render callbacks

The framework's `Handler::prepareException()` runs BEFORE render callbacks (`renderViaCallbacks`). This means:
- `AuthorizationException` (and subclasses like `MissingAbilityException`) → `AccessDeniedHttpException`
- `ModelNotFoundException` → `NotFoundHttpException`
- `BackedEnumCaseNotFoundException` → `NotFoundHttpException`

If you register a render closure for `AuthorizationException`, it will NOT catch authorization failures in practice — you must also handle `AccessDeniedHttpException`.

### `shouldRenderJsonWhen` REPLACES `expectsJson()`, it does not extend it

Calling `$exceptions->shouldRenderJsonWhen($callback)` replaces the default `$request->expectsJson()` check entirely. If your callback returns `false` for non-api paths, web routes with `Accept: application/json` (e.g., `getJson()`/`postJson()` in tests or real XHR) will no longer receive JSON error responses. Always include `|| $request->expectsJson()` as a fallback:

```php
$exceptions->shouldRenderJsonWhen(
    fn ($request, $e) => $request->is('api/*') || $request->expectsJson()
);
```

### Sanctum guard enforces `expires_at` itself

Sanctum's `Guard::retrieve()` checks `$accessToken->expires_at->isPast()` and rejects the token BEFORE the request reaches any middleware. Custom `CheckTokenExpiry` middleware only fires when the user is authenticated via a different path (e.g., session). Expired Sanctum tokens return `AuthenticationException` ("Unauthenticated."), not the "Token expired." message from `CheckTokenExpiry`.
