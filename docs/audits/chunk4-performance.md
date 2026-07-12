# Chunk 4 — Admin & Multi-Tenant (Performance)

**Date:** 2026-04-05
**Scope:** Admin/* + Agency/* + ClientController + UserController + ProfileController + ActivityLogger + ActivityLog/Client models + related migrations.

## Summary — Top 3 fixes

1. **`ActivityLogger::checkAndSendDigest()` runs synchronously on EVERY write** and issues multiple queries + potential `Mail::send()` in the request cycle. Move to a queued job / scheduled command.
2. **`ActivityLog` table has zero indexes on the columns we filter by** (`campaign_id`, `action`, `status`, `created_at`, `user_id`). Admin log pages will degrade linearly as the table grows unbounded.
3. **Admin list endpoints have no pagination** (`UserController@index`, `ClientController@index`, `AgencyController@index`, `Admin/AudienceController@index`, `Admin/SystemStatusController@index`, `CampaignChangeController@index`). All use `->get()` + load many relations; memory + query cost will blow up.

---

## Critical (hot-path)

| # | Issue | Location | Cost | Fix |
|---|-------|----------|------|-----|
| C1 | **`ActivityLogger::checkAndSendDigest()` runs on EVERY `log()` call, i.e. on every CRUD write across the app.** Each write triggers a Cache read, a `diffInHours` check, and every 2h triggers an `ActivityLog::with('user','campaign.client','subject')->where('created_at','>',$since)->get()` followed by a sync `Mail::to(...)->send(...)`. The mail send happens inside the request that triggered the Nth write. | `app/Services/ActivityLogger.php:39-66` | **High** — every controller write pays 1–2 queries; every ~2h one unlucky user pays for a full query + SMTP round-trip inside their request (can be 500ms+). Scales with log volume. | Remove the inline trigger. Convert `ActivityDigestMail` into a scheduled command (`schedule->command(...)->everyTwoHours()`) that runs out-of-band. Alternatively dispatch a queued `SendActivityDigest` job at most once per window. Use `Cache::add('digest_lock', now(), 120*60)` (atomic) instead of get/put race. |
| C2 | **`ActivityLog` table has only default morph index. No index on `campaign_id`, `action`, `status`, `user_id`, `created_at`.** The admin ActivityLog page filters on all of them and orders by `latest()` (`created_at DESC`). As table grows past a few hundred-thousand rows (unbounded, written on every CRUD), every admin log view will do full table scans. | `database/migrations/2026_02_08_144832_create_activity_logs_table.php`, `database/migrations/2026_02_16_153953_add_status_to_activity_logs_table.php`; used heavily by `Admin/ActivityLogController.php:39-75`, `Admin/CampaignChangeController.php:46,64,132,201` | **High** — filter + sort on unindexed TEXT/INT columns on a growing table. | Add composite indexes (see Index Recommendations). In particular: `(campaign_id, status)`, `(status, created_at)`, `(action, subject_type)`, `(created_at)`, `(user_id, created_at)`. |
| C3 | **No pagination on admin list endpoints — unbounded `->get()`**. | `UserController.php:23`, `ClientController.php:32`, `Admin/AgencyController.php:18`, `Admin/AudienceController.php:15`, `Admin/RoleController.php:16`, `Agency/AgencyUserController.php:27`, `Agency/AgencyClientController.php:22-25`, `Admin/CampaignChangeController.php:53`, `Admin/SystemStatusController.php:17-38` | **High** — memory + hydration cost scales linearly with tenants/users/audiences. Audiences in particular can reach tens of thousands of rows after imports. | Add `->paginate(50)`. For DataTables-driven pages, prefer server-side pagination instead of loading everything + client-side DataTables. |
| C4 | **`CampaignChangeController@index` scans all campaigns that have any pending log, with `withCount` subquery, NO pagination**, then renders. The `whereHas` + `withCount` both re-execute the same `pending()` (+ optional budget) filter → two correlated subqueries per row. | `Admin/CampaignChangeController.php:46-53` | **High** — O(campaigns × avg_logs) with no index on `status`. | Paginate. Replace the double filter with a single `leftJoin` + `GROUP BY`, or cache per-campaign pending counts. Add the `(campaign_id, status)` index so the `whereHas` subquery is index-only. |
| C5 | **`Admin/CampaignChangeController@show` calls `->get()` then `->unique()` + `->sortByDesc()` in PHP on potentially hundreds of logs per campaign**, scanning the JSON `changes` cast on every row. Memory proportional to log history. | `Admin/CampaignChangeController.php:78-100` | **Medium-High** | Cap the window (e.g. last 30 days), paginate, or de-duplicate at the SQL level via a window function / `GROUP BY creative_id, width, height` for `CreativeFile` logs. |

---

## High

| # | Issue | Location | Fix |
|---|-------|----------|-----|
| H1 | **`UserController@index` eager-loads `clients`, `userRole`, `agencies` for ALL users then computes `agencies.map(...)` + `clients.map(...)` per row in Blade.** No pagination, no `select()` scoping. Works fine for 50 users, blows up at 5k. | `UserController.php:23`, view `resources/views/users/index.blade.php:26-28` | Paginate. Add `select('id','name','email','role_id','is_active','is_admin','can_view_budget','is_report')`. Only pluck `id,name` on pivots via `->with(['agencies:id,name','clients:id,name'])` (+ the pivot key must be included automatically via `belongsToMany`). |
| H2 | **`UserController@create` / `@edit` run a subquery per user-agency when loading the edit form** — `$user->agencies->map(...)` calls `$user->clients()->whereIn('clients.id', Client::where('agency_id',$a->id)->pluck('id'))->pluck(...)` inside a map. That's **2 queries × N agencies** per edit page. | `UserController.php:124-131` | Pre-load all `$user->clients` once keyed by `agency_id`. Example: `$byAgency = $user->clients->groupBy('agency_id');` then per agency `$byAgency->get($a->id, collect())->pluck('id')`. |
| H3 | **`ClientController@index` non-admin branch lazy-loads campaigns count scoped to `status='active'` with no index on `campaigns.status`/`client_id`.** For large campaign volumes this becomes a correlated subquery per client. | `ClientController.php:23-25` | Verify index `(client_id, status)` exists on `campaigns`; if not, add. Paginate. |
| H4 | **`AgencyController@destroy` runs `$agency->clients()->count()` then would run delete — `->exists()` is cheaper than `count()`** when only checking for presence. Same pattern in `RoleController@destroy`, `AgencyClientController@destroy` already uses `exists()`. | `Admin/AgencyController.php:100`, `Admin/RoleController.php:92` | Use `->exists()` instead of `->count() > 0`. |
| H5 | **`SystemStatusController@index` loads EVERY session row with a JOIN on users, then groups / maps in PHP**. With long `session.lifetime` and many users, this can return thousands of rows. | `Admin/SystemStatusController.php:17-54` | Aggregate in SQL: `SELECT user_id, COUNT(*) as session_count, MAX(last_activity) ... GROUP BY user_id` then join to users, or paginate. Add LIMIT. |
| H6 | **`Agency/AgencyUserController@edit` performs 3 separate pivot queries + a pivot load**: `agency->users()->where->first()->pivot`, `agency->clients()->pluck`, `user->clients()->pluck->intersect`. | `Agency/AgencyUserController.php:99-104` | Fetch pivot once via `DB::table('agency_user')->where(...)->value('access_all_clients')`. Combine the two client-id lookups into one query: `$user->clients()->where('agency_id', $agency->id)->pluck('clients.id')`. |
| H7 | **`Agency/AgencyUserController@index` eager-loads `userRole` but filters with `whereDoesntHave('userRole', fn($q)=>$q->where('is_protected', true))`** on top of `agency->users()` — a 3-join subquery per call. Fine now but should be paginated. | `Agency/AgencyUserController.php:27-30` | Paginate. Consider a dedicated `users.is_protected` cached flag or joining roles once. |
| H8 | **`assignableRoles()` loads every role, hydrates all into models, then filters in PHP using `hasPermission` on each.** Called on every agency user create/edit. | `Agency/AgencyUserController.php:207-231` | Cache the filtered role list per current user for the request (memoize on the controller). Roles are small so acceptable, but add a once-per-request memo. |

---

## Medium

| # | Issue | Location | Fix |
|---|-------|----------|-----|
| M1 | **`ActivityLogController@index` filter dropdown loads ALL users**: `User::orderBy('name')->get()`. With thousands of users, that's full hydration into the dropdown. | `Admin/ActivityLogController.php:79` | Use `->select('id','name')->get()` or a select2 AJAX autocomplete. |
| M2 | **`campaign` filter uses `whereHas` + `LIKE '%…%'`** — full scan of campaigns name column. | `Admin/ActivityLogController.php:53-55` | Add FULLTEXT index on `campaigns.name`, or switch to prefix `LIKE 'x%'`, or join + index. |
| M3 | **`excludeBudgetLogs` scans `changes LIKE '%"budget"%'`** on the JSON column — cannot use any index. | `Admin/CampaignChangeController.php:27-32` | Store a separate boolean column `has_budget_change` on `activity_logs`, maintained by the logger/observer. Or use MySQL JSON function `JSON_CONTAINS_PATH(changes,'one','$.budget')` with generated column + index. |
| M4 | **`Admin/AudienceController@index` runs 4 separate queries** (audiences + 3 DISTINCTs). With a large audience list these DISTINCT queries are ORDER BY filesort each time. | `Admin/AudienceController.php:15-37` | Cache `categories`, `subCategories`, `providers` lookups in `Cache::remember('audience_filters', 600, ...)`; invalidate on store/update/destroy/upload (already forgets `active_audiences`, add new key). |
| M5 | **`Admin/AudienceController@upload` runs `Audience::where('full_path',...)->first()` + `updateOrCreate(['full_path'=>...])` per row** → 2 queries per imported row. For a 10k-row import, ~20k queries. | `Admin/AudienceController.php:170-189` | (a) Add UNIQUE index on `audiences.full_path` to make `updateOrCreate` fast and atomic. (b) Pre-fetch all existing `full_path`s once into a Set, check membership in PHP to decide new vs updated; then `upsert()` in batches of 500. |
| M6 | **`RoleController@reorder` executes one `UPDATE` per role** inside a loop. | `Admin/RoleController.php:124-126` | Wrap in `DB::transaction` and use a single `CASE WHEN id THEN N` UPDATE, or at minimum a transaction so indexes are updated once. |
| M7 | **`ClientController@index` with the `agency` filter** runs `Agency::find($agencyId)` a second time for header display even though the join could supply the name. | `ClientController.php:29-34` | Trivial: `Agency::select('id','name')->find($agencyId)`. |
| M8 | **`Auth::user()->hasPermission(...)` called many times per request.** `hasPermission` does NOT access DB but does access `$this->userRole` relation. If relation isn't eagerly loaded on the auth user, the first call lazy-loads `roles` row. | `Admin/ActivityLogController.php:15`, `Admin/CampaignChangeController.php:19,37,74`, etc. | In a `LoadUserRole` middleware (or User boot), eager-load `userRole` onto the auth user once per request. |
| M9 | **`accessibleClientIds()` calls `Client::where('agency_id', $id)->pluck('id')` inside a `foreach` over agencies** — N+1 across agencies. `once()` memoizes per-instance but each call re-issues N queries. | `app/Models/User.php:42-47` | Replace the loop with a single `Client::whereIn('agency_id', $agencyIdsWithAccessAll)->pluck('id')`. |
| M10 | **`CampaignChangeController@downloadAll` calls `Storage::disk('creatives')->exists()` + `->size()` per file in foreach** — two stat() syscalls per file, up to 400 syscalls before the zip starts. | `Admin/CampaignChangeController.php:146-152` | Use `filesize()` once after building absolute path; skip second `exists()` check. For S3-backed disks this doubles API calls. |

---

## Low

| # | Issue | Location | Fix |
|---|-------|----------|-----|
| L1 | `agency->users()->where('user_id',$user->id)->exists()` per request instead of a simple check on a pre-loaded collection. | `Agency/AgencyUserController.php:248` | Acceptable; keep as-is. Alt: `DB::table('agency_user')->where(...)->exists()` avoids model hydration (already raw via query builder). |
| L2 | `UserController@destroy` detaches clients one table at a time; `agency_user` pivot is **not** detached (relies on cascade? not defined). | `UserController.php:108-109` | Also detach agencies or ensure cascade. Current pivot does `cascadeOnDelete()` on FK — fine. But `client_user` does the same so `->detach()` is redundant. Remove it. |
| L3 | Role cache via `Role::availablePermissions()` — static but called per render. | `Admin/RoleController.php:23,55` | If large, memoize once. Likely fine. |
| L4 | `Auth::user()` called repeatedly in `CampaignChangeController` instead of once stored. | `Admin/CampaignChangeController.php:18-19,37,74` | Minor; cache into a local var. |
| L5 | `parseBrowser` runs `str_contains` chain per session — fine; just note O(sessions) at request time. | `Admin/SystemStatusController.php:93-113` | N/A once sessions are paginated. |

---

## Caching opportunities

| Data | Current | Recommended TTL | Expected gain |
|------|---------|-----------------|---------------|
| Audience filter dropdowns (categories / subcategories / providers) | 3 queries every index page load | 10 min (invalidate on write) | 3 DISTINCT queries saved per admin page view |
| `Role::availablePermissions()` list used in create/edit forms | Recomputed per request | Forever (static) until deploy | Small, but every role form benefits |
| `assignableRoles()` per current user (Agency manager) | Reloaded on every create/edit call | Memoize per-request (once()) | Simpler, avoids redundant filters |
| `User::accessibleClientIds()` | Memoized per-instance via `once()` (good) but re-queries if user reloaded | Per-request cache is fine | — |
| Authenticated user's role + permissions | `userRole` loaded lazily on first `hasPermission()` call | Cache on the session / eager-load in middleware | Avoids 1 query per request on admin pages |
| Activity digest emails trigger check | Runs on every CRUD write (`Cache::get`) | Move to scheduled command; remove inline check | -1 Cache round-trip per write, no mail in request lifecycle |
| `ActivityLog::availableActions()` (if built for filter dropdown) | N/A | Cache forever | Future-proof |

---

## Index Recommendations

Create a new migration `add_performance_indexes_to_admin_tables`:

```php
Schema::table('activity_logs', function (Blueprint $table) {
    // Admin ActivityLog page orders by created_at DESC, filters by user_id/action/campaign
    $table->index('created_at');
    $table->index(['user_id', 'created_at']);
    $table->index(['action', 'subject_type']);
    // CampaignChangeController: pending logs per campaign (hot path)
    $table->index(['campaign_id', 'status']);
    $table->index(['status', 'created_at']);
});

Schema::table('audiences', function (Blueprint $table) {
    // updateOrCreate on full_path during imports — currently does FULL SCAN per row
    $table->unique('full_path');
    $table->index('is_active');
    $table->index('provider');
});

Schema::table('campaigns', function (Blueprint $table) {
    // ClientController@index withCount active campaigns; CampaignChangeController reads by client_id
    $table->index(['client_id', 'status']);
});

Schema::table('clients', function (Blueprint $table) {
    // Already has FK index on agency_id from foreignId(); verify in prod.
    // Add name index if list pages order by name:
    $table->index('name');
});

// sessions table already has user_id + last_activity indexes — OK.
// agency_user / client_user have composite primary keys on (a,b) — they are NOT indexed on the REVERSED pair.
Schema::table('agency_user', function (Blueprint $table) {
    $table->index('user_id'); // reverse lookup: user -> agencies
});
Schema::table('client_user', function (Blueprint $table) {
    $table->index('user_id'); // reverse lookup: user -> clients
});
```

**Notes:**

- `activity_logs.subject` already has a `nullableMorphs` index `(subject_type, subject_id)`.
- Pivot primary key `(agency_id, user_id)` gives fast `agency -> users` but not `user -> agencies`. Same for `client_user`. Add the reverse index.
- `audiences.full_path` is used by `updateOrCreate` during every uploaded row — a UNIQUE index converts a linear scan into an index seek (the single highest-value index change here after ActivityLog).
- Consider a generated column + index for `activity_logs.changes->>'$.budget'` if the `excludeBudgetLogs` LIKE scan remains.

---

## Retention / growth control for `activity_logs`

Table grows on every CRUD across the entire app and is never pruned. Recommend:

- Add a `prune:activity-logs` Artisan command that deletes `status='handled' AND created_at < now()-6months` in chunks of 10k.
- Schedule nightly.
- Or use Laravel's `Prunable` trait on the model.

Without this, any index you add will itself grow unbounded.
