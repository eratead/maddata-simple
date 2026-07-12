# Security Audit: SSO Verify-Flow Fix (commit `8f73d0b`)

**Date:** 2026-05-12
**Scope:** `app/Http/Controllers/Auth/TwoFactorController.php` (`startGoogleVerify`), `resources/views/auth/2fa-challenge.blade.php` (flash banners + masked button label), `tests/Feature/Auth/TwoFactorChallengeFlashTest.php`. Adjacent surface (`GoogleSsoController`, `SignInMethodsController`, `SsoLinkService`, `RequireTwoFactor`) reviewed for spill-over only.

## Critical
None. **No rollback recommended.**

## High
None.

## Medium
None.

## Low
None.

## Informational

- **I1 — `login_hint` leaks the linked Gmail address to Google in the OAuth `authorize` URL.** Sent over HTTPS directly to `accounts.google.com`; Google is the destination party for that authentication anyway, and they already learn the address as soon as the user signs in. No third-party receives the URL (browsers do not send a Referer header on top-level navigations to a different origin by default, and Laravel doesn't proxy through any intermediary). MadData's own logs don't capture outbound Socialite URLs. No action required.

- **I2 — Masked email in the Verify button (`j***@gmail.com`).** The button is rendered only inside `resources/views/auth/2fa-challenge.blade.php`, which is reachable only via the route `2fa.challenge` under the `auth` middleware group (`routes/auth.php:44`). The viewer is therefore already the password-authenticated user whose email it is. No pre-auth context renders this view. The mask discloses one extra character of the local part vs. the bare domain; not an account-enumeration oracle because reaching the page already requires that user's password.

- **I3 — Flash messages displayed by `session('error')` / `session('success')` on `2fa-challenge.blade.php`.** Every flash that can land on this view is a hardcoded constant string from `GoogleSsoController` (callback failure paths at L43, L50, L54, L61; doVerify mismatch at L150) or `TwoFactorController` redirects. No user-supplied input is interpolated into these messages. Blade `{{ }}` escaping is preserved, so even a future regression that injects user data would not produce XSS. No change required, but worth a note in the spec so future contributors don't add a `->with('error', $request->input(...))` here.

- **I4 — Sub-mismatch banner does not function as a linkage-disclosure oracle.** The error text "The Google account used does not match the one linked to your MadData account" is only delivered after (a) the user's password authenticated the session, and (b) the OAuth callback completed. Both gates require possession of the MadData password, so an external attacker cannot use this banner to probe whether a given MadData user has Google linked. The same fact (linkage exists) is independently disclosed by the rendered Verify button.

## Passed checks

- `startGoogleVerify` is `POST`, `auth`-gated (`routes/web.php:144-148`), and uses `$request->user()->google_email` rather than request input — no parameter injection into `login_hint`.
- `login_hint` is passed via Socialite's typed `with([...])` array; not concatenated into a URL, so no CRLF / URL-fragment injection.
- The defensive `if ($user->google_email)` guard prevents passing `null` to Socialite (covered by `TwoFactorChallengeFlashTest`).
- `RequireTwoFactor` exempt list (`auth.google.*`, `2fa.*`) is unchanged; sub-mismatch path sets `block_google_auto_verify` but does NOT set `2fa_verified`, so failed verification cannot bypass the gate.
- All five new tests assert the intended behavior including a `assertDontSee('joe.smith@gmail.com')` regression guard against accidentally rendering the unmasked address.
- Blade output uses `{{ }}` everywhere; no `{!! !!}` was introduced.
- No new mass-assignment surface; no DB writes; no migration.
- `block_google_auto_verify` flag is consumed by `pull()` so it doesn't persist; if OAuth fails repeatedly the user can manually retry — acceptable.

## Verdict

The diff is clean. The `login_hint` addition and the masked button label are appropriate, post-auth-only disclosures with no realistic attacker leverage. Flash rendering is safe because every upstream `->with('error', ...)` writes a static string. **Production stays live; no rollback warranted.**
