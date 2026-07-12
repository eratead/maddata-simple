# Chunk 3 — Creatives/Reports/Exports (Performance)

**Date:** 2026-04-05
**Scope:** CreativeController, DashboardController, ReportApiController, TokenController, Creative/CreativeFile/CampaignData/PlacementData models, CreativeObserver, CreativeFileObserver, app/Exports/*, ReportImportService.
**Top 3 to fix:**
1. ReportApiController::summary runs **4 independent full-table aggregations** (3 × `SUM()` + 1 × `orderBy→first`) when one single grouped query could return all of them.
2. CampaignMetricsService pulls **full CampaignData rows into PHP** and sums there — should be DB-side `SUM()` via `selectRaw`; also issues a 2nd identical query in `getExportData()` without reuse.
3. Missing composite index on `placements_data(campaign_id, report_date)` and on `creatives.campaign_id` (foreignId creates one but `creative_files` index is fine). `PlacementData` `->groupBy('name')` on a non-indexed combination scans all rows per campaign.

---

## Critical (hot-path)

| # | File:line | Cost | Impact | Fix |
|---|-----------|------|--------|-----|
| C1 | `ReportApiController.php:34-36` | 3 scans of `campaign_data` per request (sum impressions, sum clicks, latest row) | Each scan repeats the same WHERE. For a campaign with 365 days × multiple report rows, this is 3× the I/O it should be. | Replace the 3 queries with one: `$agg = $data->selectRaw('SUM(impressions) imps, SUM(clicks) clicks, SUM(video_100) v100, MAX(report_date) max_date')->first();` then a single follow-up `where('report_date', $agg->max_date)->value('uniques')`. Cuts the query count to 2. |
| C2 | `ReportApiController.php:72` | Extra `$data->sum('video_100')` run even for non-video campaigns guarded by `is_video`, but re-uses the `$data` builder without `clone` — works here only because Eloquent rebuilds. Still, fold into C1. | Same fix as C1. |
| C3 | `ReportApiController.php:103` + `:109` | `byDate()` runs `SUM(impressions)` once for CPM, then full `GROUP BY report_date` scan — 2 passes. | Pull `SUM(impressions)` from the aggregated grouped query in PHP: `$totalImpsForPeriod = $rows->sum('impressions')` after the grouped SELECT. Eliminates the pre-query. |
| C4 | `ReportApiController.php:165` | Identical `SUM(impressions)` over period in `byPlacement()` before the grouped query on `placements_data`. | Issue the grouped query first, then sum in PHP from the returned rows (count is bounded by #placements, typically small). |
| C5 | `ReportApiController.php:171-176` | `placements_data GROUP BY name` with WHERE `campaign_id = ? AND report_date BETWEEN ? AND ?` — **no composite index**. Full scan of campaign's rows every cache miss. Table is high-volume (per-placement × per-day). | Add index `placements_data (campaign_id, report_date)` and `placements_data (campaign_id, name, report_date)` (covering for the GROUP BY). |
| C6 | `CampaignMetricsService.php:35-44` | Loads **every** `CampaignData` row into memory then does PHP-side `sum()`, `sortByDesc()->first()`, `min()`. For multi-year campaigns: hundreds of rows hydrated as models just to sum 3 columns. | Keep the `$campaignData` collection for the chart (that's the only legitimate consumer), but don't re-sum in PHP — use a single `selectRaw('SUM(impressions) imps, SUM(clicks) clicks, SUM(visible_impressions) vis, SUM(video_100) v100, MAX(report_date) max_d, MIN(report_date) min_d')->first()` alongside. Or better, use `->toBase()->get()` (plain stdClass, skips model hydration) since these rows are read-only views. |
| C7 | `CampaignMetricsService.php:142-150` + `:196` | `getExportData()` re-runs the **same** `CampaignData` fetch done by `getMetrics()` + another `PlacementData` grouped query. Export endpoint hits two identical queries plus the dashboard may have just run them (but no caching bridge). | Cache `CampaignData` collection on the service instance for the request; better: memoize via `Cache::remember` with the same `report_version_{id}` tag the API controller uses. |
| C8 | `DashboardController.php:27` + ReportApi summary | Dashboard view has **no cache** while API summary caches for 1h. Client opens dashboard → every page load re-aggregates. | Wrap `$metricsService->getMetrics()` in `Cache::remember("dashboard_metrics_{id}_v{ver}_{start}_{end}_{budgetFlag}", 600, ...)` keyed off `report_version_{campaign_id}` used by ReportImportService. |

## High

| # | File:line | Cost | Impact | Fix |
|---|-----------|------|--------|-----|
| H1 | `ReportImportService.php:87` | `PlacementData::where(...)->delete()` deletes one-by-one with model events; for large files that is slow. | Use `DB::table('placements_data')->where(...)->delete()` (no model events — there's no `PlacementDataObserver`). |
| H2 | `ReportImportService.php:149-151` | Bulk `insert()` in chunks of 500 is fine — but the whole import is **not wrapped in a DB transaction**. Partial failures leave inconsistent state. | Wrap the delete + inserts + `updateOrCreate` + `$campaign->update` in `DB::transaction(function () { ... })`. Also adds crash-safety and reduces auto-commit overhead. |
| H3 | `ReportImportService.php:35` | `Excel::toCollection(null, $file)->first()` loads the **entire** sheet into memory, then iterates it twice (header detection pass + data pass). 50k-row import = 50k collection objects. | Use `Excel::import()` with a `WithChunkReading` importer (chunk 1000), or at minimum read the collection once and cache `$headers` instead of re-detecting in the 2nd loop. |
| H4 | `ReportImportService.php:155` | `$collection->reverse()->first(...)` creates a reversed copy of the whole sheet just to find the last row with uniques. | Capture the last valid `uniques` value during the existing 2nd loop (`$summary['uniques'] = $thisRowUniques` each iteration). Removes the reverse + extra pass. |
| H5 | `CampaignMetricsService.php:67-69` | Three `->pluck()` calls on the same collection (labels, impressions, clicks) = 3 iterations. | Single `foreach` building all three arrays in one pass. Trivial; removes 2N work. |
| H6 | `CreativeController.php:128` | Inside upload loop: `$creative->files()->where(...)->get()` per uploaded file, then `Storage::delete()` + `$old->delete()` per matching old file. When uploading a bulk drop (say 20 sizes), this is 20 queries + N deletes. | Pre-load `$existingFiles = $creative->files()->get()->groupBy(fn($f) => $f->width.'x'.$f->height)` once before the loop. Match in memory; delete in a single `whereIn('id', $ids)->delete()` at the end. |
| H7 | `CreativeController.php:212-239` | `downloadAll()`: `Storage::disk('creatives')->get($file->path)` reads each file fully into memory, then `$zip->addFromString()` keeps it until close. A creative with 10 × 50MB videos = 500MB peak memory. | Use `$zip->addFile(Storage::disk('creatives')->path($file->path), $file->name)` — zips stream from disk, no memory load. |
| H8 | `ReportApiController.php:210-220` | `campaigns()` API does `with('client')` but filters `whereHas('client', fn → whereIn(id, accessibleClientIds()))`. `accessibleClientIds()` is called per-request with no caching — examines pivot tables. | Cache `accessibleClientIds()` on the User instance (memoize via a `protected ?array $accessibleIds` property) or wrap in request-scoped cache. |

## Medium

| # | File:line | Cost | Impact | Fix |
|---|-----------|------|--------|-----|
| M1 | `CreativeController.php:111-114` | `shell_exec('ffprobe ...')` runs per video upload synchronously. 200-500ms each. | Dispatch a queued job `ExtractVideoDimensionsJob` after file save; store 0×0 temporarily. |
| M2 | `CreativeController.php:140` | `Intervention\Image` re-encodes every image in the request loop (JPEG compression, etc.) synchronously in the web process. 20 large images → 10-20s request. | Queue re-encoding/EXIF-stripping after upload, or at minimum release the request after first 3 files and queue the rest. |
| M3 | `ReportApiController.php:210` | `->paginate(50)` but also calls `->getCollection()->transform()` — works, but the underlying pagination has already loaded all 50 Eloquent models + `client` relation. Using `->select('id','name','client_id','created_at')` would avoid hydrating unneeded columns (description/meta/etc.). | Add `->select(['id','name','client_id','created_at'])` before `paginate()`. |
| M4 | `Exports/CampaignByDatesSheet.php` + `CampaignByPlacementsSheet.php` (all `FromView`) | `FromView` renders a Blade template into a DOM, then PhpSpreadsheet parses it. Fine for small reports, but memory spikes with 1000+ rows. Laravel-Excel's `FromQuery`+`WithChunkReading` is 10× lighter. | For campaigns > 500 rows, convert to `FromQuery` with chunk reading. Low priority unless exports time out. |
| M5 | `Exports/CampaignSummarySheet.php:45` | `Drawing::setPath(public_path('images/logo.png'))` reads logo each export. File I/O fine but repeated for every export. | Negligible; leave unless profiling shows it. |
| M6 | `TokenController.php:12` | `Auth::user()->tokens()->get(['id','name','created_at','expires_at'])` on token index — no pagination. If a user accumulates many tokens it grows unbounded. | Add `->orderByDesc('created_at')->paginate(25)`. |
| M7 | `CheckTokenExpiry.php:17` | `$request->user()?->currentAccessToken()` — this is resolved by Sanctum without an extra query if `auth:sanctum` already ran first. Confirm middleware order. If run before auth:sanctum on a route, it'd trigger a separate lookup. | Ensure `auth:sanctum` runs before `CheckTokenExpiry` in the `/api/reports/*` pipeline. Also: trim expired tokens via scheduled `php artisan sanctum:prune-expired --hours=24` cron. |
| M8 | `CampaignMetricsService.php:196-201` | `PlacementData::where('campaign_id', $id)->groupBy('name')` (export) — **no date filter**, aggregates all history every time a user exports. | Apply the same `$startDate/$endDate` filter used above. |
| M9 | `CreativeObserver.php` + `CreativeFileObserver.php` | Each file upload writes an `activity_logs` row synchronously — inside the upload loop that can also hit Intervention/ffprobe. | Queue activity logging (`ShouldQueue` on the observer methods is not supported, but ActivityLogger could `dispatch()` a `LogActivityJob`). |

## Low

| # | File:line | Cost | Impact | Fix |
|---|-----------|------|--------|-----|
| L1 | `DashboardController.php:25` | `session(['last_campaign_id' => ...])` on every dashboard load — session write per GET. | Only write if `session('last_campaign_id') !== $campaign->id`. |
| L2 | `CampaignMetricsService.php:71` | Raw SQL has mixed-case `sum(` vs `SUM(` — not a bug, but hurts query-cache normalization. | Normalize to uppercase. |
| L3 | `CreativeController.php:89` | `new ImageManager(new GdDriver)` created per request even if no images uploaded. | Lazy-init inside the `if ($isImage)` branch. |
| L4 | `ReportImportService.php:167` | `updateOrCreate` does SELECT then INSERT/UPDATE — 2 queries. | `upsert()` is one query when writing many, but for a single row updateOrCreate is acceptable. Leave. |
| L5 | `ReportApiController.php:232` | `JSON_UNESCAPED_UNICODE` flag passed everywhere — fine. | N/A. |

## Caching opportunities

| Data | Current | Recommended TTL | Expected gain |
|------|---------|-----------------|---------------|
| Dashboard metrics (per campaign + date range + budget flag) | None — computed on every page load | 600s, keyed with `report_version_{campaign_id}` (already bumped by `ReportImportService::invalidateReportCache`) | Removes 2-4 large aggregations per page load. Same invalidation path as API caches — free win. |
| `accessibleClientIds()` per user | Recomputed each call, can be called multiple times per request | Request-scoped memoization on `User` model | Saves pivot-table scans on `whereHas('client')` queries. |
| Report API `campaigns()` list | Not cached | 300s per user, key: user_id + start + end + page | Admin dashboards refreshing the list repeatedly get instant replies. |
| `CampaignMetricsService` — shared between Dashboard & Excel export | Re-runs both `getMetrics` and `getExportData` | In-request memoization; Export can reuse Dashboard's data | Halves work when admin opens dashboard then clicks Export. |
| Export Excel file | Regenerated each download | Store generated file keyed by `report_version` in `storage/app/exports`, serve if exists | Admins re-downloading the same report don't pay the render cost again. |

## Index recommendations

Create a new migration `add_perf_indexes_to_reporting_tables`:

```php
public function up(): void
{
    Schema::table('placements_data', function (Blueprint $table) {
        // Primary hot path: ReportApi::byPlacement + CampaignMetricsService placement aggregation
        $table->index(['campaign_id', 'report_date'], 'placements_data_campaign_date_idx');

        // Covering index for GROUP BY name within a campaign
        $table->index(['campaign_id', 'name', 'report_date'], 'placements_data_campaign_name_date_idx');
    });

    // campaign_data already has UNIQUE(campaign_id, report_date) — acts as a composite index.
    // No additional index needed on that table.

    // creatives.campaign_id was added via foreignId() which creates an index automatically.
    // Verify in production; if missing, add:
    // Schema::table('creatives', fn (Blueprint $t) => $t->index('campaign_id'));

    // creative_files.creative_id is indexed via foreignId(). OK.

    // personal_access_tokens: tokenable_type+tokenable_id already indexed via morphs();
    // token column has unique(); expires_at is indexed. All good.
}

public function down(): void
{
    Schema::table('placements_data', function (Blueprint $table) {
        $table->dropIndex('placements_data_campaign_date_idx');
        $table->dropIndex('placements_data_campaign_name_date_idx');
    });
}
```

**Notes:**
- The existing `placements_data.name` single-column index (from the migration) is nearly useless because queries always filter by `campaign_id` first. Could be dropped in the same migration to reduce write overhead.
- `campaign_data` has `UNIQUE(campaign_id, report_date)` which covers the hot path — no change needed.
- Sanctum's `personal_access_tokens.token` unique index covers the `auth:sanctum` lookup. `expires_at` is indexed — CheckTokenExpiry has no additional query cost beyond the normal token resolution.

---

## Summary of fixes by estimated impact

1. **C1/C2/C3/C4** — consolidate aggregation queries (1 query instead of 2-4 per endpoint): ~40-60% query count reduction on report endpoints.
2. **C5 + Index recs** — composite index on `placements_data(campaign_id, report_date)`: could be 10-100× query speedup depending on row count.
3. **C6/C7/C8** — move Dashboard aggregations DB-side + cache metrics: removes 200-500ms per dashboard load.
4. **H1-H4** — ReportImport transaction + single-pass parsing: halves import time and adds safety.
5. **H7** — `addFile` vs `addFromString` in `downloadAll`: removes memory ceiling for zip downloads.
6. **M1/M2/M9** — queue ffprobe, image re-encode, activity logs: removes 500-2000ms per upload request.
