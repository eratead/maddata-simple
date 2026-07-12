# SSO Verify — Flash Visibility + login_hint Fix

**Status:** Planned
**Author:** Architect (2026-05-12)
**Found by:** V0 staging walkthrough — sub-mismatch loop on `/2fa/challenge` with no visible error
**Related:** [google-sso-totp-hybrid.md](google-sso-totp-hybrid.md), [sso-rollout-user-verification.md](sso-rollout-user-verification.md)

---

## Goal

Fix the two cooperating UX bugs that turn a sub-mismatch in the Google-as-2FA verify flow into an invisible redirect loop:

1. Flash error messages set by `GoogleSsoController::doVerify()` (and `doSetup()`/`doLink()` via the catch-all error paths) never render on `resources/views/auth/2fa-challenge.blade.php`. The template only reads `$errors->has('throttle')` — `session('error')` and `session('success')` are dropped on the floor.
2. `TwoFactorController::startGoogleVerify()` calls `Socialite::driver('google')->redirect()` with no `login_hint`. Google then silently re-auths against whichever Gmail account is the "default" in the user's browser — which can easily be a different account from the one stored in `users.google_sub`. Sub mismatch is then the *expected* outcome whenever the user has more than one Gmail signed in or has signed out / signed back in.

After this fix:

- A sub-mismatch (or any other `doVerify` failure path) renders a visible inline error banner on the challenge page so the user understands what went wrong.
- The verify redirect carries `login_hint=<user.google_email>`, so Google defaults to the linked account. If that account is already signed in, silent auth uses it and the sub matches. If it isn't, Google prompts the user to sign in to *that specific account*, eliminating the "Chrome picked the wrong Gmail" failure mode.

## Non-goals

- No change to the link/setup flows beyond making their flashes visible. The flash rendering fix naturally helps `doSetup()` and `doLink()` since they write the same kind of session flash; the `login_hint` addition is verify-only because link/setup don't have a target email yet.
- No redesign of the verify UX (e.g., switching to `prompt=select_account`). `login_hint` is the lighter touch and matches Google's recommended pattern for "this user wants account X".
- No change to TOTP rendering on the same view.
- No new tests for the existing flows beyond the two scenarios this spec adds.

## Reproduction (from the V0 walkthrough)

1. Test user on staging has `google_sub` set to the sub of Gmail A (from an earlier Connect).
2. Browser's currently-active Google session is a *different* account, Gmail B — e.g. because the user signed out of A and signed in to B, or because B is `authuser=0` in a multi-account Chrome session.
3. User on `/2fa/challenge` clicks **Verify with Google**.
4. `POST /2fa/google/start-verify` → Google OAuth with `prompt=none`-style silent auth → callback with code for **Gmail B**.
5. `doVerify`: `$googleUser->getId()` (B's sub) `!==` `$user->google_sub` (A's sub) → `session()->put('block_google_auto_verify', true)` + `->with('error', '…does not match…')` + `redirect()->route('2fa.challenge')`.
6. Browser GETs `/2fa/challenge`. View renders without surfacing `session('error')` — the user sees the same page with no error. Looks like a no-op.

The fix below breaks both halves of the loop.

## File changes

### 1. `resources/views/auth/2fa-challenge.blade.php`

Add a flash banner near the top of the card content, between the existing throttle banner block and the header. It should render:

- `session('error')` — red banner, alert-triangle icon, top-level "Verification failed" headline (or similar), message body below.
- `session('success')` — green banner, check icon. (Cheaper to add now than to come back for it; the verify flow doesn't currently write success here, but the link/setup flashes can route through this view if the user lands on `2fa.challenge` after a successful enrollment.)

Markup pattern must match the existing throttle banner so the design system isn't fragmented:

```blade
@if (session('error'))
  <div class="mb-6 flex items-start gap-3 px-4 py-3 rounded-lg bg-red-50 border border-red-200">
    {{-- alert-triangle SVG --}}
    <div>
      <p class="text-sm font-semibold text-red-700">Verification failed</p>
      <p class="text-xs text-red-500 mt-0.5">{{ session('error') }}</p>
    </div>
  </div>
@endif

@if (session('success'))
  {{-- mirror, green palette --}}
@endif
```

(Builder owns the exact SVG choice — use the same icon set already on the page.)

The banner should appear *above* the header block, *inside* the existing outer `<div>` wrapper, so it's visible in both the TOTP-form branch and the Google-only branch.

### 2. `app/Http/Controllers/Auth/TwoFactorController.php`

Modify `startGoogleVerify()` to pass `login_hint`. Current body:

```php
public function startGoogleVerify(Request $request): RedirectResponse
{
    $request->session()->put('google_oauth_intent', '2fa_verify');
    $request->session()->put('google_oauth_user', $request->user()->id);

    return Socialite::driver('google')->redirect();
}
```

New body (contract — builder implements):

```php
public function startGoogleVerify(Request $request): RedirectResponse
{
    $user = $request->user();

    $request->session()->put('google_oauth_intent', '2fa_verify');
    $request->session()->put('google_oauth_user', $user->id);

    $driver = Socialite::driver('google');

    if ($user->google_email) {
        $driver = $driver->with(['login_hint' => $user->google_email]);
    }

    return $driver->redirect();
}
```

Notes for the builder:

- The `->with([…])` pattern was previously a footgun for `state` (see the 2026-05-05 summary). `login_hint` is *not* a CSRF concern; Socialite leaves its own state token alone when `with()` is used for other keys. Safe to use here.
- The `if ($user->google_email)` guard is defensive — by construction we only render the Verify-with-Google button when `hasGoogleLinked()` is true (which implies a non-null `google_sub`), but historically `google_email` could be null on rows whose link predates the current schema. Tolerate the missing email by falling back to vanilla `redirect()`; sub mismatch becomes visible (now) instead of silent.
- Do **not** apply the same `login_hint` to `startGoogleSetup()`. In the setup flow the user is choosing which Gmail to link — pre-filling would defeat the purpose. The hint is verify-only.

### 3. `resources/views/auth/2fa-challenge.blade.php` — button label

In addition to the flash banners (§1 above), change the **two** Verify-with-Google button bodies (one inside the `@if (hasTotpEnrolled)` branch at ~L106-118, one inside the Google-only branch at ~L134-145) so the label includes a masked version of the linked Gmail. Goal: the user sees which account is expected *before* clicking, eliminating the "I'll use a different Gmail" failure class even before Google's chooser kicks in.

Format: `Verify with Google (j***@gmail.com)` — show the first character of the local part, three asterisks, then `@domain.tld` verbatim. If the local part is a single character, still show one char + asterisks (`a***@gmail.com`). If `$user->google_email` is somehow null on this path (shouldn't happen — the button is only rendered when `hasGoogleLinked()`), fall back to just `Verify with Google` so we don't break the layout.

Implementation choice (builder's call): inline Blade helper using `Str::before` + `Str::after`, or a small accessor on `User` (e.g. `getMaskedGoogleEmailAttribute()`). Accessor is cleaner if you want to test the masking logic in isolation — but a 3-line inline expression in the Blade is also fine. Don't add a separate service class for it.

### 4. (No view changes for 2FA setup or settings.)

`2fa-setup.blade.php` and `settings/sign-in-methods.blade.php` are out of scope for this spec. If they have the same flash-blindness, file a follow-up after auditing them — don't bundle here.

## Class contracts touched

`TwoFactorController::startGoogleVerify(Request $request): RedirectResponse` — signature unchanged. Behaviour: now reads `$request->user()->google_email` and forwards it as `login_hint` to Socialite when set.

No other controller / service signatures change. `GoogleSsoController::doVerify()` is unchanged — its existing `->with('error', …)` already does the right thing; we're just making sure the view actually shows it.

## DB changes

None. `google_email` was already populated by `SsoLinkService::link()`.

## API endpoints

None.

## Multi-tenant impact

None. Per-user state only.

## Tests

Add to `tests/Feature/Auth/` (suggested file: `TwoFactorChallengeFlashTest.php`; builder picks the exact name):

1. **Flash-error banner renders on `/2fa/challenge`.** Authenticate a cell-A or cell-C user, set `session('error', 'Some specific message')`, GET `/2fa/challenge`, assert the response body contains "Some specific message" and the `bg-red-50` class (or whatever the chosen marker is). Regression guard against the silent-error bug.
2. **Flash-success banner renders on `/2fa/challenge`.** Same scaffold, `session('success', …)`, assert visible. Cheaper to add now than later.
3. **`startGoogleVerify` forwards `login_hint`.** Authenticate a cell-C user with `google_email = 'foo@example.com'`. Use Socialite's `Socialite::partialMock()` + `shouldReceive('driver')->andReturnSelf()`, `->shouldReceive('with')->with(['login_hint' => 'foo@example.com'])->andReturnSelf()`, `->shouldReceive('redirect')->andReturn(new RedirectResponse('https://accounts.google.com/...'))`. POST `/2fa/google/start-verify`. Assert the `with()` mock was called with the expected argument.
4. **`startGoogleVerify` skips `login_hint` when `google_email` is null.** Authenticate a user with `google_sub` set but `google_email = null`. Mock as above except assert `with()` is **not** called. Regression guard against the defensive fallback.

Existing tests in `tests/Feature/Auth/GoogleSsoLoginTest.php` etc. should be unaffected — verify they still pass without modification.

## Dependencies

- The fix lands on the `staging` branch first, gets a re-walk through V0 with the now-working flow, then ships to production as part of the existing rollout-spec Phase 1 dark deploy. **It does not require a new migration or env var.**

## Decisions (resolved during planning)

1. **Button label shows masked linked email.** Confirmed 2026-05-12 — folded into §3 of this spec. Format: `Verify with Google (j***@gmail.com)`.

## Open questions

1. **Audit setup/settings views for the same flash-blindness.** Out of scope here; track as a separate task after this fix lands on staging. The probable answer is "yes, same issue, same fix shape."
