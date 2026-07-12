# Spec: AI Campaign Assistant — Cities Not Applied (Root Cause: CSP + Observability Gaps)

**Status:** Draft
**Date:** 2026-04-16
**Owner:** Architect → Builder
**Priority:** P1 (production user-visible bug)

---

## Goal

Restore the AI Campaign Assistant's ability to populate geographic targeting (countries / regions / cities) on the campaign edit screen in production, and add the observability needed to catch silent failures of this kind in the future.

The user reports: "I asked the assistant to add cities Holon, Bat Yam, Rishon LeZion, Ramla, Lod. The reply was a confirmation, but the cities never appeared in the form, so there was nothing to save." Console shows CSP `connect-src` violations on three calls to `https://countriesnow.space/api/v0.1/...`.

## Root cause (confirmed)

1. The targeting accordion's `init()` ([resources/views/campaigns/edit.blade.php:236-237](../../resources/views/campaigns/edit.blade.php#L236-L237)) calls `loadCountriesList()` and `loadGeoData()`, which in turn fetch reference lists directly from `https://countriesnow.space` ([edit.blade.php:317](../../resources/views/campaigns/edit.blade.php#L317), [:329-330](../../resources/views/campaigns/edit.blade.php#L329-L330)).
2. Production CSP ([app/Http/Middleware/ContentSecurityPolicy.php:31](../../app/Http/Middleware/ContentSecurityPolicy.php#L31)) lists `connect-src 'self' nominatim cdn.jsdelivr.net cdn.datatables.net unpkg.com` — `countriesnow.space` is **not** allowed, so the browser blocks all three fetches.
3. With `geoCitiesList` empty, the cities autocomplete has no suggestions. While `applyUpdates()` ([edit.blade.php:464](../../resources/views/campaigns/edit.blade.php#L464)) does set `td.cities = updates.cities` directly, the user sees the same empty UI and assumes "nothing happened" — and the `Save Changes` dirty-check may also miss the bulk-assigned values depending on which Alpine watcher fires.
4. Independent of (3): the assistant chain has **no logging at all**. Controller has no try/catch, no record of the LLM input/output, no `ActivityLog` entry for AI-applied changes — so we cannot tell post-hoc whether Claude returned `updates: null`, the wrong key shape, or correct data that the frontend dropped.

## Non-goals

- **Auto-save** is explicitly out of scope. Confirmed with the user: the assistant remains draft-only; the user clicks "Save Changes" after reviewing.
- **Hebrew → English city normalization.** Cities are stored as the user typed them. Confirmed: both Hebrew (`חולון`) and English (`Holon`) are valid, side-by-side in the same campaign's `targeting_rules.cities` array. The autocomplete must surface *both* names so a user searching either script finds the same place — but the persisted value is whatever string the user (or the LLM) added.
- Refactoring `CampaignAssistantController` into an `AiAssistantService` / function-calling tools. Tracked as a follow-up if the user wants the broader cleanup.

---

## Architectural decision: proxy the geo reference data, do not whitelist the third party

Two options were considered:

| Option | Pros | Cons |
|---|---|---|
| **A. Whitelist `https://countriesnow.space` in CSP `connect-src`** | One-line change, ships in 5 minutes. | Adds a runtime dependency on a free third-party API (uptime, rate limit, future TLS issues). Every campaign edit page-load hits the third party from the user's browser. CSP grows by another origin we don't control. |
| **B. Proxy & cache the lists through Laravel** | CSP stays tight (`'self'` only). Reference data is fetched server-side once, cached for hours/days, served from `/api/geo/*`. Removes the runtime third-party dependency. Mirrors the existing pattern in [AiLocationController](../../app/Http/Controllers/AiLocationController.php) (geo lookups already proxied server-side). | Slightly more code (one controller, one cache call). Initial cache miss requires the server to reach `countriesnow.space`. |

**Decision: Option B.** The project already proxies geo lookups server-side; doing the same for the country/region/city lists is consistent, removes a runtime dependency, and avoids polluting CSP.

If `countriesnow.space` is itself unreliable enough to worry about long-term, a follow-up can swap the upstream for a static JSON dataset bundled in `storage/app/geo/`. Out of scope for this spec.

---

## File structure (new + modified)

### New
| File | Purpose |
|---|---|
| `app/Http/Controllers/GeoReferenceController.php` | Three thin endpoints: list countries, list regions for a country, list cities for a country. Reads from `GeoReferenceService`. |
| `app/Services/GeoReferenceService.php` | Resolution order per request: (1) `Cache::remember(..., 7 days, ...)`; (2) on cache miss, attempt upstream `countriesnow.space` fetch with a 5s timeout; (3) on upstream failure or empty response, fall back to the bundled static JSON in `storage/app/geo/`. Always returns plain arrays of strings. Logs to the `ai` channel at `warning` whenever the static fallback is used. |
| `storage/app/geo/countries.json` | Static fallback list of country names (string array). Source: snapshot from `countriesnow.space` taken at spec time, committed to the repo. |
| `storage/app/geo/regions/{country-slug}.json` | Per-country region snapshots. At minimum: `israel.json`, `united-states.json`. Other countries can be added incrementally; missing files mean "no fallback for this country" and the UI shows an empty list. |
| `storage/app/geo/cities/{country-slug}.json` | Per-country city snapshots. **`israel.json` must contain each city as both Hebrew and English entries** in the same array (e.g. `["Tel Aviv", "תל אביב", "Holon", "חולון", ...]`) so the autocomplete matches a user typing either script. Other countries: English only. |
| `tests/Feature/GeoReferenceControllerTest.php` | Auth required; cache hit returns instantly; upstream failure returns 200 + empty list (not 500); response shape stable. |
| `tests/Feature/CampaignAssistantLoggingTest.php` | Asserts `Log::channel('ai')` (or default channel with a tag) records request + parsed updates on every `/ai/campaign-assistant` call. |

### Modified
| File | Change |
|---|---|
| `resources/views/campaigns/edit.blade.php` (lines 315-338) | Replace the three `https://countriesnow.space/...` fetches with `/api/geo/countries`, `/api/geo/regions?country=...`, `/api/geo/cities?country=...`. Keep the same JSON shape on the client side. |
| `app/Http/Controllers/CampaignAssistantController.php` ([chat method](../../app/Http/Controllers/CampaignAssistantController.php#L10-L69)) | (a) Wrap the LLM call in try/catch. (b) `Log::channel('ai')->info('assistant.request', [...])` with user_id, campaign_id (if present), prompt length, message count. (c) `Log::channel('ai')->info('assistant.response', [...])` with parsed `updates` keys (not values, to keep PII out) and reply length. (d) On JSON parse failure log at `warning` and return a clear error to the client instead of swallowing. |
| `app/Http/Controllers/CampaignController.php` ([update method](../../app/Http/Controllers/CampaignController.php#L240-L307)) | When `targeting_rules` changes, write an `ActivityLog` entry with the diff (mirrors the existing `campaign_locations` logging pattern at lines 287-299). This closes the audit gap so we can answer "did the cities ever get saved?" without speculation. |
| `routes/web.php` | Add three `/api/geo/*` GET routes behind `auth` middleware (no Sanctum needed — these are called from authenticated browser sessions, same as the campaign edit page). |
| `app/Http/Middleware/ContentSecurityPolicy.php` | **No change.** Confirms the architectural decision above — we deliberately do not add `countriesnow.space` to `connect-src`. |
| `config/logging.php` | Add a new `ai` channel: `daily` driver writing to `storage/logs/ai-{date}.log`, `days => 30`, `level => 'info'`. Keeps assistant traffic separate from app noise so it can be tailed and reasoned about independently. |

---

## Class contracts (signatures only)

```php
// app/Services/GeoReferenceService.php
final class GeoReferenceService
{
    public function countries(): array;                    // ['Israel', 'United States', ...]
    public function regions(string $country): array;       // ['Tel Aviv District', ...]
    public function cities(string $country): array;        // ['Holon', 'Bat Yam', ...]
}
```

```php
// app/Http/Controllers/GeoReferenceController.php
final class GeoReferenceController extends Controller
{
    public function __construct(private GeoReferenceService $geo) {}

    public function countries(): JsonResponse;             // GET  /api/geo/countries
    public function regions(Request $request): JsonResponse; // GET /api/geo/regions?country=...
    public function cities(Request $request): JsonResponse;  // GET /api/geo/cities?country=...
}
```

Response shape (kept identical to what the frontend already consumes after stripping the upstream wrapper):

```json
{ "data": ["Holon", "Bat Yam", "Rishon LeZion", ...] }
```

## DB changes

None. All targeting still lives in the existing `campaigns.targeting_rules` JSON column.

## API endpoints

| Method | Path | Auth | Returns |
|---|---|---|---|
| GET | `/api/geo/countries` | `auth` | `{ data: string[] }` |
| GET | `/api/geo/regions?country={name}` | `auth` | `{ data: string[] }` |
| GET | `/api/geo/cities?country={name}` | `auth` | `{ data: string[] }` |

Cache TTL: 7 days. Cache key: `geo:countries`, `geo:regions:{slug}`, `geo:cities:{slug}`.

## Multi-tenant impact

None. Geographic reference data is global, not per-tenant. Routes only require `auth` (no Agency or Client scoping).

## Observability changes (this is half the value of the spec)

After this ships, for any future "the assistant said X but Y didn't happen" report we can answer in one query:

1. **Storage logs** (`storage/logs/laravel.log`, structured `ai.assistant.*` entries) → did Claude return updates? Which keys?
2. **`activity_logs` table** → was a `targeting_rules` change actually persisted?

This was the single biggest blocker in diagnosing the current bug — we had to guess root cause from a screenshot because the chain was completely silent.

## Dependencies

- No new Composer packages.
- No new NPM packages.
- Relies on the existing `Cache` facade (file or Redis driver — both work; production uses Redis per `MEMORY.md`).

## Verification (for the builder, before marking the task done)

1. From the campaign edit page in a non-local environment, open DevTools → Network. The three geo lookups must hit `/api/geo/*` (not `countriesnow.space`) and return 200.
2. Console must be free of CSP violations.
3. Type "תוסיף לי טרגוט מיקום גאוגרפי בערים חולון, בת ים, ראשון לציון, רמלה, לוד" into the assistant. The cities chips must appear under the **Cities** field. The "Save Changes" button must become enabled.
4. Click Save. Re-open the campaign. The five cities must be present in `targeting_rules.cities`.
5. `tail -f storage/logs/laravel.log` shows one `ai.assistant.request` and one `ai.assistant.response` entry per send.
6. `activity_logs` table contains a row recording the targeting-rules change after Save.
7. `composer run test` is green.

## Resolved decisions (post-review)

1. **Dedicated `ai` log channel** — confirmed. Configured in `config/logging.php`, written to `storage/logs/ai-{date}.log`, 30-day retention.
2. **Static fallback dataset** — confirmed. Bundled in `storage/app/geo/`. `GeoReferenceService` falls back to static when upstream fails or returns empty.
3. **Bilingual city storage** — confirmed. `targeting_rules.cities` is a free-text string array; Hebrew and English entries coexist. The Israel static dataset includes each city in both scripts so autocomplete matches either way. **No normalization, no translation, no schema change.**

## Open questions (none blocking)

- The user noted this bug is one instance of "AI says it did something but nothing changes." If other examples surface (budgets, dates, dayparting), the new `ai` channel will catch them — but the system prompt may also need tightening. Track separately if those reports come in.
