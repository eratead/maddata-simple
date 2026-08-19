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

---

## Runtime upgrades (PHP) are never routine — stage them

**A PHP point-release upgrade can break Laravel. Treat it as a feature-sized change, not maintenance.** Recorded 2026-08-19, after production was found sitting on 13 pending `php8.4-*` packages (8.4.19 → 8.4.24 from the `deb.sury.org` PPA).

Rules:

1. **Never bundle a PHP upgrade into another request.** It was pending alongside "1 security update"; applying them together would have shipped a runtime bump under an authorization to patch a vulnerability. Runtime changes get their own window and their own approval.
2. **Staging first, always** — deploy, run the **full test suite** on the upgraded runtime, then smoke the real app before production goes anywhere near it.
3. **Restart what holds the runtime in memory.** `php8.4-fpm` and the queue worker both keep the old binary and extensions loaded until restarted; the app can look fine while half of it still runs the old PHP.
4. **Check the extension list, not just `php -v`.** Laravel fails on a *missing extension* far more often than on a language change — compare `php -m` before and after.
5. **Check that staging can actually run the suite before relying on it.** Staging lacked `php8.4-sqlite3`, so the SQLite-in-memory test suite failed 705 tests with "could not find driver" — nothing to do with the upgrade. A staging box that cannot run the tests provides no validation, only the appearance of it.
6. **Staging is not a perfect proxy here.** Staging runs Apache + mod_php on Ubuntu 22.04; production runs Nginx + PHP-FPM on 24.04, from a different starting version. Staging proves *the application code runs on the new PHP*; it does not prove the FPM restart path. Know which risk you have actually retired.

## `apt-check`'s security count is not actionable on its own

`/usr/lib/update-notifier/apt-check` counts packages in the full-upgrade change set that have a security-pocket version — **including packages that are not installed**. Production reported "1 pending security update" that was `libabsl20220623t64`, absent from the system and only reachable as a new dependency of `libgav1-1` during a full upgrade. `apt-get install --only-upgrade` refuses it; `unattended-upgrade` reports "no packages found".

Before acting on a security count, resolve it to actual packages:

```bash
python3 -c "
import apt
c = apt.Cache(); c.open(None); c.upgrade(True)
for p in c.get_changes():
    if p.is_installed and any((o.archive or '').endswith('-security') for o in p.candidate.origins):
        print(p.name, p.installed.version, '->', p.candidate.version)"
```

Note also that Ubuntu publishes security fixes to `-updates` as well as `-security`, so the absence of a `-security` tag in `apt list --upgradable` does not prove the absence of security content — check the candidate's origins.
