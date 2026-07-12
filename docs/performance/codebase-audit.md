# Performance Audit: Full Codebase
**Date:** 2026-03-22

## Summary
> 1. **DashboardController::show() fires 8-10 separate queries** against campaign_data for a single page load, many of which are redundant. This is the heaviest endpoint and should be consolidated and cached.
> 2. **Missing database indexes** on `placements_data(campaign_id, report_date)`, `activity_logs(status)`, and `campaigns(status)` cause full table scans on the most-queried columns.
> 3. **N+1 on `hasPermission()`** -- every call to `User::hasPermission()` lazy-loads the `userRole` relationship if not eager-loaded, and this method is called 2-10 times per request across controllers, policies, and Blade views.

---

## Critical Issues

| Issue | Location | Impact | Fix |
|-------|----------|--------|-----|
| **Dashboard fires 8-10 redundant queries** | `DashboardController::show()` lines 31-103 | Every dashboard page load runs separate SUM queries for summary, video_100, allImpressions, allClicks, firstReportDate, plus a full `->get()` on campaignData -- all against the same `campaign_data` table with the same WHERE clause. For campaigns with months of daily data, this is extremely wasteful. | Consolidate into 1-2 queries: one `selectRaw(SUM(impressions), SUM(clicks), SUM(visible_impressions), SUM(video_100), MIN(report_date), MAX(report_date))` for summary, and one `->get()` for chart/table data. Derive allImpressions/allClicks from the collection instead of re-querying. |
| **`exportExcel()` duplicates the same pattern** | `DashboardController::exportExcel()` lines 176-255 | Same redundant multi-query pattern as `show()`, repeated independently. | Extract a shared query builder method or service class used by both `show()` and `exportExcel()`. |
| **N+1 on `User::hasPermission()` via `userRole`** | `app/Models/User.php:55-66`, called from controllers, policies, Blade views | `hasPermission()` accesses `$this->userRole` which triggers a lazy-load query on every call if the relationship is not eager-loaded. This is called 2-10+ times per request (controllers check permissions, policies check them again, Blade views check them for conditional UI). The authenticated user's role is never preloaded. | Add a `$with = ['userRole']` default on the User model, or load it once in middleware/auth and cache the result on the model instance. |
| **`allowedCampaignIds()` runs nested N+1** | `Admin/CampaignChangeController.php:22-29`, `Admin/ActivityLogController.php:30-35` | For non-admin users, this loads all clients `->with('campaigns')->get()` then flatMaps campaign IDs. This is an N+1 on the campaigns relationship if there are many clients. Worse, `allowedCampaignIds()` is called per-method (sometimes twice in CampaignChangeController). | Replace with a single query: `Campaign::whereIn('client_id', $user->clients()->pluck('clients.id'))->pluck('id')`. Cache result per-request. |

## High Priority

| Issue | Location | Impact | Fix |
|-------|----------|--------|-----|
| **Missing composite index on `placements_data(campaign_id, report_date)`** | `database/migrations/2025_05_12_062052_create_placements_data_table.php` | `PlacementData` is always queried with `WHERE campaign_id = ? AND report_date BETWEEN ? AND ?` (DashboardController, ReportApiController, CampaignController::upload). Currently only `name` is indexed. The `campaign_id` foreign key gets an implicit index, but the compound `(campaign_id, report_date)` is missing, forcing index+scan or full scan on date filtering. | Add migration: `$table->index(['campaign_id', 'report_date']);` |
| **Missing index on `activity_logs.status`** | `database/migrations/2026_02_16_153953_add_status_to_activity_logs_table.php` | `scopePending()` (`WHERE status = 'pending'`) is used on every CampaignChange page load, both in `whereHas` and `withCount`. Without an index, this scans the full activity_logs table. | Add migration: `$table->index('status');` or better: `$table->index(['campaign_id', 'status']);` |
| **Missing index on `campaigns.status`** | `database/migrations/2026_02_26_154113_add_status_to_campaigns_table.php` | `ClientController::index()` uses `withCount(['campaigns' => fn($q) => $q->where('status', 'active')])`. Campaign index also filters by status in-memory. | Add migration: `$table->index('status');` |
| **Campaigns index loads all campaigns without pagination** | `CampaignController::index()` line 42 | `->get()` loads every campaign for the user into memory. For agencies with hundreds of campaigns, this is slow and memory-heavy. The pacing calculation then iterates all campaigns. | Add `->paginate(25)` or lazy pagination. Move pacing calculation to the query layer with a subquery join. |
| **ReportApiController::campaigns() unbounded** | `ReportApiController::campaigns()` line 199 | Returns ALL campaigns as JSON with no pagination. API consumers could receive massive payloads. | Add `->paginate(50)` or at minimum `->limit(100)`. |
| **ReportApiController::summary() re-queries after `sum()`** | `ReportApiController::summary()` lines 24-27 | Calls `$data->sum('impressions')`, then `$data->sum('clicks')`, then `$data->orderByDesc()->first()` -- three separate queries from the same query builder (which gets consumed/modified). If `is_video`, also `$data->sum('video_100')` -- a 4th query. | Clone the base query once, run a single `selectRaw()` aggregate, and separately fetch latestRow. |
| **Synchronous AI API calls** | `CampaignAssistantController::chat()`, `AiLocationController::generate()` | HTTP calls to Anthropic API block the PHP worker for 2-10+ seconds. Under load, this ties up all available workers. | For `chat()`, consider streaming or queuing. For `generate()`, if acceptable UX, return a job ID and poll. At minimum, set a strict `->timeout(15)` on the HTTP client to prevent infinite hangs. |

## Medium Priority

| Issue | Location | Impact | Fix |
|-------|----------|--------|-----|
| **`Client::all()` loaded multiple times per request** | `CampaignController::index()` line 83, `::create()` line 115, `::edit()` line 315, `UserController::create()` line 29, `::edit()` line 81 | Admin users trigger `Client::all()` on every campaign/user page. With many clients, this is wasteful and never cached. | Cache client list in a request-scoped singleton or use `Cache::remember('clients_list', 300, ...)`. |
| **`Audience::where('is_active', true)->get()` on every AI chat** | `CampaignAssistantController::buildSystemPrompt()` line 71 | Loads all active audiences into the AI prompt on every chat message. This is both a DB query and a payload size issue. | Cache the audience list: `Cache::remember('active_audiences', 3600, ...)`. |
| **RoleController::reorder() updates in a loop** | `Admin/RoleController::reorder()` lines 89-91 | `Role::where('id', $id)->update(...)` inside a `foreach` -- one query per role. Typically 5-10 roles, so not critical, but still N queries. | Use `CASE WHEN` batch update or `upsert()`. |
| **CampaignController::upload() creates PlacementData in a loop** | `CampaignController::upload()` lines 223-261 | Each placement row is inserted individually via `PlacementData::create()` inside a foreach, triggering model events each time. For reports with 20-50 placements, this is 20-50 INSERT queries. | Use `PlacementData::insert($rows)` for bulk insert (skip timestamps or set them manually). |
| **No response caching on report API endpoints** | `ReportApiController` (all methods) | Report data (campaign_data, placements_data) changes only when new reports are uploaded (daily at most). Every API request recalculates aggregates from scratch. | Add `Cache::remember("report_summary_{$campaign->id}_{$start}_{$end}", 3600, ...)` and invalidate on upload. |
| **`$user->clients` accessed in Blade loops without eager loading** | `resources/views/campaigns/index.blade.php:241`, `resources/views/users/index.blade.php:27` | In the campaigns index Blade, `auth()->user()->clients` is accessed inside the loop body for permission checks. While this is a single user, the `clients` relationship may not be loaded, causing a query each time it's accessed (though Laravel caches it after first load on the same model instance). | Ensure `clients` is eager-loaded on the auth user via middleware or a global scope. |
| **CreativeController::upload() queries `creative->files()` per file** | `CreativeController::upload()` line 124 | Inside the file upload loop, `$creative->files()->where('width', $width)->where('height', $height)->get()` runs a query for each uploaded file to check for duplicates. | Pre-load all existing files before the loop: `$existingFiles = $creative->files->groupBy(fn($f) => $f->width.'x'.$f->height);` |

## Quick Wins

| Issue | Location | Fix |
|-------|----------|-----|
| **`Campaign::find()` instead of route model binding** | `DashboardController::show()` line 20 | Change signature to `show(Campaign $campaign)` -- Laravel handles 404 automatically and enables query caching. |
| **Missing `->timeout()` on HTTP calls** | `CampaignAssistantController.php:30`, `AiLocationController.php:14` | Add `->timeout(15)` to prevent workers from hanging on slow API responses. |
| **`env()` called at runtime** | `CampaignAssistantController.php:31`, `AiLocationController.php:15` | `env('ANTHROPIC_API_KEY')` should be `config('services.anthropic.key')` -- `env()` returns null when config is cached. |
| **`shell_exec()` in file upload** | `CreativeController::upload()` line 108 | `ffprobe` call via `shell_exec()` blocks the request. If ffprobe is not installed, it silently returns null. Add a config check and consider queuing video dimension detection. |
| **Duplicate `HasFactory, Notifiable` traits** | `User.php` lines 9,26 | `Notifiable` is used twice (line 9 and line 26). Remove the duplicate. |

## Caching Recommendations

| Data | Current | Recommended TTL | Expected Gain |
|------|---------|-----------------|---------------|
| Dashboard summary aggregates (per campaign) | Recalculated on every page load (8-10 queries) | 15 min, invalidate on report upload | 80% reduction in dashboard query load |
| Report API summary/by-date/by-placement | Recalculated on every API call | 60 min, invalidate on report upload | Significant for API consumers polling frequently |
| Client list (for dropdowns) | `Client::all()` on every form page | 5 min | Eliminates repeated identical queries |
| Active audiences list | Queried on every AI chat message | 60 min, invalidate on audience CRUD | Reduces DB load and AI prompt assembly time |
| User's allowed campaign IDs (non-admin) | Recalculated per controller method call | Request-scoped (per-request cache) | Prevents duplicate nested queries within a single request |
| Auth user's role + clients | Lazy-loaded on demand, multiple times per request | Request-scoped (load once in middleware) | Eliminates 2-5 redundant queries per request |

## Index Recommendations

```sql
-- Migration: add_performance_indexes

-- placements_data: all queries filter by campaign_id + report_date
ALTER TABLE placements_data ADD INDEX idx_placements_campaign_date (campaign_id, report_date);

-- activity_logs: scopePending() used in whereHas and withCount
ALTER TABLE activity_logs ADD INDEX idx_activity_logs_campaign_status (campaign_id, status);

-- activity_logs: filtered by action and subject_type in ActivityLogController
ALTER TABLE activity_logs ADD INDEX idx_activity_logs_action (action);

-- campaigns: status filtering in withCount and collection filtering
ALTER TABLE campaigns ADD INDEX idx_campaigns_status (status);

-- campaigns: client_id + status for scoped campaign counting
ALTER TABLE campaigns ADD INDEX idx_campaigns_client_status (client_id, status);

-- audiences: is_active filtering used in CampaignController and CampaignAssistant
ALTER TABLE audiences ADD INDEX idx_audiences_active (is_active);

-- campaign_data: report_date standalone for date range queries
-- (campaign_id, report_date) already has a UNIQUE index which covers most queries
-- No additional index needed for campaign_data

-- users: role_id for joins with roles table
ALTER TABLE users ADD INDEX idx_users_role_id (role_id);
```

Equivalent Laravel migration:

```php
Schema::table('placements_data', function (Blueprint $table) {
    $table->index(['campaign_id', 'report_date'], 'idx_placements_campaign_date');
});

Schema::table('activity_logs', function (Blueprint $table) {
    $table->index(['campaign_id', 'status'], 'idx_activity_logs_campaign_status');
    $table->index('action', 'idx_activity_logs_action');
});

Schema::table('campaigns', function (Blueprint $table) {
    $table->index('status', 'idx_campaigns_status');
    $table->index(['client_id', 'status'], 'idx_campaigns_client_status');
});

Schema::table('audiences', function (Blueprint $table) {
    $table->index('is_active', 'idx_audiences_active');
});

Schema::table('users', function (Blueprint $table) {
    $table->index('role_id', 'idx_users_role_id');
});
```
