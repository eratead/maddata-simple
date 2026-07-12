# Security Audit: MadData Codebase
**Date:** 2026-03-22
**Auditor:** Claude Code Security Agent
**Scope:** Full codebase audit — routes, controllers, models, middleware, Blade templates, API, and uncommitted agencies feature

---

## Critical (fix immediately)

| # | Issue | Location | Description | Fix |
|---|-------|----------|-------------|-----|
| C1 | **Missing authorization on UserController::store** | `app/Http/Controllers/UserController.php:36` | The `store()` method has no `$this->authorize('create', User::class)` call. Any authenticated user can create new users, including assigning arbitrary `role_id` values. This allows privilege escalation — a regular user can create an admin account. | Add `$this->authorize('create', User::class);` as the first line of `store()`. The `UserPolicy::create()` already restricts this to admins. |
| C2 | **Privilege escalation via role_id in User $fillable** | `app/Models/User.php:33-41` | `role_id` is in the `$fillable` array. Combined with C1, any user who can hit the store/update endpoints can assign themselves or others an admin role. Even with C1 fixed, the `update()` method does not validate that the assigning user has permission to grant the target role. | Remove `role_id` from `$fillable`. Set it explicitly in the controller after verifying the current user's permissions are >= the target role's permissions. Add anti-escalation check: `abort_if($targetRole->hasPermission('is_admin') && !$currentUser->hasPermission('is_admin'), 403)`. |
| C3 | **Open user registration route** | `routes/auth.php:16-19` | The `/register` route is publicly accessible. Anyone can create an account and gain authenticated access to the system. While new users have no clients assigned, they enter the authenticated perimeter and can exploit C1. | Either remove the registration routes entirely (recommended for a managed-service SaaS), or gate them behind admin invitation logic. |
| C4 | **IDOR in ActivityLog markAsHandled** | `app/Http/Controllers/Admin/CampaignChangeController.php:168-175` | When `log_ids` are provided, the query `ActivityLog::whereIn('id', $logIds)->update(...)` has no campaign scope. A user with `can_see_logs` permission can mark ANY activity log as handled by supplying arbitrary log IDs, including logs from other tenants' campaigns. | Scope the update: `ActivityLog::where('campaign_id', $campaign->id)->whereIn('id', $logIds)->update(...)`. Also validate log_ids: `'log_ids.*' => 'integer'`. |

---

## High (fix before release)

| # | Issue | Location | Description | Fix |
|---|-------|----------|-------------|-----|
| H1 | **No agency-based tenant scoping anywhere** | Multiple controllers | The `agency_user` pivot table exists in the new migration, and the `User::agencies()` relationship is defined, but NO controller or policy checks agency membership. The `CampaignPolicy` only checks `$user->clients->contains($campaign->client_id)` via the `client_user` pivot — it never checks if the user has access through their agency. This means the agency-user relationship is defined but not enforced. | Add a `User::accessibleClientIds()` method that unions client IDs from direct `client_user` pivot AND clients belonging to agencies from `agency_user` pivot. Use this in all policies and controllers instead of `$user->clients->contains(...)`. |
| H2 | **Campaign index has no authorization gate** | `app/Http/Controllers/CampaignController.php:25-110` | The `index()` method does not call `$this->authorize('viewAny', Campaign::class)`. While it does scope data for non-admins, there is no policy check preventing access entirely for users who should not see campaigns at all. | Add `$this->authorize('viewAny', Campaign::class)` and update `CampaignPolicy::viewAny()` (currently returns `false`) to return `true` for users with `can_view_campaigns` permission. |
| H3 | **campaigns_client.index route bypasses resource controller** | `routes/web.php:37` | `GET /campaigns/client/{client_id?}` maps to `CampaignController::index()` but is defined separately from the resource route. While the controller does check `$user->clients->contains('id', $client_id)`, the `client_id` parameter is passed as a raw route parameter (not model binding), so a deleted client's ID could be used. | Use route model binding: `Route::get('/campaigns/client/{client?}', ...)` and validate the bound model. |
| H4 | **No privilege-escalation prevention in role management** | `app/Http/Controllers/Admin/RoleController.php` | Admins can create/edit roles with `is_admin` permission without restriction. Per `docs/project_context.md`, "A user cannot grant a role that has higher permissions than their own." This rule is not enforced. | In `store()` and `update()`, validate that every permission being granted is also held by `auth()->user()`. |
| H5 | **env() called at runtime instead of config()** | `app/Http/Controllers/AiLocationController.php:15`, `app/Http/Controllers/CampaignAssistantController.php:31` | `env('ANTHROPIC_API_KEY')` is called at runtime. After `php artisan config:cache`, `env()` returns `null`, breaking the feature. More critically, if the config cache is not present, `env()` exposes environment variable access patterns. | Move to `config('services.anthropic.key')` and define the key in `config/services.php`. |
| H6 | **Staging database password in MEMORY.md** | `.claude/projects/-Users-mg-projects-maddata-simple/memory/MEMORY.md` | The staging DB password `[REDACTED]` is stored in plaintext in a file that may be committed or synced. | Remove the password from this file immediately. Use a password manager or SSH-tunneled access instead. |

---

## Medium (fix soon)

| # | Issue | Location | Description | Fix |
|---|-------|----------|-------------|-----|
| M1 | **Weak password policy on user creation** | `app/Http/Controllers/UserController.php:40` | Password minimum is only 6 characters with no complexity requirements. The registration controller uses `Rules\Password::defaults()` but the admin user creation does not. | Use `Rules\Password::defaults()` or `Rules\Password::min(8)->mixedCase()->numbers()` in UserController validation. |
| M2 | **LIKE injection in ActivityLog search** | `app/Http/Controllers/Admin/ActivityLogController.php:74-77` | User input from `search` and `campaign` filters is interpolated into `LIKE` clauses with `%` wildcards. While Eloquent parameterizes the value, the `%` and `_` LIKE wildcards in user input are not escaped, allowing search manipulation. | Escape LIKE special chars: `$searchTerm = str_replace(['%', '_'], ['\\%', '\\_'], $searchTerm)`. |
| M3 | **Unescaped {!! !!} in Blade template** | `resources/views/components/campaign/targeting-accordion.blade.php:125` | `{!! $tabLabel !!}` outputs HTML without escaping. While `$tabLabel` is currently set from code (not user input), this is a latent XSS vector if the label source ever changes. | Use `{{ $tabLabel }}` or ensure the label is explicitly sanitized. If HTML is intentional (e.g., icons), use `@js()` or `Blade::sanitize()`. |
| M4 | **Unescaped {!! !!} for chart data** | `resources/views/dashboard/index.blade.php:607-624` | Chart labels and data arrays are output with `{!! json_encode(...) !!}`. While these are numeric/date values from the database (not direct user input), if a campaign name or date format were ever included, this would be an XSS vector. | Use `@js($chartLabels)` instead of `{!! json_encode($chartLabels) !!}` for all chart data outputs. |
| M5 | **CampaignChangeController download uses public disk** | `app/Http/Controllers/Admin/CampaignChangeController.php:115-119` | Files are served from `Storage::disk('public')`, which is symlinked to the webroot. This means creative files referenced by activity logs may be directly accessible via URL without authentication. | Use a private disk (like the `creatives` disk used in `CreativeController`) and serve through an authenticated route. |
| M6 | **No rate limiting on AI endpoints** | `routes/web.php:14-15` | `/ai/generate-locations` and `/ai/campaign-assistant` have no rate limiting. A malicious authenticated user could rapidly call these endpoints, incurring significant API costs. | Add `->middleware('throttle:10,1')` to both routes. |
| M7 | **Token scoping not enforced in Report API** | `app/Http/Controllers/ReportApiController.php` | Sanctum tokens have no abilities/scopes defined. Any valid token can access all API endpoints. The `campaigns()` endpoint filters by user's clients, but if the token user is an admin, all campaigns are exposed. | Define token abilities on creation (e.g., `createToken($name, ['reports:read'])`), and check abilities in the controller with `$request->user()->tokenCan('reports:read')`. |

---

## Low / Informational

| # | Issue | Location | Description |
|---|-------|----------|-------------|
| L1 | **Duplicate trait usage in User model** | `app/Models/User.php:13,26` | `Notifiable` trait is used twice (lines 13 and 26). Not a security issue but indicates code quality oversight. |
| L2 | **Debug artifact** | `app/Http/Controllers/ClientController.php:19` | Commented-out `dump($user)` — remove to prevent accidental uncommenting in production. |
| L3 | **Missing CSRF token validation note** | `routes/web.php` | All POST/PUT/DELETE routes within the `auth` middleware group benefit from Laravel's default `VerifyCsrfToken` middleware. This is correctly configured. Informational only. |
| L4 | **No Content-Security-Policy on main pages** | Global | Only the creative file preview has CSP headers. Consider adding a global CSP header via middleware to mitigate XSS impact. |
| L5 | **No audit logging for role changes** | `app/Http/Controllers/Admin/RoleController.php` | Changes to roles (which control permissions) are not logged to ActivityLog. An admin could silently elevate permissions. |
| L6 | **Zip bomb risk in downloadAll** | `app/Http/Controllers/CreativeController.php:204-238`, `Admin/CampaignChangeController.php:122-158` | No limit on the number or total size of files added to zip archives. A campaign with many large files could cause memory exhaustion. Consider adding a size check. |

---

## Passed Checks

- **Mass assignment protection**: All models use explicit `$fillable` arrays. No `$guarded = []` found anywhere.
- **SQL injection**: All raw SQL uses `selectRaw()` with hardcoded column names — no user input interpolated into raw SQL. Eloquent parameterized queries used correctly throughout.
- **CSRF protection**: Laravel's default CSRF middleware is active for all web routes. API routes use Sanctum token auth.
- **Password hashing**: User model casts `password` as `hashed`. Both `RegisteredUserController` and `UserController` use `Hash::make()`.
- **2FA enforcement**: `RequireTwoFactor` middleware is appended to the web middleware group, enforcing 2FA globally.
- **Token expiry**: `CheckTokenExpiry` middleware correctly checks `expires_at` on Sanctum tokens. Tokens default to 30-day expiry.
- **Token ownership**: `TokenController` scopes all operations to `Auth::user()->tokens()`, preventing IDOR on token management.
- **File upload validation**: `CreativeController` validates MIME types, file size (50MB max), and uses safe random paths. EXIF metadata is stripped from images.
- **File preview security**: Creative file preview includes `X-Content-Type-Options: nosniff` and restrictive CSP headers.
- **Shell command safety**: `ffprobe` invocation in `CreativeController` uses `escapeshellarg()` correctly.
- **Sensitive data hidden**: User model `$hidden` array includes `password`, `remember_token`, and `google2fa_secret`. 2FA secret is encrypted at rest.
- **Admin routes protected**: All `/admin/*` routes use the `admin` middleware (or `can_see_logs` for log routes).
- **Policy-based authorization**: `ClientController`, `CampaignController` (most methods), `CreativeController`, and `DashboardController` all use `$this->authorize()` with proper policies.
- **Alpine.js data escaping**: Templates consistently use `@js()` or `Js::from()` for passing PHP data to Alpine.js contexts.

---

## Summary

| Severity | Count |
|----------|-------|
| Critical | 4 |
| High | 6 |
| Medium | 7 |
| Low/Info | 6 |
| Passed | 16 |

**Top Priority Actions:**
1. Add authorization to `UserController::store()` (C1) — any authenticated user can create admin accounts
2. Remove `role_id` from User `$fillable` and add escalation prevention (C2)
3. Disable or gate the public registration route (C3)
4. Scope the `markAsHandled` query to the campaign (C4)
5. Implement agency-based tenant scoping across all policies (H1)
