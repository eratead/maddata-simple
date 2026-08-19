# MadData Dashboard — Architecture & File Map

> **Platform:** Laravel 12 · Blade + Alpine.js · Tailwind CSS · Vite · PestPHP v3
> **Purpose:** Campaign management and reporting platform for digital advertising.
> Clients own Campaigns; campaigns carry daily metrics, placement metrics, creative files, audience segments, and geographic targeting.

---

## Table of Contents

1. [Models & Schema](#1-models--schema)
2. [Observers & Event-Driven Logging](#2-observers--event-driven-logging)
3. [Services](#3-services)
4. [HTTP — Controllers](#4-http--controllers)
   - [Core Application](#41-core-application)
   - [Admin Panel](#42-admin-panel)
   - [Auth](#43-auth)
   - [Report API](#44-report-api)
5. [HTTP — Middleware](#5-http--middleware)
6. [HTTP — FormRequests (Validation)](#6-http--formrequests-validation)
7. [Policies (Authorization)](#7-policies-authorization)
8. [Exports](#8-exports)
9. [Mail](#9-mail)
10. [Console Commands](#10-console-commands)
11. [Routes](#11-routes)
12. [Database Migrations](#12-database-migrations)
13. [Frontend — Blade Views](#13-frontend--blade-views)
    - [Layouts](#131-layouts)
    - [Campaigns](#132-campaigns)
    - [Creatives](#133-creatives)
    - [Clients](#134-clients)
    - [Users](#135-users)
    - [Admin Panel](#136-admin-panel)
    - [Dashboard & Reports](#137-dashboard--reports)
    - [Tokens & Profile](#138-tokens--profile)
    - [Auth Views](#139-auth-views)
    - [Email Templates](#1310-email-templates)
    - [Reusable Blade Components](#1311-reusable-blade-components)
14. [Frontend — JavaScript](#14-frontend--javascript)
15. [Frontend — CSS & Design System](#15-frontend--css--design-system)
16. [Tests](#16-tests)
17. [Service Providers & Bootstrap](#17-service-providers--bootstrap)
18. [System Health Monitor](#18-system-health-monitor)

---

## 1. Models & Schema

| File | Responsibility |
|------|---------------|
| `app/Models/User.php` | Platform user. Carries legacy boolean flags (`is_admin`, `is_report`, `can_view_budget`) plus a `role_id` FK. Exposes `hasPermission($key)` which checks the Role's JSON permissions array first, then falls back to legacy booleans. Has a many-to-many with `Client` via `client_user` pivot. Stores `google2fa_secret` with an `encrypted` cast (auto encrypt/decrypt via Laravel); the column is `nullable text` to accommodate the serialised encrypted format. |
| `app/Models/Role.php` | Permission group. Stores a JSON `permissions` array (e.g. `["is_admin","can_edit_campaigns","can_see_logs"]`). Ordered by `sort_order`. Used by `User::hasPermission()` as the primary permission source. Defines the canonical permission key constants. |
| `app/Models/Agency.php` | Top-level tenant entity. Has many Clients; belongs to many Users via `agency_user` pivot (with a `role` column, default `viewer`). Created from the former `agency` string column on `clients` via the `migrate:agencies-data` command. |
| `app/Models/Client.php` | Belongs to an Agency (`agency_id` FK) and has many Campaigns; belongs to many Users via `client_user` pivot. Acts as the primary tenancy boundary — users only see campaigns belonging to their connected clients. |
| `app/Models/Campaign.php` | Core ad campaign entity. Holds budget, expected impressions, start/end dates, status, `targeting_rules` (JSON), `required_sizes`, and a `creative_optimization` flag. Has rich relationships: belongs to Client, has many CampaignData, PlacementData, Creatives, ActivityLogs, Audiences (many-to-many), and CampaignLocations. |
| `app/Models/CampaignData.php` | Daily performance metrics row. Unique on `(campaign_id, report_date)`. Stores impressions, clicks, cost, visible_impressions, uniques, and video engagement quartiles (25/50/75/100%). |
| `app/Models/PlacementData.php` | Placement-level metrics. Same metric columns as CampaignData but scoped to a named placement. Table name: `placements_data`. |
| `app/Models/Creative.php` | An ad creative belonging to a Campaign. Holds name, landing URL, and status. Has many CreativeFiles and belongs to Campaign. Observed by `CreativeObserver`. |
| `app/Models/CreativeFile.php` | A single uploaded file attached to a Creative. Stores path, mime_type, dimensions (width/height), and file size. Polymorphically referenced as the `subject` of ActivityLog entries. Observed by `CreativeFileObserver`. |
| `app/Models/CampaignLocation.php` | A geographic pin for a Campaign's radius targeting. Stores latitude, longitude, and `radius_meters`. Belongs to Campaign. |
| `app/Models/ActivityLog.php` | Immutable audit record for any change in the system. Uses a polymorphic `subject` (Campaign, Creative, or CreativeFile). Stores `action`, `description`, `changes` (JSON diff), `status` (`pending`/`handled`), and the acting `user_id`. Exposes `scopePending()`. |
| `app/Models/Audience.php` | A targetable audience segment. Belongs to a provider; categorised by `main_category` / `sub_category`. Carries `full_path`, `estimated_users`, and an `icon`. Can be deactivated (`is_active`). Many-to-many with Campaign. |

---

## 2. Observers & Event-Driven Logging

| File | Responsibility |
|------|---------------|
| `app/Observers/CampaignObserver.php` | Fires on Campaign `created` and `updated`. On create: writes a `pending` ActivityLog. On update: compares `targeting_rules` and `creative_optimization` before/after and writes a detailed diff log so changes flow into the Campaign Changes CRM. |
| `app/Observers/CreativeObserver.php` | Fires on Creative `created`, `updated`, and `deleted`. Writes ActivityLog entries with a JSON diff of changed fields so the audit trail tracks every creative lifecycle event. |
| `app/Observers/CreativeFileObserver.php` | Fires on CreativeFile `created` and `deleted`. Logs file upload and removal events into the ActivityLog, referencing the file as the polymorphic subject. |

---

## 3. Services

| File | Responsibility |
|------|---------------|
| `app/Services/ActivityLogger.php` | Single-purpose helper that resolves the correct `campaign_id` from any polymorphic subject (Campaign, Creative, or CreativeFile) and writes an ActivityLog row. Also dispatches a 2-hour digest email to users who have opted into activity notifications. Keeps observer code thin by centralising write logic. |
| `app/Services/CampaignMetricsService.php` | Extracts all campaign metrics computation from DashboardController. Accepts a Campaign with optional date range and returns structured arrays for summary stats, chart data, placement data, and budget calculations. Shared by both `DashboardController::show()` and `exportExcel()`. |
| `app/Services/ReportImportService.php` | Handles the full Excel report import pipeline: reads the uploaded file, parses headers, detects dates and video fields, bulk-inserts PlacementData rows, upserts CampaignData summary, and invalidates Report API cache via a version-counter pattern. |
| `app/Services/Health/SystemHealthService.php` | Orchestrates every registered health check into a `HealthSnapshot`. Caches the snapshot for 30s behind a single-flight `Cache::lock`, with a no-TTL last-known-good copy served while a rebuild is in flight. Never throws: a check class that blows up degrades to one CRIT result and the snapshot still builds. See §18. |
| `app/Services/Health/HostFacts.php` | The only reader of `/run/maddata/host-facts.json`, the file the root cron writes. Memoized (registered as a singleton) so one snapshot build touches disk once. Returns null rather than throwing on a missing or malformed file. Also exposes `withinBootGrace()`, read from `/proc/uptime`: for a minute or two after a reboot the facts file does not exist yet, and without this every restart looks like an outage — check H1 says "recently booted" instead of CRIT, and `health:alert` stays quiet until the window closes. |
| `app/Services/Health/HealthMarkers.php` | Single source of truth for every health cache key, plus `store()` and `record()`. Markers are written by one class and read by another, so a typo would surface as a permanently STALE check rather than an error — both ends name the key from here. |
| `app/Services/Health/HealthFormat.php` | Value formatting (durations, percentages, bytes, milliseconds) shared by the check classes, the CLI table and — from Phase 3 — the admin map. |
| `app/Services/GeoReferenceService.php` | Resolves country, region, and city reference lists for the campaign targeting UI. Resolution order per call: (1) 7-day cache; (2) upstream `countriesnow.space` with a 5-second timeout; (3) static JSON fallback from `storage/app/geo/`. Logs `geo.fallback_used` to the `ai` channel on fallback. Israel cities include both Hebrew and English entries in the same flat array to support bilingual autocomplete. |

---

## 4. HTTP — Controllers

### 4.0 Controller Concerns (Shared Traits)

| File | Responsibility |
|------|---------------|
| `app/Http/Controllers/Concerns/PreventsPrivilegeEscalation.php` | Reusable trait that enforces per-permission escalation guards. `preventPrivilegeEscalation(User $actingUser, Role $targetRole)` iterates every permission in the target role's JSON array and aborts 403 if the acting user does not hold that permission. Applied by `UserController`, `AgencyUserController`, and any future controller that assigns roles. |

### 4.1 Core Application

| File | Responsibility |
|------|---------------|
| `app/Http/Controllers/CampaignController.php` | Full CRUD for Campaigns. `index()` returns campaigns ordered by `created_at` desc, scoped to the user's clients for non-admins. `store()` and `update()` delegate validation to `StoreCampaignRequest` / `UpdateCampaignRequest` and enforce client ownership via `abort(403)`. Computes pacing metrics (delivery rate, spend rate) for the list view. |
| `app/Http/Controllers/CreativeController.php` | Manages Creatives and their file uploads. All methods are gated via `CampaignPolicy`. `upload()` enforces dual MIME validation (`mimes:` + `mimetypes:`), strips EXIF from images by re-encoding via Intervention Image (GD driver), and stores files at a random safe path. `preview()` adds `X-Content-Type-Options: nosniff` and a restrictive CSP header. `downloadAll()` ZIPs files to a private temp path (`storage/app/temp/`) with a random suffix — never under the public symlink. |
| `app/Http/Controllers/ClientController.php` | CRUD for Clients. All write operations are admin-only (enforced by `ClientPolicy`). Index is accessible to all authenticated users. |
| `app/Http/Controllers/UserController.php` | CRUD for Users. `index()` eager-loads `clients` and `userRole`, passes `$roles` and `$clients` for filter dropdowns. Handles client attachment (pivot sync) and role assignment on store/update. `reset2fa(User $user)` clears `google2fa_secret` (admin-only, also invalidates any active remember-device cookie on next request). |
| `app/Http/Controllers/DashboardController.php` | Aggregates campaign performance data for the reporting dashboard. Budget and cost-based metrics (`$budget`, `$cpm`, `$cpc`, `$spent`) are only computed and passed to the view when the user has the `can_view_budget` permission. Supports date range filtering. Handles Excel export dispatch. |
| `app/Http/Controllers/CampaignAssistantController.php` | Receives free-text campaign briefs via AJAX and forwards them to the Anthropic Claude API. Returns a structured JSON payload with suggested campaign settings (targeting, budget, schedule, etc.). Gated to users with `can_edit_campaigns`. Wraps the Anthropic call in a try/catch and logs `assistant.request`, `assistant.response`, `assistant.parse_failure`, and `assistant.upstream_error` entries to the `ai` log channel. |
| `app/Http/Controllers/GeoReferenceController.php` | Three thin GET endpoints (`/api/geo/countries`, `/api/geo/regions`, `/api/geo/cities`) that delegate to `GeoReferenceService` and return `{ data: string[] }`. Behind the `auth` middleware only — no Sanctum — since they are called from authenticated browser sessions on the campaign edit page. Replaces the former direct browser calls to `countriesnow.space`, keeping CSP tight. |
| `app/Http/Controllers/AiLocationController.php` | Accepts a location description and uses the Claude API to infer geographic coordinates and radius, returning a JSON suggestion for the campaign location radius-targeting UI. |
| `app/Http/Controllers/TokenController.php` | Manages Sanctum personal access tokens for the Report API. Creates tokens with a 30-day expiry, supports single-click extension, and allows deletion. Only accessible to users with `campaign_manager` middleware. |
| `app/Http/Controllers/ProfileController.php` | Standard Laravel Breeze profile controller: update display name/email, change password, delete account. |

### 4.2 Admin Panel

| File | Responsibility |
|------|---------------|
| `app/Http/Controllers/Admin/AgencyController.php` | Full CRUD for Agency entities. Lists agencies with client count, supports create/edit/delete. `destroy()` prevents deletion if the agency has clients attached, returning an error flash. Admin-only via `admin` middleware. |
| `app/Http/Controllers/Admin/ActivityLogController.php` | Displays the full audit trail. Supports filtering by action type, acting user, campaign, and date range. Eager-loads the polymorphic `subject` relationship using `morphWith([CreativeFile::class => ['creative']])` to prevent N+1 when logs reference creative files. Access is scoped: admins see all logs; users with `can_see_logs` see only logs for campaigns connected to their clients. |
| `app/Http/Controllers/Admin/CampaignChangeController.php` | The "Campaign Changes CRM" — lists campaigns with `pending` ActivityLogs and lets admins review, download supporting files, and mark changes as `handled`. `downloadAll()` writes a ZIP to `storage/app/temp/` with a random suffix for security. Access scoped the same way as `ActivityLogController`. |
| `app/Http/Controllers/Admin/AudienceController.php` | Full CRUD for Audience segments plus bulk import via Excel upload. Supports filtering by `main_category`. Uses `is_active` soft-toggle rather than deletion. |
| `app/Http/Controllers/Admin/RoleController.php` | CRUD for Roles with a permissions matrix UI. Supports drag-and-drop reordering via a dedicated `reorder()` action that updates `sort_order` values. Admin-only. |

### 4.2b Agency Management

| File | Responsibility |
|------|---------------|
| `app/Http/Controllers/Agency/AgencyUserController.php` | Agency-scoped CRUD for Users. Every method authorizes via `AgencyPolicy::manage`. Lists users in the agency via pivot with status/role info. `store()` creates users, attaches to agency pivot with `access_all_clients` flag, and syncs client_user for specific access. Implements anti-escalation: target role cannot have `can_manage_users` or exceed the manager's own permissions. `destroy()` disables users (`is_active = false`) without deleting records or detaching pivots. |
| `app/Http/Controllers/Agency/AgencyClientController.php` | Agency-scoped CRUD for Clients. Every method authorizes via `AgencyPolicy::manage`. Lists clients belonging to the agency with campaign counts. `store()` forces `agency_id` from the route parameter (managers cannot override). `destroy()` refuses deletion when the client has campaigns. |

### 4.3 Auth

Standard Laravel Breeze controllers under `app/Http/Controllers/Auth/`:

| File | Responsibility |
|------|---------------|
| `AuthenticatedSessionController.php` | Login and logout. `destroy()` also forgets the `2fa_remember` cookie so the user is fully de-authenticated. |
| `TwoFactorController.php` | Full TOTP 2FA lifecycle. `showSetup()` generates a temp secret in session and renders a QR code (inline SVG via BaconQrCode). `confirmSetup()` verifies the first code and persists the encrypted secret to the DB. `showChallenge()` shows the 6-digit entry screen. `verify()` validates the TOTP code, marks the session as verified, and optionally sets a 30-day HMAC remember-device cookie (HTTP-only, SameSite=strict). `startGoogleVerify()` forwards `login_hint=user.google_email` to Socialite so Google defaults to the linked account, eliminating the wrong-Gmail sub-mismatch failure mode. |
| `RegisteredUserController.php` | New user registration. |
| `PasswordResetLinkController.php` | Send password reset email. |
| `NewPasswordController.php` | Set new password from reset link. |
| `PasswordController.php` | Update password from profile. |
| `ConfirmablePasswordController.php` | Require password re-entry for sensitive actions. |
| `VerifyEmailController.php` | Handle email verification link click. |
| `EmailVerificationNotificationController.php` | Resend verification email. |
| `EmailVerificationPromptController.php` | Show "please verify your email" screen. |

### 4.4 Report API

| File | Responsibility |
|------|---------------|
| `app/Http/Controllers/ReportApiController.php` | Sanctum-authenticated JSON API under `/api/reports/`. Exposes four endpoints: campaign list, summary metrics, metrics by date, and metrics by placement. Budget-related fields are conditionally included based on the token owner's `can_view_budget` permission. |

---

## 5. HTTP — Middleware

| File | Responsibility |
|------|---------------|
| `app/Http/Middleware/EnsureUserIsAdmin.php` | Terminates requests with `403` if the authenticated user lacks `is_admin`. Applied to all `/admin/*` routes. |
| `app/Http/Middleware/EnsureUserIsCampaignManager.php` | Passes the request through if the user is an admin or has the `can_edit_campaigns` permission. Applied to token management routes. |
| `app/Http/Middleware/EnsureUserCanSeeLogs.php` | Passes the request through if the user is an admin or has the `can_see_logs` permission. Applied to activity-log and campaign-change routes. |
| `app/Http/Middleware/CheckTokenExpiry.php` | Inspects the Sanctum token's `expires_at` custom column and aborts `401` if expired. Applied to all `/api/reports/*` routes. |
| `app/Http/Middleware/RequireTwoFactor.php` | Enforces TOTP 2FA on every authenticated web request. Fast-paths: unauthenticated users, the testing environment, and exempt routes (`login`, `register`, `logout`, `password.*`, `verification.*`, `2fa.*`). Checks `session('2fa_verified')` first, then validates the `2fa_remember` HMAC cookie for remembered devices. Redirects to `2fa.setup` if no secret is configured, or to `2fa.challenge` otherwise. Applied globally to the `web` middleware group. |
| `app/Http/Middleware/ContentSecurityPolicy.php` | Adds a `Content-Security-Policy` response header to all web requests. Restricts `default-src` to `'self'`, allows `'unsafe-inline'` and `'unsafe-eval'` for `script-src` (required by Alpine.js), `'unsafe-inline'` for `style-src` (Tailwind), and blocks framing via `frame-ancestors 'none'`. Applied globally to the `web` middleware group. |

---

## 6. HTTP — FormRequests (Validation)

| File | Responsibility |
|------|---------------|
| `app/Http/Requests/StoreCampaignRequest.php` | Validates new campaign creation. Enforces `start_date` ≥ today, `end_date` > `start_date`, `budget` and `expected_impressions` with positive-integer bounds, `name` min-length, required `client_id`, and required `status`. |
| `app/Http/Requests/UpdateCampaignRequest.php` | Validates campaign edits including the full `targeting_rules` payload. Enforces enum constraints on `genders`, `ages`, `incomes` (`0-195K`, `195-220K`, `220-245K`, `245K+`), device types, OS, connection types, environments, and days. Validates `cities` as an array (not a string). Caps `allowlist`/`blocklist` length and `radius_meters` to `max:100000`. |
| `app/Http/Requests/StoreCreativeRequest.php` | Validates creative creation: `name` min-length, `landing` URL format with `max:2048`, `status` enum. |
| `app/Http/Requests/UpdateCreativeRequest.php` | Same rules as `StoreCreativeRequest`, all fields optional on update. |
| `app/Http/Requests/ProfileUpdateRequest.php` | Email uniqueness check (ignoring the current user's own record) and name validation. |
| `app/Http/Requests/Auth/LoginRequest.php` | Email/password presence and throttled rate-limiting on failed attempts. |
| `app/Http/Requests/StoreClientRequest.php` | Validates new client creation: required name, required agency_id FK (must exist in agencies table). Non-admin users are further restricted via `Rule::in()` to only their own agencies. |
| `app/Http/Requests/UpdateClientRequest.php` | Validates client updates: required name, required agency_id FK (must exist in agencies table). Non-admin users are further restricted via `Rule::in()` to only their own agencies. |
| `app/Http/Requests/StoreAudienceRequest.php` | Validates new audience creation: required main_category and name, optional sub_category/provider/estimated_users/is_active. `authorize()` gates on `AudiencePolicy::create`. |
| `app/Http/Requests/UpdateAudienceRequest.php` | Validates audience updates: same rules as store. `authorize()` gates on `AudiencePolicy::update` for the route-bound Audience. |
| `app/Http/Requests/StoreUserRequest.php` | Validates new user creation: name, unique email, strong password (min 8, mixed case, numbers), optional role_id, and multi-agency assignments array with per-agency `access_all_clients` flag and optional specific client IDs. |
| `app/Http/Requests/UpdateUserRequest.php` | Validates user updates: name, unique email (ignoring self), optional password with strength rules, optional role_id, and multi-agency assignments array with per-agency `access_all_clients` flag and optional specific client IDs. |
| `app/Http/Requests/StoreAgencyRequest.php` | Validates new agency creation: required unique name. |
| `app/Http/Requests/UpdateAgencyRequest.php` | Validates agency updates: required name unique (ignoring self). |
| `app/Http/Requests/Agency/StoreAgencyUserRequest.php` | Validates agency-scoped user creation: required name, unique email, strong password, required role_id, optional access_all_clients boolean, and optional clients array. |
| `app/Http/Requests/Agency/UpdateAgencyUserRequest.php` | Validates agency-scoped user updates: same as store but email unique ignoring self, password nullable, and is_active boolean for enable/disable toggle. |
| `app/Http/Requests/Agency/StoreAgencyClientRequest.php` | Validates agency-scoped client creation: required name (string, max:255). Agency ID comes from the route, not the form. |
| `app/Http/Requests/Agency/UpdateAgencyClientRequest.php` | Validates agency-scoped client updates: required name (string, max:255). Cannot change agency_id. |
| `app/Http/Requests/StoreRoleRequest.php` | Validates new role creation: required unique name, optional permissions array. |
| `app/Http/Requests/UpdateRoleRequest.php` | Validates role updates: required name unique (ignoring self), optional permissions array. |

---

## 7. Policies (Authorization)

| File | Responsibility |
|------|---------------|
| `app/Policies/CampaignPolicy.php` | Object-level authorization for Campaign actions. `view()` passes for admins or users whose clients include the campaign's client. `create()` / `update()` require `can_edit_campaigns`. `delete()` is admin-only. Used throughout `CreativeController` to gate file operations by the parent campaign's ownership. |
| `app/Policies/ClientPolicy.php` | All operations (view, create, update, delete) require `is_admin`. No campaign-manager exceptions — client entities are admin-only. |
| `app/Policies/UserPolicy.php` | Guards user management routes; admins can manage all users. |
| `app/Policies/AgencyPolicy.php` | Agency-level authorization. `manage()` allows admins or users who belong to the agency AND have `can_manage_users` permission. `view()` allows admins or any user belonging to the agency. Used by agency-scoped controllers for user/client management. |
| `app/Policies/AudiencePolicy.php` | Authorization for Audience CRUD. All five standard methods (`viewAny`, `view`, `create`, `update`, `delete`) allow access to users with `is_admin` OR `can_manage_clients`. Registered in `AppServiceProvider` via `Gate::policy`. |

---

## 8. Exports

| File | Responsibility |
|------|---------------|
| `app/Exports/CampaignExport.php` | Multi-sheet Excel export orchestrator. Implements `WithMultipleSheets` and composes the three sheet classes below into a single `.xlsx` file. |
| `app/Exports/CampaignSummarySheet.php` | Builds the "Summary" sheet: total impressions, clicks, CTR, cost, CPM, CPC, and video completion metrics for the selected campaign and date range. |
| `app/Exports/CampaignByDatesSheet.php` | Builds the "By Date" sheet: one row per `report_date` with daily performance metrics. |
| `app/Exports/CampaignByPlacementsSheet.php` | Builds the "By Placements" sheet: one row per placement with aggregated metrics. |

---

## 9. Mail

| File | Responsibility |
|------|---------------|
| `app/Mail/ActivityDigestMail.php` | Mailable for the 2-hour activity digest. Receives a collection of ActivityLog entries grouped by client and campaign. Sent only to users who have opted in via `receive_activity_notifications`. Rendered by `resources/views/emails/activity_digest.blade.php`. |

---

## 10. Console Commands

| File | Responsibility |
|------|---------------|
| `app/Console/Commands/UpdateCampaignStatuses.php` | Scheduled Artisan command. Queries campaigns where `start_date` = today and status is `paused` → sets to `active`. Queries campaigns where `end_date` < today and status is `active` → sets to `paused`. Intended to run daily via the Laravel scheduler. |
| `app/Console/Commands/MigrateAgenciesData.php` | One-time data migration command (`php artisan migrate:agencies-data`). Reads distinct `agency` strings from the `clients` table, creates corresponding `Agency` records via `firstOrCreate`, and backfills `agency_id` on each client. Must be run after the `create_agencies_table` and `add_agency_id_to_clients_table` migrations but before the `drop_agency_string_from_clients_table` cleanup migration. |
| `app/Console/Commands/RunHealthCheck.php` | `php artisan health:check` — the answer to "is production OK?" over SSH. Renders a failing-first table or `--json`, and exits non-zero per `--fail-on=warn\|crit` so it composes with cron and deploy gates. Rebuilds by default; `--cached` reads the cached snapshot. |
| `app/Console/Commands/RefreshHealthSnapshot.php` | `health:refresh-snapshot`, scheduled every minute. Rebuilds the snapshot off-path so a real admin request is always just a cache read. |
| `app/Console/Commands/PublicProbe.php` | `health:probe`, scheduled every minute. Curls the public HTTPS URL from outside the framework and records `{ok, latency_ms, checked_at, consec_fails}` for check P1. Always exits 0 — a failing probe is data for the monitor, not a broken command. |
| `app/Console/Commands/MarkRestoreDrill.php` | `health:mark-restore-drill` — records that a backup restore drill was actually performed (check B4). Run at the end of the backup-restore runbook. |
| `app/Console/Commands/SendHealthAlert.php` | `health:alert`, scheduled every five minutes. Transition-based operator alerting: nothing fires on a single observation (deploy blips), a persisting problem nags every `realert_hours`, a new failing check re-alerts immediately, and recovery always sends a notice. Fails toward alerting when its own state is unreadable, and never records a notification it failed to send. See §18. |
| `app/Console/Commands/SendActivityDigest.php` | Scheduled Artisan command (`php artisan digest:send-activity`). Reads `last_activity_digest_sent_at` from cache, finds all `ActivityLog` rows since that timestamp, and queues an `ActivityDigestMail` to every active opted-in user. Always updates the cache timestamp on completion so the next run only covers new activity. Registered to run every two hours via `routes/console.php`. Replaces the previous in-request `ActivityLogger::checkAndSendDigest()` coupling. |

---

## 11. Routes

| File | Responsibility |
|------|---------------|
| `routes/web.php` | All authenticated web routes. Organises routes into logical groups: campaign CRUD, creative file management, client CRUD, user CRUD (including `POST /users/{user}/reset-2fa` with `admin` middleware), dashboard + export, admin sub-group (`/admin/*`), activity logs + campaign changes, API token management, and Sanctum report API (`/api/reports/*`). |
| `routes/auth.php` | Laravel Breeze auth routes plus 2FA routes: `GET/POST 2fa/setup` (setup flow), `GET 2fa/challenge` + `POST 2fa/challenge` (verify, `throttle:5,1`). All 2FA routes are inside the `auth` middleware group and are exempt from `RequireTwoFactor`. |

**Key API routes (within `web.php`):**

| Method | URI | Name | Controller |
|--------|-----|------|------------|
| GET | `/api/reports/summary/{campaign}` | `reports.summary` | `ReportApiController@summary` |
| GET | `/api/reports/by-date/{campaign}` | `reports.by-date` | `ReportApiController@byDate` |
| GET | `/api/reports/by-placement/{campaign}` | `reports.by-placement` | `ReportApiController@byPlacement` |
| GET | `/api/reports/campaigns` | `reports.campaigns` | `ReportApiController@campaigns` |
| GET | `/api/geo/countries` | `geo.countries` | `GeoReferenceController@countries` |
| GET | `/api/geo/regions?country={name}` | `geo.regions` | `GeoReferenceController@regions` |
| GET | `/api/geo/cities?country={name}` | `geo.cities` | `GeoReferenceController@cities` |

---

## 12. Database Migrations

| File | Schema Change |
|------|--------------|
| `0001_01_01_000000_create_users_table.php` | Initial `users` table with `is_admin`, `is_report` legacy flags. |
| `0001_01_01_000001_create_cache_table.php` | Laravel cache table. |
| `0001_01_01_000002_create_jobs_table.php` | Laravel queue jobs table. |
| `2025_05_07_120919_create_clients_table.php` | `clients` table (`name`, `agency`). |
| `2025_05_08_081615_add_role_to_users_table.php` | Adds `is_report` flag to users. |
| `2025_05_12_055140_create_campaigns_table.php` | `campaigns` table (`client_id`, `name`, `budget`, `expected_impressions`, `is_video`, `start_date`, `end_date`). |
| `2025_05_12_055616_create_client_user_table.php` | `client_user` many-to-many pivot. |
| `2025_05_12_060931_create_campaign_data_table.php` | `campaign_data` daily metrics with unique constraint on `(campaign_id, report_date)`. |
| `2025_05_12_062052_create_placements_data_table.php` | `placements_data` placement-level metrics. |
| `2026_02_02_105559_create_creatives_table.php` | `creatives` table (`campaign_id`, `name`, `landing`, `status`). |
| `2026_02_02_110141_create_creative_files_table.php` | `creative_files` table (`creative_id`, `path`, `mime_type`, `size`, `width`, `height`). |
| `2026_02_02_112057_add_campaign_id_to_creatives_table.php` | Adds `campaign_id` FK to `creatives`. |
| `2026_02_03_123710_add_details_to_creative_files_table.php` | Adds additional metadata columns to `creative_files`. |
| `2026_02_08_144832_create_activity_logs_table.php` | `activity_logs` table with polymorphic `subject`, `action`, `description`, `changes` (JSON), `status`, `user_id`. |
| `2026_02_09_102309_add_receive_activity_notifications_to_users_table.php` | Adds `receive_activity_notifications` boolean to `users`. |
| `2026_02_10_132311_add_required_sizes_to_campaigns_table.php` | Adds `required_sizes` JSON column to `campaigns`. |
| `2026_02_16_153953_add_status_to_activity_logs_table.php` | Adds `status` (`pending`/`handled`) to `activity_logs`. |
| `2026_02_17_071125_add_creative_optimization_to_campaigns_table.php` | Adds `creative_optimization` boolean to `campaigns`. |
| `2026_02_22_122703_create_roles_table.php` | `roles` table (`name`, `permissions` JSON, `sort_order`). |
| `2026_02_22_123614_add_role_id_to_users_table.php` | Adds `role_id` FK to `users`. |
| `2026_02_24_102347_add_sort_order_to_roles_table.php` | Adds `sort_order` to `roles`. |
| `2026_02_26_154113_add_status_to_campaigns_table.php` | Adds `status` (`active`/`paused`) to `campaigns`. |
| `2026_03_02_000001_create_audiences_table.php` | `audiences` table (`provider`, `icon`, `main_category`, `sub_category`, `name`, `full_path`, `estimated_users`, `is_active`). |
| `2026_03_02_000002_create_campaign_audience_table.php` | `campaign_audience` many-to-many pivot. |
| `2026_03_02_000003_add_icon_to_audiences_table.php` | Adds `icon` field to `audiences`. |
| `2026_03_04_000001_add_targeting_rules_to_campaigns_table.php` | Adds `targeting_rules` JSON column to `campaigns` (genders, ages, incomes, devices, OS, connection, environments, days, times, geo, allowlist, blocklist). |
| `2026_03_04_000002_create_campaign_locations_table.php` | `campaign_locations` table (`campaign_id`, `lat`, `lng`, `radius_meters`). |
| `2026_03_05_170243_create_personal_access_tokens_table.php` | Sanctum `personal_access_tokens` with custom `expires_at` column. |
| `2026_03_10_114121_add_created_at_index_to_campaigns_table.php` | Performance index on `campaigns.created_at` for default-sort queries. |
| `2026_03_18_162113_add_google2fa_secret_to_users_table.php` | Adds nullable `google2fa_secret` `text` column to `users` (after `password`). Uses `text` rather than `string` because Laravel's encrypted cast produces serialised payloads longer than 255 characters. |
| `2026_03_22_000001_create_agencies_table.php` | `agencies` table (`id`, `name` unique, timestamps). Top-level tenant entity in the Agency → Client hierarchy. |
| `2026_03_22_000002_add_agency_id_to_clients_table.php` | Adds nullable `agency_id` FK to `clients`, referencing `agencies`. Nullable initially to allow safe backfill via `migrate:agencies-data`. |
| `2026_03_22_000003_create_agency_user_table.php` | `agency_user` many-to-many pivot (`agency_id`, `user_id`, `role` string default `viewer`). Composite primary key. Cascade deletes on both FKs. |
| `2026_03_22_000005_drop_agency_string_from_clients_table.php` | Drops the legacy `agency` string column from `clients` after data has been migrated to the `agencies` table via the `migrate:agencies-data` command. |
| `2026_03_23_073504_update_agency_user_add_access_all_clients.php` | Drops the `role` string column from `agency_user` pivot, adds `access_all_clients` boolean (default true) and timestamps. Supports per-agency client visibility scoping. |
| `2026_03_23_073517_add_is_active_to_users.php` | Adds `is_active` boolean (default true) to `users` table. Supports user disable/enable lifecycle without soft deletes. |

---

## 13. Frontend — Blade Views

### 13.1 Layouts

| File | Responsibility |
|------|---------------|
| `resources/views/layouts/app.blade.php` | Main authenticated layout. Renders the `<x-sidebar>` component, injects the `@yield('content')` slot, and includes Alpine.js and Vite-compiled assets. |
| `resources/views/layouts/guest.blade.php` | Minimal layout for login, register, and password reset pages — no sidebar. |
| `resources/views/layouts/auth-split.blade.php` | Split-screen auth layout used by the 2FA flow and the redesigned login page. Left panel (`md:w-5/12`, dark `#111827`) shows the MadData branding, headline, and three feature bullets (First-Party Data / Algorithmic Trading / Deep Transparency) with Flowbite icons. Right panel (`flex-1`, white) renders the `$slot` form content. |

### 13.2 Campaigns

| File | Responsibility |
|------|---------------|
| `resources/views/campaigns/index.blade.php` | Lists all campaigns visible to the current user. Uses the `<x-ui.datatable>` component. Shows pacing indicators. "New Campaign" button is visible to admins and users with `can_edit_campaigns`. |
| `resources/views/campaigns/create.blade.php` | Campaign creation form. Alpine.js powers the Required Creative Sizes accordion: admins can toggle individual size pills and the custom sizes input; campaign managers can toggle the Video/Static group buttons only (individual pills are `pointer-events:none`). |
| `resources/views/campaigns/edit.blade.php` | The most complex view. Contains: Campaign Details form, Schedule section, Required Creative Sizes (same permission-gated Alpine.js logic as create), Audiences accordion with search modal, Targeting accordion with 5 tabs (Demographics, Geo & Locations, Devices & Tech, Inventory, Schedule). Geo tab uses CountriesNow API typeahead for countries/regions/cities. AI Campaign Assistant panel (slide-in drawer). Campaign Summary modal. |

### 13.3 Creatives

| File | Responsibility |
|------|---------------|
| `resources/views/creatives/create.blade.php` | Simple form to create a Creative (name, landing URL, status) attached to a Campaign. Uses `@push('page-title')` breadcrumb and `@push('page-actions')` for Cancel + Create buttons. |
| `resources/views/creatives/edit.blade.php` | Edit creative metadata and manage uploaded files. Shows Required Sizes status pills (emerald when satisfied). File grid with per-card download/delete. Upload zone powered by `uploadHandler()` Alpine component: drag-drop, client-side image dimension checking (with duplicate detection against already-uploaded sizes), `URL.revokeObjectURL` cleanup. |

### 13.4 Clients

| File | Responsibility |
|------|---------------|
| `resources/views/clients/index.blade.php` | Lists clients with campaign count. Admin-only write actions. |
| `resources/views/clients/create.blade.php` | Create client form. |
| `resources/views/clients/edit.blade.php` | Edit client form. |

### 13.4b Agency Clients

| File | Responsibility |
|------|---------------|
| `resources/views/agency/clients/index.blade.php` | Lists clients scoped to a specific agency with campaign count. Delete button disabled for clients with campaigns. |
| `resources/views/agency/clients/create.blade.php` | Agency-scoped client creation form. Agency shown as read-only text (not editable). |
| `resources/views/agency/clients/edit.blade.php` | Agency-scoped client edit form. Agency shown as read-only. Delete available only when client has no campaigns. |

### 13.5 Users

| File | Responsibility |
|------|---------------|
| `resources/views/users/index.blade.php` | Lists all users. Alpine.js powers real-time search (by name/email), role dropdown filter, and client typeahead filter. Uses `x-for` over embedded JSON — no server-side pagination. Delete forms inline each row with manual `_token` / `_method` hidden inputs (required because `@csrf` / `@method` don't work inside `x-for`). |
| `resources/views/users/create.blade.php` | Create user with role selection and multi-client attachment. |
| `resources/views/users/edit.blade.php` | Edit user — same fields as create. |

### 13.6 Admin Panel

| File | Responsibility |
|------|---------------|
| `resources/views/admin/agencies/index.blade.php` | Lists all agencies in a DataTable with client count badges, created date, and edit/delete actions. Delete uses confirm-dialog with server-side guard against agencies with clients. |
| `resources/views/admin/agencies/create.blade.php` | Create agency form with name input and validation. Breadcrumb back to agencies index. |
| `resources/views/admin/agencies/edit.blade.php` | Edit agency form. Shows client count info. Delete button only visible when agency has zero clients. |
| `resources/views/admin/audiences/index.blade.php` | Audience segment management. Supports category filtering, inline activation toggle, and bulk Excel upload. |
| `resources/views/admin/roles/index.blade.php` | Lists roles with drag-and-drop sort ordering. |
| `resources/views/admin/roles/create.blade.php` | Create role with a permissions checkbox matrix. |
| `resources/views/admin/roles/edit.blade.php` | Edit role name and permissions. |
| `resources/views/admin/activity_logs/index.blade.php` | Audit trail viewer. Filter by action, user, campaign, and date. Shows subject type, description, and JSON diff of changes. |
| `resources/views/admin/campaign_changes/index.blade.php` | CRM list of campaigns with `pending` activity logs, showing change counts and last activity. |
| `resources/views/admin/campaign_changes/show.blade.php` | Detail view for a single campaign's pending changes: shows each log entry, allows file download, and provides a "Mark as Handled" action. |

### 13.7 Dashboard & Reports

| File | Responsibility |
|------|---------------|
| `resources/views/dashboard/index.blade.php` | Campaign performance dashboard. Renders metric cards (impressions, clicks, CTR, video completion), time-series charts, and placement breakdowns. Budget/cost cards are conditionally rendered based on `can_view_budget`. |
| `resources/views/dashboard/export.blade.php` | Form to select campaign, date range, and sheet types for the Excel export. |
| `resources/views/exports/campaign_summary.blade.php` | Blade template for the summary Excel sheet. |
| `resources/views/exports/campaign_by_dates.blade.php` | Blade template for the by-date Excel sheet. |
| `resources/views/exports/campaign_by_placements.blade.php` | Blade template for the by-placements Excel sheet. |

### 13.8 Tokens & Profile

| File | Responsibility |
|------|---------------|
| `resources/views/tokens/index.blade.php` | Lists API tokens with expiry dates. Inline forms for creation (with name input), 30-day extension, and deletion. |
| `resources/views/profile/edit.blade.php` | Profile edit shell that renders three partial forms. |
| `resources/views/profile/partials/update-profile-information-form.blade.php` | Name and email update form. |
| `resources/views/profile/partials/update-password-form.blade.php` | Change password form. |
| `resources/views/profile/partials/delete-user-form.blade.php` | Account deletion with password confirmation. |

### 13.9 Auth Views

| File | Responsibility |
|------|---------------|
| `resources/views/auth/login.blade.php` | Login form. Redesigned to use `<x-auth-split-layout>` (split-screen enterprise look) with Flowbite-icon-adorned email and password fields, orange focus rings, and an inline forgot-password link. |
| `resources/views/auth/2fa-setup.blade.php` | TOTP setup wizard. Three-step flow: (1) install authenticator app, (2) scan QR code (inline SVG) or enter manual key with copy button, (3) enter first 6-digit code to confirm. Uses `<x-auth-split-layout>`. |
| `resources/views/auth/2fa-challenge.blade.php` | 2FA challenge screen. Shows a throttle error banner when `$errors->has('throttle')` (from the 429 exception handler). Mono 6-digit input, "Remember this device" checkbox (30-day cookie), and a hidden POST logout form. Uses `<x-auth-split-layout>`. |
| `resources/views/auth/register.blade.php` | Registration form. |
| `resources/views/auth/forgot-password.blade.php` | Send password reset link form. |
| `resources/views/auth/reset-password.blade.php` | Set new password form (with token). |
| `resources/views/auth/confirm-password.blade.php` | Re-enter password for sensitive actions. |
| `resources/views/auth/verify-email.blade.php` | Prompt to check inbox for verification email. |

### 13.10 Email Templates

| File | Responsibility |
|------|---------------|
| `resources/views/emails/activity_digest.blade.php` | HTML email template for the 2-hour activity digest. Groups logs by client and campaign. Sent to opted-in users by `ActivityLogger`. |

### 13.11 Reusable Blade Components

**Layout & Navigation:**

| File | Responsibility |
|------|---------------|
| `resources/views/components/sidebar.blade.php` | Main navigation sidebar. Renders nav links based on permissions: Campaigns (always), Report (always), Manage sub-menu (Clients, Users — admin only), Activity Logs + Campaign Changes (visible to `is_admin` or `can_see_logs`). Alpine.js powers mobile toggle. |
| `resources/views/components/dropdown.blade.php` | Generic Alpine.js dropdown shell with click-outside handling. |
| `resources/views/components/dropdown-link.blade.php` | A styled `<a>` or `<button>` for use inside a `<x-dropdown>`. |
| `resources/views/components/nav-link.blade.php` | Sidebar nav link with active-state styling. |
| `resources/views/components/responsive-nav-link.blade.php` | Mobile-friendly nav link variant. |

**Containers:**

| File | Responsibility |
|------|---------------|
| `resources/views/components/page-box.blade.php` | White card container (`bg-surface`, shadow, rounded). The standard wrapper for all page sections. |
| `resources/views/components/dialog.blade.php` | Reusable modal dialog shell. Accepts a title slot and wraps content with an Alpine.js `x-show` backdrop and close button. |
| `resources/views/components/modal.blade.php` | Alternative modal component. |
| `resources/views/components/filter-box.blade.php` | Styled container for filter controls above a table. |

**Forms:**

| File | Responsibility |
|------|---------------|
| `resources/views/components/text-input.blade.php` | Styled `<input>` with consistent border and focus ring. Forwards all attributes. |
| `resources/views/components/input-label.blade.php` | `<label>` with standard weight and colour. |
| `resources/views/components/input-error.blade.php` | Renders a `$message` in red beneath a field. |
| `resources/views/components/autocomplete-input.blade.php` | Alpine.js-powered typeahead input. Accepts a `suggestions` array, emits `selected` events. Used for client selection in multiple forms. |
| `resources/views/components/dates-filter.blade.php` | Date range picker component for filtering dashboard and export views. |

**Buttons:**

| File | Responsibility |
|------|---------------|
| `resources/views/components/primary-button.blade.php` | Orange CTA button (`bg-[#F97316]`). Forwards all attributes; merges base classes so callers can add extras. |
| `resources/views/components/secondary-button.blade.php` | Neutral outlined button (`bg-white border-gray-200`). |
| `resources/views/components/danger-button.blade.php` | Red destructive-action button (`bg-red-600`). |

**UI Utilities:**

| File | Responsibility |
|------|---------------|
| `resources/views/components/ui/datatable.blade.php` | Wrapper that initialises DataTables.js on a `<table>`. Reads `data-order` attributes on `<th>` elements to control default and column sort behaviour (custom logic: uses `td.dataset.order` over `td.innerText` for numeric/date sort overrides). |
| `resources/views/components/ui/size-pill.blade.php` | Small badge pill used in the Required Creative Sizes accordion. Renders a size label with active/inactive state styling. |
| `resources/views/components/scripts/datatables.blade.php` | Injects DataTables.js CDN links and shared initialisation config. Included once per page via the layout. |
| `resources/views/components/application-logo.blade.php` | SVG MadData logo. |
| `resources/views/components/auth-session-status.blade.php` | Shows flash `status` session message as a styled emerald banner (e.g. "Password reset link sent"). |
| `resources/views/components/page-header.blade.php` | Standardised page heading block. Accepts `title`, optional `description`, and a `$slot` for action buttons. Renders the title left and actions right, stacking on mobile. |
| `resources/views/components/flash-messages.blade.php` | Renders `session('success')`, `session('error')`, and `session('warning')` as styled inline banners. Drop `<x-flash-messages />` once at the top of any page content area. |
| `resources/views/components/confirm-dialog.blade.php` | Global Alpine.js confirmation dialog. Listens for `confirm-action` dispatch events (`{ message, confirmText, onConfirm }`). Renders a modal overlay with a customisable message and confirm/cancel buttons. Avoids inline `onclick` + `confirm()` patterns across the app. |

---

## 14. Frontend — JavaScript

| File | Responsibility |
|------|---------------|
| `resources/js/app.js` | Entry point. Initialises Alpine.js and imports `campaigns.js` and `utils.js`. Alpine is started here so all `x-data` components are available globally. |
| `resources/js/campaigns.js` | Persists the user's last-selected client to `localStorage` with a 2-hour TTL. Pre-selects the client dropdown on the campaign create form based on this stored value, reducing repetitive clicks. |
| `resources/js/utils.js` | Shared utility: `getWithExpiry(key)` and `setWithExpiry(key, value, ttlMs)` — localStorage helpers that attach an ISO expiry timestamp to stored values. |
| `resources/js/bootstrap.js` | Laravel-generated bootstrap file. Sets up Axios with CSRF header and imports Laravel Echo configuration (not actively used but kept for future real-time features). |

---

## 15. Frontend — CSS & Design System

| File | Responsibility |
|------|---------------|
| `resources/css/app.css` | Tailwind CSS entry point (`@tailwind base/components/utilities`). Contains minimal custom CSS overrides (e.g. DataTables styling adjustments). |
| `tailwind.config.js` | Extends the default Tailwind theme with a custom semantic design system: **Colors** (`primary`, `accent`, `surface`, `background`, `textMuted`, `textSubtle`, `success`, `warning`, `danger`); **Shadows** (custom elevation tokens); **Animations** and **Keyframes** for the AI assistant loading spinner. All UI components reference these semantic tokens — never raw hex or arbitrary Tailwind values. |
| `vite.config.js` | Vite bundler config with the `laravel-vite-plugin`. Configures PostCSS with Tailwind and Autoprefixer. Input entrypoints: `resources/css/app.css` and `resources/js/app.js`. |

---

## 16. Tests

### Feature Tests

| File | What it covers |
|------|---------------|
| `tests/Feature/CampaignControllerTest.php` | Campaign CRUD — create, update, delete, access control by client ownership, status transitions. |
| `tests/Feature/CampaignUploadTest.php` | CSV/Excel campaign data upload and parsing logic. |
| `tests/Feature/CreativeUploadTest.php` | Creative file upload — MIME validation, storage path safety, image re-encoding. |
| `tests/Feature/DashboardControllerTest.php` | Dashboard metrics aggregation; `can_view_budget` gating; date filter logic. |
| `tests/Feature/ClientControllerTest.php` | Client CRUD — admin-only create/delete enforcement. |
| `tests/Feature/UserControllerTest.php` | User CRUD — role assignment, client attachment, admin-only access. |
| `tests/Feature/TokenControllerTest.php` | API token lifecycle — creation with expiry, extension, deletion, middleware enforcement. |
| `tests/Feature/ReportApiTest.php` | Core report API endpoints with Sanctum token auth. |
| `tests/Feature/ReportApiExtendedTest.php` | Extended report API scenarios — budget visibility gating, multi-campaign access. |
| `tests/Feature/ApiErrorResponseTest.php` | Regression coverage for `/api/*` error response contract — asserts JSON 401/403/404/422/429 for missing/invalid/expired tokens, missing abilities, route-model binding failures, and validation errors. Critical case: requests with `Accept: text/html` must still receive JSON (guards against Laravel's default redirect-to-login behavior for non-JSON requests). |
| `tests/Feature/GeoReferenceControllerTest.php` | Auth enforcement on `/api/geo/*` routes; validation for missing `country` param; upstream-success happy path; 7-day cache warm path (assert upstream only called once); upstream-500 falls back to static JSON (asserts `Israel` in countries list); bilingual Israel cities fallback (asserts both `Holon` and `חולון` etc.); missing static file returns `[]` not 500; `geo.fallback_used` warning is emitted on fallback. |
| `tests/Feature/CampaignAssistantLoggingTest.php` | Asserts `assistant.request` and `assistant.response` are logged to the `ai` channel on a successful call; `updates_keys` contains `'cities'` when the LLM returns cities; malformed JSON from the LLM returns 502 and logs `assistant.parse_failure` at warning; upstream HTTP 500 returns 502 and logs `assistant.upstream_error` at error; request log contains correct `user_id`, `message_count`, `last_user_message_length`; null updates logs empty `updates_keys`. |
| `tests/Feature/CampaignAudienceTest.php` | Audience sync — attaching/detaching audiences to campaigns via pivot. |
| `tests/Feature/CampaignChangeFilterTest.php` | `scopePending()` and filtering of ActivityLogs in the CRM view. |
| `tests/Feature/CampaignCreativeOptimizationTest.php` | Observer diff detection for `creative_optimization` flag changes. |
| `tests/Feature/CreativeSizeTest.php` | Required creative sizes — storage format and retrieval. |
| `tests/Feature/ProfileTest.php` | Profile update and account deletion. |
| `tests/Feature/Admin/AudienceControllerTest.php` | Admin audience CRUD, bulk Excel import, `is_active` toggle. |
| `tests/Feature/Admin/ActivityLogControllerTest.php` | Log visibility scoping — admins see all; `can_see_logs` users see only connected campaigns. |
| `tests/Feature/Admin/CampaignChangeControllerTest.php` | Campaign Changes CRM — list, download, mark as handled. |
| `tests/Feature/Admin/RoleControllerTest.php` | Role CRUD, permissions matrix, `sort_order` reordering. |
| `tests/Feature/Auth/*.php` | Full Breeze auth flows: login, register, password reset, email verification, session management. |

### Unit Tests

| File | What it covers |
|------|---------------|
| `tests/Unit/CampaignTest.php` | Campaign model scopes, status helpers, and relationship integrity. |
| `tests/Unit/ClientTest.php` | Client model relationship and helper logic. |
| `tests/Unit/UserTest.php` | `User::hasPermission()` — dual-layer resolution (Role JSON first, then legacy booleans); edge cases for missing role. |

### Test Infrastructure

| File | Responsibility |
|------|---------------|
| `tests/TestCase.php` | Base class — uses `RefreshDatabase` and `WithFaker`. Configures SQLite in-memory DB (via `phpunit.xml`). |
| `tests/Pest.php` | Pest PHP config. Declares `uses(TestCase::class)->in('Feature', 'Unit')`. Can house global helper functions. |

---

## 17. Service Providers & Bootstrap

| File | Responsibility |
|------|---------------|
| `app/Providers/AppServiceProvider.php` | Registers the three model observers (`CampaignObserver`, `CreativeObserver`, `CreativeFileObserver`) in the `boot()` method. This is the single place that wires event-driven logging into the application lifecycle. |
| `app/View/Components/AppLayout.php` | Backing class for the `<x-app-layout>` component. Renders `layouts/app.blade.php`. |
| `app/View/Components/GuestLayout.php` | Backing class for the `<x-guest-layout>` component. Renders `layouts/guest.blade.php`. |
| `app/View/Components/AuthSplitLayout.php` | Backing class for the `<x-auth-split-layout>` component. Accepts a `title` prop and renders `layouts/auth-split.blade.php`. Used by the login page and all 2FA views. |
| `bootstrap/app.php` | Laravel 12 application bootstrap. Registers middleware aliases (`admin`, `campaign_manager`, `can_see_logs`, `two_factor`, `check-token-expiry`, `ability`). Appends `ContentSecurityPolicy`, `RequireTwoFactor`, and `AdminOnlyMode` to the `web` group. Centralized API error rendering: `shouldRenderJsonWhen` forces JSON on every `api/*` path regardless of the client's `Accept` header, and dedicated `render()` closures translate `AuthenticationException` → 401, `AuthorizationException`/`AccessDeniedHttpException` → 403, `ValidationException` → 422 (with `errors` key), `NotFoundHttpException`/`ModelNotFoundException` → 404, `ThrottleRequestsException` → 429, any other `HttpException` → its native status, and a fallback `Throwable` → 500 (message suppressed, no stack trace leak). Each closure returns `null` on non-`api/*` paths so the web login redirect, 2FA throttle inline-error flow, and other default web behaviors remain intact. This is the **only** place exception handling is customized in Laravel 12 — there is no `App\Exceptions\Handler` class. |

---

*Last updated: 2026-04-16 — AI Campaign Assistant cities hotfix: added `GeoReferenceService`, `GeoReferenceController`, `/api/geo/*` routes, bilingual Israel static dataset, `ai` log channel in `config/logging.php`, structured logging in `CampaignAssistantController`, plus `GeoReferenceControllerTest` and `CampaignAssistantLoggingTest`.*

---

## 18. System Health Monitor

**Spec:** `docs/specs/system-health-monitor.md` · **Runbook:** `docs/runbooks/health-monitor.md`

Answers "is production OK?" from a CLI, and (Phase 2/3) by email and an admin
page. Design ported down from the erate-v2 fleet monitor and scaled to MadData's
single droplet.

**The structural idea:** a root OS cron writes host facts to a JSON file on
tmpfs; the app only ever reads it. PHP-FPM never shells out, never holds a sudo
grant, and never talks to systemd or apt. Because the facts path has no Redis,
MySQL or Laravel in it, it still reports correctly when those are exactly what
is broken.

### Contracts

| File | Responsibility |
|------|---------------|
| `app/Enums/HealthStatus.php` | `OK`/`WARN`/`CRIT`/`STALE` with a `worstOf()` combinator, `forPill()` (STALE→WARN), design-system colour tokens and shell exit codes. STALE outranks OK so blind checks are visible, but never outranks CRIT so it cannot mask a real outage. |
| `app/Dtos/HealthCheckResult.php` | One row of the check catalog: key, label, status, node, value, threshold, link. `node` must be the node the check really belongs to — a CRIT tagged to the wrong node is invisible on the map. |
| `app/Dtos/HealthSnapshot.php` | The whole system at one instant. `toArray()` **is** the JSON contract consumed by `health:check --json` and (Phase 3) the admin poller. The cache stores this array rather than the object, so a deploy that changes the class cannot fatal on unserialize. Also exposes `failing()` (worst first) and `signature()` (Phase 2 alert de-duplication). |
| `app/Services/Health/Checks/HealthCheck.php` | Abstract base. **`run()` must never throw** — `guard()` turns any throwable into a CRIT tagged to the check's real node. Also holds inclusive threshold evaluation (`evaluateOver`/`evaluateUnder`) and the age-since-marker shape most of the catalog uses. |

### Check classes

| File | Checks | Responsibility |
|------|--------|---------------|
| `app/Services/Health/Checks/HostCheck.php` | H1–H6 | Everything from the facts file: file freshness (CRIT when missing — a monitor that has gone blind is worse than none), CPU/memory/disk, systemd unit states each tagged to its own node, pending reboot. |
| `app/Services/Health/Checks/EdgeProbeCheck.php` | P1–P2 | Reads the probe marker (never curls inline, so a snapshot build cannot block on the network) and TLS days remaining. Two consecutive probe failures, not one blip, is what escalates to CRIT. |
| `app/Services/Health/Checks/DataStoreCheck.php` | D1–D3 | MySQL reachability on the dedicated 2s-timeout `mysql_health` connection, Redis memory against its own ceiling, and campaign-data freshness. D3 is informational and can never reach CRIT — uploads are manual, so "stale" often just means nobody uploaded. |
| `app/Services/Health/Checks/QueueCheck.php` | Q1–Q3 | Depth, failures in the last 24h, and the worker heartbeat. Q3 is the load-bearing one: systemd reports "active" for a wedged worker, and only a job that executes disproves that. |
| `app/Services/Health/Checks/SchedulerCheck.php` | S1–S2b | Cron→Laravel heartbeat plus per-job success markers, so a scheduler whose jobs all throw stops looking healthy. |
| `app/Services/Health/Checks/BackupCheck.php` | B1–B4 | Backup age, off-site upload, restore-drill age, and size-vs-median. B2 catches the failure a naive "did it run?" check misses: mysqldump exiting 0 having written half a database. |

### Alerting (Phase 2)

| File | Responsibility |
|------|---------------|
| `app/Console/Commands/SendHealthAlert.php` | The state machine. Keeps one "episode" record — what is failing, since when, and whether anyone has been told — in `HealthMarkers::ALERT_STATE`, and decides from it whether this observation deserves an email. Four rules: no alert on a single observation; fail toward alerting when its own state is unreadable; silence must be earned (re-alert timer, new-failure re-alert, mandatory recovery notice); a send that throws is logged and left un-recorded so the next tick retries. |
| `app/Mail/HealthAlertMail.php` | The mailable. **Deliberately not `ShouldQueue`**, unlike `ActivityDigestMail` — a queued alert would sit in the queue forever exactly when the queue worker is what died, which is one of the failures it exists to report. Subject line is ASCII so it survives SMS and pager gateways. |
| `resources/views/emails/health_alert.blade.php` | Status banner tinted by severity, a failing-checks-only table (key, label, node, value, threshold), and a pointer to the runbook. Recovery mode reports how long the episode lasted instead. |

Alert recipients come from `HEALTH_ALERT_RECIPIENTS` (comma-separated), not from
the `receive_activity_notifications` user flag: health mail is operations, not
product, and has to reach someone even when the database is what is sick.

**Honest limit:** if the droplet, the network or SMTP is down, none of this
fires. Detecting that requires an external watcher on `/up` — see the runbook.

### Supporting files

| File | Responsibility |
|------|---------------|
| `app/Jobs/QueueHeartbeatJob.php` | Dispatched onto the real queue every minute; writes its marker only when it actually executes. Does no work and holds no state — it must never be the job that poisons the queue it watches. |
| `scripts/health-facts.sh` | Root OS cron, every minute. Real CPU via `vmstat` (loadavg runs well above true utilization and produces false CRITs), averaged over `HEALTH_CPU_SAMPLE_SECONDS` (default 15) and offset from the top of the minute by the crontab's `sleep 25` — sampling at `:00` measures `schedule:run` booting PHP, which on a single-core droplet reads as 100% while the box is idle. Also memory, disk, systemd states, `apt-check` security count, TLS expiry, backup-dir stats → atomic write to `/run/maddata/host-facts.json`. Degrades to `null` fields rather than failing. |
| `scripts/backup-production.sh` | *(modified)* Writes `/var/backups/maddata/backup-last.json` on completion, feeding B1–B3. Persistent storage, not tmpfs: it records that a backup happened, which must outlive a reboot — unlike the host facts, which are a live sample and should not. |
| `config/health.php` | Every threshold, node label, systemd unit→node map, file path and the check registry. No threshold is ever hardcoded in a check class. |
| `config/database.php` | *(modified)* Adds the `mysql_health` connection — a clone of `mysql` with `PDO::ATTR_TIMEOUT => 2`, so a MySQL outage degrades the pill instead of hanging every admin page. |
| `routes/console.php` | *(modified)* Schedules the refresh, probe, alert and heartbeat, and records the business jobs' success markers via `->onSuccess()` — so the marker means "completed", not "started". The health tasks use `Schedule::call(fn () => Artisan::call(...))` rather than `Schedule::command()`: the latter spawns a fresh `php artisan` process per task, and three extra PHP boots a minute measurably loaded the single-core production droplet. The commands still exist for manual use. |
| `app/Providers/AppServiceProvider.php` | *(modified)* Registers `HostFacts` as a singleton so one snapshot build reads the facts file once. |

### Admin surface (Phase 3)

**Spec:** `docs/specs/health-monitor-phases-3-4.md`

| File | Responsibility |
|------|---------------|
| `app/Http/Controllers/Admin/MonitorController.php` | `/admin/monitor` (page), `/admin/monitor/data` (polled JSON, `throttle:60,1`) and `/admin/monitor/refresh` (POST, `throttle:6,1`). Thin by contract — no thresholds, no formatting, no queries. There is deliberately no API Resource: `HealthSnapshot::toArray()` is already the documented contract, and wrapping it would make two places to change one shape. Refresh is POST because it mutates cache state, and a GET that mutates is prefetchable by a browser link preview. |
| `app/View/Components/HealthPill.php` | The header pill. Renders into `layouts/app.blade.php`, which **every** authenticated page uses, so the admin gate is `shouldRender()` rather than an `@if` in the layout — no page can render it unguarded, and non-admins get no markup at all rather than a hidden element. Reads `pillStatus()` (cache-only, never rebuilds) and still wraps it in its own try/catch: everything else in the monitor fails one panel, this would fail every page in the app. |
| `resources/views/admin/monitor.blade.php` | The page and its Alpine component. Everything renders from the Alpine state object, never from Blade — Blade seeds it once via `@js()` and the poller replaces it wholesale, because two rendering paths for one shape is how the initial and refreshed views drift apart. The status→colour map holds **complete Tailwind class literals**; an interpolated `bg-${token}-500` is invisible to the JIT compiler and would ship the page with no colours. |
| `resources/views/components/monitor/{node-card,kpi-tile,check-row}.blade.php` | The three repeated pieces. Each takes an `expr` prop naming the Alpine variable it should read, so the caller owns the loop and the component has no hidden coupling to a variable name. |
| `resources/views/components/health-pill.blade.php` | Pill markup, tinted by a PHP `match` over `HealthStatus` (again full class literals, not interpolated tokens). |

**The stale badge is load-bearing.** `snapshot_ttl` is 300s and `SNAPSHOT_LAST`
has no TTL at all, so a dead scheduler would otherwise render a confidently
green page forever — the worst failure a monitor has. Past
`health.ui.stale_seconds` the header says the snapshot is old regardless of what
`overall` claims. Check S1 covers the same ground, but the header must not
depend on the reader noticing a check.

`SystemHealthService::refreshOnDemand()` backs the refresh button: it takes the
single-flight lock and, when it cannot get it, serves the in-flight result
rather than queueing a second rebuild. Two admins clicking on a sick box is
exactly when the probes are slowest, because the MySQL round trip and the Redis
`INFO` are expensive *because* MySQL and Redis are what is broken. `snapshot()`
is now `cached() ?? refreshOnDemand()`, so the lock logic exists once.

### Dependency currency (Phase 4)

**Spec:** `docs/specs/health-monitor-phases-3-4.md` §9-12

The structural change is that **a check's node now decides its delivery
channel.** `config/health.php`'s `alert_excluded_nodes` (currently `platform`)
splits the catalog in two: `HealthSnapshot::alertable()` / `alertStatus()` drive
`health:alert`, `digestable()` drives the weekly `deps:digest`, and
`signature()` is computed from the alertable half so a new advisory can never
read as "something new broke". `overall` stays honest, so the page and the pill
still go red — the page is pull, alerting is push, and only push is filtered.

| File | Responsibility |
|------|---------------|
| `app/Services/Health/Checks/DependencyAdvisoriesCheck.php` | d1 — deployed `composer.lock` vs Packagist's advisory API, matched with `composer/semver`. Cached **by the lock's sha256**, so a deploy re-queries instead of serving a day-old clean bill. An unreachable feed serves the last known result and says so, or warns; it is never green. Unrated severity counts as high. Dev-only advisories cap at WARN — production installs `--no-dev`, so they are not on the box, but they are on every developer's machine. |
| `app/Services/Health/Checks/RuntimeEolCheck.php` | d2 — PHP/MySQL/Redis/Nginx versions vs `config/dependency_maintenance.php`. MySQL is read on the short-timeout `mysql_health` connection. Three subtleties, each there to keep a finding actionable: a branch missing from the table WARNS rather than passing; a null support window is OK-with-a-note when another check owns that runtime's currency (`tracked_by`) but WARNS when it does not; and a runtime past its **upstream** window WARNS rather than going CRIT when its version string carries a distro marker, because Ubuntu backports fixes into 24.04 `main` — the branch is frozen, the box is not exposed, and a CRIT only an OS upgrade could clear is permanent red. That last one is detected from the version string, not configured, so an upstream-repo build re-escalates on its own. Plus a check on the table's own `reviewed_at` — a support table nobody revisits reports "all supported" forever. |
| `app/Services/Health/Checks/OsPatchCheck.php` | d3 — pending security updates and whether a reboot is owed, from the facts file. Holds a since-marker because apt only reports the CURRENT count, so "unpatched for a month" is otherwise indistinguishable from "unpatched since lunchtime"; the marker resets when the backlog clears **or shrinks**. A `null` count is STALE, never 0. |
| `app/Services/Health/Checks/PatchRunFreshnessCheck.php` | d4 — how long since a human patched, from the marker `deps:mark-patch-run` writes. Also stores the lock hash at the time, so a recent patch run that no longer describes the deployed lock still warns. Never marked reads WARN, not STALE: "nobody has ever patched this" is a fact, not missing data. |
| `app/Services/Health/Checks/SecurityPostureCheck.php` | X1 expired Sanctum tokens (WARN only — `CheckTokenExpiry` already refuses them, so leftovers are untidy rather than dangerous) and X2 failed-login bursts. |
| `app/Services/Health/Listeners/RecordFailedLogin.php` | Feeds X2 by incrementing a self-expiring one-minute cache bucket per failed login — no table, no migration, nothing to prune. Wrapped in try/catch: a monitoring counter must never stop people signing in. **Registered explicitly in `AppServiceProvider`, and deliberately NOT in `app/Listeners`** — event discovery is active here, so a class in that directory with a typed `handle()` gets registered a second time and double-counts. |
| `app/Console/Commands/SendDependencyDigest.php` + `app/Mail/DependencyDigestMail.php` + `resources/views/emails/dependency_digest.blade.php` | The weekly channel. **Always sends, including all-clear** — a report that only appears on bad news is indistinguishable from one that has stopped working. |
| `app/Console/Commands/MarkPatchRun.php` | Writes d4's marker with the lock hash. The check nags; a human does the work. |
| `config/dependency_maintenance.php` | The support-window table. Every date carries its `source`; an unsourced table reads green with authority. |
| `scripts/health-facts.sh` | *(modified)* Counts security updates via `apt-get -s upgrade` filtered to `-security` pockets, **not** `apt-check` — the latter counts packages that are not installed, which produced a permanently stuck amber no action could clear. Writes `null` when it cannot compute. |

### Tests

`tests/Unit/Health/` (enum + snapshot DTO) and `tests/Feature/Health/` (one file
per check class, plus `SystemHealthServiceTest`), with the command tests in
`tests/Feature/Commands/`. Shared fixtures live in `tests/Pest.php`
(`fakeHostFacts`, `fakeBackupMarker`, `checksByKey`) — **no test executes a shell
command.** `SystemHealthServiceTest` explicitly asserts the resilience property:
a check class that throws still yields a built snapshot.

`MonitorControllerTest` and `HealthPillTest` cover the Phase 3 surface. Two
things there are deliberate rather than incidental: the agency-manager 403 is
asserted **separately** on the page, the JSON endpoint and the refresh POST
(health is system-level, so the admin middleware is the only thing scoping it,
and the JSON endpoint is the one that actually leaks); and the resilience
property is re-asserted at the HTTP boundary — every check throwing must still
produce a 200 with a CRIT payload, not a 500. The pill is tested on an
unrelated page, because that is where it lives, and the non-admin case asserts
the markup is **absent** rather than merely hidden.
