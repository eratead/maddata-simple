# Google SSO + TOTP Hybrid Authentication

**Status:** Implemented (revised 2026-05-05)
**Author:** Architect (2026-05-04)
**Builds on:** existing TOTP-2FA flow (`google2fa_secret` on `users`, `RequireTwoFactor` middleware, `2fa.setup` / `2fa.challenge` routes)

---

## Goal

Add **Google OAuth as a second factor** alongside the existing email/password + TOTP flow. **All logins start with email + password.** Google is offered only as an alternative second factor — replacing or supplementing TOTP — never as a standalone login button.

Every user has exactly one first factor (email + password) and must have at least one second factor: TOTP, Google, or both. The `RequireTwoFactor` middleware enforces the second-factor step; the single `2fa_verified` session flag signals completion regardless of which second factor was used.

The existing UX friction of TOTP enrollment is addressed by offering Google as a simpler second-factor option during the forced-enrollment `2fa.setup` screen. Users who prefer email + password + TOTP keep exactly the flow they have today.

## Non-goals

- Apple Sign-in. Same architecture would extend, but Google first.
- Auto-provisioning users from a Google identity. Admin still creates the MadData user; the user then *connects* their Google account in settings.
- SSO-only sessions / different session lifetime for SSO logins. Sessions remain unchanged after login.
- Requiring TOTP enrollment for users who only have Google connected and a viewer role.

## High-level model

| Per-user second-factor state | `2fa.challenge` shows |
|---|---|
| `google2fa_secret` set, no Google | TOTP code input only |
| `google_sub` set, no TOTP | "Verify with Google" button only |
| Both set | TOTP code input + "Or verify with Google" button |
| Neither | Redirected to `2fa.setup` (forced enrollment) |

The `2fa.setup` screen offers two choices: "Use Authenticator app" (TOTP) or "Use Google account" (one-click link + verify). There is no "primary" second factor — both paths set `session('2fa_verified', true)`.

The `login_method` session flag has been **removed**. The single `2fa_verified` flag is the only 2FA signal.

## Second-factor policy (no role gating)

There is **no role-based requirement**. Every user, including admins, can use Google as their sole second factor if they choose. The only invariant the system enforces is:

> A user must always have at least one second factor — either TOTP enrolled, or Google linked, or both.

In practice that becomes two simple guards in the settings flow:

| Action | Allowed when |
|---|---|
| Disable TOTP | Google is linked |
| Disconnect Google | TOTP is enrolled |
| Both at once | Never (would lock the user out) |

## Database changes

New migration: `add_google_sso_columns_to_users_table`

```
users
+ google_sub        VARCHAR(255)  NULL  UNIQUE     // Google's stable user id (the OIDC `sub` claim)
+ google_email      VARCHAR(255)  NULL             // shown in settings as "Connected to: x@gmail.com"
+ google_linked_at  TIMESTAMP     NULL             // audit
```

- `google_sub` is the link key, **not the email**, so users can have a MadData email different from their Google email.
- No changes to existing `google2fa_secret` or any 2FA-related column.
- No new column for "auth method" — having `google_sub` set IS the SSO opt-in; having `google2fa_secret` set IS the TOTP opt-in.

## File structure

### New files
- `app/Http/Controllers/Auth/GoogleSsoController.php` — Socialite redirect + callback.
- `app/Http/Controllers/Auth/SignInMethodsController.php` — settings page actions: connect Google, disconnect Google, disable TOTP.
- `app/Services/Auth/SsoLinkService.php` — encapsulates the link rules (find by sub → find by email → block; password-confirmation check; logging).
- `database/migrations/{ts}_add_google_sso_columns_to_users_table.php` — the columns above.
- `resources/views/auth/sign-in-methods.blade.php` — settings UI section (or add a partial inside an existing settings view if one exists).
- `tests/Feature/Auth/GoogleSsoLoginTest.php`
- `tests/Feature/Auth/SignInMethodsTest.php`
- `tests/Feature/Auth/RequireTwoFactorRolePolicyTest.php`

### Modified files
- `app/Http/Middleware/RequireTwoFactor.php` — removed `login_method=sso` fast-path; now routes to `2fa.challenge` for any user with TOTP or Google enrolled (but not yet verified), to `2fa.setup` for users with neither.
- `app/Http/Controllers/Auth/TwoFactorController.php` — added `startGoogleSetup()` and `startGoogleVerify()` actions; updated `showChallenge()` to accept Google-only users.
- `app/Http/Controllers/Auth/GoogleSsoController.php` — replaced anonymous login flow with `2fa_setup` and `2fa_verify` callback branches; removed `redirect()` method and `SsoLinkService::resolveLogin()` call.
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php` — removed `session(['login_method' => 'password'])`.
- `app/Models/User.php` — `$fillable` adds `google_sub`, `google_email`, `google_linked_at`; cast `google_linked_at` to datetime; `hasTotpEnrolled()` and `hasGoogleLinked()` helpers.
- `routes/web.php` — removed `auth.google.redirect`; added `2fa.google.start-setup` (POST) and `2fa.google.start-verify` (POST) behind `auth` middleware.
- `resources/views/auth/login.blade.php` — removed "Sign in with Google" button and divider.
- `resources/views/auth/2fa-setup.blade.php` — added "Use Google account" option (visible when `google_sso_enabled`).
- `resources/views/auth/2fa-challenge.blade.php` — conditional rendering: TOTP form when enrolled; "Verify with Google" button when Google linked; Google-only variant when no TOTP.
- `resources/views/settings/sign-in-methods.blade.php` — fixed layout (matches `profile/edit.blade.php`); fixed nested-form bug (outer `<form @submit.prevent>` → `<div>`); improved button spacing.
- `config/auth.php` — `google_sso_enabled` flag.
- `config/services.php` — `google.client_id`, `google.client_secret`, `google.redirect`.
- `composer.json` — `laravel/socialite` dependency.

## Class / method contracts (signatures only)

```php
// app/Services/Auth/SsoLinkService.php
class SsoLinkService
{
    /** Returns the User to log in, or throws SsoLoginException with a code. */
    public function resolveLogin(SocialiteUser $google): User;

    /** Connects a Google identity to a User. Caller must have already verified password. */
    public function link(User $user, SocialiteUser $google): void;

    /** Removes the Google link from a User. */
    public function unlink(User $user): void;
}

class SsoLoginException extends \RuntimeException
{
    // codes: NO_USER_FOUND, EMAIL_MATCH_NO_LINK, USER_INACTIVE
}
```

```php
// app/Http/Controllers/Auth/GoogleSsoController.php
class GoogleSsoController
{
    public function redirect(): RedirectResponse;          // Socialite::driver('google')->redirect()
    public function callback(Request $request): RedirectResponse;  // resolves via SsoLinkService, logs the user in, sets session('login_method', 'sso')
}
```

```php
// app/Http/Controllers/Auth/SignInMethodsController.php
class SignInMethodsController
{
    public function index(): View;                                  // settings page — shows status of password+TOTP and Google
    public function startConnectGoogle(ConfirmPasswordRequest $r);  // password check, then Socialite redirect with `state=link`
    public function disconnectGoogle(ConfirmPasswordRequest $r);    // password check, set google_sub=null
    public function disableTotp(ConfirmPasswordRequest $r);         // password check, requires_totp=false, then null google2fa_secret
}
```

```php
// app/Models/User.php (additions)
class User
{
    public function hasTotpEnrolled(): bool; // !empty($this->google2fa_secret)
    public function hasGoogleLinked(): bool; // !empty($this->google_sub)
}
```

## Login flow

1. User hits `GET /login`. Login view shows **only** the email + password form. There is no "Sign in with Google" button here.
2. On successful password validation (`AuthenticatedSessionController::store`), the session is regenerated and the user is redirected to `dashboard`.
3. `RequireTwoFactor` middleware intercepts the redirect:
   - **`2fa_verified` set** → pass through.
   - **TOTP enrolled or Google linked (but not verified)** → redirect to `2fa.challenge`.
   - **Neither enrolled** → redirect to `2fa.setup`.
4. `2fa.setup` offers two choices:
   - "Use Authenticator app" → existing TOTP QR-code + confirm flow.
   - "Use Google account" → POST `2fa.google.start-setup` → Socialite redirect with HMAC-signed `state=2fa_setup:{userId}:{hmac}` → callback links Google + sets `2fa_verified=true`.
5. `2fa.challenge` branches by enrolled factors:
   - TOTP only → code input form.
   - Google only → "Verify with Google" button → POST `2fa.google.start-verify` → Socialite redirect with `state=2fa_verify:{userId}:{hmac}` → callback asserts Google sub matches stored sub → sets `2fa_verified=true`.
   - Both → code input + "Or verify with Google" button.
6. On any successful second-factor completion: `session(['2fa_verified' => true])` → redirect to intended URL.

## Settings flow

- `GET /settings/sign-in-methods` shows three rows:
  - **Email + password**: always present, "Change password" link.
  - **Authenticator app (TOTP)**: status (Enabled / Not set up). Buttons: Set up | Disable. "Disable" disabled (with tooltip "Connect Google first so you don't lock yourself out") when Google is **not** linked.
  - **Google account**: status (Not connected / Connected to `x@gmail.com`). Buttons: Connect | Disconnect. "Disconnect" disabled (with tooltip "Set up Authenticator first so you don't lock yourself out") when TOTP is **not** enrolled.
- Connect Google: form posts password → server verifies → 302 to Socialite redirect with `state=link:{userId}` query parameter signed with `app.key` (HMAC). Callback distinguishes `state=link:*` from a fresh login.
- Disconnect / Disable TOTP: each is a `POST` with current-password input (Laravel's `password` validation rule). Server re-checks the lockout invariant (Google linked / TOTP enrolled) and returns 422 if the request would leave the user with no method.
- Audit logging: every link / unlink / TOTP disable goes through `ActivityLogger` so the changes show up in admin logs.

## Multi-tenant impact

None. Authentication is per-user, orthogonal to Agency/Client. SSO doesn't change pivot relationships. Disabled users (`is_active=false`) are blocked at SSO callback the same way they're blocked at password login.

## Configuration / secrets

```
# .env additions
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=${APP_URL}/auth/google/callback
```

```php
// config/services.php (added)
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT_URI'),
],

// config/auth.php (added)
'google_sso_enabled' => env('GOOGLE_SSO_ENABLED', false),
```

(No `totp_required_permissions` key — TOTP enforcement is now per-login-method, not per-role.)

The Google OAuth client is created in Google Cloud Console with the production redirect URI `https://ad.maddata.media/auth/google/callback` and the staging URI `https://msdev.maddata.media/auth/google/callback` registered as additional authorised redirect URIs.

## Dependencies

- **Composer**: `laravel/socialite ^5.x` (zero-config Google driver included).
- **Cloud**: a Google OAuth 2.0 Client ID configured for both prod and staging redirect URIs.
- **Manual ops step (one-time)**: each existing user keeps current login intact; nothing automatic happens to their record on deploy.

## Test plan

- **Login / 2FA setup**
  - User with no factor enrolled → forced to `2fa.setup`, which shows both TOTP and Google options (when SSO enabled).
  - `2fa_setup` Google flow: HMAC valid, Google sub not already taken → links Google + sets `2fa_verified=true`.
  - `2fa_setup` Google flow: HMAC mismatch → rejected.
  - `2fa_setup` Google flow: Google sub already linked to another user → rejected.
- **2FA challenge**
  - TOTP-only user → shows code input, no Google button.
  - Google-only user → shows "Verify with Google" button only, no code input.
  - Both enrolled → shows code input + "Or verify with Google".
  - `2fa_verify` Google flow: sub matches → `2fa_verified=true`.
  - `2fa_verify` Google flow: sub mismatch → rejected, `2fa_verified` stays false.
  - `2fa_verify` Google flow: HMAC mismatch → rejected.
  - `2fa_verify` Google flow: session userId mismatch → rejected, redirect to login.
- **`RequireTwoFactor` middleware**
  - `2fa_verified=true` → pass through (regardless of how it was set).
  - TOTP enrolled, not verified → redirect to `2fa.challenge`.
  - Google linked, no TOTP, not verified → redirect to `2fa.challenge`.
  - Neither enrolled → redirect to `2fa.setup`.
  - Sanctum token requests → skip gate entirely.
- **Connect / disconnect (settings page)**
  - Connecting Google requires correct password → records sub + linked_at + google_email.
  - Wrong password → no link, validation error.
  - Disconnecting requires correct password → sub becomes null.
  - Disconnecting Google when TOTP is **not** enrolled → 422 with the lockout message.
  - Disabling TOTP when Google is **not** linked → 422 with the lockout message.
- **Audit**
  - Link, unlink, TOTP disable each create an `ActivityLog` row.

## Open questions

1. **Should we auto-create a User on first SSO from a recognised email domain (e.g. anyone @maddata.media)?** Probably yes for a small team but increases attack surface. Default: **no auto-create**, decide once we have prod usage signal.
2. **Forgot-password for SSO-only users:** if a user disabled their password and only has Google, and Google access is lost, recovery is admin-driven (admin generates a temp password). Acceptable for v1; revisit if it bites.
3. **Should TOTP be skipped for SSO admins if Google account uses Workspace 2-Step Verification?** No way to detect that from the OIDC payload reliably. Default: always require TOTP for admins, even with SSO.
4. **Apple Sign-in** — defer to v2. Column naming (`google_*`) is intentionally provider-specific to keep v1 simple; if Apple is added later, generalise to a `user_sso_identities` pivot table.

## Rollout

1. Land migration + Socialite + middleware change behind a feature flag (`config('auth.google_sso_enabled')`, default `false`). Google options on `2fa.setup` and `2fa.challenge` are hidden when disabled.
2. Deploy to staging with flag on; smoke-test full matrix above with real Google accounts.
3. Deploy to prod with flag on. No data migration needed; existing users unaffected until they choose to connect.
4. Announce to viewers: "you can now disable the Authenticator app — go to Settings > Sign-in methods."

---

## Revision history

- **2026-05-04** — Initial spec drafted.
- **2026-05-05** — Replaced anonymous "Sign in with Google" login button with Google-as-2FA-option flow after staging UX feedback. All logins now start with email + password. Google is offered only as an alternative second factor (on `2fa.setup` and `2fa.challenge`). Removed `login_method` session flag; `2fa_verified` is the single 2FA signal. Deleted `SsoLinkService::resolveLogin()` anonymous-login path. Deleted `auth.google.redirect` route.
