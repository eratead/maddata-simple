# MadData — Full Code Audit Summary

**Audit date:** 2026-04-05
**Scope:** 90 PHP app files, 86 Blade templates, 39 migrations, all routes
**Method:** 5 chunks × 3 agents (reviewer / security / performance) = 15 parallel audits
**Reports:** `docs/audits/chunk{1-5}-{reviewer,security,performance}.md`

---

## TL;DR — AI Key Exposure (user priority)

**✅ PASS across all 5 chunks.** Anthropic API key is read server-side only via `config('services.anthropic.api_key')` in `CampaignAssistantController` and `AiLocationController`. It is never:
- rendered to Blade, JS, `x-data`, `data-*` attributes, or meta tags
- included in JSON responses, XLSX exports, logs, or error messages
- visible in `resources/js/` asset bundles
- exposed via `SystemStatusController` or any admin view

**Residual risks flagged (non-critical):**
- **Chunk 2 M1** — If `APP_DEBUG=true` in production, Laravel's debug page would render the outbound `x-api-key` header in the stack trace. Verify production `.env` sets `APP_DEBUG=false`.
- **Chunk 2 H3** — AI endpoints are rate-limited `10,1` (~14.4k req/day per user); no daily token-spend cap → cost abuse possible.
- **Chunk 2 L2** / **Chunk 5 H-5** — `AiLocationController::generate` and `CampaignAssistantController` only require `auth` middleware, no permission gate. Any authenticated user (even read-only report viewers) can burn Anthropic tokens.

---

## Critical Issues Ranked (Fix First)

| # | Severity | Chunk | File:Line | Issue |
|---|---|---|---|---|
| 1 | **Critical** | 4-reviewer | `Admin/AgencyController.php:53` | `manager_password` passed raw to `User::create()` — relies on `hashed` cast; inconsistent with rest of codebase. Verify the cast is present or this is plaintext storage. |
| 2 | **Critical** | 4-security | `Agency/AgencyUserController.php:91-166, 236-241` | Agency Manager can edit/disable/re-role an admin user sharing their agency if that admin's role lacks `is_protected=true`. Guard checks role flag only, never `hasPermission('is_admin')`. |
| 3 | **Critical** | 1-reviewer | `app/Http/Controllers/UserController.php` | Missing per-permission escalation guard present in `AgencyUserController` and `RoleController`. Only `is_admin` is blocked. |
| 4 | **Critical** | 1-reviewer | `app/Policies/UserPolicy.php` | `update/delete` have no self-targeting guard — admins can demote/delete themselves. No last-admin invariant (4-reviewer C5). |
| 5 | **Critical** | 1-reviewer | `app/Models/User.php` | `hasPermission()` returns true for legacy admins even when `is_active=false` — disabled admin users still bypass all checks. |
| 6 | **Critical** | 2-reviewer | `CampaignController.php::store` | Privilege escalation on `expected_impressions` (non-admin can set). |
| 7 | **Critical** | 2-reviewer | `CampaignAssistantController.php` | Uncapped `chatHistory` size → DoS + Anthropic cost bomb. |
| 8 | **Critical** | 2-performance | `CampaignController.php:42` + `views/campaigns/index.blade.php:209` | N+1 on `client.agency` (accessed in Blade, not eager-loaded) + unbounded `->get()` on index + `orderByRaw(COALESCE(...))` blocks index usage. |
| 9 | **Critical** | 2-performance | `CampaignAssistantController.php:29`, `AiLocationController.php:14` | Sync 15s Anthropic HTTP calls hold PHP-FPM workers + session locks. |
| 10 | **Critical** | 3-reviewer | `creative_files.creative_id` migration | `cascadeOnDelete` bypasses Eloquent events → blob leakage on disk when Creative deleted. |
| 11 | **Critical** | 3-reviewer | `CreativeFileObserver::deleted` | Doesn't remove files from disk; deletion logic scattered in controller. |
| 12 | **Critical** | 3-reviewer | `CreativeController.php::preview/downloadFile` | Cross-tenant file access risk — global route-model binding, no `client_id` scoping defence-in-depth. |
| 13 | **Critical** | 3-performance | `ReportApiController.php:34-72, 103, 165` | 3-4 independent aggregation scans of `campaign_data` per request where one `selectRaw` would suffice. |
| 14 | **Critical** | 3-performance | `CampaignMetricsService.php:35-44` | Hydrates every row as Eloquent model, does PHP-side `sum/sortByDesc/min`. Dashboard has zero caching. |
| 15 | **Critical** | 4-reviewer | `Admin/AudienceController.php` | Zero auth checks, zero `ActivityLogger` calls across all 6 actions. Uses raw `Request` instead of FormRequests. |
| 16 | **Critical** | 4-reviewer | `UserController::destroy` | Detaches `clients` but not `agencies` → orphan `agency_user` pivots. |
| 17 | **Critical** | 4-reviewer | `StoreClientRequest` / `UpdateClientRequest` | `agency_id` marked `nullable`, violates "Client belongs to ONE Agency" core rule. |
| 18 | **Critical** | 4-reviewer | `Admin/AgencyController::store` | Auto-created "Agency Manager" role bypasses `preventPrivilegeEscalation()` pattern. |
| 19 | **Critical** | 4-performance | Multiple admin controllers | All admin list endpoints unpaginated (users, clients, agencies, audiences, roles, activity logs, campaign changes, system status). |
| 20 | **Critical** | 1-reviewer | `EnsureUserIsAdmin` middleware | Dereferences null user → NPE risk. |

---

## High-Severity Themes

### 1. Authorization drift across controllers
Three different privilege-escalation patterns: `RoleController`, `UserController`, `AgencyUserController`. `AgencyUserController` is the cleanest — promote that pattern project-wide. (Chunks 1 & 4 reviewer)

### 2. Policies are inconsistent or stubs
- `UserPolicy::view()` hardcoded `return false`
- `ClientPolicy::view()` stub returning false
- `CampaignPolicy` legacy fallback inconsistent across view/create/update/delete
- `AgencyPolicy` missing CRUD parity
- `CreativeController::upload` uses inline `abort(403)` instead of `$this->authorize('upload')`
- `/api/reports/campaigns` bypasses `CampaignPolicy` for inline admin check
- `/agency/{agency}/*` routes gated by `auth` only, no route-level authz middleware

### 3. N+1 and unbounded queries everywhere
- `User::accessibleClientIds()` — loops `Client::where('agency_id')` per agency (runs on nearly every non-admin request)
- Blade templates reading relationships not eager-loaded (chunks 2 & 5)
- Heavy admin `x-data` payloads dumping entire user collections with nested pivots
- `users/index.blade.php:21-44`, `agency/users/index.blade.php:29` client-side filtering with no pagination

### 4. File/Export injection risks
- **Excel/CSV formula injection** — placement names and `$campaign->name` render unescaped into XLSX cells
- **Path traversal** via unsanitized `$file->name` in `download()` headers + `ZipArchive::addFromString()` (Zip Slip)
- **Content-Disposition spoofing** via raw `$campaign->name` in filename

### 5. Sync operations blocking request thread
- `ActivityLogger::checkAndSendDigest()` runs SMTP sync on every CRUD write
- `PasswordResetLinkController` / `EmailVerificationNotificationController` — sync SMTP
- `shell_exec(ffprobe)` + Intervention re-encode blocking file upload
- AI endpoints (15s timeout) holding workers + session locks

### 6. Missing indexes (confirmed through multiple chunks)
- `activity_logs(created_at)` — ORDER BY on every admin pageload, no index
- `placements_data(campaign_id, report_date)` — only indexed by `name` (wrong leading column)
- `campaigns(status, end_date)` — needed for status cron
- `campaigns(start_date)` + `COALESCE(start_date, created_at)` filesort
- Pivot reverse-lookups: `agency_user.user_id`, `client_user.user_id`, `campaign_audience` reverse
- `audiences(is_active, main_category, sub_category, name)` covering index for picker

### 7. Throttling gaps on auth
- `POST /2fa/setup/confirm` — no throttle → TOTP brute-force (6-digit space)
- `POST /forgot-password` — no throttle → enumeration + quota burn
- `LoginRequest` throttles by email+IP → credential-spraying via proxy rotation
- Sanctum `TokenController::extend` — unlimited token lifetime extension, no audit log

---

## Positive Observations

Strong foundations already in place:

- **Defense-in-depth on AI keys** — server-side only, never leaks client-side (user's #1 concern ✅)
- **Tenant isolation** via `CampaignPolicy::view` + `accessibleClientIds()` works correctly for Campaigns
- **2FA secret** encrypted at rest + hidden attribute
- **Privilege escalation** correctly blocked in `UserController`, `RoleController`, `AgencyUserController` (minus the drift noted above)
- **File preview headers** hardened: `nosniff`, strict CSP `default-src 'none'`, `Content-Disposition: inline`
- **EXIF stripping** via Intervention image re-encode
- **Private creatives disk** outside webroot
- **Sanctum** with `ability:reports:read` scoping + `CheckTokenExpiry`
- **Blade escaping discipline** — `@js()` / `Js::from()` used consistently; `{!! !!}` only in one trusted SVG
- **CSRF** on every form; all non-auth web routes behind `auth` middleware
- **Encrypted 2FA secret**; safe `$fillable` on User/Role/Agency
- **Clean login throttling** + session regeneration

---

## Recommended Fix Priority

### Phase 1 — Critical security & data safety (this week)
1. Verify `User.password` has `hashed` cast in production → remediate #1
2. Fix Agency Manager admin-user escalation gap (#2)
3. Unify privilege-escalation guards across `UserController`/`AgencyUserController`/`RoleController`
4. Add self-targeting guard + last-admin invariant to `UserPolicy`
5. `AudienceController` — add policies, FormRequests, ActivityLogger
6. Fix `StoreClientRequest`/`UpdateClientRequest` to require `agency_id`
7. Add `client_id` scoping to creative file routes
8. Add throttles to 2FA confirm + forgot-password
9. Cap `chatHistory` size on AI endpoints
10. Add permission gate to `AiLocationController` + `CampaignAssistantController`

### Phase 2 — Performance hot-paths (next sprint)
1. Add `activity_logs(created_at)` index
2. Add `placements_data(campaign_id, report_date)` composite index
3. Add `campaigns(status, end_date)` + `campaigns(start_date)` indexes
4. Add pivot reverse-lookup indexes (`agency_user.user_id`, `client_user.user_id`)
5. Paginate all admin list endpoints
6. Move `ActivityLogger` digest + password/verification emails to queue
7. Rewrite `CampaignMetricsService` + `ReportApiController` aggregations to single `selectRaw`
8. Fix `User::accessibleClientIds()` N+1 (one `whereIn`)
9. Move AI HTTP calls behind queue or async (streaming or job)
10. Switch sessions to Redis

### Phase 3 — Defense-in-depth & quality (ongoing)
- Replace inline `abort(403)` with `$this->authorize()` across controllers
- Fix stub Policy methods (`UserPolicy::view`, `ClientPolicy::view`)
- Add Excel/CSV formula escaping in exports
- Sanitize filenames in download/zip handlers
- Move `unpkg.com` Leaflet assets into Vite bundle
- Replace inline `onclick`/`onchange` handlers with `confirm-action` Alpine component (currently forcing `unsafe-inline` in CSP)
- Remove dead `CampaignFullSheet` export
- Deduplicate admin/agency controllers (shared service layer)

---

## File Map

| Chunk | Reviewer | Security | Performance |
|---|---|---|---|
| 1 Auth & RBAC | `chunk1-reviewer.md` | `chunk1-security.md` | `chunk1-performance.md` |
| 2 Campaigns Core | `chunk2-reviewer.md` | `chunk2-security.md` | `chunk2-performance.md` |
| 3 Creatives/Reports/Exports | `chunk3-reviewer.md` | `chunk3-security.md` | `chunk3-performance.md` |
| 4 Admin & Multi-Tenant | `chunk4-reviewer.md` | `chunk4-security.md` | `chunk4-performance.md` |
| 5 Frontend & Schema | `chunk5-reviewer.md` | `chunk5-security.md` | `chunk5-performance.md` |

All reports live in `docs/audits/` and are read-only audits; no production code was modified.
