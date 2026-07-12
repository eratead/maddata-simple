# Chunk 2 — Campaigns Core (Performance)

**Date:** 2026-04-05
**Scope:** CampaignController, CampaignAssistantController, AiLocationController,
Campaign/Audience/CampaignLocation models, CampaignObserver, CampaignMetricsService,
UpdateCampaignStatuses command.

## Summary
Top 3 fixes:
1. **Queue the Anthropic HTTP calls** (CampaignAssistantController, AiLocationController). A 15 s sync timeout on two web routes blocks PHP-FPM workers and degrades the whole app under light load.
2. **Paginate + eager-load on Campaign::index** — currently unbounded `->get()` over ALL campaigns, with `client.agency` accessed in Blade producing an N+1 on the agency relationship.
3. **Cache CampaignMetricsService aggregates** per `{campaign_id, date_range}` — they are recalculated on every dashboard/report render and hit `campaign_data` + `placements_data` with SUM/GROUP BY every time.

---

## Critical (hot-path)

### C1. Unbounded result set on Campaigns index
**File:** `app/Http/Controllers/CampaignController.php:42`
```php
$campaigns = $campaigns->orderByRaw('COALESCE(start_date, created_at) DESC')->get();
```
**Cost:** Loads EVERY campaign for the user in memory. Memory grows linearly with tenant size; request time grows with JSON cast overhead on `targeting_rules`. There is no pagination and no `select()`.
**Impact:** Core listing page; hit constantly. At 5k+ campaigns this is ~MB of hydrated models + JSON parsing per page render.
**Fix:**
- Add `->paginate(25)` (or cursor pagination) and update the Blade/DataTables.
- Limit columns: `->select('id','name','client_id','status','start_date','end_date','expected_impressions','budget','created_at')` — `targeting_rules` (TEXT JSON) is not needed on the listing.
- `orderByRaw(COALESCE(...))` prevents index usage — add a generated/stored `sort_date` column or just `orderByDesc('start_date')` with NULLs sorted via a secondary `orderByDesc('created_at')`.

### C2. N+1 on `client.agency` in index view
**File:** `app/Http/Controllers/CampaignController.php:29` → view `resources/views/campaigns/index.blade.php:209`
```php
Campaign::with('client')  // only client, not client.agency
// Blade: {{ $campaign->client->agency ?? '' }}
```
`agency` is a **relationship** on `Client`, so `$campaign->client->agency` triggers one extra query per campaign.
**Impact:** N queries per page. With 200 campaigns visible = 200 extra SELECTs from `agencies`.
**Fix:** `Campaign::with(['client:id,name,agency_id', 'client.agency:id,name'])`.

### C3. AI endpoints block PHP-FPM workers on 15 s HTTP calls
**Files:**
- `app/Http/Controllers/CampaignAssistantController.php:29`
- `app/Http/Controllers/AiLocationController.php:14`

Sync `Http::timeout(15)->post(...)` inside a web request. A few parallel users tie up all workers, and each request holds a DB connection + session lock the whole time.
**Impact:** Head-of-line blocking of the entire site. Session locking causes the user's other tabs to stall.
**Fix:** Either
- Push to a queued job that writes result to cache keyed by a client-generated request id, frontend polls `/ai/result/{id}`; **or**
- Minimum: `session()->save()` before the HTTP call to release session lock; lower timeout to 10 s; wrap in circuit breaker (Laravel `RateLimiter` + fail fast after repeated failures).

### C4. CampaignMetricsService recomputes the same aggregates every request
**File:** `app/Services/CampaignMetricsService.php:35-76`, `142-200`
Each call hydrates the full `campaign_data` for the campaign then re-sums with PHP (`$campaignData->sum('impressions')` etc.), plus a separate SUM/GROUP-BY query on `placements_data`. For a 90-day campaign that's 90 rows hydrated per call; for export it hydrates again (duplicated `CampaignData::where(...)->get()` block in `getExportData`).
**Impact:** 2-3× O(rows) on every show/report/export call. Heavy when rendering dashboards with multiple panels.
**Fix:**
- Replace PHP `->sum()` with a single DB `selectRaw('SUM(impressions) impressions, SUM(clicks) clicks, SUM(visible_impressions) visible, SUM(video_100) v100, MAX(report_date) last_date')` — one row instead of N.
- Keep the full `$campaignData` collection ONLY if the chart actually needs per-day rows; otherwise use a separate lean query returning just `report_date, impressions, clicks` (no video cols) for the chart.
- Cache the aggregate row + placement rows for `cache()->remember("campaign:{$id}:metrics:{$start}:{$end}", 300, ...)` — invalidate on `ReportImportService::import` (new data insert).
- `getExportData()` duplicates `getMetrics()` logic → extract a private `aggregate()` method and reuse.

### C5. Missing compound index `(campaign_id, report_date)` usage on `campaign_data` for range scans is OK, but not on aggregate
`campaign_data` has `unique(campaign_id, report_date)` which already covers the range scan in `CampaignMetricsService`. **No fix** — confirmed good. Noted to avoid false positives.

---

## High

### H1. Pacing impressions query scans cross-tenant rows unnecessarily
**File:** `app/Http/Controllers/CampaignController.php:50-53`
```php
CampaignData::selectRaw('campaign_id, SUM(impressions) as total_impressions')
    ->whereIn('campaign_id', $campaignIds)
    ->groupBy('campaign_id')
```
After paginating (C1), `$campaignIds` will be small (≤25) so this becomes cheap — but today it passes every campaign id in the system. Combined with C1, table scan + grouping on a (potentially multi-million row) `campaign_data` table.
**Fix:** Depends on C1. After pagination, ensure the `(campaign_id, report_date)` unique already serves this — yes (group by leading col). No further index change.

### H2. Yesterday metrics query hits without a covering index for a date filter + IN list
**File:** `app/Http/Controllers/CampaignController.php:91-94`
```php
CampaignData::whereIn('campaign_id', $campaignIds)
    ->where('report_date', $yesterday)
    ->selectRaw('SUM(impressions) as total_impressions, SUM(clicks) as total_clicks')
```
Uses the `(campaign_id, report_date)` unique — fine. But paired with unbounded `$campaignIds` (see C1) it degenerates. Cache this top-box result for 5-10 min: `cache('dashboard:overview:'.$user->id, 300, ...)`.

### H3. CampaignObserver logs via `ShouldHandleEventsAfterCommit` but still serial writes inside the transaction flow
**File:** `app/Observers/CampaignObserver.php:23-116`
Every update can emit 1-4 separate `ActivityLog::insert` calls (budget + optimization + targeting + from controller: locations, audiences). That's up to **5 extra INSERTs per campaign save**.
**Impact:** Campaign edit round-trip is DB-write-heavy; activity_logs grows fast.
**Fix:**
- Batch inserts: collect diffs into one array, call `ActivityLogger::logMany($entries)` using `ActivityLog::insert([...])` (single INSERT).
- Alternatively push the whole diff payload onto a queue and let a job write logs asynchronously (keeps request thin).

### H4. `UpdateCampaignStatuses` has no index for the daily cron query
**File:** `app/Console/Commands/UpdateCampaignStatuses.php:30-32`
```php
Campaign::whereDate('end_date','<',today())->where('status','active')->update(...)
```
There is a `campaigns_status_index` but no index on `end_date`. Current `whereDate('end_date', ...)` also **disables any index** on `end_date` even if present (because of the `DATE()` wrapper).
**Impact:** Full table scan daily. Grows linearly with campaigns.
**Fix:**
- Use `->where('end_date', '<', today())` (no `whereDate`) — `end_date` is already a DATE column so function wrapping is unnecessary.
- Add composite index `(status, end_date)` — perfect for this exact predicate.

### H5. `syncAudiences` triggers up to 4 extra queries per call
**File:** `app/Http/Controllers/CampaignController.php:201-226`
- `pluck('audiences.id')` (SELECT 1)
- `sync()` (internal SELECT + INSERT/DELETE)
- `Audience::whereIn(..., added)->pluck('name')` (SELECT 2)
- `Audience::whereIn(..., removed)->pluck('name')` (SELECT 3)
- `$campaign->audiences()->get(...)` AFTER sync (SELECT 4)

**Fix:** Load all involved audiences once in one query `Audience::whereIn('id', array_unique([...$before, ...$after]))->get(['id','name'])->keyBy('id')` and use it for both names and the JSON response.

---

## Medium

### M1. `audiencesJson()` returns ALL active audiences, unpaginated
**File:** `app/Http/Controllers/CampaignController.php:183-189`
If audiences are 1k+, JSON payload is large + slow client-side filter. Cache this for 10 minutes since audiences rarely change.
```php
Cache::remember('audiences:active:json', 600, fn() => Audience::where('is_active', true)->...->get([...]));
```
Invalidate on Audience create/update/delete via observer.

### M2. Blind `$campaign->locations()->delete()` + loop insert on every edit
**File:** `app/Http/Controllers/CampaignController.php:259-267`
Even if locations are unchanged, we DELETE all rows then re-INSERT one at a time. Typical campaign: 1-20 locations → 1 DELETE + N INSERTs per edit.
**Fix:**
- Diff `$locationData` vs current locations; noop if unchanged.
- Otherwise use a single `CampaignLocation::insert($rows)` bulk insert (but then `created_at/updated_at` must be set explicitly).

### M3. `accessibleClientIds()` / `accessibleClients()->get()` called multiple times per request
**File:** `app/Http/Controllers/CampaignController.php:32, 38, 83, 115, 126, 147, 173, 239`
Each call re-runs the multi-tenant pivot JOIN. Cache on the User instance (e.g., memoize with a trait: `$user->accessibleClientIds ??= ...`).
**Fix:** Add a cached accessor on User model using Laravel's `Attribute::make(get: fn() => $this->_ids ??= ...)`.

### M4. `order` without covering index on `audiences`
**File:** `app/Http/Controllers/CampaignController.php:183-187`
`orderBy('main_category')->orderBy('sub_category')->orderBy('name')` + `where is_active=1`. Only individual single-col indexes exist on main_category, sub_category, is_active (no composite).
**Fix:** Add composite `(is_active, main_category, sub_category, name)` index — enables the filter + sort without filesort.

### M5. Chart/dash arrays eagerly built even when not viewed
**File:** `app/Services/CampaignMetricsService.php:67-98`
`chartLabels`, `chartImpressions`, `dashDateRows`, `dashPlacementRows` are always computed, even for callers that only need summary (e.g., API summary endpoints, exports).
**Fix:** Split into `summary()`, `chartSeries()`, `dashRows()`, `placementBreakdown()` so callers pull only what they need.

---

## Low

### L1. `json_encode` diff in update() to detect location changes is O(n log n) sort-sensitive
**File:** `app/Http/Controllers/CampaignController.php:276`
Cheap (few locations) but a proper diff (count + field comparison) is clearer and avoids JSON cost. Cosmetic.

### L2. `$campaign->refresh()` after update
**File:** `app/Http/Controllers/CampaignController.php:290`
Extra SELECT that is only needed because the controller then checks `start_date`. Could compute `start_date` BEFORE `$campaign->update($validated)` and include it in `$validated`.

### L3. Double date check in `store()`
**File:** `app/Http/Controllers/CampaignController.php:136-139`
Same pattern as L2 — an extra UPDATE for empty `start_date`. Move to the model's `creating` event or into a single insert.

### L4. `Audience::whereIn($added)->pluck('name')->join(', ')`
**File:** `app/Http/Controllers/CampaignController.php:213, 218`
Safe but re-queries the same data that was just synced. See H5.

### L5. `$campaign->load(['creatives','audiences','locations'])` on edit
**File:** `app/Http/Controllers/CampaignController.php:171`
Good — already eager-loaded. But `creatives` triggers N+1 for `creativeFiles` inside the Blade if files are rendered. Verify edit view; if so, extend to `creatives.files`.

---

## Caching opportunities

| Data | Current | Recommended TTL | Expected Gain |
|------|---------|-----------------|---------------|
| Active audiences JSON (`/campaigns/{c}/audiences/json`) | DB fetch every request | 10 min, bust on Audience write | –1 large SELECT + JSON encode per picker open |
| Campaign metric aggregates (per `{id, start, end}`) | Recomputed every show/report/export | 5 min, bust on `ReportImportService` upsert | –2 queries + PHP summation per dashboard load |
| Dashboard "yesterday" overview (`CampaignController::index` top boxes) | Recomputed every index page load | 5 min per user | –1 IN/WHERE query per page load |
| `accessibleClientIds()` | Recomputed 3-8 times per request | Request-scoped memoize | –multiple pivot joins |
| `clients_list` (admins) | Already cached 300 s | keep | — |
| Placement breakdown per campaign | Recomputed every show | 5 min, bust on import | –1 GROUP BY on placements_data per view |

---

## Index recommendations

```php
// database/migrations/2026_04_05_000001_campaigns_core_perf_indexes.php
Schema::table('campaigns', function (Blueprint $t) {
    // H4: daily cron UpdateCampaignStatuses
    $t->index(['status', 'end_date'], 'campaigns_status_end_date_index');
    // Optional: support ORDER BY start_date DESC on index page
    $t->index('start_date', 'campaigns_start_date_index');
});

Schema::table('audiences', function (Blueprint $t) {
    // M4: audiencesJson() filter + ordering
    $t->index(
        ['is_active', 'main_category', 'sub_category', 'name'],
        'audiences_active_category_name_index'
    );
});

Schema::table('campaign_locations', function (Blueprint $t) {
    // Edit page load: WHERE campaign_id = ?
    // (FK adds an index in MySQL by default — verify on prod; add explicitly if dropped)
    $t->index('campaign_id', 'campaign_locations_campaign_id_index');
});
```

Also rewrite `UpdateCampaignStatuses`:
```php
Campaign::where('end_date', '<', today())  // no whereDate()
    ->where('status', 'active')
    ->update(['status' => 'paused']);
```
This now uses `campaigns_status_end_date_index`.
