# Agency User Management — Task Breakdown

**Spec:** `docs/specs/agency-user-management.md`

---

## Phase 1: Database & Model Foundation

- [x] **DB-1:** Migration: update `agency_user` — drop `role` column, add `access_all_clients` boolean (default true)
- [x] **DB-2:** Migration: add `is_active` boolean (default true) to `users` table
- [x] **MOD-1:** Update `User::agencies()` relationship — remove `role` pivot, add `access_all_clients` pivot + timestamps
- [x] **MOD-2:** Add `is_active` to User `$fillable` and `$casts` (boolean)
- [x] **MOD-3:** Add `scopeActive()` and `scopeDisabled()` scopes to User model
- [x] **MOD-4:** Update `User::accessibleClientIds()` — respect `access_all_clients` flag per agency
- [x] **MOD-5:** Add `User::isManagerInAgency(Agency)` and `User::managedAgency()` methods
- [x] **MOD-6:** Add `can_manage_users` and `can_manage_clients` to `Role::availablePermissions()`
- [x] **MOD-7:** Block disabled users from login — add `is_active` check to `LoginRequest::authenticate()`
- [x] Run tests after Phase 1

## Phase 2: Authorization

- [x] **AUTH-1:** Create `AgencyPolicy` with `manage()` and `view()` methods
- [x] **AUTH-2:** Register `AgencyPolicy` in `AuthServiceProvider` or auto-discover (auto-discovery works, no manual registration needed)
- [x] **AUTH-3:** Add single-agency constraint enforcement — users with `can_manage_users` cannot belong to multiple agencies
- [x] Run tests after Phase 2

## Phase 3: Agency User Management (Controller + Views)

- [x] **CTRL-1:** Create `AgencyUserController` with all CRUD methods (index, create, store, edit, update, destroy)
- [x] **CTRL-2:** Create `StoreAgencyUserRequest` Form Request (email unique, password rules)
- [x] **CTRL-3:** Create `UpdateAgencyUserRequest` Form Request (email unique excluding self, password nullable, is_active toggle)
- [x] **CTRL-4:** Implement anti-escalation logic (cannot grant `can_manage_users`, cannot exceed own perms)
- [x] **CTRL-5:** Implement user disable in `destroy()` — set `is_active = false`, preserve all pivots
- [x] **CTRL-6:** Implement re-enable toggle via `update()` — set `is_active = true`
- [x] **VIEW-1:** Create `resources/views/agency/users/index.blade.php` — list agency users, show disabled with visual distinction
- [x] **VIEW-2:** Create `resources/views/agency/users/create.blade.php` — form with role picker (filtered), client access toggle (All / Specific)
- [x] **VIEW-3:** Create `resources/views/agency/users/edit.blade.php` — same as create + status toggle + email editable
- [x] **ROUTE-1:** Add `agency/{agency}/users` routes to `web.php`
- [x] Run tests after Phase 3

## Phase 4: Agency Client Management (Controller + Views)

- [x] **CTRL-7:** Create `AgencyClientController` with CRUD methods (scoped to agency)
- [x] **CTRL-8:** Create `StoreAgencyClientRequest` Form Request
- [x] **CTRL-9:** Create `UpdateAgencyClientRequest` Form Request (cannot change agency_id)
- [x] **VIEW-4:** Create `resources/views/agency/clients/index.blade.php`
- [x] **VIEW-5:** Create `resources/views/agency/clients/create.blade.php`
- [x] **VIEW-6:** Create `resources/views/agency/clients/edit.blade.php` — agency_id locked (hidden)
- [x] **ROUTE-2:** Add `agency/{agency}/clients` routes to `web.php`
- [x] Run tests after Phase 4

## Phase 5: Admin Agency Creation Flow

- [x] **ADMIN-1:** Update admin AgencyController `create`/`store` — add optional "Initial Manager" fields (name, email, password)
- [x] **ADMIN-2:** On agency creation with manager fields: auto-create user, assign/create "Agency Manager" role, attach to agency with `access_all_clients = true`
- [x] **VIEW-7:** Update `resources/views/admin/agencies/create.blade.php` — add collapsible "Initial Manager" section
- [x] Run tests after Phase 5

## Phase 6: Sidebar & Navigation

- [x] **NAV-1:** Update sidebar — show agency section for users with `can_manage_users` (single agency, no dropdown)
- [x] **NAV-2:** Agency Manager sidebar shows: "Agency: [Name]" header, then Users + Clients links
- [x] **NAV-3:** Ensure sidebar links use `agency/{id}/users` and `agency/{id}/clients` routes

## Phase 7: Tests

- [x] **TEST-1:** Pest tests for AgencyUserController CRUD (create, edit, update, disable, re-enable)
- [x] **TEST-2:** Pest tests for anti-escalation (cannot grant `can_manage_users`, cannot exceed own perms)
- [x] **TEST-3:** Pest tests for single-agency manager constraint (manager cannot be attached to 2nd agency)
- [x] **TEST-4:** Pest tests for AgencyClientController CRUD (scoped to agency, cannot change agency_id)
- [x] **TEST-5:** Pest tests for `accessibleClientIds()` with `access_all_clients` flag (true vs false)
- [x] **TEST-6:** Pest tests for disabled user login blocked
- [x] **TEST-7:** Pest tests for admin agency creation with auto-manager
- [x] **TEST-8:** Pest tests for multi-agency regular users (viewer in 2 agencies sees both agencies' clients)
- [x] Run full test suite

---

## Stats

| Phase | Tasks |
|-------|-------|
| Phase 1: Database & Models | 9 |
| Phase 2: Authorization | 3 |
| Phase 3: Agency User CRUD | 10 |
| Phase 4: Agency Client CRUD | 7 |
| Phase 5: Admin Creation Flow | 3 |
| Phase 6: Sidebar/Nav | 3 |
| Phase 7: Tests | 8 |
| **Total** | **43** |
