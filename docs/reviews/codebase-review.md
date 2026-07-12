# Review: Full Codebase Audit
**Date:** 2026-03-22
**Status:** Needs changes

---

## Critical Issues (must fix)

### 1. Multi-Tenant Data Scoping Ignores Agency Pivot
**Files:** All controllers that scope by `$user->clients()`
**Severity:** Critical

The `project_context.md` defines that users assigned to an agency (via `agency_user` pivot) should have access to ALL clients within that agency. However, no controller or policy checks the `agency_user` pivot table. The `User` model defines `agencies()` relationship but it is never used for data scoping.

- `CampaignController::index()` only checks `$user->clients()` (the `client_user` pivot)
- `CampaignPolicy` only checks `$user->clients->contains($campaign->client_id)`
- `ClientController::index()` only checks `$user->clients()`
- `DashboardController::show()` relies on the CampaignPolicy which has the same gap
- `ActivityLogController` and `CampaignChangeController` also only scope through `$user->clients()`

**Impact:** Users assigned to an agency via `agency_user` cannot see any of that agency's client data unless they are also individually assigned via `client_user`. The entire agency-level access layer is non-functional.

**Fix:** Create a method like `User::accessibleClients()` that merges clients from both pivots:
```php
public function accessibleClients()
{
    $directClientIds = $this->clients()->pluck('clients.id');
    $agencyClientIds = Client::whereIn('agency_id', $this->agencies()->pluck('agencies.id'))->pluck('id');
    return Client::whereIn('id', $directClientIds->merge($agencyClientIds)->unique());
}
```
Then use this everywhere instead of `$user->clients()`.

### 2. `clients` Resource Route Has No Admin Middleware
**File:** `routes/web.php:35`

```php
Route::resource('clients', \App\Http\Controllers\ClientController::class);
```

This route is inside the `auth` group but has no `admin` middleware. While the controller uses `$this->authorize()` calls that check `is_admin` via the `ClientPolicy`, the `clients.show` route exists (from the resource) but has no corresponding controller method. This would result in a 500 error if accessed. More importantly, the `clients` resource should be under the admin prefix for consistency with the architecture.

### 3. `env()` Used Directly in Controllers (Breaks Config Caching)
**Files:** `app/Http/Controllers/CampaignAssistantController.php:31`, `app/Http/Controllers/AiLocationController.php:15`

Both AI controllers use `env('ANTHROPIC_API_KEY')` directly. After running `php artisan config:cache`, `env()` returns `null` outside of config files. This will silently break AI features in production/staging.

**Fix:** Add to `config/services.php`:
```php
'anthropic' => ['api_key' => env('ANTHROPIC_API_KEY')],
```
Then use `config('services.anthropic.api_key')` in controllers.

### 4. Commented-Out Debug Helper in Production Code
**Files:**
- `app/Http/Controllers/CampaignController.php:270` -- `// dd($summary);`
- `app/Http/Controllers/ClientController.php:19` -- `// dump($user);`

These are minor but signal that debugging artifacts slipped through. The `dd()` on line 270 is inside the upload loop and if accidentally uncommented would halt file processing.

### 5. Privilege Escalation Risk in `UserController::store()` and `update()`
**File:** `app/Http/Controllers/UserController.php`

The `store()` and `update()` methods accept a `role_id` from the request without verifying that the assigning user has higher privileges than the role being assigned. Per `project_context.md`: "A user cannot grant a role that has higher permissions than their own." This check is completely missing.

### 6. `markAsHandled()` Does Not Scope `log_ids` to Campaign
**File:** `app/Http/Controllers/Admin/CampaignChangeController.php:175`

```php
ActivityLog::whereIn('id', $logIds)->update(['status' => 'handled']);
```

When `log_ids` are provided, this updates ANY activity log by ID without verifying they belong to the given `$campaign`. A user could mark logs from other campaigns as handled by submitting arbitrary IDs.

---

## Suggestions (recommended improvements)

### 7. DashboardController Is a Fat Controller
**File:** `app/Http/Controllers/DashboardController.php`

The `show()` method is ~120 lines with complex financial calculations (CPM, CPC, spent), multiple database queries, and data transformation. The `exportExcel()` method duplicates much of this logic. The private `calculateSummary()` method (line 145) is dead code -- never called anywhere.

**Recommendation:** Extract a `CampaignMetricsService` that both `show()` and `exportExcel()` share. Delete the unused `calculateSummary()` method.

### 8. CampaignController::upload() Is 165 Lines of Business Logic
**File:** `app/Http/Controllers/CampaignController.php:143-307`

The `upload()` method contains Excel parsing, data transformation, date format detection, PlacementData creation, CampaignData upsert, and video detection. This should be extracted into a service class (e.g., `ReportImportService`).

### 9. Missing Form Requests for Several Controllers
**Files:**
- `ClientController::store()` and `update()` use inline `$request->validate()`
- `UserController::store()` and `update()` use inline `$request->validate()`
- `AgencyController::store()` and `update()` use inline `$request->validate()`
- `RoleController::store()` and `update()` use inline `$request->validate()`

Per project standards, Form Requests should be used for all validation. Only `CampaignController` and `CreativeController` properly use Form Requests.

### 10. `addslashes()` Used for JS Escaping in Blade Templates
**Files:** Multiple Blade views (clients/index, agencies/index, users/edit, etc.)

`addslashes()` is used to escape names injected into Alpine.js `$dispatch()` calls. This is not safe for all edge cases (e.g., names containing backticks, HTML entities, or Unicode). Use `@js()` or `e(json_encode())` instead, which properly handle all escaping.

Example (clients/index.blade.php:93):
```blade
message: '{{ addslashes($client->name) }} will be permanently removed.',
```
Should be:
```blade
message: @js($client->name) + ' will be permanently removed.',
```

### 11. N+1 Risk in CampaignController::index()
**File:** `app/Http/Controllers/CampaignController.php:83`

```php
$clients = $user->hasPermission('is_admin') ? Client::all() : $user->clients;
```

`$user->clients` triggers a lazy-loaded query. If `clients` was already loaded, this is fine, but it's inconsistent -- the admin path uses `Client::all()` (separate query) while the non-admin path may or may not trigger N+1 depending on whether the relationship was already loaded.

### 12. Duplicate `use Notifiable` Trait in User Model
**File:** `app/Models/User.php:8,26`

The `Notifiable` trait is used twice:
- Line 13: `use HasApiTokens, Notifiable;`
- Line 26: `use HasFactory, Notifiable;`

This is harmless but messy. Consolidate to a single `use` statement.

### 13. Missing `is_video` and `budget` Casts on Campaign Model
**File:** `app/Models/Campaign.php`

`is_video` is in `$fillable` but not in `$casts`. It should be cast to `boolean`. `budget` is not cast to `decimal` or `float`, which could lead to precision issues in the financial calculations throughout `DashboardController` and `ReportApiController`.

### 14. `CampaignChangeController::download()` Uses Wrong Disk
**File:** `app/Http/Controllers/Admin/CampaignChangeController.php:115-119`

Downloads use `Storage::disk('public')` while `CreativeController` uses `Storage::disk('creatives')`. If creatives are stored on the `creatives` disk, the download in CampaignChangeController will fail with a file-not-found error.

### 15. `UserController` Routes Missing Admin Middleware
**File:** `routes/web.php:23-24`

The users resource route is inside the `auth` group but not under the admin prefix/middleware. The controller does use `$this->authorize()` which checks admin via policy, but it's inconsistent with the architecture where admin routes should be under `/admin/*` with the `admin` middleware.

### 16. Redundant `middleware('auth')` on Routes Already Inside Auth Group
**File:** `routes/web.php`

Several routes inside the `Route::middleware(['auth'])->group()` block redundantly add `->middleware('auth')` again (lines 24, 39, 43, 51, 66). This is harmless but noisy.

### 17. Agency Feature Missing Tests
The entire agency feature (model, controller, migrations, factory, data migration command) has no test coverage. This is a significant feature addition that should have at least:
- CRUD tests for `AgencyController`
- Tests for the `MigrateAgenciesData` command
- Tests verifying agency-client relationship
- Tests for `ClientFactory` now defaulting to `Agency::factory()`

### 18. `ClientFactory` Always Creates an Agency
**File:** `database/factories/ClientFactory.php:16`

```php
'agency_id' => \App\Models\Agency::factory(),
```

This means every test that creates a `Client` will also auto-create an `Agency`. Existing tests that don't expect an `agencies` table may break when migrations are run. Consider making this `nullable`:
```php
'agency_id' => null,
```

---

## Praise (what was done well)

- **Consistent use of Policies** -- `CampaignPolicy`, `ClientPolicy`, and `UserPolicy` are properly defined and called via `$this->authorize()` in controllers.
- **Proper eager loading** -- `Client::with('agency')->withCount(...)`, `Campaign::with('client')`, `User::with(['clients', 'userRole'])`, and `ActivityLog::with([...])` all demonstrate awareness of N+1 prevention.
- **Security headers on file preview** -- `CreativeController::preview()` sets `X-Content-Type-Options: nosniff`, `Content-Security-Policy: default-src 'none'`, and uses the recorded MIME type. This is excellent defense against stored XSS via uploaded files.
- **EXIF stripping on image uploads** -- Re-encoding images through Intervention Image removes EXIF metadata, preventing GPS location leaks.
- **Sanctum token scoping** -- Tokens are scoped to the authenticated user with explicit expiry (`expires_at`) and a custom `CheckTokenExpiry` middleware.
- **Proper use of `@js()` in many Blade templates** -- Alpine.js data initialization uses `@js()` correctly in campaigns/create, campaigns/index, users/index, and components.
- **Clean migration structure** -- All migrations have proper `up()` and `down()` methods. The agency migrations use proper foreign key constraints with `nullOnDelete()` and `cascadeOnDelete()`.
- **Agency data migration command** -- `MigrateAgenciesData` wraps the migration in a DB transaction and uses `firstOrCreate` to be idempotent.
- **Good UI consistency** -- Blade views follow the design system (Tailwind-only, dark sidebar, orange accent, Flowbite-style icons, `x-page-box` components).
- **Role-based permission system** -- The `Role` model with JSON permissions and `User::hasPermission()` with legacy fallback is well-designed and pragmatic.
