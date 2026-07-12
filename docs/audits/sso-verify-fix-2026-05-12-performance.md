# Performance Audit: SSO Verify-Flow Fix (commit `8f73d0b`)

**Date:** 2026-05-12
**Scope:** `TwoFactorController::startGoogleVerify()`, `resources/views/auth/2fa-challenge.blade.php`, `tests/Feature/Auth/TwoFactorChallengeFlashTest.php`. Spot-check of `SignInMethodsController`, `SsoLinkService`, `GoogleSsoController::callback()`, `RequireTwoFactor`.

## Summary

**Zero performance issues found in today's diff.** The change is hot-path-safe: the 2FA challenge view does no DB work beyond what was already loaded by `auth()` (which uses the cached resolved user), the `login_hint` addition adds one attribute read and one fluent call on the Socialite driver, and the masked-email helper is pure string manipulation. The `ActivityLogger` cleanup (no `checkAndSendDigest` in the request path) is confirmed landed. No new N+1, no synchronous external calls, no expensive renders.

## Verification of the questions raised

| Question | Result | Evidence |
|---|---|---|
| `auth()->user()` cached across multiple Blade calls? | Yes. Laravel's `AuthManager::user()` memoizes the resolved user on the guard. The seven `auth()->user()` calls in `2fa-challenge.blade.php` resolve to the same instance. | `Illuminate\Auth\SessionGuard::user()` short-circuits on `$this->user` after first call. |
| `User::$with = ['userRole']` still present (PERF-C3)? | Yes. | `app/Models/User.php:16` |
| Masked-email helper touches anything other than strings? | No. `Str::substr($googleEmail, 0, 1) . '***@' . Str::after($googleEmail, '@')` — two pure-PHP string ops. | `resources/views/auth/2fa-challenge.blade.php:141, 170` |
| `SignInMethodsController::index()` introduces N+1? | No. Returns `Auth::user()` (already-hydrated, with `userRole` eager-loaded via `$with`). View uses scalar attributes only (`$user->email`, `$user->google_email`, `hasTotpEnrolled()`, `hasGoogleLinked()`). | `app/Http/Controllers/Auth/SignInMethodsController.php:23-28` |
| `ActivityLogger::checkAndSendDigest()` cleanup landed? | Yes. Zero occurrences in `app/`. `ActivityLogger::log()` is just a single `ActivityLog::create(...)` insert. | `grep checkAndSendDigest app/` → no matches; `app/Services/ActivityLogger.php` confirms. |
| Hot-path expensive calls introduced in `2fa-challenge.blade.php`? | No. All Blade method calls (`hasTotpEnrolled`, `hasGoogleLinked`, `google_email`) are `!empty()` checks or direct attribute reads on the already-loaded `$user`. No file I/O, no DB, no config lookups in loops. | View body inspection. |
| `startGoogleVerify()` adds DB or external work? | No. One scalar read (`$user->google_email`, already loaded), one fluent `->with([...])` on the Socialite driver, then `->redirect()`. The Socialite redirect is an HTTP 302, not an outbound API call. | `app/Http/Controllers/Auth/TwoFactorController.php:183-197` |

## Notes on the broader SSO surface (no action needed)

- **`RequireTwoFactor` middleware** — unchanged today. The pre-existing `$user->currentAccessToken()` double-call (chunk1 C4) is still there but is a micro-issue (no DB hit) and not in today's diff.
- **`GoogleSsoController::callback()`** — runs at most once per login, not on every request. `User::find($userId)` runs once per OAuth round-trip; `User::where('google_sub', ...)->exists()` runs once for collision check in `doLink`/`doSetup`.
- **`SsoLinkService::link()/unlink()`** — one `UPDATE` + one `INSERT` per call, on rare paths (settings page, OAuth callback). Acceptable.

## Caching recommendations

None for today's diff. No repeated work introduced.

## Index recommendations

**`users.google_sub` already indexed** — the SSO migration declared it `->unique()` (`database/migrations/2026_05_04_142828_add_google_sso_columns_to_users_table.php:12`), which MySQL implements as a UNIQUE index. All `WHERE google_sub = ?` lookups in `GoogleSsoController::doLink/doSetup` and `SsoLinkService::resolveLogin` hit this index. No additional indexes recommended.

## Verdict

Clean. Hot-path-safe. No regressions, no caching gaps, no scalability concerns at current or 100x scale.
