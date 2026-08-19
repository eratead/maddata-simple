# MadData — Consolidated Audit TODO
**Generated:** 2026-03-22
**Sources:** Security Audit, Code Review, Performance Audit

---

## HOTFIX — API Error Response Hardening
**Spec:** [api-error-response-hardening.md](../specs/api-error-response-hardening.md)
**Incident:** Production users of `/api/reports/*` receive HTML login page instead of JSON 401 when their Bearer token fails validation. Downstream integrations break with `"Could not find variable: campaign_id"`.
**Root cause:** `app/Exceptions/Handler.php` is dead code in Laravel 12 — Laravel uses its default handler, which redirects non-JSON requests to `/login`. Postman's default `Accept: */*` does not trigger the JSON branch.
**Priority:** P0 — user-facing regression.

### Tasks (builder sequence)

- [x] **1. Register `shouldRenderJsonWhen` in `bootstrap/app.php`**
  Inside the existing `->withExceptions(function (Exceptions $exceptions) { ... })` closure, add `$exceptions->shouldRenderJsonWhen(fn ($request, $e) => $request->is('api/*'));` so Laravel's internal "expects JSON" decision respects API paths regardless of the `Accept` header.

- [x] **2. Add `AuthenticationException` render closure in `bootstrap/app.php`**
  Register `$exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) { ... })` that returns `response()->json(['message' => 'Unauthenticated.'], 401)` when `$request->is('api/*')`, else `null` to fall through.

- [x] **3. Add `AuthorizationException` render closure in `bootstrap/app.php`**
  Return `response()->json(['message' => 'This action is unauthorized.'], 403)` for `api/*` paths. Covers Sanctum ability check failures (`ability:reports:read`). NOTE: Also added `AccessDeniedHttpException` handler because Laravel's `prepareException()` converts `AuthorizationException` → `AccessDeniedHttpException` before render callbacks run.

- [x] **4. Add `ValidationException` render closure in `bootstrap/app.php`**
  Return `response()->json(['message' => 'The given data was invalid.', 'errors' => $e->errors()], 422)` for `api/*` paths.

- [x] **5. Add `NotFoundHttpException` / `ModelNotFoundException` render closure in `bootstrap/app.php`**
  Return `response()->json(['message' => 'Resource not found.'], 404)` for `api/*` paths. This catches route-model binding failures (`/api/reports/summary/{campaign}` with a non-existent campaign id).

- [x] **6. Extend the existing `ThrottleRequestsException` render closure**
  Currently it only handles the web case. Add an `api/*` branch that returns `response()->json(['message' => 'Too many requests.'], 429)` before the web redirect-back path. Preserve existing 2FA throttle behavior on web routes.

- [x] **7. Add fallback `Throwable` render closure in `bootstrap/app.php`**
  For `api/*` paths only, return `response()->json(['message' => 'Server error.'], 500)` on any uncaught exception. Do **not** leak exception messages or stack traces. Keep this as the last render closure so specific types match first. Also added generic `HttpException` handler before the Throwable fallback to preserve correct HTTP status codes for any unhandled HttpException subclass.

- [x] **8. Delete `app/Exceptions/Handler.php`**
  Dead code in Laravel 12 (no references anywhere in the codebase). Confirmed with `grep -rn "App\\\\Exceptions\\\\Handler" .` before deleting.

- [x] **9. Create `tests/Feature/ApiErrorResponseTest.php`** covering eight scenarios against `/api/reports/campaigns`:
  - a. No Authorization header → 401 JSON
  - b. Malformed Authorization header (`"Bearer garbage"`) → 401 JSON
  - c. Valid header, non-existent token hash → 401 JSON
  - d. Valid token with `expires_at` in the past → 401 JSON (Sanctum guard rejects expired token as Unauthenticated, not Token expired — CheckTokenExpiry only fires via session auth)
  - e. Valid token missing `reports:read` ability → 403 JSON
  - f. Valid token + ability, invalid `start` query param → 422 JSON with `errors` key (added validation to `campaigns()` method)
  - g. Valid token + ability, valid request → 200 JSON (regression guard)
  - h. Case (a) repeated with `Accept: text/html`, `Accept: */*`, and no `Accept` header — all return JSON

  Every assertion includes `assertHeader('Content-Type', 'application/json')`, `assertDontSee('<!DOCTYPE html', false)`, and `assertDontSee('Welcome back', false)`.

- [x] **10. Run the full test suite**
  `composer run test` — 491 passed, 1 skipped (pre-existing skip), 0 failed. All existing tests preserved.

- [x] **11. Manual verification against local dev server**
  Verified via `php artisan serve` on port 8765. All three variants (default Accept, `Accept: text/html`, `Accept: */*`) return `HTTP/1.1 401` + `Content-Type: application/json` + body `{"message":"Unauthenticated."}`. Critical regression case (text/html) confirmed fixed.

- [ ] **12. Deploy**
  Push to `staging` branch first. Verify against staging with Postman using the exact request from the incident report. Then merge to `main` and deploy to production via the `server` agent. After production deploy, confirm with the original failing request that JSON is returned.

### Follow-up (not part of this hotfix)

- [ ] **Investigate why tokens started being rejected post-cutover** — delegate to `server` agent. Compare `personal_access_tokens` row counts and sample hashes between old prod (207.154.253.28, DB `maddata_simple_prod`) and new prod (164.90.233.136, DB `maddata`). Check `APP_KEY` parity. This is the *upstream* root cause of users hitting auth failures in the first place; the hotfix above only ensures those failures return JSON instead of HTML.

- [ ] **Decide whether to move `/api/reports/*` from `routes/web.php` to `routes/api.php`** — tracked as open question #1 in the spec. Larger refactor, defer to a follow-up spec once this hotfix ships.

---

## ✅ Completed — Provision & Setup New Droplet (Phases 1-5)
**Spec:** [production-new-droplet-migration.md](../specs/production-new-droplet-migration.md)
**New droplet:** 164.90.233.136 (`new.ad.maddata.media`)
- [x] Phase 1: Droplet provisioned (Ubuntu 24.04, fra1, hardened, swap, UFW)
- [x] Phase 2: Stack installed (Nginx, PHP 8.4-FPM, MySQL 8, Redis, Node 20, ffmpeg, Certbot)
- [x] Phase 3: Services configured (DB `maddata`, PHP-FPM tuned, Nginx vhost, TLS cert, backups dir)
- [x] Phase 4: App deployed (git clone, composer, npm build, .env, migrations, storage:link, caches)
- [x] Phase 5: System services (queue worker systemd, scheduler cron, backup cron, logrotate)
- [x] Phase 6 rehearsal: Data imported from old prod, quick spot-check passed

### Health check 2026-04-12 (all passed)
- [x] All 5 services active (Nginx, PHP-FPM, MySQL, Redis, queue worker)
- [x] Queue worker alive and draining jobs table
- [x] Scheduler cron installed, backup cron running (3 nightly backups completed)
- [x] Zero Laravel errors in log
- [x] DB matches old prod (16 users, 35 clients, 76 campaigns, 10 agencies, 4 roles)
- [x] Disk: 6.2 GB / 48 GB (14%), swap barely used
- [x] TLS valid, PHP config correct, logrotate installed

---

## 🎯 Current Task — Production Cutover Execution
**Spec:** [cutover-execution-2026-04-12.md](../specs/cutover-execution-2026-04-12.md)
**Date:** 2026-04-12
**Strategy:** Fresh dump → maintenance on old → admin-only on new → DNS flip → admin QA → verify roles → open to all

### Step 1 — Fresh dump from old prod
- [ ] `mysqldump --single-transaction --quick --routines --triggers -u webusr -p maddata_simple_prod | gzip > /tmp/maddata_final.sql.gz` on old droplet (207.154.253.28)

### Step 2 — Maintenance mode on old prod
- [ ] `php artisan down --render="errors::503" --retry=600` on old droplet (`/var/www/prod/maddata-simple`)

### Step 3 — Enable admin-only mode on new prod
- [ ] Toggle admin-only ON via `/admin/system-status` or `Cache::forever("admin_only_login", true)`

### Step 4 — Transfer & import fresh data
- [ ] `scp` dump file from old to new droplet
- [ ] Drop/recreate `maddata` DB, import dump
- [ ] `php artisan migrate --force` (apply any newer migrations)
- [ ] `php artisan seed:staging-roles` (idempotent)

### Step 5 — Clear caches & restart services
- [ ] `config:clear && cache:clear && view:clear && route:clear`
- [ ] `config:cache && route:cache && view:cache`
- [ ] `systemctl restart maddata-queue.service && systemctl reload php8.4-fpm`
- [ ] Re-enable admin-only mode (cache:clear wiped it): `Cache::forever("admin_only_login", true)`

### Step 6 — Flip APP_URL & Nginx
- [ ] Change `APP_URL=https://ad.maddata.media` in `.env`, re-cache config
- [ ] Update Nginx `server_name` to include `ad.maddata.media`

### Step 7 — DNS switch
- [ ] In DO panel: change `ad.maddata.media` A record from `207.154.253.28` to `164.90.233.136`
- [ ] Wait 1-2 min for propagation (TTL=60s)

### Step 8 — Certbot for ad.maddata.media
- [ ] `certbot --nginx -d ad.maddata.media -d new.ad.maddata.media` (after DNS propagates)

### Step 9 — Admin QA on real domain
- [ ] Admin login works at `https://ad.maddata.media`
- [ ] Dashboard loads, campaigns render with correct data (spot-check 2-3)
- [ ] Agency list: 10 agencies
- [ ] User list: 16 users with correct roles
- [ ] Activity logs show Israel time
- [ ] By-date table: no "Visible" column
- [ ] Non-admin login attempt → sees maintenance message
- [ ] Check `storage/logs/laravel.log` for errors

### Step 10 — Verify user roles
- [ ] Michael & Eran → Admin role
- [ ] Agency managers → Agency Manager role
- [ ] All others → appropriate viewer roles
- [ ] No orphaned/missing role assignments
- [ ] Disabled users still disabled

### Step 11 — Open to all users
- [ ] Toggle admin-only OFF via `/admin/system-status` or `Cache::forever("admin_only_login", false)`
- [ ] Verify a non-admin can log in

### Post-cutover monitoring
- [ ] T+1h: spot-check pages, `jobs` table count, tail log
- [ ] Verify nightly backup at 03:00 with fresh data
- [ ] T+24h: review logs, confirm backup ran
- [ ] T+1 week: backup rotation working, no user issues
- [ ] T+2 weeks: drop `maddata_simple_prod` on old droplet, archive files, remove Apache vhost

---

## 📋 Post-Cutover — Performance Audit Fix Wave
**Spec:** [docs/specs/performance-audit-2026-04.md](../specs/performance-audit-2026-04.md)
**Status:** Findings recorded. Deferred until production cutover is complete and stable ≥ 24 hours.
**Why deferred:** Zero user impact at current data scale (76 campaigns, 0 creatives, 0 activity logs). Fixes become verifiable only after stress-seeding realistic volumes. Meta-fix (#14 strict lazy loading) would red-line the test suite unless all N+1s are fixed first — don't do piecemeal.

### Prep
- [ ] Production cutover complete and stable ≥ 24 hours
- [ ] Write `StressTestSeeder` (500 creatives, 2,000 CreativeFiles, 5,000 activity logs)
- [ ] Capture baseline query counts per list endpoint (before measurements)

### Application fixes (in impact order per spec)
- [ ] **#5** ActivityLogger unbounded `->get()` in digest dispatch — move to queued job, add limit, select only needed columns
- [ ] **#3** `Creative::find()` in loop in `campaign_changes/show.blade.php:86` — batch pre-load in controller
- [ ] **#4** Filesystem `getimagesize()` in loop in same view — persist width/height at upload, remove fallback
- [ ] **#1** Missing `creatives.files` eager load in `CampaignController::edit` — add to line 182
- [ ] **#2** Missing `morphWith` in `ActivityLogger::checkAndSendDigest` for CreativeFile subject
- [ ] **#7 + #8** Tenant-scope `pluck()+whereIn()` → `whereExists` rewrite in ActivityLogController + CampaignController
- [ ] **#6** Unbounded `->get()` + PHP dedupe in `CampaignChangeController::show` — SQL dedupe or hard cap
- [ ] **#9** `CampaignController::index` — add `->select([...])` excluding `targeting_rules`, `required_sizes`
- [ ] **#10** `Client::all()` → `Client::select('id','name','agency_id')` in 3 cached dropdown sites
- [ ] **#13** Dead-code `$campaign->client->agency` cell in campaigns/index — delete or fix

### Meta fixes (apply together with above)
- [ ] **#14** Add `Model::preventLazyLoading(! app()->isProduction())` to `AppServiceProvider::boot()` — test suite must be green after all N+1 fixes land
- [ ] **#15** Add query-count regression tests (`DB::enableQueryLog()` + assert ≤ N queries) per list endpoint

### Verify
- [ ] Re-run baseline measurement, document before/after in spec
- [ ] Full test suite green with strict lazy loading on
- [ ] Manual smoke test against stress-seeded data
- [ ] Deploy, monitor MySQL slow query log 24 hours

---

## ✅ Completed — Display Activity Log Timestamps in Israel Time
**Spec:** [docs/specs/display-activity-logs-israel-time.md](../specs/display-activity-logs-israel-time.md)
**Scope:** Display layer only. Storage stays UTC. Activity log index + digest email + refactor existing hardcoded string in campaign_changes show view.

- [x] Add `'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'Asia/Jerusalem'),` to [config/app.php](../../config/app.php) (alongside existing `'timezone' => 'UTC'`)
- [x] Add `APP_DISPLAY_TIMEZONE=Asia/Jerusalem` to [.env.example](../../.env.example) and local [.env](../../.env) if missing
- [x] Wrap timestamp with `->timezone(config('app.display_timezone'))` at [resources/views/admin/activity_logs/index.blade.php:182](../../resources/views/admin/activity_logs/index.blade.php#L182)
- [x] Wrap timestamp with `->timezone(config('app.display_timezone'))` at [resources/views/emails/activity_digest.blade.php:48](../../resources/views/emails/activity_digest.blade.php#L48)
- [x] Refactor hardcoded `'Asia/Jerusalem'` to `config('app.display_timezone')` at [resources/views/admin/campaign_changes/show.blade.php:68](../../resources/views/admin/campaign_changes/show.blade.php#L68)
- [x] **Do not touch** `config('app.timezone')` (`UTC`), any `now()` calls, any DB writes, or `SystemStatusController.php`
- [x] Add Pest feature test asserting the admin activity log index renders timestamps in Israel time (pick a date unambiguously in IST or IDT — e.g., Jan 15 or Jul 15 — to avoid DST ambiguity)
- [x] Add Pest test asserting `ActivityDigestMail::render()` output contains the Israel-time string, not the UTC string
- [x] Grep `tests/` for existing activity-log timestamp assertions that may break and fix any that do
- [ ] Run `composer run test` — must stay green
- [ ] Manual check: open `/admin/activity-logs` and confirm visible timestamps match Israel wall-clock; trigger digest email preview and confirm the same

---

## ✅ Completed — Remove "Visible" Column From By-Date Report Table
**Spec:** [docs/specs/remove-visible-column-by-date.md](../specs/remove-visible-column-by-date.md)
**Scope:** UI-only. Do NOT drop DB column, KPI card, or By-Placement column.

- [x] Remove `<th>Visible</th>` header from by-date table — [resources/views/dashboard/index.blade.php:448](../../resources/views/dashboard/index.blade.php#L448)
- [x] Remove `<td x-text="nf(row.visible)">` row cell from by-date table — [resources/views/dashboard/index.blade.php:463](../../resources/views/dashboard/index.blade.php#L463)
- [x] Remove `<td>{{ $summary['visible'] ?? 0 }}</td>` totals cell from by-date table — [resources/views/dashboard/index.blade.php:477](../../resources/views/dashboard/index.blade.php#L477)
- [x] Remove `'visible' => (int) $r->visible_impressions,` from `$dashDateRows` map in [app/Services/CampaignMetricsService.php:82](../../app/Services/CampaignMetricsService.php#L82)
- [x] **Do not touch** `$summary['visible']` (KPI card) or `$dashPlacementRows['visible']` (placement table)
- [x] Opportunistically delete any dead `case 'visible':` branch in the Alpine `_sortRows` helper in the same blade — only if clearly unreachable
- [x] Run `composer run test` — must stay green; existing tests should need no edits
- [x] Add a narrow feature-test assertion that the by-date `<thead>` no longer contains a "Visible" column (see spec §Tests)
- [ ] Manual check: load the dashboard for a campaign with report data — by-date table has no Visible column; Viewability % KPI card still renders; By-Placement table still shows its Visible column

---

## 🔴 P0 — Critical Security (fix immediately)

- [x] **SEC-C1: Add authorization to `UserController::store()`** — Any authenticated user can create new users with any role, including admin. Add `$this->authorize('create', User::class)` as first line. `UserPolicy::create()` already restricts to admins.
  - File: `app/Http/Controllers/UserController.php:36`

- [x] **SEC-C2: Remove `role_id` from User `$fillable` + add escalation prevention** — Combined with C1, allows privilege escalation. Set `role_id` explicitly in controller after verifying current user's permissions >= target role.
  - File: `app/Models/User.php:33-41`

- [x] **SEC-C3: Disable or gate public `/register` route** — Anyone can create an account and enter the authenticated perimeter. For a managed-service SaaS, registration should be admin-invitation only.
  - File: `routes/auth.php:16-19`

- [x] **SEC-C4: Scope `markAsHandled()` to campaign** — `ActivityLog::whereIn('id', $logIds)->update(...)` has no campaign scope. Add `.where('campaign_id', $campaign->id)` and validate `log_ids.*` as integer.
  - File: `app/Http/Controllers/Admin/CampaignChangeController.php:168-175`

---

## 🔴 P0 — Chunk 3 Reviewer (Creatives / Reporting / Exports)

- [ ] **REV3-C1: Delete creative files from disk when Creative is deleted** — DB cascade removes `creative_files` rows without firing Eloquent events, leaking blobs on the `creatives` disk forever. Add a `deleting` observer on `Creative` that loops `$creative->files` and calls `Storage::disk('creatives')->delete($f->path)` before the row is removed.
  - Files: `app/Observers/CreativeObserver.php`, `app/Http/Controllers/CreativeController.php:64-73`, `database/migrations/2026_02_02_110141_create_creative_files_table.php:16`

- [ ] **REV3-C2: Centralise CreativeFile disk deletion in the observer** — `CreativeFileObserver::deleted` only logs activity; disk deletion is inlined in `CreativeController::deleteFile` and `::upload`. Move `Storage::disk('creatives')->delete($file->path)` into `CreativeFileObserver::deleting()` and remove duplicated calls from the controller.
  - File: `app/Observers/CreativeFileObserver.php:44-52`

- [ ] **REV3-C3: Defence-in-depth on preview/download file routes** — `{file}` model binding has no tenant scoping; relies entirely on `CampaignPolicy::view`. Add explicit `client_id` membership assertion in `CreativeController::preview` and `::downloadFile` so a regressed policy cannot enable global file enumeration.
  - File: `app/Http/Controllers/CreativeController.php:176-206`

- [ ] **REV3-C4: Report cache version invalidation is driver-dependent** — `Cache::increment("report_version_{$id}")` silently fails on file/array drivers if the key was never seeded. First-ever `ReportImportService::import` may not invalidate the cached summary. Either seed with `Cache::add(..., 0)` or store the version on the `Campaign` model.
  - Files: `app/Services/ReportImportService.php:199-202`, `app/Http/Controllers/ReportApiController.php:15-18`

- [ ] **REV3-H5: Gate `TokenController` behind permission + rate limiter** — Any authenticated user (including Viewers) can mint `reports:read` Sanctum tokens with no permission check or throttle. Add `can:manage-tokens` middleware (or `hasPermission('is_report')`) and a rate limiter on `tokens.create`.
  - File: `app/Http/Controllers/TokenController.php`, `routes/web.php:121-124`

- [ ] **REV3-H6: Cap token extension lifetime and audit it** — `extend()` lets owners push expiry by 30 days indefinitely with no log. Cap total lifetime (e.g., created_at + 180 days), log `token.extended` via ActivityLogger, make TTL configurable.
  - File: `app/Http/Controllers/TokenController.php:38-46`

See full findings: `docs/audits/chunk3-reviewer.md`.

---

## 🟠 P1 — High Security + Architecture

- [x] **ARCH-H1: Implement agency-based tenant scoping** — `agency_user` pivot exists but no controller or policy queries it. Create `User::accessibleClientIds()` that unions `client_user` + clients from `agency_user` agencies. Replace all `$user->clients->contains(...)` checks.
  - Files: All controllers + policies that scope by user

- [x] **SEC-H2: Add authorization gate to Campaign index** — `index()` has no `$this->authorize('viewAny', Campaign::class)`. Update `CampaignPolicy::viewAny()` to return true for users with proper permissions.
  - File: `app/Http/Controllers/CampaignController.php:25`

- [x] **SEC-H4: Add privilege-escalation prevention to RoleController** — Admins can create/edit roles with `is_admin` without restriction. Validate every permission being granted is also held by `auth()->user()`.
  - File: `app/Http/Controllers/Admin/RoleController.php`

- [x] **SEC-H5: Replace `env()` with `config()` in AI controllers** — `env()` returns null after `config:cache`. Add `'anthropic' => ['api_key' => env('ANTHROPIC_API_KEY')]` to `config/services.php`.
  - Files: `AiLocationController.php:15`, `CampaignAssistantController.php:31`

- [ ] **SEC-H6: Remove staging DB password from MEMORY.md** — Plaintext password in a synced file.
  - File: `.claude/projects/-Users-mg-projects-maddata-simple/memory/MEMORY.md`

- [x] **REV-2: Move `clients` resource route under admin middleware** — Currently exposed outside `/admin/*` prefix. `clients.show` has no controller method (would 500).
  - File: `routes/web.php:35`

- [x] **REV-15: Move `users` resource route under admin middleware** — Same issue as clients — inside `auth` group but not under admin prefix.
  - File: `routes/web.php:23-24`

---

## 🟠 P1 — High Performance

- [x] **PERF-C1: Consolidate DashboardController queries** — 8-10 redundant queries per page load. Merge into 1-2 queries using `selectRaw(SUM(...))` for aggregates. Extract `CampaignMetricsService` shared by `show()` and `exportExcel()`.
  - File: `app/Http/Controllers/DashboardController.php`

- [x] **PERF-C3: Fix N+1 on `User::hasPermission()` via `userRole`** — Lazy-loads role on every call (2-10+ per request). Add `protected $with = ['userRole']` on User model or load in auth middleware.
  - File: `app/Models/User.php:55-66`

- [x] **PERF-C4: Optimize `allowedCampaignIds()` — replace nested N+1** — Replace `clients()->with('campaigns')->get()->flatMap()` with single query: `Campaign::whereIn('client_id', $user->clients()->pluck('clients.id'))->pluck('id')`.
  - Files: `CampaignChangeController.php:22-29`, `ActivityLogController.php:30-35`

- [x] **PERF-H1: Add missing database indexes** — Create migration with composite indexes for `placements_data(campaign_id, report_date)`, `activity_logs(campaign_id, status)`, `campaigns(status)`, `campaigns(client_id, status)`, `audiences(is_active)`, `users(role_id)`.

- [x] **PERF-H4: Add pagination to campaigns index** — Currently loads ALL campaigns into memory with `->get()`.
  - File: `CampaignController::index()` line 42

- [x] **PERF-H5: Add pagination to Report API campaigns endpoint** — Returns all campaigns as JSON with no limit.
  - File: `ReportApiController::campaigns()` line 199

- [x] **PERF-H7: Add timeout to Anthropic API calls** — Synchronous calls block PHP workers 2-10+ seconds. Add `->timeout(15)` minimum.
  - Files: `CampaignAssistantController.php`, `AiLocationController.php`

---

## 🟡 P2 — Medium Security

- [x] **SEC-M1: Strengthen password policy on UserController** — Minimum 6 chars, no complexity. Use `Rules\Password::defaults()` or `Password::min(8)->mixedCase()->numbers()`.
  - File: `app/Http/Controllers/UserController.php:40`

- [x] **SEC-M3: Replace `{!! $tabLabel !!}` with escaped output** — Latent XSS vector.
  - File: `resources/views/components/campaign/targeting-accordion.blade.php:125`

- [x] **SEC-M4: Replace `{!! json_encode() !!}` with `@js()` for chart data** — Safer escaping.
  - File: `resources/views/dashboard/index.blade.php:607-624`

- [x] **SEC-M5: Serve creative files from private disk with auth** — Currently on `public` disk, accessible without authentication.
  - File: `app/Http/Controllers/Admin/CampaignChangeController.php:115-119`

- [x] **SEC-M6: Add rate limiting to AI endpoints** — No throttle on expensive Anthropic API calls.
  - File: `routes/web.php:14-15`

- [x] **SEC-M7: Add token scoping to Report API** — Any valid Sanctum token accesses all endpoints.
  - File: `app/Http/Controllers/ReportApiController.php`

---

## 🟡 P2 — Medium Code Quality

- [x] **REV-7: Extract `CampaignMetricsService` from DashboardController** — `show()` is ~120 lines, `exportExcel()` duplicates logic. Delete unused `calculateSummary()`.
  - File: `app/Http/Controllers/DashboardController.php`

- [x] **REV-8: Extract `ReportImportService` from CampaignController::upload()** — 165 lines of Excel parsing, data transformation, and DB operations.
  - File: `app/Http/Controllers/CampaignController.php:143-307`

- [x] **REV-9: Create Form Requests for ClientController, UserController, AgencyController, RoleController** — All use inline `$request->validate()` instead of dedicated Form Requests.

- [x] **REV-10: Replace `addslashes()` with `@js()` in Blade templates** — Unsafe for edge cases (backticks, Unicode).
  - Files: `clients/index`, `agencies/index`, `users/edit`, etc.

- [x] **REV-4: Remove commented-out debug helpers** — `// dd()` and `// dump()` left in controllers.
  - Files: `CampaignController.php:270`, `ClientController.php:19`

- [x] **REV-12: Fix duplicate `Notifiable` trait in User model** — Used twice.
  - File: `app/Models/User.php`

- [x] **REV-13: Add missing casts to Campaign model** — `is_video` should be `boolean`, `budget` should be `decimal`.
  - File: `app/Models/Campaign.php`

---

## 🟡 P2 — Medium Performance

- [x] **PERF-M1: Cache `Client::all()` for form dropdowns** — Called on every campaign/user form page. Use `Cache::remember('clients_list', 300, ...)`.

- [x] **PERF-M2: Cache active audiences list** — Loaded on every AI chat message. Use `Cache::remember('active_audiences', 3600, ...)`.

- [x] **PERF-M4: Bulk insert PlacementData in upload** — Currently inserted one-by-one in a loop. Use `PlacementData::insert($rows)`.
  - File: `CampaignController::upload()` lines 223-261

- [x] **PERF-M5: Add response caching to Report API** — Data changes only on report upload. Cache with campaign-specific key, invalidate on upload.

---

## 🟢 P3 — Quick Wins & Cleanup

- [x] **PERF-QW1: Use route model binding in DashboardController** — Change `show()` to `show(Campaign $campaign)`.
- [x] **REV-14: Fix CampaignChangeController using wrong storage disk** — Already fixed in a previous pass (uses `creatives` disk).
- [x] **REV-16: Remove redundant `->middleware('auth')` on routes inside auth group** — Harmless but noisy.
- [x] **SEC-L4: Add global Content-Security-Policy header via middleware**
- [x] **SEC-L5: Add audit logging for role changes** — Changes to RBAC not logged.
- [x] **SEC-L6: Add size limit to zip downloads** — Prevent memory exhaustion from large creative sets.

---

## 🧪 Testing Gaps

- [x] **Write Pest tests for Agency feature** — CRUD, data migration command, agency-client relationships, factory behavior.
- [x] **Write RBAC boundary tests** — Verify users cannot access data outside their assigned agencies/clients.
- [x] **Test privilege escalation prevention** — After implementing SEC-C2/H4.

---

## Stats

| Priority | Count |
|----------|-------|
| P0 Critical Security | 4 |
| P0 Chunk 3 Reviewer | 6 |
| P1 High Security/Arch | 7 |
| P1 High Performance | 7 |
| P2 Medium Security | 6 |
| P2 Medium Code Quality | 7 |
| P2 Medium Performance | 4 |
| P3 Quick Wins | 6 |
| Testing Gaps | 3 |
| **Total** | **50** |

---

## HOTFIX — AI Campaign Assistant: Cities Not Applied (CSP + Observability)
**Spec:** [ai-campaign-assistant-cities-fix.md](../specs/ai-campaign-assistant-cities-fix.md)
**Incident:** Production user asked the assistant to add five Israeli cities to a campaign's geographic targeting. The assistant replied "done" but no chips appeared and `Save Changes` stayed disabled. Console showed CSP `connect-src` violations on three calls to `https://countriesnow.space/...` from the geo accordion's init.
**Root cause:** The targeting accordion fetches country/region/city reference lists directly from `countriesnow.space`, but production CSP does not allow that origin. The whole chain is also unlogged, which is why the failure was invisible until reproduced manually.
**Decision:** Proxy the geo lists through a Laravel `/api/geo/*` controller (cached server-side), keep CSP tight, and add structured logging across the assistant chain plus an `ActivityLog` entry for `targeting_rules` changes.
**Priority:** P1 — production user-visible bug, affects the headline AI feature.

### Tasks (builder sequence)

- [x] **0. Add dedicated `ai` log channel in `config/logging.php`**
  New channel: `'ai' => ['driver' => 'daily', 'path' => storage_path('logs/ai.log'), 'level' => 'info', 'days' => 30, 'replace_placeholders' => true]`. All assistant + geo-fallback logging in later tasks targets this channel via `Log::channel('ai')->...`.

- [x] **1. Bundle static geo fallback dataset under `storage/app/geo/`**
  Create `storage/app/geo/countries.json` (string array of country names — snapshot from `countriesnow.space`).
  Create `storage/app/geo/regions/israel.json` and `storage/app/geo/regions/united-states.json` (string arrays).
  Create `storage/app/geo/cities/israel.json` containing **each Israeli city as both Hebrew and English entries** in the same flat string array (e.g. `["Tel Aviv", "תל אביב", "Holon", "חולון", "Bat Yam", "בת ים", ...]`). Source the Hebrew names from a public list (Wikipedia / data.gov.il); commit the JSON file directly so there is no runtime dependency.
  Create `storage/app/geo/cities/united-states.json` (English only). Other countries can be added later — missing files mean the static fallback returns `[]` for that country.

- [x] **2. Create `app/Services/GeoReferenceService.php`**
  Three public methods: `countries(): array`, `regions(string $country): array`, `cities(string $country): array`. Each wraps `Cache::remember('geo:...', now()->addDays(7), fn() => $this->resolve(...))`. Resolution order inside `resolve()`: (a) attempt upstream `Http::timeout(5)->...` to `countriesnow.space`; (b) on exception, non-2xx, or empty `data` array, fall back to reading the corresponding file from `storage/app/geo/`; (c) if the static file is also missing, return `[]`. Whenever the static fallback is used, `Log::channel('ai')->warning('geo.fallback_used', ['endpoint' => ..., 'country' => ...])`. Country-name → filename slugging via `Str::slug($country)` so `"United States"` → `united-states.json`.

- [x] **3. Create `app/Http/Controllers/GeoReferenceController.php`**
  Constructor-injects `GeoReferenceService`. Three actions: `countries()`, `regions(Request $request)`, `cities(Request $request)` — each returns `response()->json(['data' => [...]])`. Validate `country` query param (`required|string|max:100`) on the regions/cities endpoints.

- [x] **4. Register routes in `routes/web.php`**
  Inside the existing `auth`-protected group, add three GET routes: `/api/geo/countries`, `/api/geo/regions`, `/api/geo/cities`, all pointing at `GeoReferenceController`. Named: `geo.countries`, `geo.regions`, `geo.cities`. **Do NOT** put these under the Sanctum `/api/reports` group — they are called from authenticated browser sessions on the campaign edit page.

- [x] **5. Update `resources/views/campaigns/edit.blade.php` (lines 315-338)**
  Replace `fetch('https://countriesnow.space/api/v0.1/countries/iso')` → `fetch('/api/geo/countries')`. Replace the two POST calls in `loadGeoData()` with GET calls to `/api/geo/regions?country=...` and `/api/geo/cities?country=...` (URL-encode the country name). Adjust the response-shape parsing — backend now returns `{ data: string[] }` flat, so the existing `data.data.map(c => c.name)` becomes `data.data`. The autocomplete's existing `filter(c => c.toLowerCase().includes(q))` ([edit.blade.php:228](../../resources/views/campaigns/edit.blade.php#L228)) already supports any-script substring match, so Hebrew search "חול" will find "חולון" and English "Hol" will find "Holon" without further changes — both entries are in the same `geoCitiesList` array.

- [x] **6. Add structured logging to `CampaignAssistantController::chat`**
  At the top of `chat()`, `Log::channel('ai')->info('assistant.request', ['user_id' => auth()->id(), 'message_count' => count($validated['chatHistory']), 'last_user_message_length' => ...])`. After parsing the LLM response, `Log::channel('ai')->info('assistant.response', ['user_id' => auth()->id(), 'reply_length' => strlen($parsed['reply'] ?? ''), 'updates_keys' => array_keys($parsed['updates'] ?? []), 'raw_text_length' => strlen($text)])`. **Do not log raw user message content or LLM reply bodies** — keys/lengths only, to keep PII out of logs. Wrap the Anthropic HTTP call in try/catch; on exception `Log::channel('ai')->error(...)` and return a 502 JSON error.

- [x] **7. Log `targeting_rules` diffs in `CampaignController::update`**
  After `$campaign->update($validated)` (line ~268), if `$validated['targeting_rules'] ?? null` differs from the original campaign's `targeting_rules`, call `ActivityLogger::log(...)` with action `targeting_updated`, `campaign_id`, and a `metadata` payload containing `before` and `after` arrays. Mirror the pattern already used for `campaign_locations` at lines 287-299.

- [x] **8. Write `tests/Feature/GeoReferenceControllerTest.php`**
  - Unauthenticated request → 302 (web auth redirect).
  - Authenticated `GET /api/geo/countries` returns `{ data: [...] }` with at least one entry. Mock `Http::fake([...])` so tests don't hit the network.
  - Second call within the same test hits the cache (assert `Http::assertSentCount(1)`).
  - Upstream returns 500 → endpoint returns 200 with the **static fallback** payload (assert `'Israel'` is in the countries list, since it's in `storage/app/geo/countries.json`) and writes `geo.fallback_used` to the `ai` channel.
  - `GET /api/geo/cities?country=Israel` with upstream faked to fail → response contains both `'Holon'` and `'חולון'` (proves the bilingual static fallback works).
  - `regions` / `cities` without `country` → 422.

- [x] **9. Write `tests/Feature/CampaignAssistantLoggingTest.php`**
  - `Log::channel('ai')` spy + POST to `/ai/campaign-assistant` with `Http::fake([...])` returning a canned Anthropic response containing `updates.cities`. Assert the `ai` channel received `assistant.request` and `assistant.response` with `updates_keys` containing `'cities'`.
  - LLM returns malformed JSON → endpoint returns 502, logs `warning` to `ai` channel.
  - Anthropic call throws → endpoint returns 502, logs `error` to `ai` channel.

- [x] **10. Manual production-style verification on staging**
  Deploy to staging (`scripts/deploy-staging.sh`). On the campaign edit page: DevTools → Network shows `/api/geo/*` 200s, no CSP violations in console. Trigger the AI assistant with the Hebrew cities prompt from the bug report (`תוסיף לי טרגוט מיקום גאוגרפי בערים חולון, בת ים, ראשון לציון, רמלה, לוד`). Confirm Hebrew chips appear, Save enables, and the `activity_logs` row appears after Save with both Hebrew strings in the `after.cities` payload. Then add an English city ("Tel Aviv") via the autocomplete and confirm both languages coexist in the same campaign.

- [x] **11. Run full suite and Pint**
  `composer run test` → green. `./vendor/bin/pint` → no diffs. Update `docs/architecture_map.md` with the new `GeoReferenceController`, `GeoReferenceService`, and the `ai` log channel.

---

## Google SSO + TOTP Hybrid Authentication

**Spec:** [google-sso-totp-hybrid.md](../specs/google-sso-totp-hybrid.md)
**Goal:** Add Google OAuth as an additional login method, gate TOTP behind a role policy so viewers can run on SSO alone while admins/editors keep the second factor.
**Priority:** P2 — UX win for viewers, no production fire.
**Feature flag:** `config('auth.google_sso_enabled')` defaults `false`; flip per-environment.

### Tasks (builder sequence)

- [x] **1. Composer dependency**
  `composer require laravel/socialite`. Verify the package's service provider auto-registers in Laravel 12 (no manual provider/aliases edits needed).

- [x] **2. Migration: `add_google_sso_columns_to_users_table`**
  Adds `google_sub VARCHAR(255) NULL UNIQUE`, `google_email VARCHAR(255) NULL`, `google_linked_at TIMESTAMP NULL`. Does NOT touch `google2fa_secret`. Down-migration drops the three columns. Run `php artisan migrate` and confirm the table shape with `php artisan tinker` `\DB::select('describe users')`.

- [x] **3. Update `App\Models\User`**
  Add the three new columns to `$fillable`. Cast `google_linked_at` to `datetime`. Add two accessors: `hasTotpEnrolled(): bool` (non-empty `google2fa_secret`), `hasGoogleLinked(): bool` (non-empty `google_sub`). Pure functions — no DB queries. **No role-based TOTP accessor.**

- [x] **4. Add config keys**
  In `config/auth.php` add `'google_sso_enabled' => env('GOOGLE_SSO_ENABLED', false)`. In `config/services.php` add the `'google'` block (`client_id`, `client_secret`, `redirect`). Update `.env.example` accordingly. **Do NOT add `totp_required_permissions`** — TOTP is now enforced per login method, not per role.

- [x] **5. Service: `App\Services\Auth\SsoLinkService`**
  Methods per spec. `resolveLogin()` queries by `google_sub` first; if no match, queries by Google email and throws `EMAIL_MATCH_NO_LINK` when one exists; throws `NO_USER_FOUND` otherwise; throws `USER_INACTIVE` if `is_active=false`. `link()` writes `google_sub`, `google_email`, `google_linked_at` and calls `ActivityLogger::log('sso.linked', $user, ...)`. `unlink()` nulls them and logs `sso.unlinked`. No password verification inside the service — controllers verify before calling.

- [x] **6. Controller: `App\Http\Controllers\Auth\GoogleSsoController`**
  `redirect()` returns `Socialite::driver('google')->redirect()`. `callback()` retrieves the SocialiteUser, distinguishes login vs link based on a signed `state` query (login = no state, link = `state=link:{userId}` HMAC-signed with `app.key`). On login: calls `SsoLinkService::resolveLogin()`, then `Auth::login()`, then writes `session(['login_method' => 'sso', '2fa_verified' => true])` so the TOTP middleware passes through, then redirects to intended URL or `/dashboard`. On link: calls `SsoLinkService::link()` and redirects back to `/settings/sign-in-methods` with a success flash. Failure paths flash a localised message and redirect to `/login` (login flow) or `/settings/sign-in-methods` (link flow).

- [x] **7. Tag the existing password-login flow with `login_method`**
  In whichever Fortify/Breeze action currently calls `Auth::attempt()`/`Auth::login()` for the email+password form, immediately after success write `session('login_method', 'password')`. This is the signal `RequireTwoFactor` reads in step 8. Existing 2FA enrollment / challenge flow is otherwise unchanged.

- [x] **8. Controller: `App\Http\Controllers\Auth\SignInMethodsController`**
  `index()` returns the settings view with TOTP / Google statuses. `startConnectGoogle()` validates `password` against `Hash::check`, then 302s to Socialite redirect with `state=link:{userId}` HMAC-signed. `disconnectGoogle()` validates password AND `$user->hasTotpEnrolled()` (lockout invariant), then calls `SsoLinkService::unlink()`. `disableTotp()` validates password AND `$user->hasGoogleLinked()` (lockout invariant), nulls `google2fa_secret`, logs `totp.disabled` via `ActivityLogger`. Use a single `App\Http\Requests\Auth\ConfirmPasswordRequest` for the password rule. Both lockout-guard violations return 422 with a clear error message.

- [x] **9. Update `RequireTwoFactor` middleware**
  After the existing exempt-route / api-token / verified-session checks, add a fast-path: if `session('login_method') === 'sso'` → `return $next($request);` regardless of TOTP enrollment state. Everything else (no method tag, or `login_method=password`) keeps the existing behaviour — `2fa.setup` if no secret, `2fa.challenge` if secret but not verified. Legacy sessions created before deploy have no `login_method` tag and fall through to the existing password+TOTP path, which is the safe default.

- [x] **10. Routes**
  In `routes/web.php`:
  - Public auth: `Route::get('/auth/google/redirect', [GoogleSsoController::class, 'redirect'])->name('auth.google.redirect')->middleware('guest')` (only if `config('auth.google_sso_enabled')`).
  - Public auth: `Route::get('/auth/google/callback', [GoogleSsoController::class, 'callback'])->name('auth.google.callback')->middleware(['web'])` — must be reachable by both guest (login) and auth (link).
  - Authenticated: `Route::prefix('/settings/sign-in-methods')->name('settings.sign-in-methods.')->group(...)` with `index`, `start-connect-google`, `disconnect-google`, `disable-totp`. All require `auth`.

- [x] **11. Views**
  `resources/views/auth/login.blade.php` — add a "Sign in with Google" button below the password form, visible only when `config('auth.google_sso_enabled')`. `resources/views/settings/sign-in-methods.blade.php` — three rows (password, TOTP, Google) with current status and the appropriate Connect/Disconnect/Set up/Disable buttons. Lockout-invariant UX: "Disable TOTP" disabled with tooltip "Connect Google first so you don't lock yourself out" when `! hasGoogleLinked()`; "Disconnect Google" disabled with tooltip "Set up Authenticator first so you don't lock yourself out" when `! hasTotpEnrolled()`. Wire a sidebar link to `settings.sign-in-methods.index`.

- [x] **12. Tests: `tests/Feature/Auth/GoogleSsoLoginTest.php`**
  Use Socialite's testing helpers (`Socialite::shouldReceive('driver->user')->andReturn(...)`). Cases per the spec test plan: linked user (any role) logs in and lands on dashboard with no TOTP challenge; email-match-no-link blocked; no-match blocked; inactive user blocked.

- [x] **13. Tests: `tests/Feature/Auth/SignInMethodsTest.php`**
  Connect requires password; wrong password fails; disconnect requires password; **disconnecting Google when TOTP not enrolled returns 422**; **disabling TOTP when Google not linked returns 422**; each successful action writes the corresponding `ActivityLog` row.

- [x] **14. Tests: `tests/Feature/Auth/LoginMethodMiddlewareTest.php`**
  Session with `login_method=sso` and TOTP enrolled → `RequireTwoFactor` passes through (no challenge). Session with `login_method=password` and TOTP enrolled but unverified → redirects to `2fa.challenge`. Session with no `login_method` tag (legacy) and TOTP enrolled → redirects to `2fa.challenge` (safe default).

- [ ] **15. Run suite + Pint + manual verify on staging**
  `composer run test` green. `./vendor/bin/pint` no diffs. Deploy to staging with `GOOGLE_SSO_ENABLED=true` and a Google OAuth client whose redirect URI is the staging callback. Real-world walks (any role): (a) connect Google in settings, log out, log back in via Google, verify dashboard loads with no TOTP prompt; (b) disable TOTP after Google is connected, log out, log back in via Google, dashboard loads; (c) try to disconnect Google when it's the only method — verify 422; (d) try to disable TOTP when Google isn't linked — verify 422.

- [ ] **16. Production rollout**
  Provision a production Google OAuth client (separate from staging) with prod redirect URI. Set `GOOGLE_SSO_ENABLED=true` and the client secrets in prod `.env`. Deploy. Announce in the team chat / dashboard banner that anyone can now connect a Google account and skip the Authenticator app.
  → Superseded by the staged plan in [google-sso-production-rollout.md](../specs/google-sso-production-rollout.md). Use the tasks below.

---

## 🚀 Google SSO — Production Rollout (staged)

**Spec:** [google-sso-production-rollout.md](../specs/google-sso-production-rollout.md)
**Strategy:** Phase 0 (provision OAuth client, no app changes) → Phase 1 (dark deploy, flag OFF) → Phase 2 (flip flag, smoke test) → Phase 3 (announce). Rollback by flipping `GOOGLE_SSO_ENABLED=false` and re-caching config.
**Owner sequence:** humans for Phase 0 + 3, `server` agent for Phase 1 + 2 deploy steps.

### Phase 0a — Publish legal pages on maddata.media (prereq for Phase 0b)

- [ ] **R0a.1** Have an attorney review [docs/legal/privacy-policy.md](../legal/privacy-policy.md) and [docs/legal/terms-of-service.md](../legal/terms-of-service.md). Focus areas: ToS §11 (warranties), §12 (limitation of liability), §16 (governing law). The drafts are pre-flagged as starting points, not final legal copy.
- [ ] **R0a.2** Apply attorney edits. Delete the "Drafting note" block at the top of each document before publishing — those notes are for the editorial workflow, not for end users.
- [ ] **R0a.3** Publish on the marketing site at `https://maddata.media/privacy` and `https://maddata.media/terms`. Mechanics depend on the marketing-site stack (WordPress, static, etc.) — handed to the team that owns maddata.media.
- [ ] **R0a.4** Verify both URLs return 200 from a clean browser session and are crawlable (no auth wall, no `noindex`).

### Phase 0b — Provision production Google OAuth client

- [ ] **R0.1** Confirm GCP org owner (default: Michael). Get console access for whoever runs the runbook.
- [ ] **R0.2** Fill the Branding screen (currently open in console.cloud.google.com → Google Auth Platform → Branding) with the published URLs from R0a.3: `https://maddata.media/privacy` for "Application privacy policy link" and `https://maddata.media/terms` for "Application Terms of Service link". Save.
- [ ] **R0.2.1** Move the OAuth consent screen from *Testing* to *In production* via Audience → Publishing status. Confirm the warning banner ("App is in testing mode") is gone before creating the client. Required to avoid the 100-test-user cap.
- [ ] **R0.3** Create OAuth 2.0 Client ID per [§Google Cloud Console runbook](../specs/google-sso-production-rollout.md#google-cloud-console-runbook). Application type **Web**, name `MadData Production`, authorised JS origin `https://ad.maddata.media`, redirect URI exactly `https://ad.maddata.media/auth/google/callback`.
- [ ] **R0.4** Capture Client ID + Client Secret into the vault (1Password entry `MadData Prod — Google OAuth`).
- [ ] **R0.5** Sanity-check: hit the verification URL from the runbook in an unauthenticated browser → consent screen shows `MadData` and no `redirect_uri_mismatch`.
- [ ] **R0.6** Re-walk the staging matrix one more time at commit `7ed660b` to confirm: sign-out from `/2fa/challenge`; SSO-only auto-verify with loop protection; `/2fa/setup` two-card layout (TOTP collapsed). No code changes — just a verification pass before touching prod.

### Phase 1 — Dark deploy (flag OFF)

- [ ] **R1.1** SSH to prod (`root@164.90.233.136`).
- [ ] **R1.2** Edit `/var/www/maddata/.env`. Add `GOOGLE_SSO_ENABLED=false`, `GOOGLE_CLIENT_ID={prod_id}`, `GOOGLE_CLIENT_SECRET={prod_secret}`, `GOOGLE_REDIRECT_URI=https://ad.maddata.media/auth/google/callback`. Confirm the four lines are present and correctly quoted (no spaces around `=`).
- [ ] **R1.3** `cd /var/www/maddata && git pull origin main`. Confirm HEAD includes commit `7ed660b` or later.
- [ ] **R1.4** `composer install --no-dev --optimize-autoloader`.
- [ ] **R1.5** `npm ci && npm run build` (only if any frontend assets changed; check `git diff --name-only HEAD@{1}` before running to skip when nothing in `resources/` changed).
- [ ] **R1.6** `php artisan migrate --force`. Expect one new migration: `2026_05_04_142828_add_google_sso_columns_to_users_table`. Confirm with `php artisan migrate:status`.
- [ ] **R1.7** `php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan view:clear` then `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
- [ ] **R1.8** `systemctl reload php8.4-fpm`. (Confirm `nginx` is the front; per memory the new prod is Nginx + FPM, not Apache + mod_php like the old droplet.)
- [ ] **R1.9** Verification (Phase 1 checks from spec): migrate-status check, `tinker` describe-users check, normal login round-trip, `2fa.setup` shows TOTP-only, settings page has no Google row, `storage/logs/laravel.log` clean. **All six must pass before Phase 2.**

### Phase 2 — Flip flag, smoke test

- [ ] **R2.1** Edit `/var/www/maddata/.env`: change `GOOGLE_SSO_ENABLED=false` → `GOOGLE_SSO_ENABLED=true`.
- [ ] **R2.2** `php artisan config:clear && php artisan config:cache`.
- [ ] **R2.3** `systemctl reload php8.4-fpm`.
- [ ] **R2.4** Verification (Phase 2 checks from spec): Google row visible in settings; admin Connect Google → link works; logout → re-login → 2FA challenge shows TOTP + "Or verify with Google"; new test user picks Google in `2fa.setup` → links → dashboard; lockout invariants enforced; no Cloudflare interstitial in browser network logs; no `InvalidStateException` in `laravel.log`.
- [ ] **R2.5** If any check fails: `GOOGLE_SSO_ENABLED=false`, re-cache config, reload FPM. Triage offline — do not iterate on prod.

### Phase 3 — Announce

- [ ] **R3.1** Decide announcement channel (open question #2 in spec). Default: Slack message to the team plus a one-line dashboard banner gated by per-user dismiss. If the dashboard banner is wanted, it's a small follow-up spec — *do not bundle with the deploy*.
- [ ] **R3.2** Post the announcement: "You can now connect a Google account in Settings → Sign-in methods. After connecting you can use Google instead of (or alongside) the Authenticator app."

### Phase 4 — Cleanup window (defer, separate spec)

- [ ] **R4.1** After 1 week of stable prod usage, file a follow-up spec covering: delete `SsoLinkService::resolveLogin()`, expand the `block_google_auto_verify` one-shot pattern comment, fix the `composer run test` zip-stream OOM. None block the rollout.

---

## 🔍 SSO Rollout — Per-User Authentication Verification

**Spec:** [sso-rollout-user-verification.md](../specs/sso-rollout-user-verification.md)
**Complements:** the rollout spec above — slots into the gap between Phase 1 (dark deploy) and Phase 3 (announce).
**Goal:** Confirm every active prod user can authenticate after the SSO flag flip, and pre-resolve anyone who can't.
**Trigger:** Google production branding verified 2026-05-12 — branding-side blocker is now clear.

### 🔧 SSO Verify — Flash + login_hint Fix (blocks V0 completion)

**Spec:** [sso-verify-flash-and-hint-fix.md](../specs/sso-verify-flash-and-hint-fix.md)
**Found by:** V0 walkthrough on 2026-05-12 — sub-mismatch loop with no visible error.
**Why this blocks V0:** With these bugs in place we can't reliably tell whether a verify failure is a real bug or a user picking the wrong Gmail. Fix first, then resume V0.

#### Operational unstick — the test user's `google_sub` is currently stale

The staging test user is linked to a Gmail that's no longer reachable in the browser, so every Verify-with-Google attempt sub-mismatches. Recover before continuing:

- [ ] **F0** Either (a) submit the **TOTP code** on the same `/2fa/challenge` page (the form is still rendered above the Verify button — TOTP works regardless of the Google link state), then go to Settings → Sign-in methods → Disconnect Google, then re-link with the Gmail you actually want to use; **or** (b) if TOTP isn't available, SSH to staging and run in tinker: `\App\Models\User::where('email', 'TEST_USER_EMAIL')->first()->update(['google_sub' => null, 'google_email' => null, 'google_linked_at' => null]);` Then log in normally and re-link from Settings.

#### Code fix (hand to builder)

- [x] **F1** Edit `resources/views/auth/2fa-challenge.blade.php`: add a `@if (session('error'))` banner and a `@if (session('success'))` banner above the header block, inside the existing outer wrapper so both the TOTP and Google-only branches see it. Mirror the markup pattern from the existing throttle banner (same palette / spacing / icon set). See spec §1 for the contract.
- [x] **F1b** In the same file: update **both** Verify-with-Google button bodies (one inside the TOTP-enrolled branch ~L106-118, one inside the Google-only branch ~L134-145) so the label reads `Verify with Google (j***@gmail.com)` using a masked version of `auth()->user()->google_email`. Format: first char of local part + `***` + `@domain.tld`. Fall back to plain `Verify with Google` if `google_email` is null. See spec §3.
- [x] **F2** Edit `app/Http/Controllers/Auth/TwoFactorController.php::startGoogleVerify`: read `$request->user()->google_email`, and if set, call `Socialite::driver('google')->with(['login_hint' => $email])->redirect()` instead of the bare redirect. Fall back to the bare redirect when `google_email` is null. **Do not** touch `startGoogleSetup` — the hint is verify-only by design. See spec §2 for the contract.
- [x] **F3** Write `tests/Feature/Auth/TwoFactorChallengeFlashTest.php` (or similarly named) with the four scenarios from spec §Tests, **plus** a fifth: render `/2fa/challenge` for a cell-C user with `google_email='joe.smith@gmail.com'` and assert the response contains `j***@gmail.com` and does NOT contain the unmasked address. Use Socialite's partial-mock pattern for F3 #3/#4 — the existing `tests/Feature/Auth/GoogleSsoLoginTest.php` is the reference for how this codebase mocks Socialite.
- [x] **F4** Run `composer run test` (or `php -d memory_limit=256M vendor/bin/pest` if the suite OOMs per the parked issue). All previous tests green, four new tests green. Run `./vendor/bin/pint` — no diffs.
- [ ] **F5** Deploy to staging via `git push origin main:staging` + the code-only deploy sequence from memory (`git pull && composer install --no-dev --optimize-autoloader && php artisan config:clear && cache:clear && route:clear && view:clear`).
- [ ] **F6** Re-walk the sub-mismatch path on staging to confirm the visible error: deliberately link the test user to Gmail A, sign out of A in the browser, sign in to Gmail B, click Verify with Google. Expect Google to prompt for Gmail A (because of `login_hint`); if you ignore the hint and pick B, expect `/2fa/challenge` to render the red flash banner reading "The Google account used does not match the one linked to your MadData account."
- [x] **F7** Update `docs/architecture_map.md` if the auth section references the challenge view or `startGoogleVerify` — note the `login_hint` behaviour in one line.

#### Resume V0 after F6 is green

The original V0.1–V0.6 tasks below pick up unchanged once F6 confirms the fix. V0 is the *clean* walkthrough — `login_hint` should mean we never trigger the mismatch path during V0 at all.

---

### Checkpoint 0 — Staging smoke test with a fresh Gmail (do this first, today)

- [ ] **V0.1** Pick a Gmail address that was **never** on the Cloud Console → Audience → Test users list. A personal or throwaway Gmail is fine.
- [ ] **V0.2** From a clean browser session (incognito or different profile, signed out of any MadData account): log in to staging (`https://msdev.maddata.media`) as an existing cell-A user (TOTP-enrolled, no Google link). Verify TOTP. Land on dashboard.
- [ ] **V0.3** Settings → Sign-in methods → Connect Google → password confirm → Google consent screen. **Confirm:** app name is `MadData`, no "unverified app" warning, scopes are `Your email address` + `Your personal info` only. Approve. Return to MadData with success flash. In tinker on staging: `\App\Models\User::where('email', '...')->first()->google_sub` is populated.
- [ ] **V0.4** Log out. Log back in with email+password. Expect server-side auto-redirect to Google (no click). Expect dashboard.
- [ ] **V0.5** Independent unauthenticated check (no MadData session): hit `https://accounts.google.com/o/oauth2/v2/auth?client_id={staging_client_id}&redirect_uri=https%3A%2F%2Fmsdev.maddata.media%2Fauth%2Fgoogle%2Fcallback&response_type=code&scope=openid+email+profile` with a never-whitelisted Gmail. Consent screen renders cleanly, no warning. Cancel out — we don't need to complete this flow, only verify the screen.
- [ ] **V0.6** If any of V0.1–V0.5 fails: **stop**. Triage on staging before scheduling the prod rollout. Capture the failure mode in the runbook from V3.

### Pre-Phase-2 (do these between Phase 1 deploy and Phase 2 flag flip)

- [x] **V1** ~~Open Cloud Console → Audience → Publishing status. Confirm "In production".~~ Confirmed 2026-05-12 — screenshot in conversation. The "4 / 100 user cap" counter on that page is informational only; Google's docs explicitly state the cap doesn't apply to verified apps using approved scopes (`openid email profile`).
- [ ] **V2** Sanity-check the **production** OAuth client's consent screen from an incognito browser session, after rollout-spec R0.3 provisions it. Use a Google account that is NOT in any test-users list. Expect MadData branding, no unverified-app warning. (Staging-side is covered by V0.5.)
- [ ] **V3** Census active users on prod via `php artisan tinker` (snippet in spec §Checkpoint 1). Capture the output into `docs/runbooks/sso-rollout-user-census.md`. Working assumption: every `is_active=true` row has `totp_enrolled=true`. If the query surfaces any exceptions, note their IDs in the runbook so Michael / Eran know whose "what's this setup screen?" message to expect. **No outbound notifications** — per decision #2 in the spec.
- [ ] **V7** Provision a sentinel test user on production: email `qa@maddata.media`, `is_active=true`, no role assignment, no `client_user` / `agency_user` attachments, password stored in the `MadData Prod — Google OAuth` vault entry. Used only for Checkpoint 2 walks. Per decision #3, this account is **disabled** at the end of Checkpoint 2 — see V_disable below.

### Phase 2 walks (run within ~10 minutes of the flag flip)

- [ ] **V8** Sentinel walk: cell D → cell B. Log in with email+password → expect `/2fa/setup` two-card view → pick Google card → consent screen → callback → dashboard. Verify in tinker that `users.where('email', 'qa@maddata.media')->first()->google_sub` is populated. Verify `users.google_email` and `google_linked_at` are also set.
- [ ] **V9** Sentinel walk: cell B → cell B (auto-verify). Log out from V8 state → log in with email+password again → expect immediate server-side redirect to Google (no click) → expect dashboard. Confirm no `2fa.challenge` view ever rendered (check `laravel.log` if uncertain).
- [ ] **V10** Sentinel walk: cell B → cell C (add TOTP). From V9, go to Settings → Sign-in methods → "Set up Authenticator". Scan QR with a real authenticator app, confirm code. Verify both factors are now active in Settings.
- [ ] **V11** Sentinel walk: cell C choosing TOTP. Log out → log in → expect `/2fa/challenge` with TOTP input AND "Or with Google" button visible → submit TOTP → dashboard.
- [ ] **V12** Sentinel walk: cell C choosing Google. Log out → log in → expect same `/2fa/challenge` → click "Or with Google" → consent (auto-approves since already linked) → dashboard.
- [ ] **V13** Sentinel walk: cell C → cell A (disconnect Google). Settings → Disconnect Google (password confirm) → expect dashboard with success flash. Verify in tinker that `google_sub` is now NULL. Verify Disconnect-Google button is now gone from Settings.
- [ ] **V14** Sentinel walk: lockout invariant — cell A. From V13, try Settings → Disable TOTP. Expect 422 + tooltip "Connect Google first so you don't lock yourself out". Re-link Google via the Connect flow before continuing.
- [ ] **V15** Sentinel walk: lockout invariant — cell B. Disable TOTP (now allowed because Google is linked again) → try Settings → Disconnect Google. Expect 422 + tooltip "Set up Authenticator first so you don't lock yourself out".
- [ ] **V16** Real-user walk (one consenting cell-A user). Ask Michael or Eran (or another cell-A volunteer) to log in normally. Confirm `/2fa/challenge` shows TOTP input plus the discoverability hint card from commit `f7b8da2`. Confirm TOTP code accepts and dashboard renders. **Do not** ask them to connect Google — they decide on their own time.
- [ ] **V_disable** After V8–V16 are all green, disable the sentinel account: `\App\Models\User::where('email', 'qa@maddata.media')->update(['is_active' => false]);`. Confirm in tinker that `is_active=false` and that the account no longer appears in the admin user list. Per decision #3 in the spec — the sentinel is a debug-only account, not a permanent fixture.

### Post-flip monitoring (first 24 hours)

- [ ] **V17** Tail `storage/logs/laravel.log` once per hour for the first 6 hours. Flag any `Socialite\Two\InvalidStateException`, `auth.failed`, or generic 500 from any `auth.google.*` route. Any single occurrence → investigate same-hour.
- [ ] **V18** At T+24h, count `ActivityLog` rows with action `sso.linked` to gauge adoption. Capture the count in the rollout runbook.
- [ ] **V19** At T+24h, count `users.where('is_active', true)->whereNotNull('google_sub')` for the same purpose. Should match V18 within rounding.

### Day-7 + ongoing

- [ ] **V20** At Phase 2 + 7 days, re-run the census from V3 and diff against the original list. **Not an adoption check** — SSO is not a target (decision #4). The diff exists to confirm the link feature is reachable: any non-zero `google_sub IS NOT NULL` count = working. Zero count = check the Connect-Google button isn't broken before assuming "nobody wanted it".
- [ ] **V21** Confirm Michael / Eran haven't been asked to help recover anyone in person beyond what was expected from the V3 census. Per decision #2, recovery happens in person on demand — there's no notification channel to scan. Any recovery action that did happen should have left an `auth.recovered_by_admin` ActivityLog row (see V22 runbook); spot-check the table.

### Operational

- [ ] **V22** Write `docs/runbooks/sso-lockout-recovery.md` from spec §Lockout recovery procedure — verbatim copy of the four steps plus the diagnose / reset / verify / log code blocks. **Add a short "Test fixtures" section** documenting the disabled sentinel account (`qa@maddata.media`, `is_active=false`, password in vault) so future ops know it's available to re-enable for auth-related deploy smoke tests. This is the runbook the recovery owner reads at 2am, not this architect spec. Commit alongside the spec.

---

## 🌐 Marketing Site Rebuild (maddata.media off WordPress)

**Spec:** [marketing-site-rebuild.md](../specs/marketing-site-rebuild.md)
**Goal:** Replace WordPress at `maddata.media` with a minimal Laravel 12 app. Three pages (home, privacy, terms) + contact form. No admin, no DB. Content is git-managed markdown.
**Scope:** Separate project at `/Users/mg/projects/maddata-marketing/`, separate git repo, separate Resend API key. Zero shared code with `maddata-simple`.
**Builder hand-off:** all `M*` tasks below execute against the new `maddata-marketing` repo, NOT this repo.

### Phase 1 — Scaffold

- [ ] **M1.1** Create new GitHub repo `maddata-marketing` (private). Capture clone URL.
- [ ] **M1.2** Local: `cd ~/projects && composer create-project laravel/laravel maddata-marketing && cd maddata-marketing`. Initial commit and push to `main`.
- [ ] **M1.3** Install build deps: `npm install -D tailwindcss @tailwindcss/typography postcss autoprefixer && npx tailwindcss init -p`. Configure Tailwind to scan `resources/views/**/*.blade.php` and `resources/content/**/*.md`. Add the `@tailwindcss/typography` plugin (used for legal-page rendering).
- [ ] **M1.4** Set Tailwind theme: orange accent `#F97316`, dark navy gradient stops matching the PDF deck (e.g. `from-slate-950 via-slate-900 to-blue-950`), Inter font via `@fontsource/inter` or Google Fonts CDN.
- [ ] **M1.5** Install runtime deps: `composer require league/commonmark` (Laravel's `Str::markdown()` already wraps it but pin it explicitly), `composer require resend/resend-php` (only if needed; the Laravel framework's `resend` mailer covers it).
- [ ] **M1.6** Configure `.env`: `APP_NAME=MadData`, `APP_URL=https://maddata.media`, `MAIL_MAILER=resend`, `RESEND_KEY=...` (use the new marketing-scoped key), `MAIL_FROM_ADDRESS=hello@maddata.media`, `MAIL_FROM_NAME=MadData`.
- [ ] **M1.7** Add `.env.example` with the same keys (no real secret values), commit.
- [ ] **M1.8** Run `php artisan serve` locally; confirm Laravel default page renders. First green light.

### Phase 2 — Content infrastructure

- [ ] **M2.1** Create `resources/content/` directory.
- [ ] **M2.2** Copy the markdown source from `maddata-simple/docs/legal/privacy-policy.md` → `resources/content/privacy.md`. Strip the `> Drafting note` block at the top before committing.
- [ ] **M2.3** Same for `terms-of-service.md` → `resources/content/terms.md`.
- [ ] **M2.4** Create `resources/content/home.md` with the section copy (hero, audiences, long-tail, brand-lift, advertising tools). Pull from the current WP site + the PDF deck's framing.
- [ ] **M2.5** Create `resources/content/clients.json` — array of `{name, logo, alt}` for the client logo strip. Logos referenced from `public/images/clients/`.
- [ ] **M2.6** Create `resources/content/placements.json` — array of `{name, logo, category}` for the premium-placements grid.
- [ ] **M2.7** Drop logo image assets into `public/images/clients/` and `public/images/placements/`. Optimise SVG where possible; PNG/WebP otherwise.

### Phase 3 — Routes, controllers, views

- [ ] **M3.1** `routes/web.php` — three GET routes (`/`, `/privacy`, `/terms`) and one POST (`/contact`). Apply `throttle:5,60` to the POST.
- [ ] **M3.2** `app/Http/Controllers/HomeController.php` — `__invoke()` returns the home view with parsed `home.md` sections + `clients.json` + `placements.json` data.
- [ ] **M3.3** `app/Http/Controllers/LegalController.php` — `privacy()` and `terms()`. Each reads its markdown file, renders via `Str::markdown()`, returns a legal-page Blade view with the rendered HTML and a `lastUpdated` date pulled from a frontmatter line or git mtime.
- [ ] **M3.4** `app/Http/Controllers/ContactController.php` — `store(ContactRequest $req)`. Validates, checks honeypot, sends `ContactFormSubmission` mailable to `support@erate.co.il`, redirects back with flash. No DB write.
- [ ] **M3.5** `app/Http/Requests/ContactRequest.php` — validation rules per spec (`name`, `email`, `company` nullable, `message`, `_honeypot` empty).
- [ ] **M3.6** `app/Mail/ContactFormSubmission.php` — Mailable with subject `New MadData contact: {name}`, plain-text body containing all four fields.
- [ ] **M3.7** Layout: `resources/views/layouts/site.blade.php` — `<html lang="en">`, `<head>` with title/meta/OG tags, `<body>` with `<x-header />` slot `<x-footer />`. Tailwind classes for the dark-navy gradient background.
- [ ] **M3.8** Components: `resources/views/components/header.blade.php` (logo + minimal nav: Home, Contact), `footer.blade.php` (copyright, Privacy Policy link, Terms link), `contact-form.blade.php` (form fields + honeypot + Alpine.js submit handler).
- [ ] **M3.9** Pages: `resources/views/pages/home.blade.php` (hero, sections per content/home.md, placements grid, clients strip, contact form), `pages/privacy.blade.php` (legal layout, prose), `pages/terms.blade.php` (legal layout, prose). Use Tailwind's `prose` class from the typography plugin for the legal pages.

### Phase 4 — Tests + local verification

- [ ] **M4.1** Pest test: `tests/Feature/PageRoutesTest.php` — GET `/`, `/privacy`, `/terms` all 200, contain expected headings.
- [ ] **M4.2** Pest test: `tests/Feature/ContactFormTest.php` — valid POST sends mail (`Mail::fake()` + `assertSent`), invalid POST returns 422, honeypot-filled POST silently 302s with no mail sent, rate-limit kicks in after 5 requests/hour from same IP.
- [ ] **M4.3** Run full suite: `composer run test` green. Run Pint: `vendor/bin/pint`, no diffs.
- [ ] **M4.4** Local manual check: `npm run dev` + `php artisan serve`, walk all three pages, submit the contact form via Mailtrap (`MAIL_MAILER=log` or a dev-only Resend key) and confirm the email body looks right.

### Phase 5 — Design pass via Claude Artifacts

- [ ] **M5.1** Mock the home page in Claude (Artifacts) using the brand constraints: orange `#F97316` accent, dark navy gradient (slate-950 → slate-900 → blue-950), Inter font, English copy, sections in the order from the spec, mobile-first responsive.
- [ ] **M5.2** Iterate 2–3 rounds until the visual hierarchy feels right. Capture the final Tailwind classes.
- [ ] **M5.3** Port the mock into the real Blade templates. Keep the same content data sources (`home.md`, `clients.json`, `placements.json`); the design changes only affect markup and Tailwind classes.
- [ ] **M5.4** Re-run M4.3 — full Pest suite green, Pint clean. Local manual walkthrough.

### Phase 6 — Deploy preview vhost

- [ ] **M6.1** SSH to prod droplet. `mkdir -p /var/www/maddata-marketing && cd /var/www/maddata-marketing && git clone <repo-url> .` Set ownership, permissions, `.env`, `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, `php artisan storage:link`, `config/route/view:cache`.
- [ ] **M6.2** Add Nginx vhost for `new.maddata.media` pointing at `/var/www/maddata-marketing/public`. Reuse the existing PHP-FPM pool initially (FPM-pool split is a follow-up if attack-surface concerns escalate).
- [ ] **M6.3** Add `new.maddata.media` A record at DigitalOcean DNS pointing to `164.90.233.136`. Wait for propagation.
- [ ] **M6.4** `certbot --nginx -d new.maddata.media`. Reload nginx.
- [ ] **M6.5** Smoke-test on `https://new.maddata.media`: all three pages render, contact form delivers an email to `support@erate.co.il`, no console errors, mobile responsive looks correct.

### Phase 7 — Cutover (DNS flip)

- [ ] **M7.1** 24h before cutover: lower `maddata.media` apex DNS TTL to 60s.
- [ ] **M7.2** Snapshot the WordPress droplet in DigitalOcean (rollback insurance).
- [ ] **M7.3** Update Nginx vhost on prod to also serve `maddata.media` and `www.maddata.media` from the same `maddata-marketing` document root.
- [ ] **M7.4** Change `maddata.media` apex A record from the WP droplet IP to `164.90.233.136`. Update the `www` CNAME too.
- [ ] **M7.5** Wait for DNS propagation (~5 minutes at TTL 60s). Verify with `dig maddata.media @8.8.8.8`.
- [ ] **M7.6** `certbot --nginx -d maddata.media -d www.maddata.media`. Reload nginx.
- [ ] **M7.7** Verify `https://maddata.media/`, `/privacy`, `/terms`, contact form all work on the real domain.
- [ ] **M7.8** Confirm Google OAuth Branding screen still shows the privacy/terms URLs as valid (no change should occur — same paths). If Google re-runs verification, it should pass.

### Phase 8 — Cleanup

- [ ] **M8.1** Wait 7 days on `maddata.media` with no incidents.
- [ ] **M8.2** Delete `docs/legal/privacy-policy.md`, `terms-of-service.md`, `privacy-policy.html`, `terms-of-service.html`, `_md2html.py` from the `maddata-simple` repo. Marketing repo is now canonical for legal content. Commit message: `Remove legal drafts; canonical copies live in maddata-marketing`.
- [ ] **M8.3** Snapshot the WordPress droplet one more time, then destroy it in DigitalOcean. Cancel any WP-only DO charges.
- [ ] **M8.4** Restore the `maddata.media` apex DNS TTL to a normal value (3600).

---

## System Health Monitor
**Spec:** [system-health-monitor.md](../specs/system-health-monitor.md)
**Added:** 2026-08-19
**Context:** Production (`ad.maddata.media`, single droplet) has no health checks, no monitoring and no alerting today — only Laravel's bare `/up`. Design ported down from the erate-v2 fleet monitor, which has been in production since 2026-07-23.
**Order:** Phases are independently shippable. HM-0 first — it is 5 minutes of work and covers the one failure mode no on-droplet code can ever catch.
**Status (2026-08-19):** All four phases are **built and deployed to production** (`87354d0`). 37 checks. Full suite 807 passing.

**What the new checks found on their first production run** — all real, none caused by the deploy:
- `d1` — composer advisories against the deployed lock, **2 critical and 13 high**. Verified by hand against Packagist before being believed. `phpoffice/phpspreadsheet` 1.30.2 carries both criticals; `laravel/framework` is at 12.12.0 against advisories fixed in 12.60; `guzzlehttp/guzzle` 7.9.3 against `<7.15.2`. **This is the largest outstanding item in the project right now.**
- `d2-mysql` / `d2-redis` — MySQL 8.0.46 and Redis 7.0.15 are both past their **upstream** windows, and both are Ubuntu 24.04 `main` packages that Canonical still backports fixes into. They report WARN "plan a migration", not CRIT: the branch is frozen, the box is not exposed, and a CRIT only an OS upgrade could clear is permanent red. Took two attempts to get right — see the build notes in the spec's §10.
- `d3` — now reads **0 pending**, retiring the stuck "1 pending security update" that no action could clear.
- `d4` / `B4` — never-recorded markers. `d4` stays WARN deliberately: recording a patch run nobody performed would be a false entry in the one check that answers "when did a human last patch?".
- `X1` — expired Sanctum tokens still in the table. Housekeeping.
- `S2a`/`S2b` — job-history markers with no completions yet on the new code.

**Still outstanding, both needing droplet work:** `unattended-upgrades` (HM-4.12) and **HM-0.1, the external uptime watcher** — still the single highest-value item, because nothing running on the droplet can report that the droplet is dead.
Production reads DEGRADED / 3 failing: a pending reboot (real, needs a decision) and two scheduled-job markers that self-clear on their first run.
Remaining: `HEALTH_ALERT_RECIPIENTS` + the forced alert test (HM-2.4), and the external watcher (HM-0.1).

**Found by deploying and watching it** — all fixed and redeployed:
- H2 read a steady 100% CPU on a 98%-idle box. Production is single-core, and the facts cron sampled one second at `:00`, exactly when `schedule:run` boots PHP — it was measuring the monitoring's own overhead. Health tasks now run in-process, CPU averages over 15s, and the cron is offset by 25s.
- B2 read "dump shrank to 47% of median" because the facts cron stats the backup directory mid-write. Would have fired a false alarm every night at 03:00 once alerting was on.
- The production TLS certificate lives under `new.ad.maddata.media/`, not `ad.maddata.media/` — P2 would have been STALE forever without the crontab override.
- **Rebooting production (`cdde032`)** wiped the backup marker, which sat on tmpfs next to the facts file. B1 went CRIT "backups unverifiable" although backups were fine, and would have stayed there for 17 hours. Facts are a live sample and *should* die with a reboot; the marker is a record of a past event and must not. Now at `/var/backups/maddata/backup-last.json`.
- **The same reboot sent a genuine false alarm (`2166e7b`)** — for ~85s the wiped facts file made every host check STALE and H1 CRIT, a signature indistinguishable from the host being down. Consecutive-observation suppression could not help: the box was already in a DEGRADED episode, and escalations inside an episode alert immediately by design. H1 and `health:alert` now both read `/proc/uptime` and hold judgement for `HEALTH_BOOT_GRACE_SECONDS` (180).

### Phase 0 — External watcher (no code)

- [ ] **HM-0.1** Register `https://ad.maddata.media/up` with a free external uptime monitor (UptimeRobot / healthchecks.io / DO Monitoring), 1-minute interval, alerting to the operator's email + phone. This is the only thing that can detect a dead droplet, dead network, or dead PHP-FPM. Record the monitor name/URL in `docs/runbooks/health-monitor.md` when HM-1.13 creates it.
- [x] **HM-0.2** *(done 2026-08-19 — `maddata-queue` and `php8.4-fpm` guesses were both correct; `CACHE_STORE` is unset so it defaults to `database`, now pinned via `HEALTH_MARKER_STORE`; `QUEUE_CONNECTION=database`.)* Confirm on the prod droplet (needed verbatim by later tasks, spec open questions 2 & 3): the queue worker's **systemd unit name**, and the actual `QUEUE_CONNECTION` / `CACHE_STORE` values in `/var/www/maddata/.env`. Write the answers into the spec's §11 before starting HM-1.

### Phase 1 — Spine, host facts, core checks, CLI

- [x] **HM-1.1** `config/health.php` — node label map, every threshold from spec §3 (env-overridable), facts/marker file paths, probe URL, alert recipients, `realert_hours`. No thresholds hardcoded in check classes, ever.
- [x] **HM-1.2** `config/database.php` — add a `mysql_health` connection: clone of `mysql` plus `PDO::ATTR_TIMEOUT => 2`. Without this, a MySQL outage hangs every admin page instead of degrading the pill (erate-v2 scar tissue).
- [x] **HM-1.3** `app/Enums/HealthStatus.php` — `OK/WARN/CRIT/STALE`, `worstOf()`, `forPill()` (STALE→WARN), `colorToken()`, `label()`. Unit-tested (`tests/Unit/Health/HealthStatusTest.php`).
- [x] **HM-1.4** `app/Dtos/HealthCheckResult.php` + `app/Dtos/HealthSnapshot.php` per spec §4. Document `HealthSnapshot::toArray()` in-file as the JSON contract the UI polls. Unit test the worst-of rollup and node grouping.
- [x] **HM-1.5** `app/Services/Health/Checks/HealthCheck.php` — abstract base with `run(): array` and the `guard()` wrapper that turns any thrown probe into a CRIT result tagged to its real node. **No check class may ever throw.**
- [x] **HM-1.6** `scripts/health-facts.sh` — root cron, every minute. vmstat CPU (NOT loadavg/nproc), mem %, disk root %, systemd unit states, `/var/run/reboot-required`, `apt-check` security count, TLS `-enddate` days remaining, newest `/var/backups/maddata` dir mtime + size. Atomic write (temp + `mv`) to `/run/maddata/host-facts.json`, mode 644. Numbers and states only — no IPs, no secrets. Script itself mode 700 root.
- [x] **HM-1.7** `app/Services/Health/HostFacts.php` — the only reader of the facts file. `read(): ?array`, `ageSeconds(): ?int`. No shell execution anywhere in the app, ever.
- [x] **HM-1.8** `app/Services/Health/SystemHealthService.php` — `snapshot()` (30s cache + `Cache::lock` single-flight + no-TTL `health:snapshot:last` fallback), `refresh()`, `pillStatus()` (never throws). Pin the cache store explicitly per HM-0.2; do not inherit the default.
- [x] **HM-1.9** Check classes `HostCheck` (H1–H6), `DataStoreCheck` (D1–D3), `QueueCheck` (Q1–Q3), `SchedulerCheck` (S1–S2b), `EdgeProbeCheck` (P1–P2), `BackupCheck` (B1–B4) — spec §3 thresholds, registered in `SystemHealthService`.
- [x] **HM-1.10** `app/Jobs/QueueHeartbeatJob.php` + `app/Console/Commands/PublicProbe.php` (`health:probe`, 3s curl of `/up`, `consec_fails` tracking) + `app/Console/Commands/RefreshHealthSnapshot.php` (`health:refresh-snapshot`).
- [x] **HM-1.11** `routes/console.php` — schedule `health:refresh-snapshot`, `health:probe`, and the `QueueHeartbeatJob` dispatch everyMinute (`withoutOverlapping`), plus a `Schedule::call()` scheduler-heartbeat marker. Add one success-marker line each to `UpdateCampaignStatuses` and `SendActivityDigest` (feeds S2a/S2b) — smallest possible touch to existing commands.
- [x] **HM-1.12** `scripts/backup-production.sh` — write `/run/maddata/backup-last.json` `{ts, local_bytes, remote_ok, remote_bytes}` on completion. Feeds B1–B3. Do not change any existing backup behavior.
- [x] **HM-1.13** `app/Console/Commands/RunHealthCheck.php` — `health:check {--json} {--fail-on=warn|crit}`, human table or JSON, **exit code reflects worst status**. Plus `docs/runbooks/health-monitor.md`: tmpfs dir creation, the two crontab lines, env vars, and what each CRIT means and what to do about it.
- [x] **HM-1.14** Tests: one file per check class in `tests/Feature/Health/` covering threshold boundaries (69/70/71%), STALE and missing-marker paths; `SystemHealthServiceTest` asserting **each dependency throwing still yields a built snapshot** with a CRIT on the right node; `RunHealthCheckTest` exit codes; `PublicProbeTest` with `Http::fake()` for success/fail/timeout incl. `consec_fails` reset. No test may execute a shell command — use fixture JSON.
- [x] **HM-1.15** Deploy Phase 1 — **done 2026-08-19** (staging then production; both on `0fb7a34`). Was: Provision `/run/maddata`, install the facts script + root crontab, set the `.env` values from HM-0.2, seed the backup marker (`scripts/backup-production.sh` once — until it runs, B1 correctly reads CRIT), record the 2026-07-12 restore drill, then verify `php artisan health:check` returns all-green. Full steps: [docs/runbooks/health-monitor.md](../runbooks/health-monitor.md). `docs/architecture_map.md` §18 is already updated.

### Phase 2 — Alerting

- [x] **HM-2.1** `app/Console/Commands/SendHealthAlert.php` (`health:alert`, everyFiveMinutes) + `app/Mail/HealthAlertMail.php` following the existing `ActivityDigestMail` pattern. Signature-based state in the persistent cache store; fire on transition-to-worse or after `realert_hours`; recovery notice on return to all-OK.
- [x] **HM-2.2** Flap suppression: CRIT requires **2 consecutive** non-OK observations (a deploy restart resolves inside one interval). If the suppression state's own read/write throws, **skip suppression and alert anyway** — fail toward alerting. Mail failures logged, never thrown.
- [x] **HM-2.3** Tests: transition fires; repeat inside the window does not; re-alert after the window does; recovery notice fires; one CRIT observation suppressed, two not; unreadable suppression state still alerts; mailer throwing does not fail the command.
- [x] **HM-2.4** *(done 2026-08-19 — `HEALTH_ALERT_RECIPIENTS=gurovm@gmail.com` on production only; a forced alert was delivered through Resend with no errors, and a real unprompted alert fired during the reboot, which is how the boot-grace gap was found.)* Was: Set `HEALTH_ALERT_RECIPIENTS` in the prod `.env`, then force one real alert end-to-end with `php artisan health:alert --force` to prove mail actually lands, and again after stopping the queue worker for 15 minutes to prove the state machine fires unprompted. An untested alert path is not an alert path. (Scheduling is already committed in `routes/console.php`; the full state machine was driven end-to-end locally against the log mailer.)

### Phases 3 & 4 — design decisions (2026-08-19)

**Spec:** [health-monitor-phases-3-4.md](../specs/health-monitor-phases-3-4.md) — the detail lives there; these tasks are the sequence.

| | Decision | Consequence |
|---|---|---|
| D1 | No history | **Zero migrations across both phases.** No `health_events`, no charts. |
| D2 | Read-only page + one "Refresh now" POST | Marker commands stay CLI-only. |
| D3 | d1–d4 are **digest-only**, not transition alerts | A check's *node* now decides its channel — structural, see HM-4.2. |
| D4 | Pill top-right in the header, admins only | Renders into `layouts/app.blade.php`, so the gate is `shouldRender()`, never a template `@if`. |

### Phase 3 — Admin surface

- [x] **HM-3.1** `config/health.php` — add the `ui` block: `poll_seconds` (30), `stale_seconds` (180), `kpi_keys` (`H2 H4 Q1 Q2 B1 P1`). KPI tiles are selected by check key from config, never hardcoded in Blade.
- [x] **HM-3.2** `SystemHealthService::refreshOnDemand(): HealthSnapshot` — takes `SNAPSHOT_LOCK`; acquired → `forget(SNAPSHOT)` + `refresh()`; not acquired → last-known-good; lock store down → `build()`. Never throws. **The controller must not call `refresh()` directly** — two tabs on a sick box would stampede the MySQL round-trip and Redis `INFO` that are slow precisely because MySQL and Redis are what's sick.
- [x] **HM-3.3** `app/Http/Controllers/Admin/MonitorController.php` — thin `index()` / `data()` / `refresh()`. No thresholds, no formatting, no queries. **No API Resource class**: `HealthSnapshot::toArray()` is already the documented contract, and wrapping it makes two places to change one shape.
- [x] **HM-3.4** Routes in the existing `admin` group in `routes/web.php`: `GET /admin/monitor`, `GET /admin/monitor/data` (`throttle:60,1` — the only polled admin route), `POST /admin/monitor/refresh` (`throttle:6,1`). Refresh is POST, never GET: it mutates cache and a GET that mutates is prefetchable by a browser link preview.
- [x] **HM-3.5** `resources/views/admin/monitor.blade.php` + `components/monitor/{node-card,kpi-tile,check-row}.blade.php` — spec §6 layout. Tailwind + design-system tokens only, no JS libraries. Seed the initial snapshot via `@js()`.
- [x] **HM-3.6** Alpine: poll `/data` every `ui.poll_seconds`, `document.hidden`-aware (skip while hidden, poll **immediately** on `visibilitychange`); fetch failure keeps the last state behind an amber "live updates interrupted" banner, never blank; "refreshed Ns ago" ticker; "Refresh now" disables + spins and re-enables on failure too, with a distinct 429 message.
  **Two rules that decide whether this page is right:** (a) everything renders from the Alpine state object, never from Blade — one shape, one rendering path; (b) the status→colour map holds **complete Tailwind class literals** (`'bg-emerald-500'`), never interpolated `` `bg-${token}-500` ``, which the JIT cannot see and which ships the page colourless.
- [x] **HM-3.7** **Stale-snapshot badge** — when `generated_at` is older than `ui.stale_seconds`, the header says so regardless of `overall`. `snapshot_ttl` is 300s and `SNAPSHOT_LAST` has no TTL at all, so a dead scheduler otherwise renders **stale green**, the worst failure a monitor has. S1 covers it as a check; the header must not need the reader to notice a check.
- [x] **HM-3.8** `app/View/Components/HealthPill.php` + `components/health-pill.blade.php`, slotted into `layouts/app.blade.php`'s `h-14` header right of `@stack('page-actions')`. Gate via `shouldRender()` using the same `hasPermission('is_admin')` predicate as `EnsureUserIsAdmin`. Cache-only (`pillStatus()`), never rebuilds, `STALE` → gray "Unknown" on any failure. Renders **nothing** for non-admins — not `x-show`, which would ship system status into the DOM of every client-facing page.
- [x] **HM-3.9** Sidebar "Monitor" nav link (extend the admin-group active condition at `sidebar.blade.php:95`, don't replace it) + cross-link from `/admin/system-status`. The two pages stay separate: system-status is a *control* page with destructive buttons, the monitor is read-only and left open polling.
- [x] **HM-3.10** Tests — `MonitorControllerTest`: admin 200 + exact JSON shape; **agency manager 403 on the page, `/data` and `/refresh` as three separate assertions** (the JSON endpoint is the one that leaks and does not inherit the page's test); guest 302 on all three; refresh returns a newer `generated_at`; refresh under a held lock returns the cached one unchanged; **`/data` returns 200 with a CRIT payload when every dependency throws**; both throttles bite. `HealthPillTest`: renders for admin on an unrelated page, **absent** (not hidden) for non-admin, and a throwing `pillStatus()` still leaves that page at 200.
- [x] **HM-3.11** *(done 2026-08-19 — `docs/architecture_map.md` §18 gained an "Admin surface (Phase 3)" subsection; deployed to staging then production, both on `87354d0`.)* **`npm ci && npm run build` turned out to be mandatory and is missing from the documented code-only recipe** — `/public/build` is gitignored, so Phase 3's new Tailwind classes simply do not exist on a server that only pulled and ran composer. Verified after each deploy with `grep -c 'bg-emerald-500' public/build/assets/*.css`.

### Phase 4 — Dependency & version currency

- [x] **HM-4.1** `config/dependency_maintenance.php` — `reviewed_at`, a `runtimes` table (`product`, `branch`, `security_support_until`, **`source`**) for PHP/MySQL/Redis/Nginx, thresholds (`eol_warn_days` 90, `table_review_months` 6), and the advisories block (endpoint, 24h cache, real user agent, 8s timeout). **Source every date from the vendor's published policy and record where it came from** — an unsourced EOL table reads green with authority. No dated window published → explicit `null` that reads WARN, never a guessed date.
- [x] **HM-4.2** **The alert split — land this with its tests in one change.** `config/health.php`: `alert_excluded_nodes => ['platform']`, `deps_digest_recipients`, day/hour. `HealthSnapshot`: add `alertable()`, `alertStatus()`, `digestable()`, and **change `signature()` to compute from `alertable()`**. `SendHealthAlert`: every `overall`/`failing()` decision point moves to `alertStatus()`/`alertable()`; `HealthAlertMail` gets one muted footer line when `digestable()` is non-empty. A half-applied split silences outage alerting — the one regression this vertical cannot afford.
  *Accepted consequence:* `overall` stays honest, so a CRIT advisory turns the page and pill red while nothing emails. Reversible with one config line if the permanent red is what ends up ignored.
- [x] **HM-4.3** `DependencyAdvisoriesCheck` (d1) — deployed `composer.lock`, `packages` **and** `packages-dev`, matched with `composer/semver` (already vendored). Cache **keyed by the lock's sha256** so a deploy re-queries instead of serving 24h of stale "clean". Feed down → last-known-good, else WARN "advisory feed unreachable"; **never OK on a dead feed**. Unrated severity counts as high. Dev-only advisories **capped at WARN** and labelled `(dev)` — production installs `--no-dev`, so they are not deployed, but they are on dev machines and in CI (spec open question 1).
- [x] **HM-4.4** `RuntimeEolCheck` (d2) — PHP via `PHP_VERSION`, MySQL via `select version()` on the **`mysql_health`** connection (never the default one), Redis via `INFO server`, Nginx from the facts file. WARN <90d, CRIT past the date, **STALE when a version can't be determined**. Plus a separate result: WARN when `reviewed_at` is >6 months old — the table's own dead-man switch, because a stale table reports "all supported" forever.
- [x] **HM-4.5** `scripts/health-facts.sh` — replace the `apt-check` security count. **Do not trust `apt-check` as-is** (found 2026-08-19 trying to apply prod's "1 pending security update"): it counts packages in the full-upgrade change set that have a security-pocket version, *including packages that are not installed*. On production that 1 was `libabsl20220623t64` — not installed, only ever arriving as a new dependency of `libgav1-1` during a full upgrade — so `--only-upgrade` refuses it and `unattended-upgrade` reports "no packages found". A naive d3 shows a permanently-stuck amber **no action can clear**, which is how a monitor teaches people to ignore it. Count only **installed** packages whose candidate comes from a `-security` pocket and is upgradable via `--only-upgrade`; if it can't be computed, write `null`. Needs a root-cron redeploy, not just a `git pull`.
- [x] **HM-4.6** `OsPatchCheck` (d3) — reads `pending_security` / `reboot_required`. **`null` count → STALE, never 0**: a monitor that cannot count must not report "clean". Sustained window in a check-side marker `{count, first_seen}`, reset when the count reaches 0 or the set shrinks (the facts only ever report *now*): WARN >7d, CRIT >30d. `reboot_required` → WARN always. Facts stale >48h WARN / >7d CRIT.
- [x] **HM-4.7** `PatchRunFreshnessCheck` (d4) + `deps:mark-patch-run {--note=}` writing `{ts, lock_sha, note}` (`lock_sha` = sha256 of `composer.lock`). **Never marked → WARN "never recorded", not STALE** — "nobody has ever patched this box" is a fact, not missing data. WARN >35d or lock drift while d1 shows highs; CRIT >60d.
- [x] **HM-4.8** `app/Listeners/RecordFailedLogin.php` — X2 needs a feed that does not exist today (no failed-login table; `LoginRequest` uses only the RateLimiter). Listen on `Illuminate\Auth\Events\Failed`, increment a per-minute cache bucket `health:auth:fail:{YmdHi}` with a 20-min TTL. No table, no migration. **Wrap the write in try/catch and swallow — a cache outage must never be able to break logging in.**
  **Register it explicitly** with `Event::listen(Failed::class, RecordFailedLogin::class)` in `AppServiceProvider::boot()`, alongside the observers. Auto-discovery *is* active (`Application::configure()` chains `->withEvents()`, `$shouldDiscoverEvents = true`, path `app/Listeners`) and dropping the file in would work — but under discovery a renamed method or dropped type-hint silently un-registers the listener and **X2 reports a confident green "0 failed logins" forever**. That is the stale-green failure this vertical exists to prevent, and nothing about it looks wrong. Also: no deploy script runs `event:cache`, so discovery reflects on every boot. See spec §11a.
- [x] **HM-4.9** `SecurityPostureCheck` — X1: expired `personal_access_tokens` still present, WARN >0, never CRIT (housekeeping), `link` → `/tokens`. X2: sum the last 15 buckets, WARN ≥20, CRIT ≥100. Register both new check classes in `config('health.checks')`.
- [x] **HM-4.10** `deps:digest` + `DependencyDigestMail` (follow `ActivityDigestMail`), scheduled weekly Monday 08:00 with an **explicit `->timezone(config('app.display_timezone'))`** — the app runs **UTC** (`config/app.php:68`) with `display_timezone` = `Asia/Jerusalem`, so a bare `08:00` lands at 10:00 or 11:00 Israel time depending on DST. **In-process via `Schedule::call()`** — single-core droplet, per the existing comment in `routes/console.php`. Reports `digestable()` **and** the platform node's passing checks. **Always sends, including all-clear**: a digest that appears only on bad news is indistinguishable from one that has stopped working — the same argument that put recovery notices in Phase 2. Mail failures logged never thrown; empty recipients → SUCCESS.
- [x] **HM-4.11** Tests — `HealthSnapshotAlertSplitTest` (the partition; `signature()` unmoved by a platform flip — this is what proves the split can't silence outages); extend `SendHealthAlertTest` (platform-only CRIT sends nothing, data CRIT still sends, mixed sends the data one with the footer); d1 feed-down/cache-bust/dev-cap; d2 boundaries + stale table; d3 **`null` → STALE asserted explicitly**, sustained windows, marker reset; d4 never-marked/drift; X1/X2 boundaries and **a throwing cache store not breaking login** (assert the login response, not the marker); digest all-clear/findings/no-recipients/throwing-mailer. No test executes a shell command; facts from fixture JSON.
- [ ] **HM-4.12** *(2026-08-19: docs done — runbook "Dependency currency (Phase 4)", architecture_map §18, parent spec §10. Deployed to staging and production on `87354d0`. The facts script needed no separate reinstall: root's crontab calls `/var/www/maddata/scripts/health-facts.sh` in the repo directly, so `git pull` updated the live copy — confirmed rather than assumed. `slow-facts.cache` was cleared and `pending_security` recomputed to **0**, which retires the permanently-stuck "1 pending security update" the old `apt-check` logic reported. **Remaining: `unattended-upgrades` only.**)* Ops + docs: enable `unattended-upgrades` on production (**security pocket only**, `Automatic-Reboot "false"`, keep a `.bak`) — provisioning, never the deploy flow. Redeploy the facts script (HM-4.5). Update `docs/runbooks/health-monitor.md`, `docs/architecture_map.md` §18, and the parent spec's §3 catalog + §10 phasing table.

---

## PHP 8.4.19 → 8.4.24 on production
**Added:** 2026-08-19 · **DONE — staging validated, production upgraded 2026-08-19 ~11:00 UTC (13:58 IDT).**
**Rule:** [lessons.md](../lessons.md) — runtime upgrades are feature-sized, staged first, never bundled into another change.

### Staging validation (complete)

- [x] **PHP-1** Upgraded staging 8.4.6 → 8.4.24 (a *bigger* jump than production's, so a conservative proxy). PHP packages only, `--force-confold` to preserve tuned config.
- [x] **PHP-2** Extension set **identical** before/after — 53 modules, none lost. This is the check that matters: Laravel breaks on a missing extension far more often than on a language change.
- [x] **PHP-3** **Full suite green on 8.4.24: 722 passed, 1 skipped** — identical to local on 8.4.7.
- [x] **PHP-4** App smoke: `/up` 200, `/login` 200, `/` 302; Laravel 12.12.0 boots; artisan works.

### Incidental fixes made along the way

- [x] Staging Composer was **2.3.10 (July 2022)** vs production's 2.9.5 — it emitted a wall of PHP 8.4 deprecations and could not authenticate to GitHub for dev packages. Updated to 2.9.5; old binary at `/root/composer-2.3.10.bak`.
- [x] Staging lacked `php8.4-sqlite3`, so the suite (SQLite in-memory) could not run there at all. Installed.
- [x] Staging disk **95% → 65%**: `/var/mail/root` held **3,853,036 cron failure mails accumulated since September 2022**, from jobs belonging to a different app on the same box (`/var/www/dev/maddata`: `taboola:sites`, `outbrain:budgets`, `eskimi:campaigns` — since commented out, spool never cleaned). Truncated in place; 300 KB sample kept at `/root/mail-sample-before-truncate-20260819.txt`. Journal vacuumed to 200 MB. Check H4 confirmed the recovery.

### Production (done 2026-08-19)

**Result:** 8.4.19 → 8.4.24, CLI and FPM both. Extension set **identical, 57 modules, none lost**. `/up` 200, `/login` 200 in 99 ms, Laravel 12.12.0 boots, FPM workers confirmed on the new binary, no errors logged. Queue worker verified *consuming* on the new runtime — check Q3's heartbeat advanced after the restart, which is the thing `systemctl is-active` cannot tell you. `health:check` 25/26 green, exit 0. One active user session during the ~1s FPM restart; sessions live in the database so nobody was logged out.

- [x] **PHP-5** Take a fresh backup first: `scripts/backup-production.sh` (also refreshes the B1/B3 marker).
- [x] **PHP-6** Record `php -m | sort` and `php -v` before, for the after-comparison.
- [x] **PHP-7** Upgrade the 13 `php8.4-*` packages **only** — not the other 22 pending `noble-updates`, which are unrelated and belong in their own change:
      `DEBIAN_FRONTEND=noninteractive apt-get install -y --only-upgrade -o Dpkg::Options::=--force-confold -o Dpkg::Options::=--force-confdef $(apt-get -s upgrade | grep '^Inst php' | awk '{print $2}')`
- [x] **PHP-8** **Restart `php8.4-fpm` AND `maddata-queue`.** Both hold the old binary and extensions in memory; the app can look fine while half of it still runs the old PHP.
- [x] **PHP-9** Diff `php -m` against the before-capture. Any missing extension is a rollback trigger.
- [x] **PHP-10** Verify: `php artisan health:check` all green, `/up` 200, `/login` 200, log clean, queue heartbeat (Q3) recovering after the worker restart.
- [ ] **PHP-11** *(not needed — extensions identical, no rollback)* Rollback command, kept for reference: `apt-get install --allow-downgrades php8.4-*=8.4.19-1+ubuntu24.04.1+deb.sury.org+1`, restart both services.

**Known limit of the staging evidence:** staging is Apache + mod_php on Ubuntu 22.04; production is Nginx + PHP-FPM on 24.04. Staging proves *the application code runs correctly on 8.4.24*. It does **not** exercise the PHP-FPM restart path — that risk is retired only on production, which is why PHP-8 and PHP-10 exist.
