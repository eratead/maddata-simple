# Chunk 2 — Campaigns Core (Reviewer)

**Date:** 2026-04-05
**Scope:** CampaignController, CampaignAssistantController, AiLocationController,
Campaign/Audience/CampaignLocation models, Store/UpdateCampaignRequest,
CampaignObserver, CampaignMetricsService, UpdateCampaignStatuses command.

---

## Critical

### C1. `AiLocationController::generate` has no auth/permission guard
**File:** `app/Http/Controllers/AiLocationController.php:10-12`
Route presumably under `auth` middleware, but unlike `CampaignAssistantController::chat`
there is no `hasPermission('can_edit_campaigns')` check. Any authenticated user
(including read-only clients) can burn Anthropic API credits by sending
unlimited prompts. This is both a cost/DoS vector and violates the Client
"read-only view" constraint in `project_context.md` §1.
**Fix:** Add `abort_unless(auth()->user()->hasPermission('can_edit_campaigns'), 403);`
at the top of `generate()`, or gate the route with a middleware.

### C2. Privilege escalation: non-admins can edit `expected_impressions` on create
**File:** `app/Http/Controllers/CampaignController.php:120-142` vs `:246-248`
`update()` strips `expected_impressions` for non-admins, but `store()` does not.
A campaign manager can set any `expected_impressions` at creation time, which
directly manipulates CPM/spent calculations (see
`CampaignMetricsService:108-113`). This is a business-logic bypass.
**Fix:** Mirror the update guard in `store()`:
```php
if (! Auth::user()->hasPermission('is_admin')) {
    unset($validated['expected_impressions']);
}
```

### C3. `CampaignAssistantController::chat` does not cap chat history size
**File:** `app/Http/Controllers/CampaignAssistantController.php:14-17,21-27`
`chatHistory` is `required|array` with no `max`. A user can POST a 10k-message
history, blowing Anthropic token limits, timing out requests, and burning cost.
Similarly, no per-message `content` length cap.
**Fix:** Add `'chatHistory' => 'required|array|max:40'`,
`'chatHistory.*.content' => 'required|string|max:4000'`, and truncate/trim
server-side.

---

## High

### H1. Location update in `CampaignController::update` is destructive & not transactional
**File:** `app/Http/Controllers/CampaignController.php:259-267`
Locations are deleted then recreated outside a DB transaction. If the second
insert fails (e.g., validation edge case, DB error), the user loses all
locations with no recovery. Also not an upsert, so IDs churn and any downstream
reference breaks.
**Fix:** Wrap in `DB::transaction(...)` (also covering `$campaign->update(...)`
and activity log writes) or use `syncMany`/upsert-by-id semantics.

### H2. JSON-string comparison for location diff is brittle
**File:** `app/Http/Controllers/CampaignController.php:276`
`json_encode($oldLocations) !== json_encode($newLocations)` depends on
associative-key order and float/int coercion. Re-saving identical data can log
spurious "updated" entries or miss real changes (e.g., `1000` vs `"1000"`).
**Fix:** Compare structurally after `ksort`/normalization, or better extract
both sides through the same normalizer array.

### H3. `Campaign` model missing `HasMany` for activity/targeting & `client_id` not protected from tampering on update
**File:** `app/Http/Controllers/CampaignController.php:239-241`, `app/Models/Campaign.php:12-24`
`client_id` is in `$fillable` and is mass-assigned on update. The guard at
:239-241 only checks that the user has access to the *target* client — it does
not check access to the *current* campaign's client. A user with access to
client A and B could re-assign campaign from A to B silently. While arguably
intentional, this permits cross-client leakage of historic metrics data
(campaign_data rows move with the campaign). Also, there is no check that the
user had access to the *original* `client_id` before reassignment. Combined
with no Policy method distinguishing `reassign`, this is a tenant-boundary
concern.
**Fix:** Either remove `client_id` from update-allowed fields (strip it after
validation for non-admins) or add an explicit `reassign` policy ability and
log the transition.

### H4. N+1 risk on `campaigns.index` view — `data` not eager-loaded but likely used
**File:** `app/Http/Controllers/CampaignController.php:29,42`
Only `client` is eager-loaded. If the Blade template iterates
`$campaign->data`, `$campaign->audiences`, `$campaign->locations` (for
pacing/targeting previews) this is N+1. The controller already computes
`pacingData` separately so this may be fine — but `campaigns.edit` explicitly
loads `creatives`, `audiences`, `locations` while index does not. Verify the
view and add eager loads if needed.
**Fix:** Audit `resources/views/campaigns/index.blade.php`; eager load
whatever it touches.

### H5. Observer silently coerces dirty JSON strings
**File:** `app/Observers/CampaignObserver.php:44-78`
The observer handles three representations of `targeting_rules` (array, JSON
string, decoded-JSON-string-inside-cities). This indicates the data at rest is
inconsistent — with `'targeting_rules' => 'array'` cast, values should always
be arrays. Smells like a historical bug that's being papered over. If cities
are sometimes stored as JSON strings nested inside an already-cast JSON
column, a migration/cleanup is warranted.
**Fix:** Write a data-cleanup migration, remove defensive decoding here, add
a failing test for the canonical shape.

### H6. `UpdateCampaignRequest::prepareForValidation` overwrites the entire `targeting_rules` blob
**File:** `app/Http/Requests/UpdateCampaignRequest.php:14-25`
`array_merge($this->input('targeting_rules', []), [...])` is correct on a flat
array, but the whole merged structure is written via `$this->merge([
'targeting_rules' => ... ])`. If any sibling key under `targeting_rules` was
mutated by an earlier `prepareForValidation` step in a parent request, it
would be lost. Not currently broken, but fragile.
**Fix:** Write back only the normalized `genders` key:
`$this->merge(['targeting_rules' => [...$existing, 'genders' => $normalized]]);`
(which is what it does, but the comment is that it operates on potentially-stale
input data). Low risk today — keep an eye on it.

---

## Medium

### M1. `Cache::remember('clients_list', 300, …)` never invalidated on client create/update/delete
**File:** `app/Http/Controllers/CampaignController.php:83,115,173`
Global 5-minute cache of all clients. When a client is added/renamed/deleted,
the campaign forms show stale data for up to 5 minutes. Also the cache is
unscoped — but admin-only, so no tenant leak, only staleness.
**Fix:** Invalidate via `Cache::forget('clients_list')` in a `ClientObserver`,
or drop the cache (admin list is small).

### M2. Duplicate cache+accessibleClients logic repeated 3x
**File:** `app/Http/Controllers/CampaignController.php:83,115,173`
Identical expression appears in `index`, `create`, `edit`. Violates DRY.
**Fix:** Extract a private helper `clientsForCurrentUser(): Collection`.

### M3. Thin-controller violation: pacing computation in `index`
**File:** `app/Http/Controllers/CampaignController.php:45-76,86-98`
40+ lines of metric aggregation belong in `CampaignMetricsService` (or a new
`CampaignListMetricsService`). Keeps controllers thin per CLAUDE.md.
**Fix:** Move pacing + yesterday-totals into a service method returning the
view payload.

### M4. Thin-controller violation: `update()` has substantial business logic (locations, diff, log)
**File:** `app/Http/Controllers/CampaignController.php:250-288`
The location-sync + diff-logging logic should live in a
`SyncCampaignLocationsAction` or similar.

### M5. AI prompt hard-codes domain vocabulary in controller
**File:** `app/Http/Controllers/CampaignAssistantController.php:65-101`
Large multi-line prompt with magic strings (ages, incomes, regions) embedded
inline. Both target values and translations live here but the same validation
vocabulary is also in `UpdateCampaignRequest` — two sources of truth. If a
value is added to one, the other drifts.
**Fix:** Extract targeting enums to `app/Constants/CampaignTargeting.php` or
dedicated enums; reference them from both the FormRequest rules and the
prompt builder.

### M6. `CampaignMetricsService` calls `Auth::user()` directly
**File:** `app/Services/CampaignMetricsService.php:105,155`
Services should be permission-agnostic or take the user as a parameter.
Makes the service harder to test and to reuse from CLI/queue contexts (no
auth user).
**Fix:** Accept `?User $user = null` or `bool $includeBudget` arg.

### M7. Duplicate budget/CPM computation across `getMetrics` and `getExportData`
**File:** `app/Services/CampaignMetricsService.php:100-118,155-174`
Near-identical logic duplicated; drift risk (e.g., `getMetrics` uses
`$summary['impressions']` while `getExportData` uses `$totalImpressions`).
**Fix:** Extract `computeBudgetMetrics(Campaign, int $impressions, int $clicks): array`.

### M8. `UpdateCampaignStatuses` command has no logging/audit entry
**File:** `app/Console/Commands/UpdateCampaignStatuses.php:30-34`
Bulk `update()` bypasses the `CampaignObserver::updated` hook (Eloquent events
fire on model instance updates only, not query-builder mass updates), so
status changes are NOT logged in `activity_logs`. This violates CLAUDE.md rule
"Every CRUD must log via ActivityLogger".
**Fix:** Iterate models (`->get()` then `->save()` each), or manually write
`activity_logs` rows after the bulk update.

### M9. No `before_or_equal:end_date` on `start_date` validation
**File:** `app/Http/Requests/StoreCampaignRequest.php:21-22`, `UpdateCampaignRequest.php:34-35`
Only `end_date >= start_date` is enforced. Acceptable, but for symmetry and
clearer errors consider adding `before_or_equal:end_date` to start_date.

### M10. Hard-coded status enum repeated
**File:** `StoreCampaignRequest.php:25`, `UpdateCampaignRequest.php:38`, `UpdateCampaignStatuses.php:32`, `CampaignController.php:87`
`'active'`/`'paused'` appear as magic strings in 4+ places.
**Fix:** Introduce a `CampaignStatus` PHP enum and reference it.

### M11. `syncAudiences` does not validate audience `is_active`
**File:** `app/Http/Controllers/CampaignController.php:196-204`
Validation uses `exists:audiences,id` only. An inactive audience can still be
attached via direct POST, even though the UI filters them out.
**Fix:** `'audience_ids.*' => ['integer', Rule::exists('audiences','id')->where('is_active', true)]`.

---

## Low / Nitpicks

### L1. `index($client_id = 0)` uses sentinel `0` for "all clients"
**File:** `app/Http/Controllers/CampaignController.php:23`
Magic number. Use `?int $clientId = null` with `is_null()` checks.

### L2. `client_id != 0` loose comparison
**File:** `app/Http/Controllers/CampaignController.php:31,79`
Use strict comparison `$clientId !== null` (after L1 refactor).

### L3. Spelling: "optimisation" vs "optimization"
**File:** `app/Observers/CampaignObserver.php:35-36`
Mixed US/UK: the DB column is `creative_optimization` but log text says
"optimisation". Pick one.

### L4. `CampaignLocation` model has no `$casts`
**File:** `app/Models/CampaignLocation.php:8-15`
`lat`/`lng` are treated as strings in the controller diff code (`:254`). Cast
`radius_meters` to int, `lat`/`lng` to `decimal:7` (or whatever the migration
uses) for consistency.

### L5. `Audience` model missing cast for `estimated_users`
**File:** `app/Models/Audience.php:23-25`
Probably an int column — should be cast to `int` for JSON responses.

### L6. `Campaign` model missing `$casts` for `expected_impressions`
**File:** `app/Models/Campaign.php:26-33`
Cast to `int` for consistency with the arithmetic at
`CampaignMetricsService:59,108`.

### L7. No factory trait declaration on `CampaignLocation`
**File:** `app/Models/CampaignLocation.php`
Missing `use HasFactory` — testing this model with `factory()` won't work.

### L8. `app(ActivityLogger::class)` used instead of constructor injection
**File:** `app/Http/Controllers/CampaignController.php:210,285`
Fine, but inconsistent — most services elsewhere are injected. Prefer
constructor injection on the controller for testability.

### L9. `response()->json($locations)` returns raw AI output directly
**File:** `app/Http/Controllers/AiLocationController.php:41`
No shape validation on each element (missing `name`, `lat`, `lng`, or types).
Could be defensively normalized before returning to frontend.

### L10. `$request->currentFormData` used as magic property
**File:** `app/Http/Controllers/CampaignAssistantController.php:19`
Works via `__get`, but `$request->input('currentFormData')` is clearer and the
validated data should be used via `$request->validated()`.

### L11. `CampaignAssistantController` hardcodes `claude-sonnet-4-6` and 15s timeout
**File:** `app/Http/Controllers/CampaignAssistantController.php:29,34`
Also `AiLocationController.php:14,19`. Move to `config/services.php` so
staging/prod can differ.

### L12. `upload()` uses inline `$request->validate` instead of FormRequest
**File:** `app/Http/Controllers/CampaignController.php:151-153`
CLAUDE.md prefers FormRequests for all validation. Small rule, but worth
extracting to `UploadCampaignReportRequest`.

### L13. `destroy($id)` uses `findOrFail` instead of route-model binding
**File:** `app/Http/Controllers/CampaignController.php:299-307`
Every other method uses `Campaign $campaign` binding. Be consistent.

### L14. Redundant `$campaign->refresh()` in `update()`
**File:** `app/Http/Controllers/CampaignController.php:290`
After `update()` the model is already fresh; refresh is only needed if
observers mutate the row (they don't here). Small perf hit.

### L15. `empty($campaign->start_date)` logic duplicated
**File:** `app/Http/Controllers/CampaignController.php:136-139,291-294`
Extract to `Campaign::ensureStartDate()` model method or run via observer
`creating` hook.

### L16. `CampaignMetricsService::getMetrics` returns 13 keys
**File:** `app/Services/CampaignMetricsService.php:120-134`
Consider a DTO or at least splitting into `MetricsSummary` and
`MetricsCharts` payloads for consumers that don't need everything.

### L17. `selectRaw('SUM(impressions)…')` using lowercase `sum()` inconsistently
**File:** `app/Services/CampaignMetricsService.php:71,197`
Mixed `SUM(...)` vs `sum(...)` case in raw SQL. Cosmetic.

---

## Positive observations

- **Policies in use** — all CampaignController CRUD endpoints call
  `$this->authorize(...)`, satisfying the Policy-based authorization rule.
- **Form Requests** with comprehensive, whitelisted enum validation for all
  targeting rules — excellent coverage of the `targeting_rules` JSON schema.
- **ShouldHandleEventsAfterCommit** interface on `CampaignObserver` is the
  correct choice for logging — prevents log entries for rolled-back
  transactions.
- **Pivot naming** `campaign_audience` correctly follows the singular
  alphabetical convention from CLAUDE.md.
- **`CampaignMetricsService`** is an appropriate extraction — metrics aggregation
  does not belong in a controller, and the DocBlock return-type shape is
  excellent.
- **AI response parsing** strips markdown fences defensively and falls back
  gracefully on unparseable JSON rather than 500-ing.
- **Activity logging on audience sync** captures both added and removed IDs
  by name — very helpful audit trail.
- **`abort_unless` permission guard** on `CampaignAssistantController::chat`
  is a clean pattern (though duplicate the same pattern on AiLocationController
  per C1).
- **Pacing calculation** correctly caps displayed percentage at 100 while
  preserving raw value for overage indicators.

---

## Required Follow-ups to `docs/tasks/todo.md` (Critical issues)

1. Add `can_edit_campaigns` permission guard to `AiLocationController::generate`.
2. Strip `expected_impressions` from non-admin `store()` payload in CampaignController.
3. Cap `chatHistory` size and per-message length in `CampaignAssistantController`.
