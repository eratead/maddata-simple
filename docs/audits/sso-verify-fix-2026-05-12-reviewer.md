# SSO Verify Fix — Reviewer

**Date:** 2026-05-12
**Commit:** `8f73d0b`
**Scope:** `app/Http/Controllers/Auth/TwoFactorController.php`, `resources/views/auth/2fa-challenge.blade.php`, `tests/Feature/Auth/TwoFactorChallengeFlashTest.php`
**Status:** Approved with comments

---

## Critical

_None._

---

## High

_None._ The fix is small, intent-matched, and the live behaviour change is
narrow. Both halves of the loop the spec targets are correctly broken.

---

## Medium

### M1. Masked-email expression is duplicated verbatim across two Blade branches
`resources/views/auth/2fa-challenge.blade.php:140-141` and `:169-170` contain
the identical `@php($googleEmail = …)` + `Str::substr(...,0,1) . '***@' . Str::after(...,'@')`
trio. The spec explicitly permitted an inline expression, but duplicating it
twice already counts as the second copy-paste — the third (when `sign-in-methods.blade.php`
or `2fa-setup.blade.php` gets the same treatment, per the spec's "Open
questions") will be the one that drifts.
**Fix:** Extract `User::getMaskedGoogleEmailAttribute(): ?string` (accessor,
testable in isolation) or a `@maskedGoogleEmail($user)` Blade directive. The
spec lists this as the builder's call; recommend doing it now while the call
sites are two, not three.

### M2. Masking helper is XSS-safe today but fragile against a malformed `google_email`
The two interpolations use `{{ }}` for output (correct), so HTML escaping is
fine. However the expression assumes `Str::after($googleEmail, '@')` returns a
domain. If `google_email` were ever `"no-at-symbol"` (data corruption, manual
edit, future provider that returns a non-email identifier), `Str::after`
returns the entire string verbatim, producing `n***@no-at-symbol`. Not a
security issue — just a visual confusion failure. An accessor (M1) would let
you guard with a single `str_contains($email, '@')` check.

---

## Low

### L1. Test `Socialite::shouldReceive('driver')` is unbounded
`tests/Feature/Auth/TwoFactorChallengeFlashTest.php:60-62, 86-88` — the
`Socialite::shouldReceive('driver')->with('google')->andReturn($driver)` lacks
an `->once()` (or `->atLeast()->once()`) constraint. If a future regression
calls `Socialite::driver('google')` twice, the test still passes. Tighten to
`->once()->andReturn($driver)` for parity with the `with()` / `redirect()`
mocks which are bound.

### L2. Test for `google_email = null` doesn't assert the user actually got the redirect to Google
The "skips login_hint" test asserts `assertRedirect()` with no target URL.
Combined with the `RedirectResponse('https://accounts.google.com/oauth')`
return value, this passes — but `assertRedirect('https://accounts.google.com/oauth')`
would be marginally more specific. Same goes for the positive test.

### L3. Masked-email test only covers the Google-only branch
The "masked google email appears in verify button" test exercises the
`@elseif (config('auth.google_sso_enabled') && auth()->user()->hasGoogleLinked())`
branch (Google-only user, no TOTP). The duplicate expression in the
TOTP-plus-Google branch (`:140-141`) is not directly tested. Add a sibling
test with `google2fa_secret` set + `google_sub` set so both render paths are
covered. Cheap and catches future divergence between the two copies (which is
exactly why M1 matters).

### L4. The flash banner SVG path is the same icon as the throttle banner
Both use the alert-triangle path (`M12 9v4m0 4h.01M10.29 3.86 ...`). That's
consistent with the spec ("match the existing throttle banner") and visually
fine, but the headlines differ ("Too many attempts" vs "Verification failed")
while the icons are identical — a screen-reader user gets no semantic
distinction. Nit; not worth fixing in this commit.

### L5. Defensive comment in `startGoogleVerify` doesn't explain the *why*
`TwoFactorController.php:192` — the `if ($user->google_email)` guard is good,
but a one-line comment ("legacy rows can have google_sub without google_email")
would save the next reader a `git blame`. The spec captures the rationale;
the code doesn't.

---

## Quality / Praise

1. The `login_hint` change correctly preserves the pre-existing `with([...])`-
   for-`state` footgun avoidance noted in the 2026-05-05 summary — only one
   key, no state key, no CSRF impact. Clean.
2. The defensive `if ($user->google_email)` fallback matches the spec
   precisely; no exception path, no `??` coercion games.
3. Flash banner markup is a faithful mirror of the existing throttle banner
   (same wrapper classes, same icon vocabulary, same headline + body
   structure) — design system stays unfragmented.
4. Test file is well-organised with section comments (`── Flash banners ──`,
   `── startGoogleVerify ──`, `── Button label ──`) matching the spec's
   logical grouping.
5. The masked-email format matches the spec character-for-character
   (`j***@gmail.com`) and the `assertDontSee('joe.smith@gmail.com')` guards
   against a regression that leaks the full address.
6. Scope discipline: setup view, settings view, and link/setup flows were
   correctly left out — spec said out-of-scope, builder honoured it.
7. `startGoogleSetup` was correctly *not* given `login_hint` (would defeat
   the purpose of an account-chooser). The comment at `:180-181` documents
   this.
8. PHP-8.2-idiomatic — fluent reassignment of `$driver`, no nested
   ternaries, single-responsibility method.

---

## Verdict

Ship it. M1 (extract a masking helper) is the only item I'd actively
recommend doing before the next SSO touch — once the third call site lands,
the inline expression becomes a maintenance liability. Everything else is
nit-level.
