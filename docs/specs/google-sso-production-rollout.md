# Google SSO + TOTP — Production Rollout

**Status:** Planned
**Author:** Architect (2026-05-10)
**Builds on:** [google-sso-totp-hybrid.md](google-sso-totp-hybrid.md) — feature already shipped to staging at commit `7ed660b`
**Target environment:** Production droplet `164.90.233.136` (`/var/www/maddata`, domain `ad.maddata.media`)

---

## Goal

Roll the Google SSO + TOTP hybrid auth feature out to production safely, using a **dark deploy → flag flip → announce** sequence so we can roll back in seconds without redeploying.

By the end of this spec the production app will:

- Have the SSO code path on disk and the `users.google_sub` / `google_email` / `google_linked_at` columns migrated.
- Have a dedicated **production** Google OAuth 2.0 Client ID (separate from staging) wired into `/var/www/maddata/.env`.
- Have `GOOGLE_SSO_ENABLED=true` in `.env`, with all SSO surfaces (login challenge, setup picker, settings page) live.
- Be reachable at `https://ad.maddata.media/auth/google/callback` from Google's authorization server.
- Be announced to users so they can find Settings → Sign-in methods.

## Non-goals

- No new feature work. This is a rollout, not a redesign.
- No cleanup of `SsoLinkService::resolveLogin()` dead code, doc-comments, or the `composer run test` OOM. Tracked separately.
- No Apple Sign-in, no auto-provisioning of users from Google identity, no `user_sso_identities` pivot. Still parked.
- No staging-side changes. Staging keeps its own OAuth client and its own `.env`.

## Rollout strategy: dark deploy, then flip

The feature flag `config('auth.google_sso_enabled')` (from `GOOGLE_SSO_ENABLED` env var) gates **every user-visible surface**:

| Surface | Flag-off behaviour |
|---|---|
| `2fa-setup.blade.php` | Two-card picker collapses to TOTP-only; Google card not rendered |
| `2fa-challenge.blade.php` | "Or verify with Google" button hidden; auto-redirect to Google for SSO-only users disabled |
| `settings/sign-in-methods.blade.php` | Google row hidden; only TOTP and password rows visible |
| `TwoFactorController::showChallenge` | Skips the SSO-only auto-redirect branch (line 107 guard) |
| `RequireTwoFactor` middleware | Unchanged — `2fa_verified` flag is the only signal |

Routes (`/auth/google/callback`, `/2fa/google/start-*`, `/settings/sign-in-methods/start-connect-google`, etc.) are always registered. With the flag off no UI links to them, so they're inert. A direct `POST` would hit the controller; if Socialite env vars are missing it 500s — acceptable, since no legitimate user can reach these endpoints without UI.

This means we can do the migration + code deploy with the flag **off**, smoke-test that nothing else broke, then flip the flag in a second step. If anything goes wrong post-flip we set `GOOGLE_SSO_ENABLED=false` and clear the config cache — sub-second rollback with no redeploy and no migration rollback.

## Phases

### Phase 0 — Provision production Google OAuth client (no app changes)

Done **before** the deploy. Output: a Client ID, a Client Secret, and a confirmed redirect URI.

**Prerequisite:** Privacy Policy and Terms of Service must be live at public URLs before the OAuth consent screen can be moved out of *Testing*. Drafts at [docs/legal/privacy-policy.md](../legal/privacy-policy.md) and [docs/legal/terms-of-service.md](../legal/terms-of-service.md). Target URLs: `https://maddata.media/privacy` and `https://maddata.media/terms`. See [§Legal-page publishing](#legal-page-publishing) below.

Runbook is in [§Google Cloud Console runbook](#google-cloud-console-runbook) below.

### Phase 1 — Dark deploy (code + migration, flag OFF)

Deploy the SSO code and run the additive migration. Flag stays `false` so users see no change.

Verification: full app still works, normal email+password+TOTP login is unchanged, `php artisan tinker` confirms the new columns exist on `users`.

### Phase 2 — Flip flag, smoke test on real prod

Set `GOOGLE_SSO_ENABLED=true` plus the OAuth client env vars in `/var/www/maddata/.env`. Re-cache config, reload PHP-FPM. Walk an admin account through the staging matrix one more time on real prod data.

Rollback if anything is wrong: set `GOOGLE_SSO_ENABLED=false`, re-cache, reload. ~10 seconds.

### Phase 3 — Announce to users

Post in the team chat / dashboard banner that anyone can connect a Google account in Settings → Sign-in methods.

### Phase 4 — Cleanup window

After 1 week of stable prod usage, the parked items (`SsoLinkService::resolveLogin()` removal, `block_google_auto_verify` comment, test-suite OOM) are picked up in a separate spec.

---

## Database changes

One migration ships with the dark deploy:

`database/migrations/2026_05_04_142828_add_google_sso_columns_to_users_table.php`

```
users
+ google_sub        VARCHAR(255)  NULL  UNIQUE     // Google's stable user id (the OIDC `sub` claim)
+ google_email      VARCHAR(255)  NULL             // shown in settings as "Connected to: x@gmail.com"
+ google_linked_at  TIMESTAMP     NULL             // audit
```

**Safety:** All three columns are nullable, no data backfill, no index on the existing columns. Table is `users` — small (16 rows on prod as of 2026-04-12 health check). Even with a UNIQUE constraint, an `ALTER TABLE` on 16 rows is sub-second. No maintenance window required.

**Down-migration:** drops the unique constraint and the three columns. Already exercised on staging.

## Env var changes

Add to `/var/www/maddata/.env`:

```
GOOGLE_SSO_ENABLED=false                          # phase 1: dark deploy
GOOGLE_CLIENT_ID={prod_client_id}                 # phase 0 output
GOOGLE_CLIENT_SECRET={prod_client_secret}         # phase 0 output
GOOGLE_REDIRECT_URI=https://ad.maddata.media/auth/google/callback
```

Phase 2 flips the first line to `GOOGLE_SSO_ENABLED=true`.

`.env.example` already documents these keys (verified 2026-05-10). No code changes required.

**`config:cache` after every `.env` edit** — this is a Laravel-12 production install with cached config. Skipping the re-cache means the new value never reaches `config()`.

---

## Google Cloud Console runbook

Owner: a Google account with admin access on the MadData GCP org (or whoever owns the staging client; ask Michael if unknown).

The goal is a **separate** OAuth 2.0 client, not a reuse of the staging client. Reasons: clean Google-side audit log, separate quota, ability to rotate credentials independently if either env is compromised.

1. **Project**
   - GCP Console → top-left project picker → use the existing MadData project (the one staging lives in) OR create a new project named `maddata-prod` if separation by project is preferred. Single-project is fine; staging vs prod will be distinguished by client name + redirect URI.
2. **OAuth consent screen** (only if not already configured for the project)
   - APIs & Services → OAuth consent screen.
   - User type: **External** (we don't have a Workspace org, and we want to allow users with arbitrary @gmail / @company emails).
   - App name: `MadData`.
   - User support email: a real ops email.
   - Authorised domains: `maddata.media`.
   - Developer contact: same ops email.
   - Scopes: leave the defaults (`.../auth/userinfo.email` and `.../auth/userinfo.profile`). `openid` is auto-included by Socialite.
   - Test users: not needed once published; if the consent screen is still in *Testing* mode, add the rollout group as test users so they can authenticate before publishing.
   - Publishing status: **In production** before the rollout. If the consent screen is still in *Testing* it caps at 100 distinct test users and shows a warning banner.
3. **Credentials → Create OAuth client ID**
   - Application type: **Web application**.
   - Name: `MadData Production` (must differ from the staging client's name to avoid mix-ups).
   - Authorised JavaScript origins: `https://ad.maddata.media` (no path).
   - Authorised redirect URIs: `https://ad.maddata.media/auth/google/callback` (exact, no trailing slash).
   - Click **Create**.
4. **Capture the credentials**
   - Copy the displayed Client ID and Client Secret immediately into a 1Password / vault entry titled `MadData Prod — Google OAuth`.
   - These are the values for `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` in production `.env`.
5. **Sanity check**
   - From any unauthenticated browser, hitting `https://accounts.google.com/o/oauth2/v2/auth?client_id={prod_client_id}&redirect_uri=https%3A%2F%2Fad.maddata.media%2Fauth%2Fgoogle%2Fcallback&response_type=code&scope=openid+email+profile` should land on the Google consent screen with the **MadData** app name. If it shows `Error 400: redirect_uri_mismatch` the redirect URI in step 3 is wrong — fix and retry.

Do **not** delete or rotate the staging client during this work. Both clients coexist permanently.

---

## Legal-page publishing

Before the OAuth consent screen can be flipped from *Testing* to *In production*, Google requires both an **Application privacy policy link** and an **Application terms of service link** under the same authorised domain (`maddata.media`).

Drafts live at:

- [docs/legal/privacy-policy.md](../legal/privacy-policy.md) — drafted 2026-05-10, marked as a draft pending legal review.
- [docs/legal/terms-of-service.md](../legal/terms-of-service.md) — drafted 2026-05-10, marked as a draft pending legal review.

Target public URLs:

- `https://maddata.media/privacy`
- `https://maddata.media/terms`

Both drafts contain a "Drafting note" block at the top. **Delete those notes before publishing.** Have an attorney familiar with Israeli Privacy Protection Law and B2B SaaS terms review §11 (warranties), §12 (limitation of liability), and §16 (governing law) of the ToS before publishing — the drafts are starting points, not final legal copy.

The publishing mechanism depends on what runs `maddata.media`. If it's WordPress: create two pages, paste rendered HTML or use a Markdown plugin. If it's static (e.g. Jekyll, Hugo, plain HTML): drop two pages into the site source and deploy. The exact mechanics are out of scope for this app spec — the team that owns `maddata.media` does the publishing.

Once both URLs are live and reachable from the public internet, paste them into the Cloud Console Branding screen the user is currently on (Application privacy policy link, Application Terms of Service link), then move the consent screen to *In production* via Audience → Publishing status.

---

## Verification matrix

Phase 1 (dark deploy, flag still OFF) — every check is a regression guard:

- [ ] `php artisan migrate:status` shows `add_google_sso_columns_to_users_table` as `Ran`.
- [ ] `php artisan tinker` → `\DB::select('describe users')` lists `google_sub`, `google_email`, `google_linked_at`.
- [ ] Existing user logs in with email + password + TOTP → reaches dashboard. (No SSO surfaces present.)
- [ ] `/2fa/setup` for a brand-new user shows TOTP-only (no Google card). Confirms flag-off path.
- [ ] `/settings/sign-in-methods` for an existing user shows TOTP + password rows only, no Google row.
- [ ] `storage/logs/laravel.log` clean — no exceptions during login.

Phase 2 (flag flipped ON) — full feature walk on prod data:

- [ ] Settings page now renders the Google row.
- [ ] Existing TOTP-only user → Settings → Connect Google → Google consent → returns linked. `users.google_sub`, `google_email`, `google_linked_at` populated for that row.
- [ ] Same user → log out → log back in with email+password → `2fa.challenge` shows TOTP input AND "Or verify with Google" button. Clicking "Verify with Google" with the linked account passes through to dashboard.
- [ ] Brand-new test user (created via admin) → first login → `2fa.setup` shows two cards, Google card on top.
- [ ] User picks Google in setup → links → dashboard. Subsequent login auto-redirects to Google with no click.
- [ ] User with Google as only second factor → Settings → "Disable TOTP" greyed (already the case). "Disconnect Google" is allowed only after they enrol TOTP.
- [ ] `auth.google.*` callback round-trip shows no `Just a moment...` Cloudflare interstitial in browser network log.
- [ ] No spike in `storage/logs/laravel.log` from `Socialite\Two\InvalidStateException` (the staging-era bug).

Rollback trigger: any of the Phase 2 checks fails AND the failure is not isolated to a single user's state. Set `GOOGLE_SSO_ENABLED=false`, `php artisan config:clear && php artisan config:cache`, `systemctl reload php8.4-fpm`, then debug offline.

## Rollback plan

| Failure mode | Rollback action |
|---|---|
| SSO surface broken (consent screen errors, callback 500, link button does nothing) | `GOOGLE_SSO_ENABLED=false` in `.env`, `php artisan config:cache`, `systemctl reload php8.4-fpm`. UI returns to pre-rollout state instantly. Migration stays in place — additive, harmless. |
| Migration failed mid-run | `php artisan migrate:rollback --step=1`. Confirm columns gone with `describe users`. Investigate offline. |
| Existing email+password+TOTP login broken (regression) | Revert the deploy: `git reset --hard {previous_prod_commit}` on prod, `composer install --no-dev --optimize-autoloader`, clear caches, reload FPM. Keep the migration — it's additive and not the cause of an auth regression. |
| Google OAuth client misconfigured (`redirect_uri_mismatch`) | Fix in Cloud Console → Credentials → edit client → save. No app-side action. |

The only non-trivial-to-reverse step is the migration, and it's been exercised on staging. Code and flag are both reversible in seconds.

---

## Multi-tenant impact

None. SSO link state is per-user (`users.google_sub`). Agency / Client / pivot tables are untouched. A user's accessible-clients view is unchanged regardless of which second factor they used.

## Dependencies

- Google Cloud Console access for whoever owns the GCP org.
- Production droplet SSH access at `164.90.233.136` (key `~/.ssh/id_rsa`, user `root`).
- Production `.env` write access (vault / 1Password for the new secrets).
- The `server` agent for the actual deploy; this spec is the runbook the agent follows.

## Open questions

1. **Who owns the GCP org / who creates the OAuth client?** The summary doesn't name them. Default assumption: Michael. If different, the runbook step "Owner: a Google account with admin access" needs an explicit name before Phase 0 starts.
2. **Announcement channel.** "Team chat / dashboard banner" was the loose phrasing in the SSO spec. If we want a banner, that's a separate small piece of UI; if it's a Slack/email message, that's a comms task. Architect default: ship a one-liner in `dashboard/index.blade.php` for a week, gated by a per-user dismiss; this is small enough to bundle with the rollout, but only if the user wants it.
3. **Phase 0 timing.** The OAuth client can be provisioned days ahead of the deploy with no risk. Recommend doing it as soon as the spec is approved, so the credentials are in the vault by the time Phase 1 runs.
