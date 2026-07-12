# Performance Audit — April 2026

**Created:** 2026-04-11
**Status:** Findings recorded. Fix wave deferred until after production cutover to new droplet.
**Scope:** Static audit of the MadData Laravel codebase against an external playbook describing the "Load the World" and hidden N+1 patterns. 15 findings across 4 of the 5 playbook patterns. Pattern 3 (observability compounding) is not applicable — no Telescope/Debugbar/Clockwork/Bullet installed.

## Context

The playbook this audit was run against was written after an April 2026 incident on an ad-serving platform called Erate, where a single campaign-placements page was loading 184,704 database rows to render 15 rows on screen. Investigation revealed a nested `with(['channels.placements'])` eager load that worked on ~20 rows of dev data and catastrophically broke on 2,300 sites × 5,690 channels × 50,000 placements of real data. The playbook prescribes five anti-patterns to hunt for: load-the-world nested eager loads, hidden N+1 via Blade partials accessing lazy relations in loops, observability tooling compounding query-size problems, over-fetching `TEXT`/`JSON`/`BLOB` columns, and ORM joins that should be SQL joins.

**Why this audit now:** during Phase 6 cutover rehearsal, the user asked for a proactive audit against this playbook before real creative and activity-log data starts flowing through the system. Current data scale (from imported old-prod snapshot) is 16 users, 35 clients, 76 campaigns, 1,021 campaign_data rows, 11,774 placement rows. Zero creatives and zero activity_logs because old prod predates those features. The audit therefore catches bugs that are latent today and will fire the first time real creative data arrives.

## TL;DR

The codebase is not sitting on a ticking "load the world" bomb. The Phase 2 P1–P11 performance work from the earlier security/perf audit already addressed the biggest offenders: query consolidation, indexes, pagination of list endpoints. The recent "double pagination" fix (commit `0ba0656`) intentionally reverted four controllers to `->get()` for client-side pagination, a conscious trade-off weighed against current scale.

The real findings are **four genuine Pattern-2 hidden N+1s**, **one latent Pattern 1 in an email dispatch path**, **one explicit `Model::find()` in a loop**, and a smattering of Pattern 4 over-fetching. None is catastrophic today; several become visible around 1k–10k rows of the dominant entity. Fix difficulty ranges from trivial to surgical — no structural rewrites.

## Findings

| # | File:Line | Pattern | Specific anti-pattern match | Scale at which it breaks | Fix category |
|---|---|---|---|---|---|
| **1** | `resources/views/components/campaign/creatives-accordion.blade.php:95,97` | **Pattern 2** (hidden N+1 in partial) | `$creative->files->isNotEmpty()` and `$creative->files->count()` inside `@foreach($campaign->creatives as $creative)`. Controller `CampaignController.php:182` loads `['creatives', 'audiences', 'locations']` — missing `creatives.files`. One extra query per creative. | Any campaign with >5 creatives on the edit page. Linear in creatives count. | **Trivial** — add `creatives.files` to the eager load |
| **2** | `resources/views/emails/activity_digest.blade.php:41` | **Pattern 2** (hidden N+1 in partial) | `$log->subject->creative` inside `@foreach($logs as $log)`. Parent query `ActivityLogger.php:50` does `with(['user', 'campaign.client', 'subject'])` without `morphWith` on the polymorphic subject. Every CreativeFile log triggers one lazy `creatives` SELECT during email render. | Digest emails with >10 CreativeFile logs. Invisible at dev scale; immediate at real usage (file upload bursts). | **Surgical** — replace with `'subject' => fn($m) => $m->morphWith([CreativeFile::class => ['creative']])`, matching the pattern already used correctly in `Admin/ActivityLogController.php:17-25` |
| **3** | `resources/views/admin/campaign_changes/show.blade.php:86` | **Pattern 2 (overt)** — model find in loop | `Creative::find($log->changes['creative_id'])` literally inside `@foreach($logs as $log)`. Not eager-loaded from anywhere. Fires one `SELECT creatives WHERE id=?` per iteration where the creative fallback path triggers. | Any campaign with >20 pending logs containing `creative_id` in their changes JSON. 100 logs → 100 extra queries. | **Surgical** — pre-collect `creative_id`s in the controller, batch-load `Creative::whereIn('id', $ids)->get()->keyBy('id')`, pass as lookup map |
| **4** | `resources/views/admin/campaign_changes/show.blade.php:116-128` | **Pattern 2 cousin** — filesystem I/O in loop | `Storage::disk('creatives')->exists()`, `Storage::disk('creatives')->path()`, `getimagesize()` called inside `@foreach($logs as $log)`. Not DB queries, but structurally identical — synchronous disk I/O per row. Falls back to reading file headers to get dimensions that should have been persisted at upload time. | Any campaign showing >50 pending logs where width/height columns are null. 20-100ms per hit under load. | **Surgical** — ensure `CreativeFile.width` and `CreativeFile.height` are always persisted at upload time in `CreativeController::upload`; remove the fallback `getimagesize` path from the view entirely |
| **5** | `app/Services/ActivityLogger.php:50` | **Pattern 1** (unbounded `->get()` + nested `with`) | `ActivityLog::with(['user', 'campaign.client', 'subject'])->where('created_at', '>', $since)->get()` — unpaginated. Fetches ALL activity logs from the last 2-hour window to build a digest email. Fires on every CRUD write that passes the 2-hour cache gate in `ActivityLogger.php:46`. | Any 2-hour window with >1,000 activity log rows. Worst case: bulk upload driving hundreds of Creative/CreativeFile created events in a burst — loads all of them into memory inside the same HTTP request that triggered the last one. | **Surgical** — three complementary fixes: (a) move digest send into a queued job so the fetch doesn't happen in the request path; (b) add `->limit(1000)` safety cap; (c) select only columns the email renders (`changes` JSON is fetched but never rendered) |
| **6** | `app/Http/Controllers/Admin/CampaignChangeController.php:64-78` | **Pattern 1** (unbounded `->get()`) | `$campaign->activityLogs()->pending()->with(['user', 'subject' => …])->orderBy(...)->get()` then `->unique(fn)` then `->sortByDesc(...)` in PHP. No `->limit()`, no pagination. Dedupe logic is in PHP, so the full result set must be loaded. | Any campaign with >500 pending activity logs. Memory and render time scale linearly. Today: every campaign has 0 activity logs. | **Structural** — fold dedupe into SQL via `ROW_NUMBER() OVER (PARTITION BY ... ORDER BY created_at DESC)` and paginate. Or enforce hard cap (`->limit(500)`) with a "some older pending changes truncated" banner |
| **7** | `app/Http/Controllers/Admin/ActivityLogController.php:29` | **Pattern 1** (large `IN (...)` in tenant scope) | For non-admins: `Campaign::whereIn('client_id', $user->accessibleClientIds())->pluck('id')` — plucks all accessible campaign IDs, then stuffs them into `$query->whereIn('campaign_id', $allowedCampaignIds)`. Exact playbook signature: unbounded `IN (...)` clause. | Any user with access to >2,000 campaigns generates multi-KB `IN` SQL clauses. Same shape as the incident's 5,690-element `IN` clause, sourced from pivot-table membership instead of nested eager loading. | **Structural** — rewrite as `whereExists` with correlated subquery. Filter stays server-side; never materializes the ID list in PHP |
| **8** | `app/Http/Controllers/CampaignController.php:94,101` | **Pattern 1** (large `IN (...)` in tenant scope) — cousin of #7 | Same `->pluck('id')` + `->whereIn($allCampaignIds, ...)` shape, twice in the same method. Nested pluck chain. | Any non-admin user whose clients own >2,000 campaigns. Less acute than #7 because `CampaignData` is a hot aggregation query (daily metrics), not a full listing — but same `IN` bloat applies. | **Structural** — fold into Eloquent subquery: `CampaignData::whereIn('campaign_id', Campaign::where(...)->select('id'))->...` |
| **9** | `app/Http/Controllers/CampaignController.php:44-46` | **Pattern 1** (unbounded `->get()`) + **Pattern 4** (over-fetching) | `$campaigns = $query->get()` with no limit. Every row pulls `required_sizes TEXT` and `targeting_rules JSON` columns which are never rendered in `resources/views/campaigns/index.blade.php` (zero references). | Functional break: ~5,000 campaigns (HTML page weight >2 MB). Memory break: ~50,000 campaigns (PHP OOM at 256 MB). Over-fetching is a constant tax: at 76 campaigns × ~3 KB `targeting_rules` = ~230 KB wasted per load. | **Structural-at-scale, Surgical-now.** Add `->select([...])` excluding text/json columns. Future: when campaigns cross ~1k, teach MadDataTable to do server-side pagination via XHR (same prescription documented in commit `0ba0656`) |
| **10** | `app/Http/Controllers/CampaignController.php:87,126,184` | **Pattern 1 (mitigated)** | `Client::all()` inside `Cache::remember('clients_list', 300, …)`. Loads all 35 clients with every column. Used only in form dropdowns. | Harmless today (cached 5 min, 35 rows). Mild concern at ~10k clients when cache expires. | **Trivial** — `Client::select('id', 'name', 'agency_id')->orderBy('name')->get()` on all three sites |
| **11** | `app/Http/Controllers/UserController.php:24` | **Pattern 1 (contained)** — example of correct pattern | `User::with(['userRole', 'agencies:id,name', 'clients:id,name'])` — three-way eager load for admin users list. Uses explicit column lists, paginated at 25. Not a risk. Kept in the table as the *reference* for how findings 1, 2, 5, 9, 10 should look after fixing. | None | **None needed** |
| **12** | `app/Http/Controllers/Admin/CampaignChangeController.php:53` | **Pattern 1 (contained)** | `Campaign::whereHas('activityLogs', ...)->withCount([...])->with('client')->paginate(25)`. Exact incident shape (`whereHas` + `withCount` + `with`) but bounded by `paginate(25)`. | Not a scale risk. Flag: `whereHas` → correlated subquery can be slow on MySQL without proper indexes. Audit performance indexes migration already addresses the most important ones. | **None needed now.** Future: if slow, `whereHas` → `whereExists` rewrite often helps the planner |
| **13** | `resources/views/campaigns/index.blade.php:209` | **Pattern 5 cousin** (dead code + latent bug) | `{{ $campaign->client->agency ?? '' }}` — `agency` is a `BelongsTo` returning Agency model, not a string. Enclosing `<td>` has `class="... hidden"`, so it never displays, but Blade still evaluates. If null-coalesce doesn't fire (it won't — all 35 clients have `agency_id NOT NULL`), this calls `htmlspecialchars()` on an Agency object. | Latent display bug. Either dead code or broken — should be `$campaign->client->agency->name`. | **Trivial** — delete the `<td>` if dead, or fix to `->agency->name` if intended |
| **14** | `app/Providers/AppServiceProvider.php` | **Meta** — no strict-loading guard | No domain model sets `protected $strictLoading = true`, and `AppServiceProvider::boot()` does not call `Model::preventLazyLoading(! app()->isProduction())`. Playbook recommends this as the framework-level backstop that makes hidden N+1s impossible. | Consequence: every future developer and every future AI assistant can reintroduce Pattern 2 bugs with no alarm. | **Trivial** (high leverage) — one line in `AppServiceProvider::boot()`. Must be added **together with** fixes for #1–#4, otherwise test suite immediately turns red |
| **15** | `tests/Feature/**/*.php` | **Meta** — no query-count regression tests | No test enforces a query budget or asserts on absence of large `IN (...)` clauses. Existing pagination test at `CampaignCrudTest.php:463` checks row count, not query count. | Every fix for #1–#9 can be silently undone by a future commit with no test failure. | **Structural** (low difficulty) — add one `DB::enableQueryLog()` + query-count assertion per list endpoint. ~15 min per test file |

## Pattern coverage summary

| Pattern | Hits | Severity distribution |
|---|---|---|
| 1. Load the world / unbounded get with nested with | 5 (findings 5, 6, 7, 8, 9) | 1 acute (#5), 2 at-scale-latent (#6, #9), 2 tenant-scope cousins (#7, #8) |
| 2. Hidden N+1 in view partials | 4 (findings 1, 2, 3, 4) | 2 database N+1, 1 overt `Model::find()` in loop, 1 filesystem I/O in loop |
| 3. Observability compounding | 0 | Not applicable — no Telescope/Debugbar/Clockwork/Bullet installed |
| 4. Over-fetching columns | 2 (findings 5, 9) | Both latent, trivially fixable via `select()` |
| 5. ORM joins that should be SQL joins | 0 acute | CampaignController::index is distant candidate at >1k campaigns |
| Meta | 2 (findings 14, 15) | Absence of framework-level guards and regression tests |

## Fix ordering by impact

Prescribed order for the eventual fix wave, worst waste ratio first:

1. **#5** — `ActivityLogger::checkAndSendDigest()` unbounded `->get()` with nested `with`. Fires inside write requests — worst potential blast radius.
2. **#3** — `Creative::find()` in loop. Overt model-find-in-loop, impossible to miss once it fires.
3. **#4** — Filesystem `getimagesize()` in loop. Structurally identical, each hit 10-100× slower than a DB query.
4. **#1** — `creatives-accordion.blade.php` missing `creatives.files`. Most commonly hit page.
5. **#2** — Digest email `morphWith` missing. Runs in queue worker, lower user-visible impact.
6. **#7 + #8** — Large `IN (...)` tenant scope rewrites to `whereExists`.
7. **#6** — Unbounded `->get()` + PHP dedupe in `CampaignChangeController::show`.
8. **#9** — `select()` tightening in `CampaignController::index`.
9. **#10** — `Client::all()` in cached dropdown.
10. **#13** — Dead-code agency cell.

**Meta fixes (#14, #15)** should be added **simultaneously** with the application-code fixes. Finding #14 in particular cannot land before #1–#4 — turning on `preventLazyLoading` while those N+1s still exist will immediately red-line the test suite.

## Why this is deferred until post-cutover

Decision taken 2026-04-11 during Phase 6 cutover rehearsal:

1. **We are mid-migration.** Every code change to main has to redeploy through staging → new droplet → final cutover, and each change invalidates part of the Phase 6 rehearsal already completed.
2. **Near-zero user impact today.** Current scale (76 campaigns, 0 creatives, 0 activity logs) means none of the findings actually hurt the app. The bugs are latent — they become visible only once real creative and activity-log data starts flowing, which by definition can't happen until after cutover.
3. **The highest-leverage meta-fix (#14) is most valuable after the N+1s are fixed,** not during cutover. Turning on strict lazy loading mid-cutover would crash every request that currently relies on a lazy load, and we'd debug Blade files instead of watching logs.
4. **Old droplet stays as cold backup for 2 weeks post-cutover** per the migration plan — that's a natural window for a performance hardening pass without production risk.
5. **Stress-seeding needed before fixes are verifiable.** Fixing N+1s against 0-row tables is fixing by faith. Proper verification requires seeding 500 creatives + 5,000 activity logs + 2,000 CreativeFiles first.

## Prerequisites before starting the fix wave

- [ ] Production cutover to new droplet is complete and stable for ≥ 24 hours
- [ ] Old droplet is confirmed as viable rollback (but not used)
- [ ] Stress-test seeder exists: 500 creatives, 2,000 CreativeFiles, 5,000 activity logs distributed across existing campaigns
- [ ] Baseline measurement captured: query count per list endpoint, before any fix
- [ ] Decision on whether to add Telescope for this audit window only, or stick with MySQL slow-query log + manual `DB::enableQueryLog()` in tests (recommended: the latter — avoids Pattern 3 from the playbook entirely)

## Fix wave plan

Do it in one branch, not piecemeal. Reasoning: the meta fixes (#14, #15) are only valuable when all of #1–#4 are already fixed, and the fix wave should produce a single coherent before/after measurement.

### Phase A — Prep
- Write `DatabaseSeeder` or `StressTestSeeder` producing realistic creative and activity-log volumes
- Run it against the (already-cutover) production DB in a scratch transaction, measure baseline query counts per endpoint, capture the numbers in this spec file as "Before" measurements

### Phase B — Application fixes (in impact order)
- Fix #5 (ActivityLogger dispatch) — move to queued job + limit + select
- Fix #3 (Creative::find in loop) — batch pre-load in controller
- Fix #4 (filesystem I/O in loop) — ensure width/height persisted at upload, delete fallback path
- Fix #1 (creatives-accordion missing files eager load)
- Fix #2 (digest email morphWith)
- Fix #7 + #8 (tenant-scope whereExists rewrites)
- Fix #6 (CampaignChangeController dedupe)
- Fix #9 (CampaignController select tightening)
- Fix #10 (Client::all select tightening, three sites)
- Fix #13 (agency cell — delete or repair)

### Phase C — Meta fixes
- Fix #14: add `Model::preventLazyLoading(! app()->isProduction())` to `AppServiceProvider::boot()`. Run the full test suite — any still-red test is an N+1 the static audit missed. Fix each one by adding the needed eager load, not by disabling the guard.
- Fix #15: add query-count regression tests to `CampaignCrudTest`, `ClientCrudTest`, `UserCrudTest`, plus new `ActivityLogControllerTest`, `CampaignChangeControllerTest`. Budget ≤ 12 queries per list page.

### Phase D — Verify
- Re-run the baseline measurement from Phase A. Document before/after in this file.
- Run the stress-seeded test suite with `Model::preventLazyLoading` on — must be green.
- Manual smoke test: load every list page and every detail page against the stress-seeded data. Verify subjective responsiveness.

### Phase E — Ship
- Single PR, single commit message referencing this spec.
- Deploy to new droplet using the backup/restore + maintenance-mode flow documented in `production-new-droplet-migration.md` Part 2.
- Monitor MySQL slow query log for 24 hours after deploy.

## What I did NOT find (explicit negatives)

These are anti-patterns the playbook hunts for that were searched and not found:

- Nested eager loads with 3+ levels (`with(['a.b.c'])`). No matches in `app/`.
- Non-cached `Model::all()` in controllers. The three `Client::all()` occurrences are all inside `Cache::remember`.
- Telescope / Debugbar / Clockwork / Bullet. None installed.
- `eager: true` on model relationships.
- Chained `->with('a')->with('b')` calls in views.
- Large `JSON` columns in list views without trimming (beyond finding #9).
- Polymorphic relations without `morphWith` in admin contexts (beyond finding #2).
- Over-eager `->withCount()` on >2 relationships in the same query.
