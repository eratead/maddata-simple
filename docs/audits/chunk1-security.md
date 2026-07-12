# Chunk 1 — Auth & RBAC (Security)

**Scope:** `app/Http/Controllers/Auth/*`, auth middleware, policies, User/Role/Agency models, Auth form requests.
**Date:** 2026-04-05
**Methodology:** Static read-only review of the files in scope plus supporting routes, `config/services.php`, `bootstrap/app.php`, `UserController`, and `Admin/RoleController` for context.

---

## AI Key Exposure Check (explicit)

A targeted sweep was performed for `OPENAI`, `ANTHROPIC`, `CLAUDE_API`, `sk-*`, and `API_KEY` across the entire repo (code, views, JS, config, docs).

**Findings:**

| # | Where | Status | Notes |
|---|-------|--------|-------|
| 1 | `config/services.php:38-39` | OK | `'anthropic' => ['api_key' => env('ANTHROPIC_API_KEY')]` — pulled from env at config time. |
| 2 | `app/Http/Controllers/CampaignAssistantController.php:30` | OK | Uses `config('services.anthropic.api_key')` server-side in `Http::withHeaders()` and never echoes the value into the response. |
| 3 | `app/Http/Controllers/AiLocationController.php:15` | OK | Same pattern as above. |
| 4 | `resources/views/**` (Blade) | OK | No matches for `anthropic`, `api_key`, `api-key`, `sk-`, `OPENAI`, `CLAUDE`. The frontend never receives the key. |
| 5 | `resources/js/**` | OK | No references. |
| 6 | `.env.example` | OK | File does not exist in repo (only `.env`, which is correctly git-ignored). |
| 7 | Unauthenticated routes | OK | Both AI endpoints (`/ai/generate-locations`, `/ai/campaign-assistant`) sit inside `Route::middleware(['auth'])` and have `throttle:10,1`. No path reaches `config('services.anthropic.*')` pre-auth. |
| 8 | Error/log exposure | OK | On upstream failure the controllers return a generic `"AI request failed."` 502 — the Anthropic response body (which could echo headers in edge cases) is **not** leaked. No `Log::` calls dump the key. |
| 9 | `.env` committed? | OK | Git history inspection not performed here, but `.env` is present locally; ensure it is in `.gitignore` (standard Laravel skeleton does this). |

**Verdict:** No AI-key exposure vector found in the current codebase. The key is only read server-side inside two controllers, both gated behind `auth` + `throttle:10,1`, and the key value is never rendered to Blade, JSON responses, or logs.

**One hardening recommendation** (Informational): `AiLocationController::generate` does not perform any permission check (just `auth`). `CampaignAssistantController::chat` correctly gates on `can_edit_campaigns`. Consider adding the same permission check to `AiLocationController` so that low-privilege authenticated users cannot burn quota against your Anthropic bill. See finding M4 below.

---

## Critical (exploitable now)

_None found in the Auth & RBAC chunk._

The biggest would-be candidates were already mitigated:
- `User::$fillable` excludes `is_admin`, `role_id`, `can_view_budget`, `password` (note: password is in fillable but auto-hashed via the `'password' => 'hashed'` cast).
- Privilege escalation is blocked in `UserController::store/update` (lines 50-56, 143-149) and `Admin/RoleController::preventPrivilegeEscalation` (lines 106-115).
- Session fixation is prevented via `$request->session()->regenerate()` after login.
- 2FA secret is `encrypted` cast + `$hidden` from serialization.

---

## High

### H1 — 2FA setup confirmation has NO rate limit (TOTP brute force during enrollment)
**File:** `app/Http/Controllers/Auth/TwoFactorController.php:53` (`confirmSetup`) · **Route:** `routes/auth.php:42` (`POST /2fa/setup`, name `2fa.confirm`)
**CWE-307 / OWASP A07 (Identification & Authentication Failures).**

Compare to `routes/auth.php:44` where `2fa.verify` gets `throttle:5,1`. The **setup confirmation** route has **no throttle middleware**. An attacker who has compromised a user's password but not their session could:
1. Reach `/2fa/setup` (user has no secret yet).
2. The controller generates a temp secret and stores it in session.
3. Attacker submits thousands of 6-digit codes (10^6 space) to `/2fa/setup` POST. With no throttle, a 6-digit TOTP with a ±1 window can be brute-forced in ≈3 × 10^5 attempts on average.

Additionally, because the temp secret is persisted to session but never rotated on failed attempts, the attack window is the entire session lifetime.

**Attack scenario:** Attacker phishes password, logs in, hits `/2fa/setup`. Script POSTs all 1,000,000 6-digit combinations. Because there's no throttle, one of them matches the freshly generated secret. Account fully compromised with attacker-controlled 2FA device.

**Fix:** Add `throttle:5,1` (or stricter) to the setup confirm route:
```php
Route::post('2fa/setup', [TwoFactorController::class, 'confirmSetup'])
    ->name('2fa.confirm')
    ->middleware('throttle:5,1');
```
Also consider rotating `session('2fa_setup_secret')` after N failed attempts and logging the event.

---

### H2 — Password reset link request (`/forgot-password`) has NO rate limit (spam + user enumeration timing)
**File:** `routes/auth.php:28-29`, `app/Http/Controllers/Auth/PasswordResetLinkController.php:26`
**CWE-307, CWE-203 (Observable Timing Discrepancy), OWASP A04.**

No throttle middleware on `POST /forgot-password`. An attacker can:
- Spam the endpoint to exhaust your transactional email quota / get your sender reputation blacklisted.
- Measure timing differences between "user exists" vs "user does not exist" responses to enumerate valid emails. `Password::sendResetLink` returns different status codes depending on user presence.

**Attack scenario:** Attacker scripts POST `/forgot-password` with 10,000 candidate emails, records response timing and status. Harvests a list of valid user emails, then runs credential stuffing against `/login` (which is throttled per email+IP, but rotating the source IP with a proxy pool defeats the per-IP half).

**Fix:** Add `throttle:5,1` or `throttle:3,15` to both the email submission and the reset POST.
```php
Route::post('forgot-password', [...])->middleware('throttle:5,1')->name('password.email');
Route::post('reset-password', [...])->middleware('throttle:5,1')->name('password.store');
```

---

### H3 — `RegisteredUserController` is reachable code even though registration routes are commented out
**File:** `app/Http/Controllers/Auth/RegisteredUserController.php`, `routes/auth.php:17-18`

The routes are commented out (good intent), but the controller still exists and can be hit if someone later re-enables the route without re-reviewing. The controller:
- Has **no `role_id`/`is_admin`/`is_active` defaulting** — a new user gets `is_active = null/false` by DB default (unknown).
- Does not send the user through 2FA enrollment before granting a session.
- `Auth::login($user)` happens **without `session()->regenerate()`**, so if the registration form is ever re-enabled, session fixation becomes possible.

**Fix:** Either delete the controller file entirely (it is explicitly a managed-service SaaS per the route comment), or add `$request->session()->regenerate()` after `Auth::login()` and explicitly default `is_active => false` and mandatory admin approval. Safer path: delete the file.

---

### H4 — `is_report` is mass-assignable on `User` and grants `can_upload_reports` via the legacy fallback
**File:** `app/Models/User.php:68-76` (`$fillable`), line 94-96 (`hasPermission` legacy fallback)

`is_report` is in `$fillable`. In `User::hasPermission()`, when `userRole` is null the code falls back to `is_report` to grant `can_upload_reports`. Any code path that does `User::create($request->all())` or `$user->update($request->all())` in the future (not today — all current callers whitelist fields) would silently grant report-upload privileges.

**Attack scenario:** A future developer adds a "profile update" endpoint that does `$user->update($request->validated())`, forgetting to exclude `is_report`. Any authenticated user POSTs `is_report=1` and gains the ability to upload performance reports (which feed client-facing dashboards = data integrity compromise).

**Fix:** Remove `is_report` from `$fillable` and always set it explicitly via `$user->is_report = ...`. Better still: migrate the remaining legacy bools to `Role.permissions` and drop the legacy fallback entirely.

---

### H5 — `UserPolicy::view()` always returns `false`, collapsing to the admin middleware as sole guard
**File:** `app/Policies/UserPolicy.php:20-23`

`view(User $user, User $model): bool { return false; }`. This means `$this->authorize('view', $user)` would **always 403**, even for admins. The only reason this isn't breaking things right now is that `UserController` never calls `authorize('view', ...)` — the route is gated by the `admin` middleware alias (`EnsureUserIsAdmin`).

This is a defense-in-depth failure: if `admin` middleware is ever removed from a route (e.g. a future "view own profile" admin route), the policy returns `false` and breaks the app — but worse, if someone "fixes" the policy by copying the `update` logic without thought, they may grant broader view access than intended.

**Fix:** Make the policy coherent with the controller's real authorization model:
```php
public function view(User $user, User $model): bool {
    return $user->hasPermission('is_admin') || $user->id === $model->id;
}
```

---

## Medium

### M1 — `CheckTokenExpiry` runs AFTER `auth:sanctum` has already authenticated the user
**File:** `app/Http/Middleware/CheckTokenExpiry.php` · **Route:** `routes/web.php:110`

`auth:sanctum` authenticates the request and populates `$request->user()` before `check-token-expiry` runs. If an attacker has a leaked expired token they still complete the Sanctum authentication cycle (which accesses DB, fires events). Not exploitable to gain access (the 401 is correctly returned), but:
- Any auth event listeners fire on an expired-token login.
- It does not use Sanctum's native `expires_at` handling (`config/sanctum.php::expiration`). If the config were ever set, you'd have double-handling.

**Fix:** Configure `sanctum.expiration` natively and remove the bespoke middleware, OR move the expiry check into a token-issuing policy. Native Sanctum expiration returns 401 without populating `$request->user()`.

---

### M2 — 2FA remember-device cookie has no per-device rotation / revocation mechanism
**File:** `app/Http/Controllers/Auth/TwoFactorController.php:117`, `app/Http/Middleware/RequireTwoFactor.php:61`
**CWE-613 (Insufficient Session Expiration).**

The remember cookie is `hash_hmac('sha256', $user->id . $secret, app.key)` — deterministic: the **same token is issued to every device the user checks "remember" on**. Consequences:
1. If one device's cookie is stolen, the attacker can bypass 2FA from any device for 30 days.
2. "Sign out all devices" has no mechanism — logging out only clears the cookie on the current browser (`Cookie::forget('2fa_remember')` in `AuthenticatedSessionController::destroy`). The same HMAC is still valid on every other device until the TOTP secret rotates.
3. The HMAC has no timestamp baked in, so server-side you cannot reject tokens older than X without rotating the secret.

**Attack scenario:** User checks "remember this device" on a shared/coffee-shop laptop. They log out → the logout clears the cookie in that browser, but the stolen cookie (copied via physical access or browser extension) still validates for 30 days from any IP. Admin has no way to revoke without calling `reset2fa` (which also rotates the TOTP).

**Fix:** Store remember-device tokens in a DB table (`two_factor_remembers: user_id, token_hash, expires_at, user_agent, last_used_at`). Issue random tokens, validate via DB lookup + `hash_equals` on the hashed token. Add a "sign out other devices" UI.

---

### M3 — CSP includes `'unsafe-inline'` and `'unsafe-eval'` in `script-src`
**File:** `app/Http/Middleware/ContentSecurityPolicy.php:27`
**CWE-1021 / OWASP A05.**

`script-src 'self' 'unsafe-inline' 'unsafe-eval'` effectively disables CSP as an XSS control. Alpine.js needs `unsafe-eval` for inline `x-data` expressions, but you do not need `unsafe-inline` if you use Alpine's CSP-safe build (`@alpinejs/csp`) and pre-declare all component data in JS modules.

**Attack scenario:** Any stored XSS (e.g. an admin-injected campaign name, a file upload that lands in a `{!! !!}` block somewhere) executes arbitrary JS with no CSP mitigation. In combination with the session-based admin panel, this can be used to pivot to full account takeover via an admin who views the injected content.

**Fix:** Switch to the Alpine CSP build, remove `'unsafe-inline'` and `'unsafe-eval'`, replace with a per-request nonce. Audit Blade templates for `{!! !!}` usage in the same sweep.

---

### M4 — `AiLocationController` has no permission check (only `auth` + throttle)
**File:** `app/Http/Controllers/AiLocationController.php:10`, `routes/web.php:11`

Every authenticated user — including low-privilege client viewers — can call the Anthropic API via this endpoint. Throttle is `10/min`. A large tenant could accumulate significant cost, and a compromised low-privilege account becomes a vector for abuse of your Anthropic billing.

Compare with the correct pattern in `CampaignAssistantController::chat:12`: `abort_unless(auth()->user()->hasPermission('can_edit_campaigns'), 403)`.

**Fix:** Add the same gate to `AiLocationController::generate`:
```php
abort_unless(auth()->user()->hasPermission('can_edit_campaigns'), 403);
```

---

### M5 — `EnsureUserIsAdmin` dereferences `auth()->user()` with no null guard
**File:** `app/Http/Middleware/EnsureUserIsAdmin.php:18`

`if (! auth()->user()->hasPermission('is_admin'))` will throw `Call to a member function hasPermission() on null` if this middleware ever runs before `auth`. Today the `admin` alias is only ever used after `auth` (e.g. `routes/web.php:64`), but this is fragile.

**Fix:**
```php
$user = auth()->user();
if (! $user || ! $user->hasPermission('is_admin')) {
    abort(403, 'Unauthorized action.');
}
```

---

### M6 — `LoginRequest` throttles by `email + IP`, enabling credential spraying
**File:** `app/Http/Requests/Auth/LoginRequest.php:101-104`

Throttle key is `strtolower(email) . '|' . ip()`. An attacker using a proxy/VPN pool to rotate IPs can spray a single email (or distribute across thousands of emails) at 5 attempts per IP, effectively unlimited globally.

**Attack scenario:** Password-spray attack — attacker tries `Summer2025!` against every harvested email from the enumeration vector (see H2). Rotating 1,000 proxy IPs × 5 attempts = 5,000 unthrottled guesses per email.

**Fix:** Add a second throttle layer keyed on email alone (e.g. 20 failures per email per hour → temporary lock) in addition to the current per-IP throttle. Consider implementing CAPTCHA after N global failures per email.

---

### M7 — Email enumeration via distinguishable responses on `/login`, `/forgot-password`, and registration timing
**File:** `app/Http/Requests/Auth/LoginRequest.php:54-70`, `PasswordResetLinkController::store`

After `Auth::attempt` succeeds, `is_active` is checked and returns a **different** error message ("Your account has been disabled…") than the generic "auth.failed". An attacker can distinguish:
- Valid email + correct password + active account → logged in
- Valid email + correct password + **disabled** account → "disabled" message (confirms the password is correct!)
- Valid email + wrong password → auth.failed
- Invalid email → auth.failed

This is a **credential confirmation oracle**: if the attacker already knows a password from a breach, they can test it against your app without triggering a login, because they learn the password is correct from the "disabled" message without consuming a success login event.

**Fix:** Return the generic auth.failed message for disabled accounts too. Log the disabled-login attempt server-side and show the real message only after proper authentication (e.g. require the user to re-verify via email for disabled accounts).

---

## Low

### L1 — `2fa_setup_secret` persisted in session indefinitely if user abandons flow
**File:** `TwoFactorController::showSetup:37`

If a user starts enrollment then abandons, `session('2fa_setup_secret')` persists for the session lifetime. If the session is hijacked later, the attacker can complete the user's 2FA enrollment with a device they control. Rare, but easy to fix with a TTL: use `session()->put('2fa_setup_secret', [...], now()->addMinutes(15))` via a custom store, or clear on every session start.

### L2 — Registration endpoints `verification.send` and `verification.verify` routes are active even though registration is disabled
**File:** `routes/auth.php:47-56`

Not harmful today (users can only land here if already authenticated + unverified), but dead weight that muddles the threat model.

### L3 — `User::$with = ['userRole']` always auto-loads the role
**File:** `app/Models/User.php:15`

Performance, not security. But in a multi-tenant app, auto-loading relations on `$user = Auth::user()` risks accidentally leaking the relation into JSON responses if a controller ever returns the user model. Today `userRole` is not in `$hidden`, so `response()->json($user)` would include role data including permissions JSON. Low severity because no current route does this.

**Fix:** Add `'userRole'` to `$hidden`, or explicitly serialize via a Resource class.

### L4 — `AdminOnlyMode` logs out non-admin users but does not log the event
**File:** `app/Http/Middleware/AdminOnlyMode.php`

Forced logouts during maintenance are not recorded in `activity_logs`. Add an `ActivityLogger::log('forced_logout', …)` call for audit.

### L5 — `Str::random(60)` for `remember_token` in `NewPasswordController` is fine, but `Str::random` is not guaranteed CSPRNG in all PHP builds
**File:** `app/Http/Controllers/Auth/NewPasswordController.php:47`

Laravel's `Str::random` uses `random_bytes` internally in modern versions — safe. Flagged only for awareness.

---

## Informational

- **I1:** `User::hasPermission()` returns `true` for every permission key when `is_admin` legacy bool is true (line 86-88). This is intentional for legacy admin users, but it means the permission-check functions cannot distinguish "admin bypass" from "explicitly granted" in audit logs.
- **I2:** `Role::availablePermissions()` is a hardcoded list of 8 permissions. When a new permission is added to a middleware/policy, it must also be added here or the UI cannot grant it. Consider a compile-time check/test.
- **I3:** `AuthenticatedSessionController::destroy` queues cookie-forget for `2fa_remember` but does not clear any `laravel_session` cookie manually; Laravel handles this, but ensure `SESSION_SECURE_COOKIE=true` and `SESSION_SAME_SITE=lax` (or `strict`) are set in production `.env`.
- **I4:** `RequireTwoFactor` exempts the entire `testing` environment (line 24). Verify that production/staging never sets `APP_ENV=testing`.
- **I5:** `NewPasswordController` regenerates `remember_token` on password reset (good — invalidates old remember-me cookies) but does not call `Auth::logoutOtherDevices()` or invalidate all existing sessions. An attacker with a valid stolen session survives a victim's password reset.
- **I6:** `validateSingleAgencyConstraint` uses `abort(422, …)` which bypasses validation error bag formatting — user gets a raw 422 page, not inline form errors. UX issue, not security.
- **I7:** `Role::is_protected` is in `$fillable` — a lower-privileged admin (if the role system is extended) could un-protect a system role then delete it. Today only `is_admin` users can touch roles, so OK.

---

## Passed Checks

- Password hashing uses the `'password' => 'hashed'` cast → bcrypt (Laravel default).
- `google2fa_secret` is `encrypted` cast + `$hidden` from serialization.
- Session regeneration after login (`AuthenticatedSessionController::store:30`).
- Session invalidation + token regeneration on logout.
- Login throttle per email+IP (5 attempts).
- 2FA challenge throttle (`throttle:5,1`).
- Email verification uses `signed` middleware + `throttle:6,1`.
- Privilege escalation prevented in `UserController::store/update` and `Admin/RoleController::preventPrivilegeEscalation`.
- `User::$fillable` excludes `is_admin`, `role_id`, `can_view_budget`, `remember_token`, `email_verified_at`.
- `User::$hidden` correctly hides `password`, `remember_token`, `google2fa_secret`.
- Registration route disabled (`routes/auth.php:17-18`).
- Anthropic API key read server-side only from `config('services.anthropic.api_key')`; never rendered to frontend.
- AI endpoints are `auth`-gated + throttled (10/min).
- `frame-ancestors 'none'` in CSP prevents clickjacking.
- CSRF protection enabled by default on all web POST routes (no `$except` list found).
