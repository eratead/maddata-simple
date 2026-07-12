# Chunk 4 — Admin & Multi-Tenant (Reviewer)

**Date:** 2026-04-05
**Scope:** Admin/Agency controllers, their FormRequests, ActivityLogger, ActivityLog & Client models.

---

## Critical

### C1. `StoreAgencyRequest` stores plaintext password — no hashing in controller
**File:** `app/Http/Controllers/Admin/AgencyController.php:53` + `app/Http/Requests/StoreAgencyRequest.php:21`
The controller writes `'password' => $request->validated('manager_password')` directly to `User::create()`. Unless a User mutator/casts `hashed` is present, this stores a plaintext password. Every other user-creation path (`UserController::store`, `AgencyUserController::store`) calls `Hash::make()` explicitly — this path does not.
**Fix:** Wrap with `Hash::make($request->validated('manager_password'))`. If the User model already has `'password' => 'hashed'` cast, standardise by removing `Hash::make()` everywhere; otherwise add it here.

### C2. Privilege escalation: non-admin can create users with elevated permissions via agency manager auto-creation
**File:** `app/Http/Controllers/Admin/AgencyController.php:36-61`
Although the agency store route is admin-gated (good), the hard-coded permission set granted to the auto-created "Agency Manager" role (`can_manage_users`, `can_edit_campaigns`, `can_view_budget`, etc.) is stamped via `Role::firstOrCreate`. If an admin without `can_view_budget` creates an agency with manager, they effectively grant a permission they don't hold (bypasses the escalation check pattern used in `RoleController` and `AgencyUserController`).
**Fix:** Route through the same `preventPrivilegeEscalation()` logic, or restrict this endpoint to users with `is_admin`.

### C3. `ClientController@store` / `@update` does not enforce agency scoping
**File:** `app/Http/Controllers/ClientController.php:47-82` + `app/Http/Requests/StoreClientRequest.php:18`
`agency_id` is marked `nullable`, and no controller-side check confirms the creating user can manage the chosen agency. Because `ClientPolicy::create/update` only verifies `is_admin`, an admin is fine — but the shape of the pivot model means an agency-manager who reaches this controller (intentionally or via a future loosening of the admin middleware) could move clients between agencies. Worse, a client can be created with `agency_id = null`, violating the "Client belongs to ONE Agency" rule in `project_context.md §2`.
**Fix:** Make `agency_id` `required|exists:agencies,id` in `StoreClientRequest`/`UpdateClientRequest`, and add DB-level `NOT NULL` if not already enforced. Add policy check `$user->hasPermission('is_admin') || $user->agencies->contains($validated['agency_id'])` before save.

### C4. `UserController@destroy` does not detach `agencies` pivot
**File:** `app/Http/Controllers/UserController.php:97-112`
`$user->clients()->detach()` is called, but `agencies()` pivot rows are left dangling. `users` are hard-deleted (no SoftDeletes), so orphan `agency_user` rows remain pointing to a missing user. If `users.id` ever gets reused (or FK cascade is absent), this leaks cross-tenant state.
**Fix:** Add `$user->agencies()->detach();` before delete, or rely on DB cascade FKs. Also consider moving to soft-delete/`is_active=false` for consistency with `AgencyUserController@destroy` which disables rather than deletes.

### C5. `UserController@destroy` privilege-escalation gap: any admin can delete another admin
**File:** `app/Http/Controllers/UserController.php:97-112`
`UserPolicy::delete` returns `true` for any `is_admin` user. There is no check preventing a lower-privilege admin from deleting a higher-privilege one, nor from deleting the last admin. Combined with `can_manage_users` (Agency Manager), if `admin` middleware ever widens, any manager could delete admins.
**Fix:** Add check "cannot delete a user whose role has permissions you don't hold" analogous to `preventPrivilegeEscalation`, plus guard last-admin invariant.

### C6. `AudienceController` — no authorization, no activity logging
**File:** `app/Http/Controllers/Admin/AudienceController.php` (entire file)
Zero `authorize()` calls, zero `ActivityLogger` calls on `store`/`update`/`destroy`/`upload`/`batchDelete`. While routes are under admin middleware, CLAUDE.md mandates "every CRUD logged via `ActivityLogger`". Also, `upload()` accepts arbitrary XLS/CSV with no row limit — potential memory DoS.
**Fix:** Add `ActivityLogger->log()` calls for create/update/delete/upload/batch-delete. Add row-count guard in `upload()`.

### C7. Form Request authorization is a blanket `return true`
**All FormRequests in scope**
Every `authorize()` returns `true` with the comment "Controller handles authorization". This is valid Laravel, but it means validated-data construction happens before auth, and if a developer ever removes the controller `$this->authorize()` call (or a new endpoint reuses the request), authorization is silently gone. Not a vuln today, but a landmine.
**Fix:** Move auth into the request where possible (e.g. `StoreClientRequest::authorize` → `$this->user()?->can('create', Client::class)`).

---

## High

### H1. Inconsistent escalation-prevention patterns across three controllers
**Files:** `RoleController.php:106-115`, `AgencyUserController.php:186-202`, `UserController.php:49-65, 142-158`
Three different implementations of the same concept. `UserController` only blocks `is_admin` escalation (not other permissions). `AgencyUserController` blocks `can_manage_users` + all higher perms. `RoleController` blocks all higher perms. A user with `can_view_budget=false` creating via `UserController` can still assign a role granting `can_view_budget=true`.
**Fix:** Extract one shared trait/service `PreventsPrivilegeEscalation::validateRoleAssignment(Role $role)` and call it everywhere. Align on the strictest semantics (per-permission check).

### H2. `UserController` single-agency-manager check runs on `count($agencies) > 1` only — allows 0
**File:** `app/Http/Controllers/UserController.php:62-64, 155-157`
A user whose role has `can_manage_users` can be saved with zero agencies, creating an orphan Agency Manager with no scope — likely a footgun (they can't manage anything and cannot be reached via agency routes).
**Fix:** Require `count($agencyData) === 1` for managers.

### H3. `UserController` does not enforce "manager limited to ONE agency" on existing user adding a 2nd agency via manual sync path
**File:** `app/Http/Controllers/UserController.php:173-189`
The check uses `$targetRole` from the new `role_id`. If a previously-non-manager user is promoted to manager while having 2 agencies, the single check works — good. But an admin could bypass by first assigning a non-manager role + multi-agency, then a second request upgrading role without sending the agencies array. Second request sees `count($agencyData) = 0` → passes, but DB still has 2 rows untouched (sync would wipe them though — verify).
**Fix:** Refetch and validate `$user->agencies()->count() <= 1` when target role has `can_manage_users`, regardless of current payload.

### H4. `AgencyUserController::store` missing duplicate-user handling
**File:** `app/Http/Controllers/Agency/AgencyUserController.php:57-78`
If an agency manager tries to attach an existing user (not a new one) the form always calls `User::create` — will fail on unique email. There's no "attach existing user" path, yet the Agency Manager is the only role intended to manage multi-agency users. Per project_context: "Regular users can belong to multiple agencies" — so an agency manager should be able to attach existing users from another agency but currently cannot.
**Fix:** Add an "attach existing user" endpoint scoped by email lookup; or document the limitation.

### H5. `ClientController::index` gives non-admins a useless page
**File:** `app/Http/Controllers/ClientController.php:21` + `app/Policies/ClientPolicy.php:14-16`
`$this->authorize('viewAny', Client::class)` returns 403 for anyone not `is_admin`, but the controller then branches on `$user->hasPermission('is_admin')` vs `$user->clients()` — the else branch is dead code.
**Fix:** Either broaden `ClientPolicy::viewAny` to allow `can_manage_clients`/`can_view_campaigns` holders, or remove the dead branch.

### H6. `CampaignChangeController::show` — budget filter ordering allows oversharing
**File:** `app/Http/Controllers/Admin/CampaignChangeController.php:81-97`
`excludeBudgetLogs` only excludes logs containing `"budget"` in the JSON column. A log with `"budget_spent"`, `"budget_remaining"`, or nested structures could leak. The `unique()` logic then also runs after the exclusion but picks "latest log for each tuple" — if the latest log has budget, it gets dropped and the user sees nothing, not the prior (non-budget) version.
**Fix:** Prefer a structured flag (e.g. `changes->contains_budget` boolean column or separate log type). At minimum, anchor the pattern: `'%"budget":%'`.

### H7. `AgencyController::destroy` does not detach pivot `agency_user` rows
**File:** `app/Http/Controllers/Admin/AgencyController.php:98-110`
Deletes the agency when `clients()->count() === 0` but leaves `agency_user` pivot rows pointing to deleted agency. Agency Managers bound to this agency now point at a ghost.
**Fix:** `$agency->users()->detach();` inside transaction, or rely on FK cascade.

### H8. `AgencyController::store` — agency creation logs outside the transaction
**File:** `app/Http/Controllers/Admin/AgencyController.php:30-66`
`ActivityLogger::log(...)` is called after the `DB::transaction` returns. If the log insert fails, the agency still exists (acceptable) — but more importantly the logger is called without the auto-created manager being logged at all. The new User is unmentioned in activity logs.
**Fix:** Log the user creation inside the transaction (or after) explicitly.

### H9. `SystemStatusController` reads `users.is_admin` directly
**File:** `app/Http/Controllers/Admin/SystemStatusController.php:27, 72`
The codebase is in the middle of migrating from `is_admin` boolean to Role-based perms (per CLAUDE.md "legacy fallback"). `terminateAll` excludes users with `is_admin=true` via raw column — misses admins whose privilege comes from a Role with `is_admin` perm.
**Fix:** Resolve admin users via `User::all()->filter(fn($u) => $u->hasPermission('is_admin'))->pluck('id')` or add a scope.

---

## Medium

### M1. `RoleController::reorder` authorization bypass potential
**File:** `app/Http/Controllers/Admin/RoleController.php:117-129`
No `authorize()` or privilege check. Routes under admin middleware protect it today, but any move to loosen middleware would expose arbitrary role reorder (which affects UI display).
**Fix:** Add an explicit permission check.

### M2. `ActivityLogController` filter query — n+1 on `subject` morph
**File:** `app/Http/Controllers/Admin/ActivityLogController.php:17-25`
`morphTo` eager-load is attempted but `morphWith` is limited to `CreativeFile`; other subject types (User, Agency, Role, Client, Campaign) still lazy-load. Over 50 paginated rows this can trigger many queries.
**Fix:** Extend `morphWith` to cover all subject types.

### M3. Code duplication between Admin & Agency scopes
**Files:** `UserController.php` vs `Agency/AgencyUserController.php`, `ClientController.php` vs `Agency/AgencyClientController.php`
Client/user sync, escalation, attach/detach logic are duplicated with subtle differences. Duplication caused H1.
**Fix:** Extract Actions (`CreateAgencyUserAction`, `UpdateUserAction`) or a `UserManager` service.

### M4. `ActivityLogger::log` silently loses `campaign_id`
**File:** `app/Services/ActivityLogger.php:18-27`
`method_exists($model, 'campaign')` is true for any model with a `campaign()` relation — but we only read `$model->campaign_id`, which is a column not relation access. For a model that has a `campaign()` method but no `campaign_id` column (e.g. `PlacementData` via `LineItem`), this returns `null` without loading the relationship.
**Fix:** `$campaignId = $model->campaign_id ?? $model->campaign?->id;`.

### M5. `ActivityLogger::checkAndSendDigest` synchronously in request lifecycle
**File:** `app/Services/ActivityLogger.php:42-66`
Fires synchronous mail on the 2-hour boundary. Under concurrent writes, multiple requests can all cross the threshold before the cache updates, sending duplicate digests.
**Fix:** Use a scheduled command + cache lock; dispatch mail via queue.

### M6. `CampaignChangeController::downloadAll` temp file race + size check after-the-fact
**File:** `app/Http/Controllers/Admin/CampaignChangeController.php:160-180`
Creates zip in shared `storage/app/temp/` with random name (ok), but adds files with original user-controlled `$file->name` as archive entry — path traversal risk if names contain `/` or `..`. Also the total-size check happens before writing, but `Storage::disk('creatives')->size()` per file generates N round-trips (acceptable for 200 files).
**Fix:** `basename($file->name)` when calling `$zip->addFile(..., basename(...))`.

### M7. `AudienceController::upload` relies on cell heuristics, silently skips rows
**File:** `app/Http/Controllers/Admin/AudienceController.php:118-190`
Skipped rows are not reported. Users uploading malformed spreadsheets see "Imported 0" with no explanation.
**Fix:** Collect skip reasons and flash them as warnings.

### M8. Inconsistent `ActivityLogger` usage on errors
**Files:** multiple — controllers log BEFORE delete in some places and AFTER in others (e.g. `AgencyController::destroy` logs before `delete()`, `UserController::destroy` logs before `delete()`, but `ClientController::destroy` logs before too — consistent actually). But on `RoleController::destroy:96` we log, then delete — if delete fails, the log is orphaned.
**Fix:** Wrap destroy + log in `DB::transaction`.

### M9. Thin-controller violation: heavy logic in UserController/AgencyUserController
**Files:** `UserController.php:67-95, 160-189`, `AgencyUserController.php:50-84, 112-157`
50+ lines of sync/pivot/escalation logic in controller actions. CLAUDE.md: "Controllers are thin (no business logic)".
**Fix:** Extract to Actions or Services.

### M10. `ProfileController@destroy` never logs
**File:** `app/Http/Controllers/ProfileController.php:43-59`
Account self-deletion is not logged via `ActivityLogger`. Also `User::delete()` here produces the same orphan pivot rows as C4.
**Fix:** Log + detach agencies/clients.

### M11. `AgencyClientController::edit/update/destroy` do not use policy for `Client`
**File:** `app/Http/Controllers/Agency/AgencyClientController.php:54-108`
Only checks `authorize('manage', $agency)` + manual `$client->agency_id !== $agency->id` guard. Works, but inconsistent with `ClientController` which uses `ClientPolicy`.
**Fix:** Add `ClientPolicy::updateForAgency(User, Client, Agency)` or inject a single rule.

---

## Low / Nitpicks

### L1. `AgencyController::update:87` passes entire `validated()` including manager fields
If `manager_name`/`manager_email`/`manager_password` get used during edit form, `update` may try to mass-assign them into Agency — mitigated by `$fillable` on Agency, but still sloppy.
**Fix:** `only(['name'])`.

### L2. `RoleController::store/update` rely on `$request->name` instead of `$request->validated('name')`
**File:** `app/Http/Controllers/Admin/RoleController.php:41, 75`
Pattern mismatch with other controllers.

### L3. `AgencyUserController::edit:99` uses `->first()?->pivot` — a full user row is selected just for pivot
**Fix:** `$agency->users()->wherePivot('user_id', $user->id)->firstPivot()` or query `agency_user` directly.

### L4. `UserController::attachClient:203-210` returns a JSON stub placeholder
Dead/unfinished code — returns "Attach client dialog for user …". Per CLAUDE.md: "No dead code or commented-out blocks".

### L5. `ClientController::index:32` — `get()` on potentially large client lists
Admins with many clients pay the full memory cost. Consider pagination or at least `limit(500)` with a search filter.

### L6. `ActivityLog` model has no `$casts` for `status`
If `status` ever needs enum semantics, add `Casts::enum(...)`.

### L7. `AudienceController` uses `Request` directly rather than a Form Request
Violates CLAUDE.md: "Form Requests used for all validation". Create `StoreAudienceRequest`, `UploadAudienceRequest`.

### L8. `StoreAgencyUserRequest` unique-email check is global
**File:** `app/Http/Requests/Agency/StoreAgencyUserRequest.php:19`
Prevents an agency manager from attaching an existing user (ties back to H4).

### L9. `SystemStatusController::parseBrowser` — Chrome check before Safari
Order matters (all Chromes contain "Safari"), current order is correct — but document intent via comment.

### L10. `AgencyController.php:9` imports `Role` and `User` but has coupling inside a controller action instead of a factory/service.

### L11. `ProfileUpdateRequest` missing `authorize()` method
Defaults to `true` from parent — fine, but per other request files in the project the pattern is explicit.

### L12. `AudienceController::upload` hardcodes header detection on the word "category"
Fragile. Use a declarative schema in `config/audiences.php`.

### L13. `tempDir` created with permissions 0750 via `mkdir` but older entries are never cleaned.
**File:** `CampaignChangeController.php:161`. `deleteFileAfterSend(true)` handles current file but if download fails server-side, file lingers.

---

## Positive observations

- **Agency enforcement via pivot columns** is consistently implemented with `access_all_clients` (matches project_context) — no stray `role` pivot references found.
- **`AgencyUserController`** correctly gates on three layers: policy (`manage`), pivot-ownership (`ensureUserBelongsToAgency`), and protected-role filtering (`ensureUserNotProtected`). This is the gold standard.
- **`CampaignChangeController`** uses defensive `allowedCampaignIds()` gate at every action entry point — good.
- **`ZipArchive` size+count limits** in `downloadAll` (500MB, 200 files) prevent DoS.
- **Agency destroy** refuses deletion when clients exist — prevents cascade orphaning of campaigns.
- **Transactions** used in `AgencyController::store` to keep agency + manager + pivot atomic.
- **Route-model binding** used throughout; `FormRequest` `Rule::unique(...)->ignore(...)` correctly applied.
- **`is_protected` role flag** used by agency-scoped controller to prevent users assigning admin-equivalent protected roles.
- **Self-disable prevention** in `AgencyUserController::destroy` (`$user->id === auth()->id()`).
- **`preventPrivilegeEscalation()` in `RoleController`** is the cleanest implementation in the chunk and should be the template for the others.
- **`ActivityLogger`** auto-resolves `campaign_id` across polymorphic subject types — nice abstraction.
- **Cache invalidation** (`clients_list`, `active_audiences`) is consistently applied after mutations.
- **`SystemStatusController::terminateAll`** correctly preserves admin sessions.

---

## Summary

**Total findings:** 32 (6 Critical, 9 High, 11 Medium, 13 Low/Nitpicks)

The chunk is functional and security-minded but suffers from **three recurring structural issues**:
1. Three separate re-implementations of privilege-escalation checks with drifting semantics.
2. Code duplication between Admin scope and Agency scope with no shared Actions/Services (fat controllers).
3. `ActivityLogger` coverage is patchy — `AudienceController`, `ProfileController`, and some destroy-then-log orderings break the audit trail.

**Recommended next steps:** Address C1-C6 immediately (plaintext password, escalation gap, missing agency_id, orphan pivots, audience auth/logging). Then refactor escalation checks into a shared trait/service and extract user/client sync into Actions.
