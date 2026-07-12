# Chunk 5 — Frontend & Schema (Performance)

**Date:** 2026-04-05
**Scope:** `resources/views/**` (86 Blade files), `database/migrations/*` (39 files), `routes/web.php`.

> **TOP 3 FIXES**
> 1. `campaigns.index` has NO pagination and runs `hasPermission()` + `accessibleClientIds()->contains()` per-row inside a non-paginated loop — will collapse under load and also inflates column count per campaign row. Paginate + precompute uploadable client-ID set once outside the loop.
> 2. `activity_logs` lacks a `created_at` index but is filtered and sorted by `created_at` on every page load; same table is filtered with `LIKE '%"budget"%"'` JSON scan in `CampaignChangeController::excludeBudgetLogs()`. Add `created_at` index + composite `(status, created_at)` + pull `is_budget_change` boolean into a dedicated column.
> 3. Index-list Blade views (`users/index`, `agency/users/index`, `campaigns/index`, `admin/audiences/index`) dump **ALL rows** (plus nested pivots) into Alpine `x-data` via `@js(...)` for client-side filtering. No server pagination. At a few hundred users this blows HTML payload past 1 MB.

---

## Critical (hot-path)

| # | Issue | Location | Cost | Fix |
|---|-------|----------|------|-----|
| C1 | `campaigns.index` has NO pagination. `Campaign::with('client')->get()` pulls ALL visible campaigns. | `app/Http/Controllers/CampaignController.php:42` | O(N) rows hydrated + rendered per request; breaks when ≥500 campaigns | Replace `->get()` with `->paginate(25)` and pass through view. `pacingData` lookup already uses aggregated `SUM()`, works fine with page slice. |
| C2 | Per-row permission + client-scope checks inside loop in Blade. `auth()->user()->hasPermission()` called twice **plus** `accessibleClientIds()->contains($campaign->client_id)` for **every campaign row**. | `resources/views/campaigns/index.blade.php:241` | N calls to permission logic per render; `accessibleClientIds()` is cached via `once()` but `->contains()` is still re-executed per row | Compute once in controller: `$canUpload = $user->hasPermission('is_admin') \|\| $user->hasPermission('can_upload_reports'); $uploadableClientIds = $user->accessibleClientIds()->flip();` then in blade: `@if($canUpload && ($isAdmin \|\| isset($uploadableClientIds[$campaign->client_id])))` |
| C3 | `activity_logs` has NO index on `created_at`, yet every page hit issues `ORDER BY created_at DESC` plus optional `whereDate('created_at' …)`. Table grows unboundedly (every Campaign insert/update writes a row via `CampaignObserver`). | `database/migrations/2026_02_08_144832_create_activity_logs_table.php` + `ActivityLogController@index:75` | Full-table sort as rows grow. O(N log N) per paginated request. | Add `$table->index('created_at')` and composite `($user_id, created_at)` since filter by user+date is common. |
| C4 | `CampaignChangeController::excludeBudgetLogs()` uses `where('changes', 'not like', '%"budget"%')`. This LIKE-with-leading-wildcard on JSON text column is **unindexable** and runs on the entire (potentially huge) activity_logs table. | `app/Http/Controllers/Admin/CampaignChangeController.php:28-32` | Full-scan on activity_logs filter for every non-budget viewer. | Add dedicated boolean column `involves_budget` on `activity_logs`, populated by `ActivityLogger` when `changes` includes the `budget` key, and filter with `->where('involves_budget', false)`. Add index. |
| C5 | Heavy Alpine payload on `users/index`: the entire user list + every user's pivot `agencies[]` + `clients[]` + a full `$clients` list are serialized into HTML for client-side filtering. | `resources/views/users/index.blade.php:21-44` | Page weight scales O(users × avg clients-per-user). At 200 users × 20 clients ≈ 4 000 nested objects inlined in HTML. | Switch to server-side filtering (GET params → controller `->paginate()`). Alternatively, serve `/admin/users.json` and fetch on demand. |
| C6 | `UserController::index` eager-loads `clients`, `userRole`, `agencies` **and returns `->get()` with no pagination**. Combined with C5 this is the ceiling. | `app/Http/Controllers/UserController.php:23` | Fully hydrated User models for all users, every sidebar visit/admin browse. | Paginate + drop `clients` eager load once client-side filter is moved server-side. |

## High

| # | Issue | Location | Cost | Fix |
|---|-------|----------|------|-----|
| H1 | `CampaignChangeController@index`: `Campaign::whereHas('activityLogs', …)->withCount(['activityLogs' => …])->get()` loads ALL matching campaigns, no pagination. Runs `pending()` scope twice (EXISTS + subquery COUNT) for every campaign. | `CampaignChangeController.php:46-53` | Two correlated subqueries × N campaigns. | Paginate; also push `pending()` state into the outer join or use a single JOIN with GROUP BY. Consider a materialized `pending_changes_count` on `campaigns`. |
| H2 | `CampaignChangeController@show` runs `$allLogs = $logsQuery->get()` then does PHP-side `unique()` + `sortByDesc()`. No pagination, whole pending set hydrated. | `CampaignChangeController.php:78-100` | O(n) memory on each show; breaks on legacy campaigns with 10k+ logs. | Move dedup logic into SQL with a `DISTINCT ON`/window function, or pre-store the `(creative_id, width, height)` tuple in a dedicated column so we can `GROUP BY` at query time. Paginate. |
| H3 | `clients.index` calls `$query->get()` (all clients) and also `Agency::orderBy('name')->get()`. No pagination. Used by admins & agency managers. | `app/Http/Controllers/ClientController.php:32-33` | Scales with all clients in tenant. | Paginate, or cache `Agency::all()` (small table) via `Cache::remember('agencies_list', 300, …)`. |
| H4 | `Campaign::accessibleClientIds()` uses `foreach ($this->agencies as $agency)` then `Client::where('agency_id', $agency->id)->pluck('id')` — **N+1 over agencies** inside the already cached `once()`. | `app/Models/User.php:42-49` | For a user with K agencies = K queries per HTTP request (first call). | Single query: `Client::whereIn('agency_id', $managedAgencyIds)->pluck('id')`. |
| H5 | `users/index` sidebar loads **every** admin's `agencies` and `clients` relations regardless of whether the user filters by them. Serialization to JSON happens on every view render. | `UserController.php:23`, `users/index.blade.php:21-44` | O(users × avg_pivot_size) memory on every /admin/users hit. | Lazy-load via AJAX; or limit initial load to first 100 users and paginate. |
| H6 | `Cache::remember('clients_list', 300, ...)` is **GLOBAL**, shared across all admins. Works here only because admins see all clients, but it risks leaking if `clients_list` is ever re-used for non-admins. Cache TTL of 5 min means newly created clients take 5 min to appear in forms. | `CampaignController.php:83, 115, 173` | Correctness/UX bug. | Bust cache on Client `saved`/`deleted` observer: `Cache::forget('clients_list')`. |
| H7 | `Audience::index` loads ALL audiences with `->get()`, no pagination, plus 3 extra full-column `distinct` scans. | `app/Http/Controllers/Admin/AudienceController.php:15-37` | Full table scan ×3 extra times per page hit. Audiences table imports from Excel can reach 10k+ rows. | Paginate audiences; cache the three distinct lists (`main_category`, `sub_category`, `provider`) with tagged cache, busted on audience upload/delete. |
| H8 | `dashboard/index` writes `window.__dashDateRows = @json($dashDateRows)` inline — for long-running campaigns this payload is the full daily time-series. | `resources/views/dashboard/index.blade.php:356-357` | 30-90 KB per request typically; 300+ KB for multi-year campaigns. | Acceptable but expose via `/api/reports/*` (already exists) and fetch async, letting HTML cache. |
| H9 | `placements_data` has `name` as a plain `string` (untrimmed) with an index but the uniqueness of placement by `name` is implicit. Aggregation queries `GROUP BY name` against millions of rows use the `(campaign_id, report_date)` composite first, then file-sort by name. | `placements_data_table` | Group+sort without covering index. | Add composite `(campaign_id, name)` to support `GROUP BY name` aggregation used in `CampaignMetricsService:71` and `:196`. |

## Medium

| # | Issue | Location | Cost | Fix |
|---|-------|----------|------|-----|
| M1 | `campaigns` table has no index on `(start_date)` or composite `(start_date, created_at)`, yet every `campaigns.index` call uses `orderByRaw('COALESCE(start_date, created_at) DESC')`. | `CampaignController.php:42` | filesort on every page render. | Either store a `sort_date` generated column and index it, or change logic to order by `start_date DESC NULLS LAST, created_at DESC` + index on `(start_date, created_at)`. |
| M2 | `campaign_data` unique `(campaign_id, report_date)` is fine, but there is no plain index on `report_date` for cross-campaign "yesterday metrics" queries. | `CampaignController.php:91-94` filters `whereIn('campaign_id', $ids)->where('report_date', $yesterday)` | Uses composite index efficiently (leading `campaign_id` via `whereIn`) — OK today. | Verify with `EXPLAIN` as dataset grows; add `(report_date, campaign_id)` if dashboard widgets ever pull "yesterday's top N". |
| M3 | `creative_files` has NO index on `creative_id` except the auto-FK index; also `(creative_id, width, height)` is a common lookup in `CreativeController@upload:128`. | `CreativeController.php:128` `where('width', $w)->where('height', $h)` | Sub-loop query runs per uploaded file. | Add composite index `(creative_id, width, height)`. |
| M4 | `agency_user` has no index on `user_id` alone — composite PK `(agency_id, user_id)` cannot serve `WHERE user_id = ?` queries. | `create_agency_user_table.php:15` | "Get all agencies for user" query scans via reverse PK — MySQL will do a full-index scan. | Add `$table->index('user_id')` (MySQL auto-creates FK index; verify in prod with SHOW INDEX). |
| M5 | `client_user` has composite PK `(client_id, user_id)` — same reverse-lookup problem for `user->clients()`. | `create_client_user_table.php:17` | Reverse-index scan. | Add `$table->index('user_id')`. |
| M6 | `campaign_audience` same pattern. | `create_campaign_audience_table.php:14` | Reverse-index scan for "which campaigns use this audience". | Add `$table->index('audience_id')`. Also add index on `audiences.full_path` since unique lookup is done during import. |
| M7 | `audiences.main_category` and `sub_category` each have their own index, but query always orders by both together. | `AudienceController.php:15-17` | Dual single-col indexes can't cover compound ORDER BY efficiently. | Composite `(main_category, sub_category, name)` — supports both index scan and `ORDER BY`. Drop the two single-col indexes. |
| M8 | `activity_logs` stores `changes` as JSON but is queried with `LIKE '%field%'` strings (see C4). Also `subject_type` is queried with `where` & `orWhere('subject_type', 'like', …)` in search. | `ActivityLogController.php:40,70` | Non-sargable LIKE on text column. | Normalize status/type filters away from LIKE; validate `$searchTerm` to exact types. |
| M9 | Inline `<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js">` + `jquery.min.js` loaded from CDN for every page that uses a `<x-ui.datatable>`. Third-party CDN = extra DNS, no SRI, uncacheable across origin. | `resources/views/components/scripts/datatables.blade.php:47-48` | Extra 2 HTTPS connections per page. | Bundle via Vite (`npm i jquery datatables.net`) — same bundle is already loaded for Chart.js on dashboard. |
| M10 | Chart.js loaded from `cdn.jsdelivr.net` as external script tag. | `dashboard/index.blade.php:565` | Extra DNS lookup. | Import via Vite bundle. |
| M11 | `logs/index` Blade uses `bg-{{ $actionColor }}-50` style dynamic Tailwind class names — Tailwind JIT may not generate all variants at build time, forcing safelist or missing styles. Not performance per se but causes flashes/layout shifts. | `activity_logs/index.blade.php:195-197` | Runtime class miss. | Safelist these classes or hardcode a `match` → fixed class string. |

## Low

| # | Issue | Location | Fix |
|---|-------|----------|-----|
| L1 | `User::$with = ['userRole']` auto-loads role on every User query. Fine, but also eager-loads for headless jobs/commands. | `app/Models/User.php:15` | Acceptable; monitor. |
| L2 | `sessions.last_activity` is indexed, good. No index on `user_id` separately from composite? `user_id` is indexed in the create migration (line 35, `0001_01_01_000000_create_users_table.php`). OK. | — | None needed. |
| L3 | `personal_access_tokens` has unique on `token` (64) and index on `expires_at` — good. | — | None. |
| L4 | `roles.permissions` JSON column — queried only via `isset()` in PHP, never DB-level. Fine. | `Role::hasPermission` | None. |
| L5 | Many Blade files inline giant SVG path data rather than using sprites or an `<x-icon>` component. Adds ~200 KB of repeated SVG markup to sidebar/buttons across pages — compressed well by gzip. | e.g. `campaigns/index.blade.php`, `sidebar.blade.php` | Refactor to `<x-icon name="users"/>` extract to `resources/views/components/icons/`. |
| L6 | `activity_logs.description` is `text` and queried with `LIKE %%`. Non-critical at low volume. | `ActivityLogController.php:69` | Add FULLTEXT index if search becomes a hot path. |
| L7 | `sidebar.blade.php` calls `Auth::user()->hasPermission()` 6 times per render. Cheap after first call (role is eager-loaded) but redundant. | `sidebar.blade.php:52,84,86,87,205,224` | Cache result into a single `@php $user = Auth::user(); $isAdmin = ... @endphp` block at top. |
| L8 | `campaigns/create.blade.php` embeds **all clients** via `@js($clients->map(...))`. Fine for admins with sane counts, can balloon to ~1000. | `campaigns/create.blade.php:64` | Add server-side search endpoint when clients > 500. |

---

## Index Recommendations (table-by-table)

| Table | Current Indexes | Recommended Additions | Justification |
|-------|-----------------|----------------------|---------------|
| `campaigns` | `client_id` (FK), `status`, `(client_id, status)`, `created_at` | **Add `(start_date DESC, created_at DESC)`** | `orderByRaw('COALESCE(start_date, created_at) DESC')` on index page is a filesort today. |
| `campaign_data` | `campaign_id` (FK), unique `(campaign_id, report_date)` | **Add `(report_date)`** | Cross-campaign "yesterday metrics" aggregation (CampaignController:91-94). |
| `placements_data` | `name`, `campaign_id` (FK), `(campaign_id, report_date)` | **Add `(campaign_id, name)`**; **drop** standalone `name` index | GROUP BY name queries in CampaignMetricsService:71,196 need campaign_id + name covering. |
| `activity_logs` | `user_id` FK, `campaign_id` FK, morph `(subject_type, subject_id)`, `(campaign_id, status)`, `action` | **Add `created_at`**, **Add `(status, created_at)`**, **Add `involves_budget` bool + index** | Sort by `created_at` + status filter on every request; eliminate LIKE scan on `changes`. |
| `audiences` | `main_category`, `sub_category`, `is_active` | **Add `(main_category, sub_category, name)` composite; drop single-col indexes on those two**; **Add `full_path` index** | Supports `ORDER BY` + import dedupe lookups. |
| `users` | `email` unique, `role_id` | **Add `is_active` or composite `(is_active, role_id)`** | `->active()` scope will be used on all user-listing queries once rolled out. |
| `client_user` | PK `(client_id, user_id)` | **Add `user_id`** | Reverse lookup `user->clients`. |
| `agency_user` | PK `(agency_id, user_id)` | **Add `user_id`** | Reverse lookup `user->agencies`. Without it `accessibleClientIds()` does index-scan. |
| `campaign_audience` | PK `(campaign_id, audience_id)` | **Add `audience_id`** | Reverse lookup. |
| `creative_files` | `creative_id` (FK) | **Add `(creative_id, width, height)`** | `CreativeController@upload:128` looks up existing file by dimension. |
| `campaign_locations` | `campaign_id` FK | None | Small rows, good as-is. |
| `clients` | `agency_id` FK | **Add `name` for `orderBy('name')`** | Agencies/clients admin listings all order by name. |
| `creatives` | none beyond id | **Add `campaign_id`** (auto-FK already there), **Add `status`** | Used for filtering active creatives. |
| `personal_access_tokens` | `token` unique, `expires_at`, morph `tokenable` | None | Already adequate. |
| `sessions` | `user_id`, `last_activity` | None | Adequate. |
| `roles` | `name` unique, `sort_order` | None | Small table. |
| `agencies` | `name` unique | None | Small table. |

### Suggested migration skeleton

```php
Schema::table('campaigns', fn (Blueprint $t) =>
    $t->index(['start_date', 'created_at'], 'campaigns_sort_date_index'));

Schema::table('campaign_data', fn (Blueprint $t) =>
    $t->index('report_date', 'campaign_data_report_date_index'));

Schema::table('placements_data', function (Blueprint $t) {
    $t->index(['campaign_id', 'name'], 'placements_data_campaign_name_index');
    $t->dropIndex(['name']); // placements_data_name_index
});

Schema::table('activity_logs', function (Blueprint $t) {
    $t->index('created_at', 'activity_logs_created_at_index');
    $t->index(['status', 'created_at'], 'activity_logs_status_created_index');
    $t->boolean('involves_budget')->default(false)->after('changes');
    $t->index('involves_budget', 'activity_logs_involves_budget_index');
});

Schema::table('audiences', function (Blueprint $t) {
    $t->index(['main_category', 'sub_category', 'name'], 'audiences_category_name_index');
    $t->index('full_path', 'audiences_full_path_index');
    $t->dropIndex(['main_category']);
    $t->dropIndex(['sub_category']);
});

Schema::table('client_user', fn (Blueprint $t) =>
    $t->index('user_id', 'client_user_user_id_index'));

Schema::table('agency_user', fn (Blueprint $t) =>
    $t->index('user_id', 'agency_user_user_id_index'));

Schema::table('campaign_audience', fn (Blueprint $t) =>
    $t->index('audience_id', 'campaign_audience_audience_id_index'));

Schema::table('creative_files', fn (Blueprint $t) =>
    $t->index(['creative_id', 'width', 'height'], 'creative_files_creative_dims_index'));

Schema::table('clients', fn (Blueprint $t) =>
    $t->index('name', 'clients_name_index'));
```

---

## Caching Opportunities

| Data | Current | Recommended TTL | Expected Gain |
|------|---------|-----------------|---------------|
| `Agency::all()` (for form dropdowns) | Re-queried on every user/client create/edit | 10 min tagged cache, busted on agency save | -3 queries per admin form load |
| `Role::orderBy('sort_order')->get()` | Re-queried on users/index, users/create, users/edit | 10 min | -1 query per admin flow |
| Distinct `audiences.main_category`/`sub_category`/`provider` | 3 separate `distinct` scans per `audiences.index` | 5 min, busted on upload | -3 full-table scans per page |
| `clients_list` (used for admin campaign create/edit) | Global `Cache::remember('clients_list', 300)` | Keep but add observer to bust on Client save/delete | Immediate freshness |
| Dashboard `dashDateRows`/`dashPlacementRows` per campaign | Computed on every page render | Keyed `cache(["campaign_metrics", $campaign->id, $from, $to])` 5 min | -2 aggregated queries per dashboard visit |
| Campaign pacing data `(pacingData)` on `campaigns.index` | Per-request aggregate | 2 min cache `campaign_pacing_{user_scope_hash}` | -1 GROUP BY per load |
| `hasPermission()` per-request | `once()`-like per-request memoization exists for `accessibleClientIds`, NOT for `hasPermission` | In-request memoization on `User` model (array cache by permissionKey) | Removes redundant role lookups (~6-20/page) |

---

## Blade View Optimizations

1. **`campaigns/index.blade.php:241`** — hoist permission + uploadable client-ID set out of the loop (see C2).
2. **`campaigns/index.blade.php:15-16`** — `$clients` serialized to JSON for the client filter dropdown. Add `->only(['id','name'])` earlier in controller and `Cache::remember` it for admins; it's already cached for the admin path, not for non-admins.
3. **`users/index.blade.php:21-44`** and **`agency/users/index.blade.php:29`** — replace client-side filter Alpine payload with server-side filter. Or split payload: send only list of `id/name/email/role` and fetch `agencies/clients` on demand per selected row.
4. **`admin/activity_logs/index.blade.php:195-197`** — dynamic `bg-{{ $actionColor }}-50` Tailwind class names risk JIT misses. Replace with a fixed `match()` mapping to literal class strings. (Correctness-adjacent but affects layout thrash.)
5. **`admin/audiences/index.blade.php:7`** — calls `$audiences->count()` on a collection that's already fully loaded; fine once paginated, but today means full-table load.
6. **`dashboard/index.blade.php:356-357`** — move `window.__dashDateRows`/`__dashPlacementRows` to a JSON endpoint; fetch via `fetch()` after first paint. Cuts HTML TTFB by N-row payload weight.
7. **`components/sidebar.blade.php:52-224`** — six `Auth::user()->hasPermission()` calls per render. Cache once at top:
   ```blade
   @php
     $u = Auth::user();
     $isAdmin = $u?->hasPermission('is_admin') ?? false;
     $canManage = $u?->hasPermission('can_manage_users') ?? false;
     $canLogs = $u?->hasPermission('can_see_logs') ?? false;
   @endphp
   ```
8. **`components/scripts/datatables.blade.php`** — bundle jQuery + DataTables via Vite; remove jsdelivr CDN; remove per-page `<script>` injection (declare on `@stack('scripts')` once in layout when any datatable is used).
9. **`components/ui/datatable.blade.php`** — currently uses hand-rolled pagination/sort logic with embedded SVG chevrons in `background-image:url('data:image/svg+xml...')` **on every `<select>`**. Extract once to a class.
10. **`admin/audiences/index.blade.php:201`** — `onclick="audienceModal.openEdit(id, ...json_encode...)"` per row means `json_encode` called N times server-side, and Blade writes the encoded strings into HTML per row. For 10k audiences this is significant HTML weight. Pass via `data-*` attributes + single `@json($audience)` or switch to Alpine `x-on:click` with a row reference.

---

## Route → View Map (heavy endpoints)

| Route | Controller | View | Risk |
|-------|-----------|------|------|
| GET `/campaigns` | `CampaignController@index` | `campaigns/index` | C1, C2 |
| GET `/dashboard/{campaign}` | `DashboardController@show` | `dashboard/index` | H8, M2 |
| GET `/admin/activity-logs` | `Admin\ActivityLogController@index` | `admin/activity_logs/index` | C3, M8 |
| GET `/admin/campaign-changes` | `Admin\CampaignChangeController@index` | `admin/campaign_changes/index` | C4, H1 |
| GET `/admin/users` | `UserController@index` | `users/index` | C5, C6 |
| GET `/admin/clients` | `ClientController@index` | `clients/index` | H3 |
| GET `/admin/audiences` | `Admin\AudienceController@index` | `admin/audiences/index` | H7, M7 |
| GET `/agency/{agency}/users` | `Agency\AgencyUserController@index` | `agency/users/index` | C5-adjacent |

---

## Summary

Project shows solid foundational thinking (`once()` memoization on `accessibleClientIds`, eager loading `with('client')`, existing perf-index migration `2026_03_22_184627`), but **none of the index/list pages paginate**, and several hot filters use unindexed columns (`activity_logs.created_at`) or unindexable LIKE scans on JSON text (`changes 'not like' '%"budget"%"'`). Fixing the five Critical items removes the biggest scaling cliffs before hitting real production volumes on `activity_logs`, `placements_data`, and `campaign_data`.
