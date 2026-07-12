# SSO Rollout — Per-User Authentication Verification

**Status:** Planned
**Author:** Architect (2026-05-12)
**Complements:** [google-sso-production-rollout.md](google-sso-production-rollout.md) — app-rollout runbook
**Triggered by:** Production Google branding verified on 2026-05-12 (Cloud Console → Google Auth Platform → Branding shows "Your branding has been verified and is being shown to users")

---

## Goal

Before, during, and after the production SSO flag flip, **confirm that every active production user can complete authentication** through the new Google-SSO + TOTP hybrid flow — and, for users who can't, resolve them in advance so the rollout strands nobody.

The existing rollout spec covers *application* correctness ("does the SSO callback work? does the settings page render?"). This spec covers *per-user* correctness ("can user N actually get in?"). The two are complementary: app green + every-user green = safe rollout.

## Non-goals

- No app code changes. This is pure verification + operational planning.
- Doesn't re-design the SSO flow.
- Doesn't replace the rollout spec — it slots into the gap between Phase 1 (dark deploy) and Phase 3 (announce).
- Doesn't cover marketing-site users — `maddata.media` is a separate app and has no auth.

## Context (what's true on 2026-05-12)

- Production droplet: `164.90.233.136`, project at `/var/www/maddata`, 16 active users on `users` (per 2026-04-12 health check).
- SSO code is on `staging` only. Production has neither the `google_sub`/`google_email`/`google_linked_at` columns nor the SSO routes wired to UI.
- The Google project that powers staging SSO has passed branding verification AND publishing status is "In production" (Cloud Console → Audience, 2026-05-12). Branding is project-level, so the production OAuth client (once provisioned per rollout-spec Phase 0b) inherits the same verified+published consent screen — no second round.
- **The "4 users / 100 user cap" counter on the Audience page is informational only.** Google's text on that screen: *"Verified apps will still display the user cap on this page, but the user cap does not apply if you are only requesting approved sensitive or restricted scopes."* Our SSO uses `openid email profile` — all three are non-sensitive and pre-approved. The cap therefore does not constrain us. The counter cannot be reset by design; ignore it.
- **What this changes for staging.** Until 2026-05-12 the staging consent screen was in *Testing* mode and only authenticated whitelisted test-user Gmails. As of 2026-05-12 any Gmail can complete the staging flow too. Worth one targeted smoke test (Checkpoint 0 below) to confirm in practice before we touch prod.

## Cohorts (the matrix we verify against)

After Phase 1 of the rollout spec runs (additive migration, flag still OFF), every active user has a `google_sub` column initialised to `NULL`. The four state cells are:

| Cell | `google2fa_secret` | `google_sub` | Auth path after Phase 2 flip | Pre-deploy risk |
|---|---|---|---|---|
| **A** — TOTP only | NOT NULL | NULL | `/2fa/challenge` → TOTP code | User lost their authenticator app |
| **B** — Google only | NULL | NOT NULL | `/2fa/challenge` → auto-redirect to Google | User no longer owns the linked Google identity |
| **C** — Both | NOT NULL | NOT NULL | TOTP input + "Or with Google" button | Mitigated by user choice |
| **D** — Neither | NULL | NULL | Forced `/2fa/setup` (two-card picker) | User hits a setup wall on next login |

**Important:** in the moment immediately after Phase 1 dark-deploy, every active prod user is in **cell A or cell D** by construction. Cells B and C require a `google_sub` value, which is only writable through a user-initiated link flow (Settings → Connect Google), which is gated by Phase 2's flag flip. So pre-Phase-2 the census reduces to one boolean: *do they have TOTP enrolled or not?*

Cells B and C are emergent — users self-select into them after the flag flip. We verify those by smoke-testing one sentinel account in Phase 2.

## Verification approach

Three sequential checkpoints, all done *as part of* the rollout — not as a separate calendar event:

### Checkpoint 0 — Staging smoke test with a fresh Gmail (pre-rollout)

Goal: prove on staging that the now-verified-and-published consent screen authenticates a Gmail account that was **never** on the project's test-users list. This is the single proof we need before assuming prod will behave the same way.

Steps:

1. Pick a Gmail address that **was not on** the Cloud Console → Audience → Test users list during the *Testing* phase. A fresh personal Gmail or a colleague's personal Gmail works. If unsure, create a throwaway Gmail.
2. From a clean browser session (private window or different profile, signed out of any MadData account):
   - Hit `https://msdev.maddata.media/login`.
   - Log in with the email+password of an existing staging user that has TOTP enrolled but no Google link (cell A). If no such user exists, create one or use an admin and accept the path change.
   - Verify TOTP. Land on dashboard.
   - Settings → Sign-in methods → Connect Google → password confirm.
   - On the Google consent screen: confirm app name is `MadData`, **no "unverified app" warning**, scopes shown are `Your email address` and `Your personal info` only.
   - Approve. Return to MadData with success flash. Verify `users.where('email', '...')->first()->google_sub` is populated in tinker on staging.
3. Log out. Log back in with email+password. Verify auto-redirect to Google (no click), then dashboard.
4. (Optional) From an unrelated browser, hit the OAuth URL directly with the staging client ID:
   `https://accounts.google.com/o/oauth2/v2/auth?client_id={staging_client_id}&redirect_uri=https%3A%2F%2Fmsdev.maddata.media%2Fauth%2Fgoogle%2Fcallback&response_type=code&scope=openid+email+profile`
   Confirm the consent screen renders for a never-whitelisted Gmail with no warning.

Failure modes worth catching here:
- "App not verified" warning → branding/publishing status is wrong; cannot proceed to prod.
- `redirect_uri_mismatch` → staging client config is stale; fix on staging first or it's a sign we'll repeat the mistake on prod.
- Successful consent but our callback 500s → bug in `GoogleSsoController::callback()` that the test-user matrix missed.

Outcome: a green Checkpoint 0 means *"the verified consent screen works for arbitrary Gmails"* is proven, not assumed. The production rollout can proceed.

### Checkpoint 1 — Pre-Phase-2 census (read-only sanity check)

Goal: confirm the working assumption that **every active user has TOTP enrolled** (cell A), so the only people who can land on `/2fa/setup` after the flag flip are users who haven't logged in since TOTP was made mandatory — and those are handled in person as they surface.

Run on prod via `php artisan tinker`:

```php
\App\Models\User::where('is_active', true)
    ->orderBy('id')
    ->get(['id', 'name', 'email', 'role_id', 'google2fa_secret', 'last_login_at'])
    ->map(fn ($u) => [
        'id' => $u->id,
        'email' => $u->email,
        'role' => optional($u->userRole)->name,
        'totp_enrolled' => ! empty($u->google2fa_secret),
        'last_login' => $u->last_login_at?->toDateString(),
    ]);
```

Expectation per Michael: every `is_active=true` row should have `totp_enrolled=true`. Any exceptions are users who slipped through the forced-TOTP rollout — note their IDs in the runbook so the admins know who to expect a "what's this setup screen?" message from. **No outbound communication.** Per project decision: problems are handled in person when users hit them.

### Checkpoint 2 — Phase 2 smoke-test matrix on real prod

Right after the rollout-spec Phase 2 flag flip, walk the matrix with a sentinel account *and* one consenting real user from each populated cell. The sentinel covers all paths without disturbing customer data; the real-user walks confirm we haven't broken anyone's existing setup.

Sentinel walks (account: `qa@maddata.media`, see V7 below):

| Walk | Starts in | Action | Expected end state |
|---|---|---|---|
| Cell D → B | password only | Log in → land on `/2fa/setup` → pick Google card → consent → callback | Dashboard, `users.google_sub` populated |
| Cell B → B again | password + Google | Log out, log back in with email+password | `/2fa/challenge` auto-redirects to Google → dashboard (no click) |
| Cell B + add TOTP → C | password + Google | Settings → set up Authenticator → scan QR | Dashboard, both factors visible in Settings |
| Cell C → C, choose TOTP | password + Google + TOTP | Log out, log in | `/2fa/challenge` shows TOTP input AND "Or with Google" button; submit TOTP → dashboard |
| Cell C → C, choose Google | same | Same path, click "Or with Google" | Dashboard |
| Cell C → disconnect Google → A | same | Settings → Disconnect Google (password confirm) | Dashboard, `users.google_sub` now NULL |
| Lockout invariant | cell A | Try Settings → Disable TOTP | 422 + tooltip "Connect Google first" |
| Lockout invariant | cell B | Try Settings → Disconnect Google | 422 + tooltip "Set up Authenticator first" |

Real-user walk (one volunteer from cell A, with their consent):

- Log in with email + password + TOTP. Land on dashboard. Confirm the discoverability hint card on `/2fa/challenge` (commit `f7b8da2`) is rendering. No regression on the existing TOTP path.

There is no real user in cell B or C at Phase 2 start, by construction — cell-B/C require post-Phase-2 link actions.

## Post-rollout monitoring

| Window | What to watch | Where | Trigger threshold |
|---|---|---|---|
| Phase 2 + 0 to 24h | `Socialite\Two\InvalidStateException` | `storage/logs/laravel.log` | any occurrence → investigate same day |
| Phase 2 + 0 to 24h | `sso.linked` ActivityLog rows | `activity_logs` | adoption signal, no threshold |
| Phase 2 + 0 to 24h | `block_google_auto_verify` session sets | grep `laravel.log` for the controller branch | recurring sets for one user → broken link, reach out |
| Phase 2 + 7 days | Count of `users.google_sub IS NOT NULL` | `php artisan tinker` | report in the next session summary |
| Phase 2 + 14 days | Any open lockout tickets | comms channel | should be zero |

## Lockout recovery procedure

When a user reports they can't log in:

1. **Diagnose** their current state:
   ```php
   $u = \App\Models\User::where('email', '...')->firstOrFail();
   [
       'totp' => ! empty($u->google2fa_secret),
       'google' => ! empty($u->google_sub),
       'google_email' => $u->google_email,
       'active' => $u->is_active,
   ];
   ```

2. **Choose the reset shape:**
   - **Stale Google link** (user lost the Google account): set `google_sub`, `google_email`, `google_linked_at` to NULL. Keep TOTP if they have it.
   - **Lost authenticator app, has Google**: tell them to use the "Or with Google" button on `/2fa/challenge`. Don't reset anything.
   - **Lost both**: null both `google2fa_secret` and the three `google_*` columns. They re-enroll fresh on next login (cell D path).
   - **Locked out and never enrolled**: shouldn't happen — they'd be in cell D already. If they can't get past `/2fa/setup`, that's a bug, not a recovery; escalate.

3. **Execute** via tinker (preferred — fires `updated` events, gives an `ActivityLog` trail) — not raw SQL:
   ```php
   $u->update([
       'google2fa_secret' => null,        // only if resetting TOTP
       'google_sub' => null,              // only if resetting Google
       'google_email' => null,
       'google_linked_at' => null,
   ]);
   \App\Services\ActivityLogger::log(
       action: 'auth.recovered_by_admin',
       subject: $u,
       metadata: ['by' => auth()->id(), 'reason' => '...']
   );
   ```

4. **Verify** by asking the user to retry login.

Owner: any user with `is_admin` or the equivalent role permission. Per memory, Michael and Eran are the two admins.

This procedure lives also in [docs/runbooks/sso-lockout-recovery.md](../runbooks/sso-lockout-recovery.md) — see V12 below.

## Multi-tenant impact

None. Authentication state is per-user; Agency / Client / pivot tables are untouched.

## Dependencies

- Rollout-spec Phase 1 must have run (so `google_sub` column exists) before Checkpoint 1's tinker query touches the new column. The TOTP-only census can run earlier; full state census needs Phase 1.
- Rollout-spec Phase 2 flag flip must precede Checkpoint 3's matrix walk.
- A production sentinel user (`qa@maddata.media` or similar) must be created before Checkpoint 3.

## Decisions (resolved during planning)

1. **Consent screen status.** Confirmed 2026-05-12: Cloud Console → Audience → Publishing status reads "In production". The "4 / 100 user cap" counter is informational only for our `openid email profile` scopes; ignore it.
2. **User communication.** None. TOTP was already forced for existing users, so anyone active has a working second factor. Users who haven't logged in since TOTP was made mandatory get the `/2fa/setup` two-card picker on their next attempt — that's expected. Any login problems are resolved in person by the admins (Michael / Eran).
3. **Sentinel user lifecycle.** The sentinel account is for the Phase 2 matrix walk and future post-deploy smoke tests — it does **not** stay live in normal operation. Disable (`is_active=false`) after Checkpoint 2 completes. Re-enable manually before any future auth-related deploy. Document its existence in the lockout-recovery runbook so future ops know it's there. (See V7 + V_disable below.)
4. **Day-7 success criterion.** No adoption target. SSO is offered as an option; users who prefer TOTP can stay on TOTP indefinitely. The Day-7 check is a usage signal only — any non-zero `google_sub IS NOT NULL` count confirms the feature is reachable; zero would prompt us to check that the link button is actually working, not that adoption is too low.
