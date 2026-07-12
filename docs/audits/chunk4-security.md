# Chunk 4 — Admin & Multi-Tenant (Security)

**Date:** 2026-04-05
**Scope:** Admin/* controllers, Agency/* controllers, UserController, ClientController, ProfileController, ActivityLogger, ActivityLog, Client, related FormRequests, `routes/web.php` admin.*/agency.* groups.

---

## AI Key / Secret Exposure Check

Grepped the chunk for `env(`, `config(`, `api_key`, `secret`, `phpinfo`, `APP_KEY`, `DB_PASSWORD`, `.env`, `{!!` across Admin/Agency controllers and all `admin/**` blade views.

| Check | Result |
|-------|--------|
| `SystemStatusController` dumps env / phpinfo / DB creds | **PASS** — only reads `sessions` table + `Cache::get('admin_only_login')`. No env/config/filesystem probing. |
| Admin blade views render `env()`/`config()` | **PASS** — no matches across `resources/views/admin/**`. |
| `{!! !!}` raw blade echoes in admin views | **PASS** — zero occurrences in admin/agency views. |
| Activity log payload may contain secrets | **PASS (advisory)** — `ActivityLogger` records the full `$changes` diff of arbitrary models. If a future model includes a token/secret column (e.g. API keys, webhook secrets, `google2fa_secret`), that value would land in `activity_logs.changes` as plaintext and be rendered to admins. Currently no observed model in the audited chunk logs secrets, but `User::$fillable` no longer includes `google2fa_secret`, so observer-based logging would not capture it. Add a denylist in `ActivityLogger::log()` to strip keys matching `/password|secret|token|api_key/i` from `$changes`. |
| `UserController::attachClient` JSON response | **PASS** — returns only `name` + `id`, no sensitive data. |
| `google2fa_secret` surfaced to admins | **PASS** — `$hidden` on User model, cast `encrypted`. `UserController::reset2fa` only nulls it. |

No hardcoded keys or secrets detected in the audited chunk. Anthropic API key correctly read via `config('services.anthropic.api_key')` in non-admin controllers (out of scope but verified they do not echo the value back in responses).

---

## Critical

| # | Finding | File:Line | CWE/OWASP | Scenario | Mitigation |
|---|---------|-----------|-----------|----------|------------|
| C-1 | **Agency Manager can edit / disable / re-role an admin user that shares their agency, bypassing the "protected" flag** | `app/Http/Controllers/Agency/AgencyUserController.php:236-241` (`ensureUserNotProtected`) + policy chain at line 91-94, 114-116, 164-166 | CWE-269 (Improper Privilege Management) / OWASP A01 Broken Access Control | An administrator is attached to Agency A (common — the root admin often owns every tenant). Their `role_id` points to a role where `is_protected` is **not** true (only the *role* flag is checked, never `hasPermission('is_admin')`). An Agency Manager of Agency A opens `PUT /agency/A/users/{admin_id}` → `ensureUserNotProtected` passes → `validateRoleAssignment` only blocks *assigning* a can_manage_users/admin role; it does not stop the manager from stripping admin powers, resetting the password, or flipping `is_active=false`. End result: the manager locks out the platform admin and takes the tenant over. | Add an admin check to the guard: `if ($user->hasPermission('is_admin') \|\| $user->userRole?->hasPermission('is_admin')) { abort(404); }`. Also refuse the action if the **target user's current role** has any permission the acting manager lacks (not only the *new* role). Apply the same guard in `destroy()`. |
| C-2 | **`is_protected` is an opt-in flag with no enforced default on admin-tier roles** | `database/migrations/*` + `app/Http/Controllers/Admin/RoleController.php:42,77` | CWE-1188 (Insecure Default Initialization) | `StoreRoleRequest`/`UpdateRoleRequest` accept any `is_protected` value, and admins can freely toggle it. If an admin (or a seeder, or a future UI tweak) creates an "Administrator" role without `is_protected=true`, finding C-1 becomes exploitable and `AgencyUserController::assignableRoles()` will **list that admin role in the manager's dropdown** (the filter only excludes roles whose permissions the current user lacks — which would also hide it, but only if the manager genuinely lacks `is_admin`; any manager mistakenly granted `is_admin` legacy column would see it). | Force `is_protected=true` at the model layer whenever `permissions['is_admin']===true` or `permissions['can_manage_users']===true`. Use a `Role::saving()` boot hook: `if (($this->permissions['is_admin'] ?? false) \|\| ($this->permissions['can_manage_users'] ?? false)) { $this->is_protected = true; }`. |

---

## High

| # | Finding | File:Line | CWE/OWASP | Scenario | Mitigation |
|---|---------|-----------|-----------|----------|------------|
| H-1 | **`agency.*` routes are only gated by `auth` middleware; authorization is entirely controller-dependent** | `routes/web.php:55-61` | CWE-862 (Missing Authorization) / OWASP A01 | `Route::prefix('agency/{agency}')->middleware(['auth'])` — no `can_manage_users` middleware at the route level. Every action in `AgencyUserController`/`AgencyClientController` relies on `$this->authorize('manage', $agency)` inside each method. A future method added without `authorize()` will silently expose agency data to every authenticated user (classic IDOR). | Add an explicit middleware to the group, e.g. `->middleware(['auth','can:manage,agency'])` using implicit policy binding, or register a dedicated `agency.manage` middleware. Defence-in-depth alongside the in-method `authorize()` calls. |
| H-2 | **Privilege escalation window in `RoleController` — `is_protected` toggle unchecked** | `app/Http/Controllers/Admin/RoleController.php:43,77,90-101` | CWE-269 | A non-admin user holding `can_manage_users` is blocked from hitting this controller by the admin route middleware, so current exploit surface is admin-only. However, `update()` lets an admin **un-protect** a role and `destroy()` then deletes it without revalidating `is_protected`, and `preventPrivilegeEscalation` only checks the *granted* permissions — not the old ones. So an admin who had `can_manage_users` stripped mid-session could still mutate protected roles until they are logged out. | In `RoleController::update()`+`destroy()`, refuse the action when `$role->is_protected === true` unless the actor has `hasPermission('is_admin')` **freshly re-checked** (`Auth::user()->refresh()->hasPermission('is_admin')`). Also persist the pre-edit snapshot and re-run `preventPrivilegeEscalation($oldPermissions)` so a demoted admin cannot re-escalate via a previously-held permission. |
| H-3 | **`StoreRoleRequest` / `UpdateRoleRequest` accept arbitrary permission keys** | `app/Http/Requests/StoreRoleRequest.php:17-20`, `UpdateRoleRequest.php:16-19` | CWE-20 (Improper Input Validation) | Rules only say `'permissions' => ['nullable','array']`. A crafted request can inject unknown keys (e.g. `permissions[is_super_god]=1`) that persist in the JSON column. `preventPrivilegeEscalation` silently aborts on unknown keys because `hasPermission('is_super_god')` is false, so escalation itself is blocked — **but** the unknown keys stay in the DB and may be honoured by future code (e.g. a new feature that checks `hasPermission('is_super_god')` will auto-grant it). | Add `'permissions.*' => ['boolean']` and `'permissions' => ['nullable','array']` plus a whitelist: `Rule::forEach(fn ($v,$attr) => in_array(str_replace('permissions.','',$attr), array_keys(Role::availablePermissions())) ? [] : ['prohibited'])` — or simply `Rule::in(array_keys(Role::availablePermissions()))` on the keys via `prepareForValidation`. |
| H-4 | **Admins are selected by legacy column `is_admin` only when terminating sessions** | `app/Http/Controllers/Admin/SystemStatusController.php:72` | CWE-285 (Improper Authorization) | `terminateAll()` does `User::where('is_admin', true)->pluck('id')` and kills every other session. Role-based admins (`is_admin=false` column, but `role.permissions.is_admin=true`) are **not** excluded and will be logged out during maintenance, including potentially the actor's peers. Conversely, a user with legacy `is_admin=true` but whose role has been downgraded is spared — giving the legacy flag implicit override again. | Replace the query with `User::get()->filter->hasPermission('is_admin')->pluck('id')` (or a DB-level check via `whereHas('userRole', fn($q) => $q->whereJsonContains('permissions->is_admin', true))->orWhere('is_admin', true)`). |
| H-5 | **`AgencyController::store` writes the manager password without hashing through `Hash::make`** | `app/Http/Controllers/Admin/AgencyController.php:53` | CWE-257 (ambiguous) | `'password' => $request->validated('manager_password')` is passed raw into `User::create()`. This currently works because `User::$casts` includes `'password' => 'hashed'`, which silently hashes on set. **If that cast is ever removed** (e.g. during a refactor to a Value Object) passwords will be stored plaintext. Every other place in the codebase (`StoreUser`, `AgencyUserController::store`, `UpdateUserRequest::update`) explicitly calls `Hash::make()`, so this is an inconsistency bug. | Normalize: `'password' => Hash::make($request->validated('manager_password'))` — matches the convention and is defence-in-depth against cast removal. |

---

## Medium

| # | Finding | File:Line | CWE/OWASP | Scenario | Mitigation |
|---|---------|-----------|-----------|----------|------------|
| M-1 | **`AudienceController::upload` has no max-size / max-rows limit** | `app/Http/Controllers/Admin/AudienceController.php:104-197` | CWE-400 (Resource Exhaustion) | `'file' => ['required','file','mimes:xlsx,xls,csv,txt']` — no `max:` rule. `Excel::toCollection()` pulls the entire sheet into memory. A 200 MB crafted XLSX could exhaust PHP memory / queue workers. Admin-only, so insider-only. | Add `'max:10240'` (10 MB) to the rule. Consider chunked import (`Excel::filter('chunk')`) or background queue dispatch for >5 k rows. |
| M-2 | **`ActivityLogger::checkAndSendDigest` runs synchronously on every log insert** | `app/Services/ActivityLogger.php:42-66` | CWE-400 | Blocking Mail send inside request lifecycle — slow admin operations, and a mail-server outage leaks stack traces via `report()`. Not directly security but enables timing-based tenancy enumeration (admin writes take longer when digests fire). | Dispatch via `SendActivityDigest` queued job; catch & swallow SMTP exceptions. |
| M-3 | **`ClientController::update` and `StoreClientRequest` let admins change `agency_id` arbitrarily without re-authorizing access** | `app/Http/Controllers/ClientController.php:47-60` + `UpdateClientRequest.php:18` | CWE-639 (IDOR via parameter) | Admin-gated route, low real risk — but a client can be *moved* from Agency A to Agency B with no ActivityLogger note of the reassignment (diff never logged) and no permission check. An admin account taken over could silently relocate clients. | Log the old→new `agency_id` diff via `ActivityLogger`: `$changes = ['agency_id' => ['old' => $client->getOriginal('agency_id'), 'new' => $client->agency_id]]`. |
| M-4 | **`RoleController::destroy` does not block deletion of protected roles** | `app/Http/Controllers/Admin/RoleController.php:90-101` | CWE-285 | Any admin can `DELETE /admin/roles/{protected_role}` as long as no users are attached. A bootstrap admin role that happens to be user-less could be deleted, breaking downstream seeders/fixtures. | Add `if ($role->is_protected) { abort(403,'Protected roles cannot be deleted.'); }`. |
| M-5 | **`ActivityLogController` does not filter by `subject_type` allowlist** | `app/Http/Controllers/Admin/ActivityLogController.php:67-72` | CWE-200 (Exposure of Sensitive Info) | `$query->where('subject_type', 'like', '%'.$searchTerm.'%')` — a non-admin user with access to at least one campaign can type any class FQN into the `search` field and learn which internal classes exist in the system (reconnaissance / stack fingerprinting). `description` search is the intended UX. | Drop the `subject_type` leg of the search `orWhere`, or restrict it to admins (`$isAdmin ? ... : ...`). |
| M-6 | **Self-disable protection is only in `AgencyUserController::destroy`, not in admin `UserController::destroy`** | `app/Http/Controllers/UserController.php:97-111` | CWE-1245 (Improper Finite State Machines) | Admin UserController does block self-delete (line 99), good. But admin `update()` allows an admin to wipe their **own** `role_id` to null, effectively locking themselves out if `is_admin` legacy column is false. Low likelihood, but permanent lockout is possible. | In `UserController::update()`, reject requests where `$user->id === auth()->id()` and the new role lacks `is_admin`. |
| M-7 | **`admin_only_login` toggle persisted in `Cache::forever`** | `app/Http/Controllers/Admin/SystemStatusController.php:62` | CWE-922 (Insecure Storage) | If the cache driver is `array` or `file` with a TTL-less backend, a cache flush during deploy silently turns off "admin-only mode" with no notification. | Persist to `settings` table (or `config cache`) and mirror to cache. Emit an `ActivityLogger` entry on every toggle. |
| M-8 | **`CampaignChangeController::downloadAll` ZIP files written to predictable temp dir with `0750`** | `app/Http/Controllers/Admin/CampaignChangeController.php:160-179` | CWE-377 (Insecure Temp File) | `storage_path('app/temp')` is created `0750`. On a shared host the web user + group are often the same, so 0700 is safer. `Str::random(16)` filename is fine, but the zip is not deleted on exception — only on successful `response()->download(...)->deleteFileAfterSend(true)`. If `$zip->open()` returns false the `$filePath` is created earlier and leaked. | Use `tempnam(sys_get_temp_dir(),'cc_')` + `0700`, wrap in `try/finally` that always `unlink()`s. |

---

## Low / Informational

- **L-1** `StoreAgencyRequest` accepts manager details in the same payload as agency creation. If validation fails on `manager_*`, the already-rendered form does not echo `manager_password` back (good). Consider a two-step wizard for clarity.
- **L-2** `AgencyUserController::ensureUserNotProtected` returns **404** (line 239). Standard OWASP guidance is 403 with an audit log entry when the resource exists but access is denied; 404 is acceptable for obscurity but prevents legitimate debugging.
- **L-3** `Role::hasPermission()` does a strict `=== true` comparison. The `filter_var(..., FILTER_VALIDATE_BOOLEAN)` pre-conversion in `RoleController` is correct, but legacy seeded roles with string `"1"` values would silently fail to match — no security impact, but a footgun.
- **L-4** `UserController::attachClient` (line 203) is a dead-stub route returning a JSON message; advertise or remove.
- **L-5** `AudienceController::batchDelete` uses `exists:audiences,id`; no rate-limiting. A compromised admin session could batch-delete the entire audience table in one request. Consider `ActivityLogger` hook for audience deletions (currently un-logged).
- **L-6** `ProfileController::update` correctly scopes to `name`+`email` via `ProfileUpdateRequest`; `role_id`, `is_admin`, `is_active`, `agency_id` are all unreachable. **PASS.**
- **L-7** `User::$fillable` does **not** contain `is_admin`, `role_id`, `can_view_budget`, `can_see_logs` — mass-assignment protected. **PASS.**
- **L-8** `Client::$fillable = ['name','agency_id']` — minimal; no mass-assignment of `id` or timestamps. **PASS.**
- **L-9** `ActivityLog` has no update/delete routes in the audited chunk — audit trail is append-only from the user-facing perspective. Admin DB access remains the only tamper path. **PASS.**
- **L-10** All blade templates in `admin/**` use `{{ }}`; no `{!! !!}` detected. XSS via logged values (e.g., user-supplied campaign names echoed in `activity_logs/index.blade.php` line 226, 247, 250) is safely escaped. **PASS.**
- **L-11** CSRF: all admin/agency forms go through standard web middleware stack; no `VerifyCsrfToken` exclusions added in this chunk. **PASS.**
- **L-12** `SystemStatusController::index` exposes `user_agent`, `ip_address`, `email`, `is_admin` — admin-only; intentional operator dashboard. **PASS.**

---

## Passed Checks (Summary)

- Tenant isolation in `AgencyUserController`/`AgencyClientController` via `authorize('manage', $agency)` + `ensureUserBelongsToAgency`/`$client->agency_id !== $agency->id`.
- Client list in `AgencyUserController::store/update` intersected with `$agency->clients()` IDs — cannot attach clients from another agency.
- `UserController` admin actions gated by `UserPolicy` (admin-only).
- `ClientController` admin actions gated by `ClientPolicy` (admin-only).
- `ActivityLogController` non-admin view restricted to campaigns under `$user->accessibleClientIds()`.
- `CampaignChangeController` scopes all actions (`show`, `download`, `downloadAll`, `markAsHandled`) via `allowedCampaignIds()`.
- `ProfileController` cannot escalate role/admin/active/agency.
- Mass-assignment: `User`, `Client`, `Agency`, `Role`, `ActivityLog` all have explicit `$fillable`.
- Password hashing: Laravel `hashed` cast on `User::$casts` + explicit `Hash::make()` in all user controllers except one (H-5).
- Blade output uses only `{{ }}` in admin/agency views — no XSS sinks found.
- Sanctum API routes unaffected; `check-token-expiry` + `ability:reports:read` scoped.

---

## Priority Action List

1. **C-1, C-2** — Patch `AgencyUserController` to reject admin-tier users & auto-protect admin roles. *Blocking.*
2. **H-1** — Add defence-in-depth middleware on `agency.*` routes.
3. **H-2, H-3** — Harden `RoleController` (block protected role mutation) and validate permission keys.
4. **H-4** — Fix `terminateAll` to respect role-based admins.
5. **H-5** — Hash the manager password explicitly in `AgencyController::store`.
6. **M-1 … M-8** — Schedule before next release.
