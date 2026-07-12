# API Error Response Hardening

**Status:** Draft
**Author:** Architect
**Date:** 2026-04-14
**Related incident:** Production users of `/api/reports/*` endpoints receiving the HTML login page body (with 200 OK) instead of JSON error responses when their Bearer token fails validation. Downstream integrations fail with "Could not find variable: campaign_id" because they try to parse HTML as JSON.

## Goal

Ensure every request to `/api/reports/*` (and any future `/api/*` route) returns a structured JSON response on authentication, authorization, validation, and not-found failures — **never** an HTML body, **never** a redirect. This must hold regardless of the `Accept` header the client sends.

## Root Cause

`app/Exceptions/Handler.php` defines a custom `unauthenticated()` method that checks `$request->is('api/*')` and returns JSON. **In Laravel 12, this file is dead code.** Laravel 12 registers exceptions exclusively through `bootstrap/app.php` via the `->withExceptions(...)` closure. There is no reference to `App\Exceptions\Handler` anywhere in the codebase, so the framework uses the default `Illuminate\Foundation\Exceptions\Handler`, which redirects to `/login` for any `AuthenticationException` where `$request->expectsJson()` is false.

Postman's default `Accept: */*` header does **not** make `expectsJson()` return true. Result: when a Bearer token is missing, invalid, or rejected by Sanctum, the client receives a 200 OK response with the full HTML login page as the body.

This regression has been latent since the Laravel 12 upgrade. It only manifests when tokens begin failing Sanctum validation — which appears to have started after the 2026-04-12 production cutover (likely separate root cause: tokens or DB state not migrated cleanly; out of scope for this spec but tracked as an open question below).

## Scope

**In scope:**
- Centralized JSON error rendering for any path beginning with `api/`
- Covers: `AuthenticationException`, `AuthorizationException`, `ValidationException`, `NotFoundHttpException` (including model binding 404s), `HttpException`, `ThrottleRequestsException`, and fallback `Throwable`
- Deletion of the dead `app/Exceptions/Handler.php` file
- Pest feature tests asserting JSON responses for every failure mode on `/api/reports/*`

**Out of scope:**
- Investigating *why* tokens are being rejected post-cutover (this is incident response — separate task for the `server` agent)
- Moving `/api/reports/*` from `routes/web.php` to `routes/api.php` (larger refactor, flagged as open question)
- Changes to `CheckTokenExpiry` middleware (already returns JSON correctly)
- Changes to token creation / management flow

## Design

### Approach

Register `AuthenticationException` and other exception renderers in `bootstrap/app.php` via `$exceptions->render(...)` closures. Each closure checks `$request->is('api/*')` and returns a `JsonResponse` with the appropriate status code and `{ "message": "..." }` body. If the path does not match `api/*`, the closure returns `null`, letting Laravel fall through to default web behavior (redirect, error page, etc.).

Additionally, call `$exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*'))` so that Laravel's *internal* "expects JSON" decision respects the API path, not just the `Accept` header. This is belt-and-suspenders — even without per-exception render closures, this alone would fix the immediate bug.

Both approaches together give us defense in depth: `shouldRenderJsonWhen` handles framework-level decisions, and the explicit render closures guarantee consistent response shapes.

### File Changes

| File | Change |
|------|--------|
| `bootstrap/app.php` | Add `shouldRenderJsonWhen` call and per-exception `render` closures inside `withExceptions()`. |
| `app/Exceptions/Handler.php` | **Delete.** Dead code in Laravel 12, misleading to readers. |
| `tests/Feature/ApiErrorResponseTest.php` | New test file covering all failure modes. |

No new classes, no new middleware, no new config, no migration.

### Response Contract

All `/api/*` error responses must conform to:

```
Content-Type: application/json
HTTP/1.1 <status>
{
  "message": "<human-readable reason>"
}
```

Validation errors additionally include:

```json
{
  "message": "The given data was invalid.",
  "errors": { "field": ["..."] }
}
```

Status code matrix:

| Exception | Status | Message |
|-----------|--------|---------|
| `AuthenticationException` (missing/invalid/rejected Sanctum token) | 401 | `"Unauthenticated."` |
| `CheckTokenExpiry` rejection (existing, unchanged) | 401 | `"Token expired."` |
| Sanctum ability check failure (`ability:reports:read`) → `AuthorizationException` | 403 | `"This action is unauthorized."` |
| `ValidationException` | 422 | `"The given data was invalid."` + `errors` |
| `ModelNotFoundException` / `NotFoundHttpException` (route binding 404) | 404 | `"Resource not found."` |
| `ThrottleRequestsException` | 429 | `"Too many requests."` |
| Uncaught `Throwable` (500) | 500 | `"Server error."` (no stack trace leaked) |

### Class Contracts

No new classes. Only modifications to `bootstrap/app.php` within the existing `withExceptions()` closure. The contract is: every closure accepts `(Throwable $e, Request $request)` and returns `JsonResponse|null` — null means "not for me, fall through".

### DB Changes

None.

### API Endpoints

No new endpoints. Existing endpoints behave the same on success; error responses become structured JSON.

### Multi-Tenant Impact

None. Error responses don't leak tenant data. The ability check (`reports:read`) continues to enforce per-token scope, and the fix does not change how Sanctum resolves the token to a user or how the controller filters data by agency/client.

### Dependencies

None. This is a pure Laravel 12 configuration change using built-in APIs.

## Testing Strategy

A single new Pest feature test file, `tests/Feature/ApiErrorResponseTest.php`, exercising `/api/reports/campaigns` as the canonical endpoint. Each case asserts (a) status code, (b) `Content-Type: application/json`, (c) JSON body shape, and (d) response body does **not** contain `"<!DOCTYPE html"` or `"Welcome back"`.

Test cases:

1. **No Authorization header** → 401 JSON `{"message":"Unauthenticated."}`
2. **Malformed Authorization header** (`"Bearer garbage"`) → 401 JSON
3. **Valid header, non-existent token hash** → 401 JSON
4. **Valid token, `expires_at` in the past** → 401 JSON `{"message":"Token expired."}` (exercises existing `CheckTokenExpiry`)
5. **Valid token, missing `reports:read` ability** → 403 JSON
6. **Valid token, valid ability, invalid query param** (e.g. bad `start` date) → 422 JSON with `errors`
7. **Valid token, valid ability, 200** → JSON success (regression guard)
8. **Accept header variations** — repeat case 1 with `Accept: */*`, `Accept: text/html`, and no `Accept` header. All must return JSON.

The `Accept: text/html` case is the critical regression test: it proves the fix does not depend on the client cooperating with headers.

## Open Questions

1. **Move `/api/reports/*` to `routes/api.php`?** Currently they live in `routes/web.php` with the `web` middleware group wrapping them. This means every API request runs through `StartSession`, `EncryptCookies`, `VerifyCsrfToken`, `ContentSecurityPolicy`, `RequireTwoFactor`, and `AdminOnlyMode`. Most of these short-circuit for Sanctum token requests, but they're pure overhead and architecturally wrong. Moving to `api.php` is cleaner but requires (a) ensuring `api.php` is registered in `bootstrap/app.php`'s `withRouting()` call, (b) verifying no existing integration relies on session-cookie auth as a fallback, and (c) updating any named-route references. **Recommend deferring to a follow-up spec** — the hardening in this spec fixes the user-visible bug without the blast radius of a route relocation.

2. **Why are tokens being rejected post-cutover?** This spec hardens the *symptom*. The underlying question — did the `personal_access_tokens` table migrate cleanly from old prod (207.154.253.28) to new prod (164.90.233.136)? Is the `APP_KEY` the same? Did any token get truncated during dump/restore? — needs a server agent to SSH in and compare DB state. Tracked separately.

3. **Should we version the API?** The endpoints are unversioned (`/api/reports/campaigns`). If the response contract ever needs a breaking change, we'll need `/api/v1/` prefixing. Flag for future, not part of this spec.

4. **Should 500 responses include a request ID for correlation with logs?** Useful for debugging but requires a request ID middleware that doesn't currently exist. Deferring.

## Rollout

1. Merge to staging branch, verify with Postman against staging that all eight test cases behave as expected end-to-end.
2. Push to main, deploy to production via standard git-pull deploy.
3. Immediately after deploy, re-run the failing Postman request from the incident report. Confirm JSON 401 (or 200 with data, depending on whether the server agent has also restored token state).
4. Reply to the user report in the original channel with the fix confirmation and a short note on response shape for their downstream integration.

## Risk

**Low.** The change is additive — it intercepts exceptions on `api/*` paths only and leaves web routes untouched. The `app/Exceptions/Handler.php` deletion is safe because the file is never loaded. The `shouldRenderJsonWhen` call affects only the framework's internal decision for `api/*` paths, not web paths. Test coverage is comprehensive enough to catch any regression in the primary failure modes.
