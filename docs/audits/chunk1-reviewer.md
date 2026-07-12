# Chunk 1 — Auth & RBAC (Reviewer)

Scope: Auth controllers, auth/RBAC middleware, Policies (User/Campaign/Client/Agency), User/Role/Agency models, LoginRequest, StoreUserRequest, UpdateUserRequest.

---

## Critical

### C1. `EnsureUserIsAdmin` assumes authenticated user — NPE risk
**File:** `app/Http/Middleware/EnsureUserIsAdmin.php:18`
The line `auth()->user()->hasPermission('is_admin')` dereferences `null` if the middleware is ever hit without `auth` preceding it (e.g., misconfigured route group). Will throw a fatal error rather than returning 403.
**Fix:** Guard with `$user = auth()->user(); if (! $user || ! $user->hasPermission('is_admin')) abort(403)` — mirror the defensive style used in `EnsureUserCanSeeLogs` / `EnsureUserIsCampaignManager`.

### C2. Privilege escalation via `can_manage_users` not blocked in `UserController`
**File:** `app/Http/Controllers/UserController.php:53`, `:146`
Anti-escalation only checks `is_admin` on the target role. A non-admin user with `can_manage_users` who reaches this admin route (guarded only by `EnsureUserIsAdmin`) would already be blocked by middleware, but the controller is nevertheless delegated to by `Agency/AgencyUserController` semantics through shared helpers. More importantly, the pattern `if (granted && ! currentUser->hasPermission(key))` is used in `AgencyUserController` and `Admin/RoleController` but is **missing entirely here**. A non-admin elevated caller (hypothetical future surface, or if middleware is relaxed) could assign `can_view_budget`, `can_see_logs`, `can_manage_users`, etc. to another user.
**Fix:** Add the same per-permission escalation check loop used in `AgencyUserController:191-225` and `Admin/RoleController:111` to `UserController::store` and `::update`. Reviewer-lens: the two places that currently guard escalation use three different patterns — consolidate into a single `PermissionGuard` service or trait.

### C3. `UserPolicy::update/delete` allow admins to self-delete or demote
**File:** `app/Policies/UserPolicy.php:36-47`
Policy returns `true` for any admin regardless of `$model`. Self-deletion is guarded inline in `UserController::destroy:99-102` but self-demotion (an admin stripping their own `is_admin` role via `update`) has no policy or controller guard. Losing the last admin is possible.
**Fix:** Add `$model->id !== $user->id` guard in `delete()` (remove controller duplicate), and in `update()` prevent demoting self OR enforce "at least one admin" invariant in the controller/service layer.

### C4. `User::hasPermission()` does not respect `is_active=false` for legacy admins
**File:** `app/Models/User.php:86-88`
`if ($this->is_admin) return true;` short-circuits before any active/disabled check. While `LoginRequest` blocks disabled users from re-auth, an already-logged-in session whose account gets disabled mid-session remains a full admin until logout.
**Fix:** Either enforce via middleware that logs out disabled users on each request, or add `if (! $this->is_active) return false;` at the top of `hasPermission()`. Document the chosen approach in `docs/project_context.md`.

---

## High

### H1. `UserController::reset2fa` bypasses the policy layer
**File:** `app/Http/Controllers/UserController.php:195`
Uses `abort_unless(auth()->user()->hasPermission('is_admin'), 403)` instead of `$this->authorize('reset2fa', $user)`. Violates CLAUDE.md rule "Policies used for authorization (no inline role checks)".
**Fix:** Add `reset2fa(User $user, User $model)` method to `UserPolicy` and call `$this->authorize('reset2fa', $user)`.

### H2. `CampaignPolicy` inconsistent legacy-fallback handling
**File:** `app/Policies/CampaignPolicy.php:26-30`, `:38-42`, `:53-57`, `:68-72`
The `! $user->role_id` fallback branches apply different semantics per action. For `view`: legacy user with access to client is allowed. For `create`: only admins allowed (any legacy user with access blocked). For `update` / `delete`: any legacy user with client access is allowed (no `can_view_budget`/`can_upload_reports` check). This is incoherent — a legacy non-admin with no flags can update and delete campaigns they have client access to, but cannot create them.
**Fix:** Define a single legacy→permission mapping (e.g., `is_report → can_upload_reports`, `can_view_budget → can_view_budget`) and apply it uniformly, OR deprecate the `! role_id` branch entirely now that roles exist. Reviewer-lens: current code implies a seed gap — decide which it is.

### H3. `ClientPolicy::view` returns `false` always
**File:** `app/Policies/ClientPolicy.php:21-24`
Admins cannot `view()` a single client via the policy. Any `authorize('view', $client)` call will 403 even for admins. Either dead (unused) or a latent bug.
**Fix:** Return `$user->hasPermission('is_admin') || $user->accessibleClientIds()->contains($client->id)`. If truly unused, delete the method rather than leaving a misleading stub.

### H4. `UserPolicy::view` returns `false` always
**File:** `app/Policies/UserPolicy.php:20-23`
Same issue. Admins should be able to view an individual user. Either a bug or dead method.
**Fix:** Return `$user->hasPermission('is_admin')` or remove the stub.

### H5. `UpdateUserRequest` will 500 on missing user route binding
**File:** `app/Http/Requests/UpdateUserRequest.php:19`
`$this->route('user')->id` will throw "Attempt to read property id on null" if the route param isn't present or model binding fails before `authorize()`. Not defensive.
**Fix:** Use `Rule::unique('users','email')->ignore($this->route('user'))` — accepts model or id and handles null safely.

### H6. `RequireTwoFactor` blanket skip in testing environment hides bugs
**File:** `app/Http/Middleware/RequireTwoFactor.php:24-26`
Every test bypasses 2FA. Fine for unrelated features, but 2FA flows themselves cannot be tested end-to-end through normal test middleware stacks.
**Fix:** Remove blanket skip; rely on `withoutMiddleware(RequireTwoFactor::class)` in tests that don't need it, so 2FA-specific feature tests can exercise the real gate.

---

## Medium

### M1. `RegisteredUserController` still open — registration should be disabled
**File:** `app/Http/Controllers/Auth/RegisteredUserController.php`
MadData is a managed service; per `docs/project_context.md` users are provisioned by admins. Public `register` route allows anyone to self-register as a no-role user with dashboard access.
**Fix:** Either delete controller + route, or gate behind admin middleware, or set `is_active=false` on creation awaiting approval.

### M2. `RegisteredUserController::store` uses raw `Request` instead of Form Request
**File:** `app/Http/Controllers/Auth/RegisteredUserController.php:30`
Violates CLAUDE.md rule "Form Requests used for all validation".
**Fix:** Extract to `RegisterRequest`. (Or delete per M1.)

### M3. `NewPasswordController::store` and `PasswordResetLinkController::store` use raw `Request`
**Files:** `app/Http/Controllers/Auth/NewPasswordController.php:31`, `app/Http/Controllers/Auth/PasswordResetLinkController.php:26`
Same Form-Request convention violation as M2.
**Fix:** Extract `NewPasswordRequest`, `PasswordResetLinkRequest`.

### M4. `PasswordController::update` uses raw `Request` with inline validation
**File:** `app/Http/Controllers/Auth/PasswordController.php:18`
Same issue; also mixes validation+domain logic in controller.
**Fix:** Extract `UpdatePasswordRequest`.

### M5. `TwoFactorController::verify` — cookie token does not rotate on secret reset
**File:** `app/Http/Controllers/Auth/TwoFactorController.php:117` and `UserController::reset2fa:197`
Token is `hmac(user_id || secret)`. When admin resets 2FA, `google2fa_secret` becomes null, but if later the user sets up a *new* secret equal to the old one (astronomically unlikely) OR if an attacker had previously copied the cookie and it's reused, the comparison to the new HMAC no longer matches — good. However there's no invalidation of old cookies across setup/reset cycles — and no `2fa_cookie_version` column — so this is essentially OK, just fragile. Reviewer-lens: the design couples trust to secret-key value with no versioning.
**Fix:** Add a `2fa_remember_version` integer on `users` that increments on reset, and include it in the HMAC.

### M6. `TwoFactorController::showChallenge` bypasses cookie re-check
**File:** `app/Http/Controllers/Auth/TwoFactorController.php:83-95`
The controller checks session flag and presence of secret, but does not attempt the remember-cookie shortcut (which lives only in `RequireTwoFactor` middleware). If a user navigates directly to `/2fa/challenge` with a valid remember cookie and no `2fa_verified` session flag, they're forced to re-enter code. (Not a bug per se, just UX/consistency.)
**Fix:** Either rely entirely on middleware to populate `2fa_verified` before reaching this controller, or duplicate the cookie check here.

### M7. `AdminOnlyMode` duplicates logic already in `LoginRequest`
**Files:** `app/Http/Middleware/AdminOnlyMode.php:15-28`, `app/Http/Requests/Auth/LoginRequest.php:64-70`
Same admin-only check is enforced in two places with slightly different side effects (middleware logs out on every request; LoginRequest blocks at attempt). Acceptable defense-in-depth, but message strings differ ("maintenance mode" vs the LoginRequest variant) and no shared constant/service.
**Fix:** Extract `AdminOnlyModeGate` service or `MaintenanceMode` class with single `check(User): void` method; consume in both places.

### M8. `EnsureUserIsCampaignManager` hard-codes role name string
**File:** `app/Http/Middleware/EnsureUserIsCampaignManager.php:25`
`$user->userRole->name === 'Campaign Manager'`. Role names are data, not identifiers. Rename the role in the DB and this middleware breaks silently.
**Fix:** Add a `can_manage_campaigns` (or `is_campaign_manager`) permission to `Role::availablePermissions()` and check that instead.

### M9. `User::validateSingleAgencyConstraint` uses `abort(422)` instead of ValidationException
**File:** `app/Models/User.php:185`, `:197`
Returning raw HTTP 422 abort bypasses Laravel validation error bag; user gets no field-level feedback.
**Fix:** Throw `ValidationException::withMessages([...])` so errors surface in form.

### M10. `User` model uses `protected $with = ['userRole']` — eager-loaded globally
**File:** `app/Models/User.php:15`
Every User query eager-loads role. Mostly fine, but causes unnecessary joins in APIs and Sanctum token auth where role is irrelevant. Reviewer-lens: tradeoff between N+1 safety and unconditional overhead.
**Fix:** Consider removing and relying on explicit `with('userRole')` at call sites, or at least document the tradeoff.

---

## Low / Nitpicks

### L1. `User::hasPermission()` parameter lacks type declaration
**File:** `app/Models/User.php:83`, `app/Models/Role.php:38`
`public function hasPermission($permissionKey): bool` — add `string` type for PHP 8.2+ strictness per project stack.

### L2. `User` model lacks `declare(strict_types=1);`
**File:** `app/Models/User.php:1`
Project context says "Strict types". Neither `User`, `Role`, `Agency` declare strict types.

### L3. Nullable union discouraged — `?Role = null` is idiomatic, `$role ?? $user->userRole` uses `??` when `?:` on nullable would also work
**File:** `app/Models/User.php:177`
Minor stylistic: method parameter defaulted to `null` is fine.

### L4. `CampaignPolicy::viewAny` returns hard `true` — inconsistent with others
**File:** `app/Policies/CampaignPolicy.php:13-16`
All other `viewAny` guards return `hasPermission('is_admin')` or similar. Returning `true` effectively means "trust the controller to scope queries". Add a comment explaining scoping is done at the query level via `accessibleClientIds()`.

### L5. `UserController::attachClient` returns stub JSON
**File:** `app/Http/Controllers/UserController.php:203-210`
Dead/placeholder route returning a debug message. Violates "No dead code" rule.
**Fix:** Remove or implement.

### L6. `UserController::edit` query inefficiency
**File:** `app/Http/Controllers/UserController.php:128-130`
`$user->clients()->whereIn('clients.id', Client::where('agency_id', $a->id)->pluck('id'))` executes an extra query per agency in the loop — N+1 across agencies. Already has `$user->clients` eager-loaded in `index` but not here.
**Fix:** Eager-load `$user->load('clients')` once, then filter in-memory by pivoted `agency_id`.

### L7. `UpdateUserRequest` / `StoreUserRequest` `authorize()` returns `true` with stale comment
**File:** `app/Http/Requests/StoreUserRequest.php:10`, `app/Http/Requests/UpdateUserRequest.php:10`
Comment says "Controller handles authorization" — OK, but reviewer-lens: Form Requests can authorize too. Either keep consistent or move authorization into the request.

### L8. `LoginRequest::throttleKey` combines email lowercase + IP — acceptable but unbounded
**File:** `app/Http/Requests/Auth/LoginRequest.php:103`
For users behind shared NAT/proxies, the IP component may cause unintended lockouts. Non-blocking.

### L9. `ContentSecurityPolicy` uses `'unsafe-inline' 'unsafe-eval'` for scripts
**File:** `app/Http/Middleware/ContentSecurityPolicy.php:27`
Negates most XSS protection. Reviewer-lens (not security-lens): at minimum document WHY (Alpine.js uses `eval`-like evaluation) or migrate to nonce-based CSP.

### L10. `VerifyEmailController` always redirects with `?verified=1` even on already-verified
**File:** `app/Http/Controllers/Auth/VerifyEmailController.php:17-25`
Not a bug, but the URL query param is duplicated across both branches — extract to a constant/single return.

### L11. `RequireTwoFactor` string literal `'2fa_verified'` repeated
**Files:** `RequireTwoFactor.php:46,63`, `TwoFactorController.php:73,85,114`
Magic string across 5 sites.
**Fix:** Extract `const SESSION_KEY = '2fa_verified'`.

### L12. `CheckTokenExpiry` does not revoke expired tokens
**File:** `app/Http/Middleware/CheckTokenExpiry.php:19-22`
Returns 401 but leaves expired token row in DB. Cleanup is separate concern but worth noting.

### L13. `Role::hasPermission` strict `=== true` comparison
**File:** `app/Models/Role.php:40`
Strict comparison is correct but means `1`, `"1"`, `"true"` stored in JSON won't match. Given `'array'` cast, values are whatever was set — ensure seeders/controllers write booleans, or loosen to `(bool)` cast. Grep RoleController to confirm.

### L14. `User::accessibleClientIds()` uses `once()` — invalidation concern
**File:** `app/Models/User.php:36`
`once()` memoizes per-instance per-request. After `$user->agencies()->sync(...)` in the same request, the cached value is stale. Not a current issue (no code re-reads after sync), but fragile.

### L15. `AgencyPolicy` missing standard CRUD methods
**File:** `app/Policies/AgencyPolicy.php`
Only `manage` and `view` exist. No `viewAny`, `create`, `update`, `delete`. `admin.agencies.*` routes rely on `EnsureUserIsAdmin` middleware instead — acceptable, but inconsistent with `UserPolicy`/`ClientPolicy`/`CampaignPolicy` patterns.
**Fix:** Add stub CRUD methods for parity, or document the middleware-only pattern choice.

### L16. `UpdateUserRequest` password rule allows empty when nullable
**File:** `app/Http/Requests/UpdateUserRequest.php:20`
`['nullable', Password::min(8)...]` — if user submits empty string, validation passes and controller handles `empty()` check. Works, but explicit `['sometimes', 'nullable', ...]` would be clearer.

---

## Positive observations

- **Defense-in-depth on admin-only mode:** Enforced both at login (`LoginRequest:64`) AND per-request (`AdminOnlyMode` middleware). Good layered approach even if it duplicates logic (see M7).
- **Anti-escalation pattern in `AgencyUserController` and `Admin/RoleController`:** Iterating permissions and blocking any the current user doesn't themselves hold is an elegant, permission-agnostic guard. (Should be lifted to `UserController` per C2.)
- **`User::validateRoleAgencyConstraint`:** Proactively enforces single-agency invariant for managers — prevents silent data corruption.
- **`google2fa_secret` has `encrypted` cast:** Correctly encrypted at rest without manual handling. And `$hidden` excludes it from serialization.
- **`RequireTwoFactor` handles Sanctum transient vs real tokens correctly** — skips 2FA only for real API tokens, not session-based TransientToken.
- **`accessibleClientIds()` memoization via `once()`:** Clean pattern to avoid repeated pivot queries within a single request.
- **Login throttling:** `RateLimiter` with email+IP keying, proper `Lockout` event dispatch — textbook Laravel.
- **Cookie clearing on logout:** `2fa_remember` cookie explicitly forgotten in `AuthenticatedSessionController::destroy` — clean session hygiene.
- **Policies are used consistently in `UserController`** (viewAny/create/update/delete) — only `reset2fa` slipped through (see H1).
- **Form Requests exist for user CRUD** (`StoreUserRequest`, `UpdateUserRequest`) following project conventions.
