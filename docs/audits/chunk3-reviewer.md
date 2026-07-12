# Chunk 3 — Creatives, Reporting & Exports (Reviewer)

**Date:** 2026-04-05
**Scope:** Creative controller/model/observer, DashboardController, ReportApiController, TokenController, Exports, ReportImportService, Creative Form Requests.

---

## Critical

### C1. Orphaned files on disk when Creative is deleted
**File:** `app/Http/Controllers/CreativeController.php:64-73`, `app/Observers/CreativeFileObserver.php`, `database/migrations/2026_02_02_110141_create_creative_files_table.php:16`
**Issue:** `creative_files.creative_id` uses `cascadeOnDelete()` at the DB level. When a Creative is deleted via `$creative->delete()`, MySQL removes the `creative_files` rows directly — Eloquent model events do NOT fire, and nothing purges the actual blobs from the `creatives` disk. Over time this leaks storage and makes GDPR/asset-deletion impossible to audit.
**Fix:** Either (a) add a `deleting` observer on `Creative` that iterates `$creative->files` and `Storage::disk('creatives')->delete($file->path)` BEFORE calling delete (child rows still exist at that point), or (b) drop the DB cascade and use Eloquent `deleting` to delete children through the model so `CreativeFileObserver::deleted` fires and centralise storage cleanup there. Option (a) is simpler.

### C2. `CreativeFileObserver::deleted` does not remove the file from disk
**File:** `app/Observers/CreativeFileObserver.php:44-52`
**Issue:** The observer only logs activity. File deletion from the `creatives` disk is inlined in `CreativeController::deleteFile()` and `CreativeController::upload()` (replacing same-dimension file). Any future deletion path (cascade, cleanup jobs, tests using `delete()`) silently leaks files.
**Fix:** Move `Storage::disk('creatives')->delete($creativeFile->path)` into `CreativeFileObserver::deleting()` (or `deleted()`), and remove the duplicated `Storage::delete` calls from the controller. Single source of truth for file lifecycle.

### C3. Cross-tenant leak in `downloadFile`/`deleteFile` via wrong policy ability
**File:** `app/Http/Controllers/CreativeController.php:166-206`
**Issue:** `deleteFile` uses `$this->authorize('update', $file->creative->campaign)` — good. But `preview` and `downloadFile` call `authorize('view', ...)`. A client user with `view` access on any campaign of the same client can preview creatives, which is fine — **but** `{file}` is route-model-bound with no scoping at all. A user could guess any `CreativeFile` id globally; binding relies purely on policy. If `CampaignPolicy::view` ever regressed, global file enumeration would be possible. Additionally, `downloadFile`/`preview` serve files even for campaigns belonging to clients the user should not see, if the policy loses scoping.
**Fix:** Low-risk hardening: add an explicit `$this->authorize('view', $file->creative->campaign)` AND assert `$file->creative->campaign->client_id` is in the user's accessible set (defence in depth). Also, in `CampaignPolicy::view`, confirm client-scoped users pass through `accessibleClientIds()`. This is defence-in-depth; worth doing because file URLs are shareable.

### C4. Report cache key does not differentiate video vs. non-video nor admin vs. client
**File:** `app/Http/Controllers/ReportApiController.php:28, 95, 158`
**Issue:** Cache key includes `canViewBudget`, but a user's `is_admin` flag (which controls video logic only indirectly) isn't separated; more importantly, `$campaign->is_video` is used inside the closure — fine for correctness, but if `is_video` flips during a report ingest (`ReportImportService::import` line 54 sets `is_video = true`), the old cache still serves the non-video summary until `invalidateReportCache()` bumps the version. That IS done in the service (line 201) — OK. **However** the cache version is read **once** at line 17-18 from `Cache::get(..., 0)`, so if the key never existed (first write) it's `0`. After increment it becomes `1` — fine. But `Cache::increment` on a non-existent key returns `false` in some cache stores (Redis is OK; file/database drivers differ). **Verify with config('cache.default')** — file/array drivers will silently fail to increment a missing key, meaning stale caches from first report import never invalidate.
**Fix:** Use `Cache::increment(... , 1)` and first seed with `Cache::add("report_version_{$id}", 0)` OR use `Cache::put` with fetched+1 value inside a lock. Alternatively, store version on the Campaign model itself (`$campaign->report_version`) to avoid cache-driver edge cases.

---

## High

### H1. Report JSON responses are not Laravel API Resources
**File:** `app/Http/Controllers/ReportApiController.php` (all methods)
**Issue:** All public API endpoints return ad-hoc arrays/paginators via `response()->json()`. No API Resource, no versioning, no documented contract. Consumers of `reports:read` tokens (external BI tools, per Sanctum) have no stable schema. Pagination in `campaigns()` returns Laravel paginator default shape, which may change across Laravel versions. CLAUDE.md and project_context.md mandate "Report API contracts consistent and documented via Resources".
**Fix:** Introduce `App\Http\Resources\SummaryReportResource`, `ByDateResource`, `ByPlacementResource`, `CampaignListResource`. Wrap collections in `ResourceCollection`. Add OpenAPI/README snippet in `docs/api/reports.md`.

### H2. `ReportApiController` uses `request('start')` — no validation
**File:** `app/Http/Controllers/ReportApiController.php:21, 90, 153, 207`
**Issue:** Date parameters are pulled directly from `request()` with no validation. Malformed input goes into `whereBetween('report_date', [$start, $end])` unchallenged. While Eloquent param-binds, invalid date strings produce MySQL warnings/empty results. Also no upper bound on range → risk of very heavy aggregation queries.
**Fix:** Use a `FormRequest` (e.g. `ReportRangeRequest`) validating `start|end` as `date_format:Y-m-d` and `end >= start`, with optional `max:365 days` span enforcement.

### H3. Controller not thin — `CreativeController::upload` is 90 lines
**File:** `app/Http/Controllers/CreativeController.php:75-164`
**Issue:** Dimension detection, image re-encoding, video streaming, duplicate-file cleanup, DB writes — all inline. Mixed responsibilities. ffprobe via `shell_exec` in a controller. No service class. Also inline validation rather than a `UploadCreativeFilesRequest`.
**Fix:** Extract to `App\Services\CreativeFileUploader` (or Action `UploadCreativeFileAction`) with methods `detectDimensions()`, `storeSanitized()`, `replaceDuplicates()`. Move validation to a `UploadCreativeFilesRequest` FormRequest. Controller shrinks to ~10 lines.

### H4. `shell_exec` without PATH validation or command existence check
**File:** `app/Http/Controllers/CreativeController.php:111-114`
**Issue:** `shell_exec('ffprobe ...')` assumes ffprobe is in PATH. On staging/prod without it, dimensions silently stay 0×0 — no error, no log. Also, while arg is `escapeshellarg`'d, shell_exec is still fragile; `Symfony\Component\Process\Process` is safer and gives proper error handling.
**Fix:** Use `Symfony\Component\Process\Process`, check exit code, log failures. Document ffprobe as a server dependency in `docs/specs/production-deploy-plan.md`.

### H5. `TokenController` has no `authorize()` guard — any authenticated user can create API tokens
**File:** `app/Http/Controllers/TokenController.php`
**Issue:** There is no role/permission check. Per project_context.md, tokens are for "campaign managers" / external reporting clients. A regular Viewer user (`is_report=false`, `is_admin=false`) can still mint `reports:read` tokens and access the Report API for every campaign they can see. More concerning: the token lifecycle has no rate limiting — a malicious/compromised user could mint unlimited tokens.
**Fix:** Gate routes with `->middleware('can:manage-tokens')` or check `hasPermission('is_report')` / `hasPermission('can_view_budget')` in controller. Add a `RateLimiter` for `tokens.create`. Consider adding a policy `TokenPolicy`.

### H6. Token expiry is always 30 days and user-controlled extend is infinite
**File:** `app/Http/Controllers/TokenController.php:24, 42-44`
**Issue:** `expires_at = now()->addDays(30)` is hardcoded. `extend()` lets any token owner repeatedly push expiry 30 days forward with no maximum lifetime, no approval, no audit log. This defeats the point of expiry.
**Fix:** (a) Make expiry configurable (`config('sanctum.token_ttl_days', 30)`); (b) cap total lifetime (e.g., original `created_at + 180 days`); (c) log `token.extended` via ActivityLogger; (d) consider admin-only extension beyond first renewal.

### H7. `CampaignSummarySheet::view()` leaks current Auth user to export
**File:** `app/Exports/CampaignSummarySheet.php:36`
**Issue:** The sheet injects `Auth::user()` into the export view. If this export is ever queued (which Maatwebsite/Excel supports and is recommended for large files), `Auth::user()` will be `null` inside the worker — template blows up with a 500 error that is hard to debug.
**Fix:** Pass the user from the controller constructor into the sheet explicitly: `new CampaignSummarySheet($campaign, $summary, $user)`.

---

## Medium

### M1. Duplication across `CampaignByDatesSheet` and `CampaignByPlacementsSheet`
**Files:** `app/Exports/CampaignByDatesSheet.php`, `app/Exports/CampaignByPlacementsSheet.php`
**Issue:** Both sheets have identical `columnWidths()` and `columnFormats()` arrays and nearly identical scaffolding (title, constructor boilerplate, view). Lots of copy-paste.
**Fix:** Extract `AbstractCampaignSheet` base class with default `columnWidths`/`columnFormats`, or a trait `WithStandardMetricsFormatting`. Reduces ~30 lines across the two sheets.

### M2. `CampaignFullSheet` is dead code
**File:** `app/Exports/CampaignFullSheet.php`
**Issue:** Not referenced by `CampaignExport::sheets()` or anywhere in the codebase. It also lacks `WithTitle` and produces a sheet named "Worksheet" by default. Either orphaned during a refactor or a remnant.
**Fix:** If unused, delete it (CLAUDE.md: "No dead code"). If it's a legacy all-in-one fallback export, document its use in the class header and wire it to a route.

### M3. `Creative` model missing `$casts` for `status`
**File:** `app/Models/Creative.php:12-17`
**Issue:** `status` is validated as `boolean` in the FormRequest but the model has no casts. Querying will return `'0'`/`'1'` or int depending on DB driver, and consumers have to guess. Also no `$casts` for `created_at`/default timestamps (those are handled).
**Fix:** Add `protected $casts = ['status' => 'boolean'];`.

### M4. `CreativeFile` missing `$casts` for numeric fields
**File:** `app/Models/CreativeFile.php`
**Issue:** `width`, `height`, `size` come back as strings in some MySQL drivers. Inconsistent type between API/export.
**Fix:** `protected $casts = ['width' => 'int', 'height' => 'int', 'size' => 'int'];`.

### M5. `CampaignData` model has no `report_date` cast
**File:** `app/Models/CampaignData.php`
**Issue:** `report_date` is returned as raw string. The Report API does `orderByDesc('report_date')->first()` and uses it directly — fine, but any Blade or calculation that expects a Carbon instance will break. Also no date casts means `CampaignData::where('report_date', now())` compares a datetime string against a date column (subtle bug surface).
**Fix:** `protected $casts = ['report_date' => 'date'];`. Same for `PlacementData`.

### M6. `StoreCreativeRequest` and `UpdateCreativeRequest` are identical
**Files:** `app/Http/Requests/StoreCreativeRequest.php`, `app/Http/Requests/UpdateCreativeRequest.php`
**Issue:** Byte-for-byte identical rules. Violates DRY.
**Fix:** Either have `UpdateCreativeRequest extends StoreCreativeRequest` and add update-specific tweaks (e.g., `sometimes` on fields), OR keep one `CreativeRequest` used by both routes. Update is often more lenient (partial update) — worth distinguishing in rules.

### M7. `ReportImportService::import` reparses headers in loop twice and relies on `$headers` leaking into second pass
**File:** `app/Services/ReportImportService.php:42-145`
**Issue:** Two loops through the collection, each checking `empty($headers)`, plus the first-loop header assignment leaks into the second loop (by accident, because the first loop `continue`s after assignment). This is fragile — reorganizing the loops will silently break header detection.
**Fix:** Do header detection ONCE before the loops; pass `$headers` explicitly. Consider extracting `detectHeaders($collection)`, `detectReportDate($collection, $headers)`, `parseRows(...)` — each a single responsibility. Easier to unit-test.

### M8. `ReportImportService` modifies Campaign inside a stream loop (`is_video` update)
**File:** `app/Services/ReportImportService.php:53-55`
**Issue:** `$campaign->update(['is_video' => true])` is called during row iteration. If the same Campaign is re-imported with a non-video file later, `is_video` is never reset to `false`. Also triggers `CampaignObserver::updated`, writing noise to activity_logs on every import.
**Fix:** Compute `$isVideo` as a local boolean, update the campaign once at the end. Consider whether `is_video` should also reset to `false` when no video columns are detected.

### M9. `DashboardController::show` pulls `start_date`/`end_date` from raw `request()` with no validation
**File:** `app/Http/Controllers/DashboardController.php:21-22, 53-54`
**Issue:** No date format validation; passed directly to `CampaignMetricsService`. Probably sanitised inside the service, but at the controller boundary it's raw user input.
**Fix:** Use a `ShowDashboardRequest` FormRequest with `date_format` rules, or a scoped `Request $request` with validation block.

### M10. `ReportApiController::campaigns()` — N+1 avoidance via `with('client')` but transforms break pagination shape
**File:** `app/Http/Controllers/ReportApiController.php:205-233`
**Issue:** Replacing the paginator's collection with a transformed array is fine, but the JSON will contain Laravel's full paginator envelope (`current_page`, `first_page_url`, `next_page_url`, `path`). For a token-consumed API, URL metadata exposes internal routes. Also, `getCollection()->transform` mutates the underlying paginator — fine, but a Resource Collection would be cleaner.
**Fix:** Use `CampaignListResource::collection($paginated)` → Laravel wraps in `data` + `meta` + `links`, hiding raw paginator shape.

### M11. No ordering stability in `byPlacement` aggregate
**File:** `app/Http/Controllers/ReportApiController.php:175`
**Issue:** `orderByDesc('report_date')` after `groupBy('name')` orders by MAX(report_date) implicitly. If two placements share the same latest date, ordering is non-deterministic across runs.
**Fix:** Add a secondary `->orderBy('name')` for stable ordering.

---

## Low / Nitpicks

### L1. `CreativeController::ALLOWED_MIMES` and `ALLOWED_MIME_TYPES` duplicated
**File:** `app/Http/Controllers/CreativeController.php:21-23`
**Fix:** Single constant array; derive both strings. Or move to `config/creatives.php`.

### L2. `storage_path('app/temp')` with `mkdir` on every downloadAll call
**File:** `app/Http/Controllers/CreativeController.php:219-222`
**Fix:** Create the directory once in a `boot` method / service provider, or use `Storage::createDirectory`.

### L3. `downloadAll` loads entire files into memory with `Storage::get()`
**File:** `app/Http/Controllers/CreativeController.php:235`
**Issue:** For 50MB videos × many files, this balloons PHP memory.
**Fix:** Use `$zip->addFile(Storage::disk('creatives')->path($file->path), $file->name)` which streams from disk.

### L4. `ZipArchive` errors not granularly handled
**File:** `app/Http/Controllers/CreativeController.php:229-239`
**Fix:** Log which files failed to add; currently silent on missing files.

### L5. `CreativeObserver` logs raw change values including potentially long/URL fields
**File:** `app/Observers/CreativeObserver.php:36-38`
**Issue:** `$changeDetails[] = $key.': "'.$value.'"';` — `landing` URL could be 2048 chars, flooding activity logs.
**Fix:** Truncate values at ~80 chars in description; keep full values in the `$changes` payload.

### L6. `CreativeObserver` `deleted` handler missing safety check
**File:** `app/Observers/CreativeObserver.php:47-50`
**Issue:** If `$creative` has soft-deletes someday, `deleted` fires on soft delete too. Currently not soft-deletable, but worth noting.

### L7. `ReportApiController` summary CTR/pacing math floor-cases with `max(1, ...)` then rounds — produces visually odd values for empty campaigns
**File:** `app/Http/Controllers/ReportApiController.php:50, 53`
**Issue:** For campaigns with 0 impressions, `ctr` returns `0.0` as expected, but `frequency = sumImpressions / max(1, 0)` → 0, which is misleading (should arguably be `null`).
**Fix:** Return `null` for ratios when the denominator is zero; let the frontend display "—".

### L8. `CampaignData` / `PlacementData` lack `belongsTo` relation back to Campaign
**Files:** `app/Models/CampaignData.php`, `app/Models/PlacementData.php`
**Issue:** No `campaign()` relation. Forces raw queries; tests and future consumers will hand-roll joins.
**Fix:** Add `public function campaign() { return $this->belongsTo(Campaign::class); }` on both.

### L9. `PlacementData` table is `placements_data` (plural+plural) — non-Laravel convention
**File:** `app/Models/PlacementData.php:12`
**Issue:** Convention would be `placement_data` (singular+singular, matching `campaign_data`).
**Fix:** Rename via migration if safe; otherwise document the inconsistency in `docs/lessons.md`.

### L10. Export filename not sanitised
**File:** `app/Http/Controllers/DashboardController.php:67`
**Issue:** `'MadData_'.$campaign->name.'.xlsx'` — if `$campaign->name` contains `/`, `\`, quotes, or unicode, browsers may reject or mangle the download.
**Fix:** `Str::slug($campaign->name)` before concatenation.

### L11. `TokenController::index` returns raw DB columns
**File:** `app/Http/Controllers/TokenController.php:12`
**Fix:** No issue security-wise (no `token` hash leaked), but mapping through a View Model / Resource would be more consistent.

### L12. `CreativeFileObserver::updated` merges current dimension state into `$changes` — misleading
**File:** `app/Observers/CreativeFileObserver.php:33-41`
**Issue:** `$changes` represents the dirty attributes; overwriting with current values hides what actually changed.
**Fix:** Keep `$changes` as-is from `getChanges()`; add a separate `context` key for dimensions.

---

## Positive observations

- **Multi-tenant discipline in CreativeController:** every method gates via `$this->authorize('update'|'view', $creative->campaign)` — consistent, policy-driven, no inline role checks. Matches project_context.md RBAC requirements.
- **Image re-encoding strips EXIF** (`CreativeController.php:140`) — good proactive privacy/security measure.
- **Stream-based video upload** avoids loading 50MB into PHP memory (line 144-148).
- **Temp zip stored outside public/** (line 218-222) — prevents webroot enumeration.
- **Preview response hardened** with `X-Content-Type-Options: nosniff`, strict CSP, `Content-Disposition: inline` (lines 186-193). Good defence-in-depth for user-supplied files.
- **Cache versioning pattern** in `ReportApiController` (`report_version_{$campaign->id}`) is elegant — avoids enumerating cache keys for invalidation. Just watch the driver edge case in C4.
- **ReportImportService::invalidateReportCache** cleanly bumps the version after every import — coupling is correct.
- **DashboardController is genuinely thin** — delegates to `CampaignMetricsService`. Exemplary.
- **Bulk insert chunking (500) in ReportImportService:149** respects MySQL packet limits.
- **Observers implement `ShouldHandleEventsAfterCommit`** — correct pattern, no half-logged rows on failed transactions.
- **Campaigns and clients eager-loaded** in `ReportApiController::campaigns` (`with('client')`) — no N+1.
- **`is_video` auto-detection** during report import is a nice UX touch (even though M8 raises concerns about the update path).
