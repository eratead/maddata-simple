# Chunk 1 — Auth & RBAC (Performance)

**Date:** 2026-04-05
**Scope:** Auth controllers, auth/RBAC middleware, policies, and `User`/`Role`/`Agency` models.
**Hot path warning:** Nearly every authenticated request runs `User::hasPermission()` one or more times through middleware, policies, blade checks, and controllers. Any extra query here multiplies across the entire request lifecycle.

---

## Critical (hot-path)

### C1. `User::accessibleClientIds()` issues per-agency queries (N+1)
- **File:** `app/Models/User.php:42-49`
- **Problem:** Inside the `once()` closure, the method iterates `$this->agencies` and runs a separate `Client::where('agency_id', $agency->id)->pluck('id')` query for every agency the user belongs to. A user in 5 agencies with `access_all_clients=true` triggers 5 queries plus the `clients()->pluck` call = 6+ round trips.
- **Requests affected:** Every request that runs `CampaignPolicy::view/update/delete` (i.e., almost every campaign-related page and API call), plus `CampaignController@*`, `ReportApiController`, `ActivityLogController`, `CampaignChangeController`. On the campaign index page, `->contains($campaign->client_id)` is called per campaign row — but `once()` memoizes so the N+1 only fires once per request. Still adds N agency queries.
- **Impact:** 3-6 extra queries per authenticated request for non-admin users. `once()` helps within a request but not across requests.
- **Fix:**
  ```php
  $agencyIds = $this->agencies
      ->filter(fn ($a) => $a->pivot->access_all_clients)
      ->pluck('id');

  $agencyClientIds = $agencyIds->isEmpty()
      ? collect()
      : Client::whereIn('agency_id', $agencyIds)->pluck('id');
  ```
  One query instead of N. Additionally consider request-scoped caching keyed by `user_id`, or a short Redis cache (see Caching section).

### C2. `$protected $with = ['userRole']` eager loads role on every User hydration
- **File:** `app/Models/User.php:15`
- **Problem:** `$with = ['userRole']` is reasonable since permissions are checked on every request — BUT it also fires for every `User::find()`, listing, test factory, paginated admin user index, activity logs joining users, etc. In contexts that load many users (admin user list, activity log pages), each row triggers the role join/load.
- **Requests affected:** Every request that hydrates `User` models. For the authenticated user this is desired; for user listings (`/admin/users`) this adds an eager load which is actually helpful (`with` on collection = 1 extra query total). So impact is neutral/positive for lists, but it **does** fire for every `User::where(...)->first()` in Notifications, Mailables, and background jobs.
- **Impact:** Medium — 1 extra query per User collection load, unavoidable given the permission model.
- **Recommendation:** Keep it (the default `$with` is justified). But add an explicit `->without('userRole')` scope for contexts that don't need permissions (mailables, notifications, simple user lookups by email for password reset).

### C3. `EnsureUserIsCampaignManager` touches `userRole` without guarding null
- **File:** `app/Http/Middleware/EnsureUserIsCampaignManager.php:24-25`
- **Problem:** `$user->userRole` is eager-loaded via `$with`, so no extra query — good. However `hasPermission('is_admin')` on line 24 calls `$this->userRole->hasPermission(...)` too, and the comparison against role name `'Campaign Manager'` is a magic string (change-risk, not a perf issue). No query issue here but the double permission+role check is slightly redundant.
- **Impact:** Low (model already loaded) — noted for completeness.

### C4. `RequireTwoFactor` middleware runs on every request but OK
- **File:** `app/Http/Middleware/RequireTwoFactor.php:36`
- **Problem:** `$user->currentAccessToken()` is called twice on line 36. Each call is cheap (returns already-set property for session users = `TransientToken`), but for API token requests it's a resolved relationship. Not a DB query — just a small duplication.
- **Fix:** Cache locally: `$token = $user->currentAccessToken();`
- **Impact:** Low.

---

## High

### H1. `hasPermission()` has no short-circuit cache per request
- **File:** `app/Models/User.php:83-103`
- **Problem:** Each call re-executes the logic (legacy check → role null check → `$this->userRole->hasPermission($key)`). In a single request this method is typically called 5-20 times: middleware (`EnsureUserIsAdmin`, `AdminOnlyMode`, `EnsureUserCanSeeLogs`, `EnsureUserIsCampaignManager`), policy methods (each policy runs it at least twice), blade `@can` directives, sidebar links, form visibility. No queries fire (model already loaded) but logic re-runs.
- **Impact:** ~5-20 redundant evaluations per request. CPU-only, but trivially cacheable.
- **Fix:** Add per-instance memoization:
  ```php
  private array $permissionCache = [];
  public function hasPermission($key): bool {
      return $this->permissionCache[$key] ??= $this->resolvePermission($key);
  }
  ```

### H2. `AgencyPolicy::manage/view` uses `$user->agencies->contains()` — forces agencies load
- **File:** `app/Policies/AgencyPolicy.php:20,34`
- **Problem:** `$user->agencies` lazy-loads the agencies relationship the first time it's referenced. For admin users, line 16/30 short-circuits (good). For non-admins, the full pivot collection is materialized including `access_all_clients`. This is fine when done once, but policy methods may be called multiple times per request (e.g., each agency in a listing) — collection is cached after first load, so only 1 query.
- **Impact:** 1 extra query per request when agency policy is hit for non-admins. Acceptable.
- **Recommendation:** For existence check only, use `$user->agencies()->whereKey($agency->id)->exists()` if you *don't* need to materialize the whole collection elsewhere. But since `accessibleClientIds()` also iterates `$this->agencies`, keeping the collection loaded is overall cheaper. **Leave as-is.**

### H3. `validateSingleAgencyConstraint` / `validateRoleAgencyConstraint` run `agencies()->count()`
- **File:** `app/Models/User.php:184, 196`
- **Problem:** `$user->agencies()->count()` issues a `SELECT COUNT(*)` query. If `$this->agencies` is already loaded (which it often is for the current user), this is wasteful. If called after attaching (admin flow), re-fetching is correct.
- **Impact:** Low — only runs on user create/update flows, not hot path.
- **Fix:** `$user->relationLoaded('agencies') ? $user->agencies->count() : $user->agencies()->count()`.

### H4. `accessibleClients()` builder cannot be chained for pagination efficiency
- **File:** `app/Models/User.php:58-61`
- **Problem:** Uses `whereIn('id', $this->accessibleClientIds())` — eager-materializes a collection then passes it as array. For users with many accessible clients (agency with hundreds of clients), this becomes a large `IN (...)` list. Acceptable for <1k clients but breaks at scale.
- **Impact:** Medium at scale. Sub-1k clients: negligible.
- **Fix:** For large tenants, switch to a subquery:
  ```php
  return Client::where(function ($q) {
      $q->whereIn('agency_id', $agencyIds)
        ->orWhereIn('id', $directClientIdsSub);
  });
  ```

---

## Medium

### M1. `CheckTokenExpiry` accesses `$request->user()?->currentAccessToken()` after Sanctum already validated
- **File:** `app/Http/Middleware/CheckTokenExpiry.php:17-19`
- **Problem:** Sanctum already checks `expires_at` natively (Laravel 10+). This middleware duplicates native behavior. Removing it saves one object-method call per API request.
- **Impact:** Micro-optimization, but dead code = bug surface. Verify Sanctum config `expiration` vs. per-token `expires_at` semantics before removing.

### M2. `AdminOnlyMode` calls `Cache::get('admin_only_login')` on every request
- **File:** `app/Http/Middleware/AdminOnlyMode.php:15`
- **Problem:** Cache hit per request. If cache driver is Redis/Memcached: ~1ms. If `file` driver (default in dev): disk I/O. Key is static so could be memoized via `Cache::store('array')->remember(...)` or a service provider singleton.
- **Impact:** Low with Redis; notable with file cache.
- **Fix:** Use `config('app.admin_only_login_cached_flag')` pulled once per boot, or Octane-safe singleton.

### M3. `EnsureUserIsAdmin` / `EnsureUserCanSeeLogs` assume `auth()->user()` is non-null
- **File:** `app/Http/Middleware/EnsureUserIsAdmin.php:18`
- **Problem:** `auth()->user()->hasPermission(...)` will throw `Call to member function on null` if `auth` middleware isn't in front. Not a perf issue per se but can cause 500s that bloat logs.
- **Impact:** Low. Fix: guard like `EnsureUserCanSeeLogs` does.

### M4. `AuthenticatedSessionController@store` — missing session DB read reduction
- **File:** `app/Http/Controllers/Auth/AuthenticatedSessionController.php:30`
- **Problem:** Sessions are DB-backed (see migration `0001_01_01_000000_create_users_table.php:33`). Every request does a SELECT + UPDATE on `sessions` table. For an auth-heavy app, this is the single largest per-request cost.
- **Impact:** HIGH in aggregate — 2 queries per authenticated request.
- **Fix:** Switch session driver to Redis (`SESSION_DRIVER=redis`). Same for `cache` driver.

---

## Low

### L1. `TwoFactorController::renderQrCode` instantiates BaconQrCode objects per request
- **File:** `app/Http/Controllers/Auth/TwoFactorController.php:136-144`
- **Problem:** Setup page only — rare call path. Not hot.
- **Impact:** None. Keep.

### L2. `RegisteredUserController` uses `Hash::make()` explicitly
- **File:** `app/Http/Controllers/Auth/RegisteredUserController.php:41`
- **Problem:** `User::$casts['password'] = 'hashed'` already auto-hashes, so `Hash::make()` double-hashes if the cast fires? Actually no — `forceFill` + `save` triggers cast; `create()` with explicit `Hash::make` would double-hash **only if** the cast is applied. Laravel's `hashed` cast is idempotent (checks if already hashed). So this is safe but redundant.
- **Impact:** None functionally; code smell. Remove `Hash::make()` — let the cast do it.

### L3. `NewPasswordController` also calls `Hash::make` explicitly (same pattern)
- **File:** `app/Http/Controllers/Auth/NewPasswordController.php:46`
- **Impact:** Same as L2.

### L4. `ContentSecurityPolicy` builds CSP header string on every response
- **File:** `app/Http/Middleware/ContentSecurityPolicy.php:25-33`
- **Problem:** `implode()` runs per request. String is static.
- **Fix:** Move to a class constant. Saves ~10 microseconds per request.
- **Impact:** Negligible.

### L5. `PasswordResetLinkController` / `NewPasswordController` issue SMTP sends synchronously
- **File:** `app/Http/Controllers/Auth/PasswordResetLinkController.php:35`
- **Problem:** `Password::sendResetLink()` sends the notification synchronously unless the Notification implements `ShouldQueue`. Check `User::sendPasswordResetNotification` — Laravel's default `ResetPassword` notification is **not** queued.
- **Impact:** ~500ms-2s HTTP request blocked during SMTP.
- **Fix:** Publish a custom `ResetPassword` notification implementing `ShouldQueue`, or override `sendPasswordResetNotification` on `User`.

### L6. `EmailVerificationNotificationController` also sends synchronously
- **File:** `app/Http/Controllers/Auth/EmailVerificationNotificationController.php:20`
- **Impact:** Same as L5.

### L7. `Role::hasPermission()` re-reads JSON each call
- **File:** `app/Models/Role.php:40`
- **Problem:** `$this->permissions` uses the `array` cast, which decodes the JSON on first access and caches. Subsequent calls are cheap. `isset` + comparison is O(1). No issue.
- **Impact:** None.

---

## Caching opportunities

| Data | Current | Recommended TTL | Expected Gain |
|------|---------|-----------------|---------------|
| `User::accessibleClientIds()` result | `once()` per-request only | Tag-cached per user, 5 min, bust on pivot change | Save 2-6 queries on 90% of authenticated requests |
| `User::hasPermission()` | Re-evaluates logic per call | In-memory per User instance (property) | CPU-only; eliminates 5-20 redundant calls/request |
| `Cache::get('admin_only_login')` | Looked up every request | Use in-memory (`array` store) wrapper | Avoid 1 cache I/O per request |
| Sessions table | DB (MySQL) | Switch to Redis (`SESSION_DRIVER=redis`) | Eliminate 2 queries per auth request — biggest win |
| `Role` rows (permissions JSON) | DB on every user hydration | `Cache::remember("role:{id}", 3600, ...)` + override `userRole()` resolver | Save 1 join/query per user load |
| Agency pivot membership | Lazy-loaded | Cache `[user_id => [agency_ids]]` for 5-15 min | Save agency join on policy checks |

---

## Index recommendations

Existing coverage (good): `users.role_id`, `users.email` unique, `agency_user` composite PK `(agency_id, user_id)`, `client_user` composite PK `(client_id, user_id)`.

**Missing indexes:**

```sql
-- Reverse lookup on agency_user (agency_id is leading in PK; user_id is NOT indexed alone)
-- Needed by: $user->agencies() which does WHERE user_id = ? (uses PK only if MySQL can skip-scan — unreliable)
ALTER TABLE agency_user ADD INDEX agency_user_user_id_index (user_id);

-- Same reverse lookup on client_user
ALTER TABLE client_user ADD INDEX client_user_user_id_index (user_id);

-- User.is_active used in User::scopeActive() (admin lists, login filtering)
ALTER TABLE users ADD INDEX users_is_active_index (is_active);

-- Clients.agency_id used by accessibleClientIds() → Client::where('agency_id', ?)
-- Check Client migration; if missing, add:
ALTER TABLE clients ADD INDEX clients_agency_id_index (agency_id);
```

> Note: MySQL 8 supports composite PK left-prefix, so `agency_user (agency_id, user_id)` PK serves queries with `WHERE agency_id = ?`. Queries with only `WHERE user_id = ?` (like `$user->agencies()`) perform a **full index scan** without a dedicated `user_id` index. This is the single most impactful missing index in the RBAC chain.

---

## Password hashing / session notes

- **BCrypt rounds:** Default Laravel 12 is 12 rounds. Not overridden in config. Appropriate for modern hardware (~250ms). No change.
- **Session driver:** Migration creates `sessions` table implying `SESSION_DRIVER=database`. **Strongly recommend Redis** — see M4.
- **2FA cookie HMAC:** Correctly uses `hash_hmac('sha256', ...)` with `hash_equals` — no timing attack, no DB query. Excellent.

---

## Summary — top 3 fixes

1. **Fix N+1 in `User::accessibleClientIds()`** (`User.php:42-49`) — collapse per-agency `Client::where` loop into a single `whereIn`. Biggest DB-query reduction per request for non-admin users.
2. **Add missing pivot reverse-lookup indexes** — `agency_user.user_id` and `client_user.user_id`. Directly speeds up `$user->agencies()` and `$user->clients()`.
3. **Switch sessions to Redis** — eliminates 2 MySQL queries on every authenticated request. Highest aggregate throughput gain.
