# Spec: Agency User Management

## Goal

Enable agency-level user management where Agency Managers can independently manage their own users and clients within their agency scope, without Platform Admin intervention. This creates a two-tier admin system:

- **Platform Admin** (MadData): Creates agencies, assigns Agency Managers, has full system access
- **Agency Manager**: Manages users and clients within their single agency only

## Business Rules

### Hierarchy
```
Platform Admin (is_admin)
  └── Agency (e.g., McCann)
       ├── Agency Manager (can_manage_users + can_manage_clients)
       │    └── MUST belong to exactly ONE agency
       │    └── Can: create users, assign client access, CRUD clients — within own agency
       │    └── Cannot: grant can_manage_users to others, exceed own permissions
       ├── Agency User (various roles: Viewer, Editor, etc.)
       │    └── CAN belong to multiple agencies (controllers, freelancers)
       │    └── Sees: ALL agency clients  -or-  specific assigned clients
       └── Clients (advertisers)
            └── Campaigns → Data, Placements, Creatives
```

### Permission Rules
1. Agency Manager can only manage users/clients in their **single** agency
2. Agency Manager cannot assign roles with `can_manage_users` permission (no sub-managers)
3. Agency Manager cannot assign roles with more permissions than they hold
4. **Agency Manager constraint:** Users with `can_manage_users` can belong to exactly ONE agency. Enforced on attach.
5. **Regular users:** Users WITHOUT `can_manage_users` CAN belong to multiple agencies (e.g., controller viewing multiple agencies, freelancer)
6. A "controller" user added to an agency with a read-only Role can view but not modify
7. Platform Admin bypasses all scoping (existing `is_admin` behavior)

### User Lifecycle
1. **Create**: Agency Manager creates user → auto-attached to manager's agency
2. **Active**: User works within agency, sees assigned clients/campaigns
3. **Leave**: User is **disabled** (`is_active = false` on User model). History preserved. Pivot records preserved.
4. **Rejoin elsewhere**: Must use a different email, or the previous agency manually changes the disabled user's email to free it up. Email stays unique in DB — no SoftDeletes.

### Email & Disable Rules
- **Email is globally unique** in the `users` table (standard DB unique constraint, no changes)
- **No SoftDeletes.** Disabled users stay in the DB with `is_active = false`
- If a person needs to join another agency with the same email, **two manual options:**
  1. Previous agency manager changes the disabled user's email (e.g., `old+disabled@gmail.com`)
  2. Person registers with a different email
- This keeps the system simple and the audit trail clean

---

## Database Changes

### Migration 1: Modify `agency_user` pivot

Drop the `role` column (not needed — global Role model handles permissions). Add `access_all_clients` boolean.

```php
// 2026_03_23_000001_update_agency_user_table.php

Schema::table('agency_user', function (Blueprint $table) {
    $table->dropColumn('role');
    $table->boolean('access_all_clients')->default(true)->after('agency_id');
});
```

**Pivot schema after migration:**
| Column | Type | Description |
|--------|------|-------------|
| agency_id | FK | Agency |
| user_id | FK | User |
| access_all_clients | boolean | `true` = sees all agency clients, `false` = only `client_user` entries |
| created_at | timestamp | |
| updated_at | timestamp | |

### Migration 2: Add `is_active` to users

```php
// 2026_03_23_000002_add_is_active_to_users.php

Schema::table('users', function (Blueprint $table) {
    $table->boolean('is_active')->default(true)->after('email');
});
```

### Migration 3: Add new permissions to seed/existing roles

No migration needed — permissions are added to `Role::availablePermissions()` and can be toggled on roles via the admin UI.

**New permissions to add:**
| Permission Key | Label | Description |
|---------------|-------|-------------|
| `can_manage_users` | Can Manage Users | Create/edit/disable users within own agency |
| `can_manage_clients` | Can Manage Clients | Create/edit/delete clients within own agency |

---

## Model Changes

### User Model

```php
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    // NO SoftDeletes — disabled users use is_active = false

    protected $fillable = [
        // ... existing fields ...
        'is_active',  // NEW
    ];

    protected $casts = [
        // ... existing casts ...
        'is_active' => 'boolean',
    ];

    // Update agencies() pivot — drop 'role', add 'access_all_clients'
    public function agencies()
    {
        return $this->belongsToMany(Agency::class)
            ->withPivot('access_all_clients')
            ->withTimestamps();
    }

    // Update accessibleClientIds() to respect access_all_clients flag
    public function accessibleClientIds(): Collection
    {
        return once(function () {
            // Direct client access (from client_user pivot)
            $directIds = $this->clients()->pluck('clients.id');

            // Agency-based access
            $agencyClientIds = collect();
            foreach ($this->agencies as $agency) {
                if ($agency->pivot->access_all_clients) {
                    // All clients in this agency
                    $ids = Client::where('agency_id', $agency->id)->pluck('id');
                    $agencyClientIds = $agencyClientIds->merge($ids);
                }
                // If access_all_clients is false, only client_user entries count
                // (those are already in $directIds)
            }

            return $directIds->merge($agencyClientIds)->unique()->values();
        });
    }

    // Check if user is a manager in a specific agency
    public function isManagerInAgency(Agency $agency): bool
    {
        return $this->agencies->contains($agency->id)
            && $this->hasPermission('can_manage_users');
    }

    // Get the single agency this manager manages (returns null if not a manager)
    public function managedAgency(): ?Agency
    {
        if (!$this->hasPermission('can_manage_users')) {
            return null;
        }
        return $this->agencies->first();
    }

    // Scope: only active users
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope: only disabled users
    public function scopeDisabled($query)
    {
        return $query->where('is_active', false);
    }
}
```

### Role Model

Add new permissions to `availablePermissions()`:

```php
public static function availablePermissions(): array
{
    return [
        'is_admin' => 'Administrator',
        'can_view_campaigns' => 'Can View Campaigns',
        'can_edit_campaigns' => 'Can Edit Campaigns',
        'can_view_budget' => 'Can View Budget',
        'can_upload_reports' => 'Can Upload Reports',
        'can_see_logs' => 'Can See Logs',
        'can_manage_users' => 'Can Manage Users',      // NEW
        'can_manage_clients' => 'Can Manage Clients',  // NEW
    ];
}
```

### Agency Model

No changes needed. Existing relationships are correct.

---

## Constraint: Single-Agency Manager

When attaching a user with `can_manage_users` to an agency, enforce they don't already belong to another agency:

```php
// In AgencyUserController or a service:
if ($targetRole->hasPermission('can_manage_users') && $user->agencies()->count() > 0) {
    abort(422, 'Users with management permissions can only belong to one agency.');
}
```

When assigning a role with `can_manage_users` to a user already in multiple agencies:
```php
if ($targetRole->hasPermission('can_manage_users') && $user->agencies()->count() > 1) {
    abort(422, 'This user belongs to multiple agencies and cannot be assigned a manager role.');
}
```

---

## Routes

### New Agency-Scoped Management Routes

```php
// routes/web.php — inside auth middleware group

// Agency Manager routes — for managing users/clients within their agency
Route::prefix('agency/{agency}')
    ->middleware(['auth'])
    ->name('agency.')
    ->group(function () {

        // Agency Users management
        Route::get('/users', [AgencyUserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [AgencyUserController::class, 'create'])->name('users.create');
        Route::post('/users', [AgencyUserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [AgencyUserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [AgencyUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AgencyUserController::class, 'destroy'])->name('users.destroy');

        // Agency Clients management
        Route::get('/clients', [AgencyClientController::class, 'index'])->name('clients.index');
        Route::get('/clients/create', [AgencyClientController::class, 'create'])->name('clients.create');
        Route::post('/clients', [AgencyClientController::class, 'store'])->name('clients.store');
        Route::get('/clients/{client}/edit', [AgencyClientController::class, 'edit'])->name('clients.edit');
        Route::put('/clients/{client}', [AgencyClientController::class, 'update'])->name('clients.update');
        Route::delete('/clients/{client}', [AgencyClientController::class, 'destroy'])->name('clients.destroy');
    });
```

### Existing Admin Routes (unchanged)

The existing `admin.users.*`, `admin.clients.*`, `admin.agencies.*` routes remain for Platform Admins. Agency Managers use the new `agency.*` routes.

---

## New Controllers

### AgencyUserController

**Location:** `app/Http/Controllers/Agency/AgencyUserController.php`

```
index(Agency $agency)
    - Authorize: user must be manager in this agency (via AgencyPolicy::manage)
    - List users belonging to this agency (via agency_user pivot)
    - Show: name, email, role, client access mode, is_active status
    - Include disabled users (is_active = false), shown with visual distinction

create(Agency $agency)
    - Authorize: manager in this agency
    - Show form: name, email, password, role (filtered), client access

store(StoreAgencyUserRequest $request, Agency $agency)
    - Authorize: manager in agency
    - Validate: name, email (unique), password, role_id, clients[]
    - Anti-escalation: role cannot have can_manage_users, cannot exceed manager's perms
    - Create User record (is_active = true)
    - Attach to agency via pivot (set access_all_clients)
    - If not access_all_clients: sync client_user with selected clients (must belong to agency)

edit(Agency $agency, User $user)
    - Authorize: manager in agency, user belongs to agency

update(UpdateAgencyUserRequest $request, Agency $agency, User $user)
    - Same validation + escalation checks as store
    - Update user fields, role, client access
    - Can also change email (to free up email for re-creation elsewhere)

destroy(Agency $agency, User $user)
    - Authorize: manager in agency
    - Set is_active = false (disable, do NOT delete)
    - Do NOT detach from pivots (preserve history)
    - Manager can also re-enable (set is_active = true) via update
```

### AgencyClientController

**Location:** `app/Http/Controllers/Agency/AgencyClientController.php`

```
index(Agency $agency)
    - Authorize: user must be manager in this agency
    - List clients where agency_id = this agency

create(Agency $agency)
    - Authorize: manager in agency
    - Show form: client name, details

store(StoreAgencyClientRequest $request, Agency $agency)
    - Create client with agency_id = agency.id

edit(Agency $agency, Client $client)
    - Authorize: manager in agency, client belongs to agency

update(UpdateAgencyClientRequest $request, Agency $agency, Client $client)
    - Update client (cannot change agency_id — only Platform Admin can reassign)

destroy(Agency $agency, Client $client)
    - Delete client only if it has no campaigns
    - Otherwise return error
```

---

## Form Requests

### StoreAgencyUserRequest

```php
rules(): [
    'name' => ['required', 'string', 'max:255'],
    'email' => ['required', 'email', 'unique:users,email'],
    'password' => ['required', Password::min(8)->mixedCase()->numbers()],
    'role_id' => ['required', 'exists:roles,id'],
    'access_all_clients' => ['boolean'],
    'clients' => ['array'],
    'clients.*' => ['exists:clients,id'],
]
```

### UpdateAgencyUserRequest

Same as store but email unique excludes current user, password nullable. Also allows `is_active` toggle.

### StoreAgencyClientRequest / UpdateAgencyClientRequest

```php
rules(): [
    'name' => ['required', 'string', 'max:255'],
    // other client fields as needed
]
```

---

## Authorization Logic

### AgencyPolicy (new)

```php
class AgencyPolicy
{
    // Can user manage this agency's users/clients?
    public function manage(User $user, Agency $agency): bool
    {
        if ($user->hasPermission('is_admin')) return true;

        return $user->agencies->contains($agency->id)
            && $user->hasPermission('can_manage_users');
    }

    // Can user view this agency? (for controllers/viewers)
    public function view(User $user, Agency $agency): bool
    {
        if ($user->hasPermission('is_admin')) return true;

        return $user->agencies->contains($agency->id);
    }
}
```

### Anti-Escalation in AgencyUserController

```php
private function validateRoleAssignment(Role $targetRole): void
{
    $currentUser = auth()->user();

    // Agency Manager cannot grant can_manage_users
    if ($targetRole->hasPermission('can_manage_users')) {
        abort(403, 'You cannot grant user management permission.');
    }

    // Cannot assign role with more permissions than own
    foreach ($targetRole->permissions as $key => $granted) {
        if ($granted && !$currentUser->hasPermission($key)) {
            abort(403, "You cannot grant the '{$key}' permission.");
        }
    }
}
```

---

## UI Changes

### Sidebar

Add agency section for Agency Managers (single agency only):

```
Agency: [Agency Name]
  ├── Users        → agency/{id}/users
  ├── Clients      → agency/{id}/clients
  └── (existing campaign/dashboard links)
```

Since Agency Managers belong to exactly one agency, no dropdown needed — just show their agency name.

### Agency User Create/Edit Form

Fields:
- Name, Email, Password
- Role (dropdown, filtered to exclude roles with can_manage_users)
- Client Access: radio [All Agency Clients | Specific Clients]
- If "Specific": multi-select of agency's clients
- Status toggle (active/disabled) — on edit form only

### Agency Client Create/Edit Form

Same as existing admin client form, but agency_id is locked to the current agency (hidden field, not editable).

---

## Login & Disabled Users

Disabled users (`is_active = false`) should be blocked from logging in:

```php
// In LoginController or AuthenticatedSessionController:
// After credentials check, before login:
if (!$user->is_active) {
    throw ValidationException::withMessages([
        'email' => ['Your account has been disabled. Contact your agency manager.'],
    ]);
}
```

---

## Platform Admin: Agency Creation Flow

When Platform Admin creates a new agency:

1. Create Agency record
2. Create "Agency Manager" user (name, email, password)
3. Create or assign "Agency Manager" Role (with can_manage_users + can_manage_clients)
4. Attach user to agency via `agency_user` pivot (access_all_clients = true)

Add "Initial Manager" fields (name, email, password) to the agency create form. These are optional — admin can also create the agency first and assign a manager later.

---

## What Changes vs Current Code

| Area | Current | After |
|------|---------|-------|
| `agency_user.role` column | Exists (unused) | Dropped — replaced by `access_all_clients` boolean |
| User disable | No mechanism | `is_active` boolean on users table |
| Email uniqueness | DB unique constraint | **Same** — stays unique, no changes |
| SoftDeletes | N/A | **Not used** — disabled via `is_active` flag |
| Permissions | 6 permissions | 8 permissions (+ `can_manage_users`, `can_manage_clients`) |
| Agency Manager | Not a concept | Role with `can_manage_users`, limited to ONE agency |
| Regular users | Can belong to multiple agencies | **Same** — no change for non-managers |
| Agency management | Admin-only (CRUD agencies) | Admin creates agencies + managers; managers self-serve |
| User management | Admin-only | Admin manages all; Agency Manager manages within agency |
| Client management | Admin-only | Admin manages all; Agency Manager manages within agency |
| `accessibleClientIds()` | Ignores `access_all_clients` flag | Respects the flag per agency |
| Login | No is_active check | Disabled users blocked from login |

---

## Open Questions

None — all questions resolved in the design discussion.

---

## Dependencies

- No new packages required
- Existing Blade component system used for new views
- Existing permission/role system extended (not replaced)
