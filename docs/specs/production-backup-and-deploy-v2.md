# Spec: Production Backup & Deploy (v2)

**Created:** 2026-04-09
**Supersedes:** `docs/specs/production-deploy-plan.md` (2026-03-23) — that plan is preserved for historical reference but is stale.
**Status:** Planning. Not yet executed.

## Goal

Two tightly-coupled objectives:

1. **Safe rewind capability** — Create a reusable backup/restore system on the production server so that, at any time, we can snapshot DB + files + code commit and restore any of them independently. This is a prerequisite to the deploy itself.
2. **Refreshed production deploy plan** — Update the March deploy plan to reflect the 13+ additional migrations, 220+ new tests, and the gaps identified during audit (OPcache, queue workers, files backup, stale count assertions).

**Scope boundary:** this spec designs the prep work and the updated plan. The actual deploy execution remains a manual, supervised operation — not fully automated.

---

## Part 1 — Backup & Restore System

### Design decisions

| Decision | Choice | Rationale |
|---|---|---|
| **Backup location** | `/var/backups/maddata/<timestamp>/` on the production server | Local first. Off-server sync is a separate concern (see Open Questions). |
| **What to back up** | MySQL dump (gzipped) + `.env` + `storage/app/` + git commit hash | Complete state required to reproduce the running app. Code itself is recoverable via `git checkout <commit>` so we don't tarball the whole project tree. |
| **Retention** | Keep last 7 timestamped backups, rotate older | Bounded disk use. Adjustable via `BACKUP_RETENTION` env var in the script. |
| **DB dump flags** | `--single-transaction --quick --lock-tables=false --routines --triggers` | InnoDB-safe, non-locking, includes stored routines and triggers. |
| **Credential handling** | Read `DB_PASSWORD` from the project's `.env` file inside the script. Never hardcode. | Fixes the precedent of hardcoded passwords in `scripts/deploy-staging.sh`. |
| **Script reusability** | Standalone — runnable any time, not just during deploy | Gives us daily backups via cron, ad-hoc snapshots, and deploy-time snapshots with one tool. |

### Files to create

#### 1. `scripts/backup-production.sh`

Runs on the production server. Produces `/var/backups/maddata/YYYYMMDD_HHMMSS/` containing:

```
db.sql.gz           ← mysqldump of maddata_simple_prod, gzipped
env.tar.gz          ← project .env file
storage-app.tar.gz  ← project storage/app/ tree (creatives, reports, uploads)
manifest.txt        ← metadata: timestamp, git commit, git branch, sizes, checksums
```

**Behavior:**
- `set -euo pipefail` at the top
- Read `DB_USER`, `DB_PASSWORD`, `DB_DATABASE` from `/var/www/prod/maddata-simple/.env`
- Pass password to `mysqldump` via `MYSQL_PWD` env var (not `-p` flag) to keep it out of process listing
- Capture `git rev-parse HEAD` and `git rev-parse --abbrev-ref HEAD` into manifest
- Compute sha256 of each archive, store in manifest
- Rotate: `ls -1t /var/backups/maddata/ | tail -n +8 | xargs -r rm -rf`
- Exit 0 on success, non-zero on any failure (script should be cron-safe)
- Final line: print manifest to stdout

**Safety:**
- No `rm -rf` of anything outside `/var/backups/maddata/`
- No writes to the project directory
- No DB schema changes
- Read-only wrt the application

#### 2. `scripts/restore-production.sh`

Usage: `./restore-production.sh <timestamp> [--db|--files|--code|--all]`

**Behavior:**
- First positional arg: timestamp directory name (e.g., `20260409_143000`). If omitted, list available backups and exit.
- Second arg: restore scope selector (default `--all`):
  - `--db` → only restore DB from `db.sql.gz`
  - `--files` → only restore `.env` + `storage/app/`
  - `--code` → only `git checkout <commit>` from manifest + `composer install` + cache clears
  - `--all` → db + files + code + post-restore hooks
- **Hard confirmation gate:** print manifest, require user to type `RESTORE` (exact uppercase) to proceed. No `-y` flag — this is a destructive, supervised operation only.
- **Maintenance mode:** before any destructive action, run `php artisan down`. After all steps succeed, run `php artisan up`. On failure, leave in maintenance mode so an operator can intervene.
- **Post-restore hooks** (all scopes):
  - `php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan route:clear`
  - `php artisan queue:restart` (workers pick up restored code)
  - `systemctl reload php8.3-fpm || systemctl reload php8.2-fpm || true` (OPcache flush)
- Exit codes: 0 success, 1 bad args / backup missing, 2 user aborted, 3 restore failure (still in maintenance mode)

**Safety:**
- Never run without explicit timestamp argument
- Never overwrite an in-progress backup
- Verify archive sha256 against manifest before extracting — refuse to restore a corrupted archive

#### 3. Cron schedule (nightly backup)

Add to root crontab on production server:

```
0 3 * * * /var/www/prod/maddata-simple/scripts/backup-production.sh >> /var/log/maddata-backup.log 2>&1
```

Runs at 3 AM server time daily. Output goes to a dedicated log. The 7-backup rotation keeps disk use bounded.

### Why not back up the full project tree?

Because the project tree = code (recoverable via git) + vendor (regenerable via `composer install`) + node_modules (gitignored) + storage (backed up separately) + .env (backed up separately). Tarballing the whole tree would be ~hundreds of MB per backup, mostly duplicated, mostly recoverable by other means.

What we back up instead:
- **Commit hash** → reproduces code via `git checkout`
- **`.env`** → reproduces configuration and secrets
- **`storage/app/`** → reproduces user uploads (the only non-git state)
- **DB dump** → reproduces all dynamic state

This is a full rewind — not a filesystem snapshot, but functionally equivalent for this app.

### What about `public/build/`?

Compiled Vite assets. Regenerated by `npm run build` during deploy. Not backed up separately; the restore flow doesn't need them because the restore hook does not rebuild assets (it checks out the old commit, which has its own `public/build/` committed if that's the convention, or rebuilds if not).

**Open question:** Is `public/build/` gitignored in production? If yes, restore-code must run `npm run build` (requires Node on server). If no, git checkout alone suffices. Builder to check during implementation.

### What about `.env` secrets rotation?

If secrets are rotated between a backup and a restore, restoring the old `.env` brings back old credentials. The restore script should warn (in the manifest print-out) if the backed-up `.env` is older than 14 days, prompting the operator to decide whether to restore it or keep the current one.

---

## Part 2 — Deploy Plan Refresh

### Pre-deploy prerequisites (must ALL be true)

- [ ] **All uncommitted work is committed** — visible column removal + Israel time activity logs are in working tree right now. Commit them first.
- [ ] **Test suite is green locally** — `composer run test` passes (472+ tests)
- [ ] **Staging is up to date with main** — `origin/staging` branch has been force-updated to match what we intend to ship
- [ ] **Staging QA has been re-run** against the current staging code — [docs/qa/staging-qa-checklist.md](../qa/staging-qa-checklist.md) items all pass
- [ ] **Current production commit hash captured** — stored in a deploy log before any `git pull`
- [ ] **Backup scripts exist on server** — Part 1 of this spec is implemented and tested
- [ ] **Fresh production backup completed** — `./backup-production.sh` ran immediately before deploy, manifest verified
- [ ] **SSH key loaded locally** — `ssh-add ~/.ssh/id_rsa`
- [ ] **Brief maintenance window coordinated** with Eran — ~3–5 min window

### Updated deploy sequence

The refreshed sequence replaces the old plan's Steps 0–9. Key additions marked 🆕.

```
0.   🆕 Capture current commit:
       ssh prod 'cd /var/www/prod/maddata-simple && git rev-parse HEAD' > /tmp/prod-pre-deploy-commit.txt

1.   🆕 Take pre-deploy backup:
       ssh prod '/var/www/prod/maddata-simple/scripts/backup-production.sh'
       (verify manifest output — confirm non-zero db.sql.gz size)

2.   🆕 Enter maintenance mode:
       ssh prod 'cd /var/www/prod/maddata-simple && php artisan down --render="errors::503" --retry=60'

3.   Push code:
       git push origin main

4.   Pull on server:
       ssh prod 'cd /var/www/prod/maddata-simple && git fetch && git pull origin main'

5.   Install dependencies:
       ssh prod 'cd /var/www/prod/maddata-simple && composer install --no-dev --optimize-autoloader'

6.   Handle legacy Sanctum migration (idempotent — safe to re-run):
       ssh prod 'cd /var/www/prod/maddata-simple && mysql … INSERT IGNORE INTO migrations …'

7.   Run migrations:
       ssh prod 'cd /var/www/prod/maddata-simple && php artisan migrate --force'

8.   Seed roles (idempotent — uses firstOrCreate):
       ssh prod 'cd /var/www/prod/maddata-simple && php artisan seed:staging-roles'

9.   Clear caches + rebuild assets:
       ssh prod 'cd /var/www/prod/maddata-simple && php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan route:clear'
       ssh prod 'cd /var/www/prod/maddata-simple && npm run build'
       (⚠️ requires Node on server — verify before first run)

10.  🆕 Restart queue workers (picks up new code):
       ssh prod 'cd /var/www/prod/maddata-simple && php artisan queue:restart'

11.  🆕 Reload PHP-FPM (flushes OPcache):
       ssh prod 'systemctl reload php8.3-fpm || systemctl reload php8.2-fpm'
       (⚠️ verify PHP version on server and update script accordingly)

12.  Session driver switch (⚠️ conditional — only if not already on database):
       ssh prod 'cd /var/www/prod/maddata-simple && grep SESSION_DRIVER .env'
       (if it shows "file", run the sed replacement; if it already shows "database", skip)

13.  Scheduler cron (⚠️ conditional — only if not already installed):
       ssh prod 'crontab -l | grep schedule:run'
       (if missing, add it; if present, skip)

14.  Exit maintenance mode:
       ssh prod 'cd /var/www/prod/maddata-simple && php artisan up'

15.  🆕 Flexible verification (no hardcoded counts):
       ssh prod 'cd /var/www/prod/maddata-simple && php artisan tinker --execute="
         echo \"Agencies:\" . \App\Models\Agency::count() . PHP_EOL;
         echo \"Roles:\"    . \App\Models\Role::count() . PHP_EOL;
         echo \"Users w/role:\" . \App\Models\User::whereNotNull(\"role_id\")->count() . PHP_EOL;
         echo \"Clients w/agency:\" . \App\Models\Client::whereNotNull(\"agency_id\")->count() . PHP_EOL;
         echo \"Latest migration:\" . DB::table(\"migrations\")->latest(\"id\")->value(\"migration\") . PHP_EOL;
       "'
       Assertions:
       - All four counts are > 0
       - Latest migration matches the last filename in database/migrations/ locally
       - No exception from tinker

16.  🆕 Run campaign status command (picks up any campaigns past end date):
       ssh prod 'cd /var/www/prod/maddata-simple && php artisan campaigns:generate-status'

17.  Smoke test (manual):
       - Login as admin → dashboard loads
       - View agencies list
       - View users list — roles visible
       - View one campaign dashboard with real data
       - Check activity log timestamps render in Israel time (new behavior from today's task)
       - Check dashboard by-date table has no "Visible" column (new behavior from today's task)

18.  🆕 Monitor for 15 minutes:
       ssh prod 'tail -f /var/www/prod/maddata-simple/storage/logs/laravel.log'
       Look for: exceptions, 500s, queue failures
```

### Rollback plan (updated)

**If deploy fails at any step before Step 14 (maintenance mode still on):**
- You are already in maintenance mode — users see 503, not errors.
- Run `./scripts/restore-production.sh <pre-deploy-timestamp> --all`
- Confirm with `RESTORE`
- Script handles db + files + code + caches + queues + OPcache + maintenance exit

**If deploy fails after Step 14 (app is live, bug in production):**
- Enter maintenance mode manually: `php artisan down`
- Then run `./scripts/restore-production.sh <pre-deploy-timestamp> --all`
- Same as above

**If only the code is broken, not the DB:**
- `./scripts/restore-production.sh <pre-deploy-timestamp> --code`
- Faster: no DB restore, just git checkout + composer + caches + queue restart + OPcache flush

**If data corruption is detected hours later:**
- A nightly backup from cron is available (see Part 1)
- `./scripts/restore-production.sh <nightly-timestamp> --db`
- ⚠️ This restores DB only; any files uploaded since the backup are KEPT; any files deleted since the backup are NOT brought back. Coordinate with ops before running.

---

## Part 3 — Pre-deploy Checklist Summary

### Prep tasks (do before scheduling a deploy window)

1. **Land uncommitted work** — commit the visible column + Israel time tasks currently in the working tree.
2. **Verify tests green** — `composer run test` must pass.
3. **Write backup script** — `scripts/backup-production.sh` per Part 1.
4. **Write restore script** — `scripts/restore-production.sh` per Part 1.
5. **Test backup script on staging first** — never debug a backup script in production.
6. **Test restore script on staging first** — simulate a rollback end-to-end.
7. **Install scripts on production server** — upload + chmod +x.
8. **Run first backup on production** — verify manifest, db.sql.gz size > 0, archives extract cleanly.
9. **Install nightly cron** — 3 AM daily backup.
10. **Update/verify staging** — ensure staging branch reflects what we intend to ship.
11. **Run full staging QA** — work through [docs/qa/staging-qa-checklist.md](../qa/staging-qa-checklist.md).
12. **Verify server prerequisites** — Node installed? PHP version? `/var/backups/maddata/` writable? Queue worker running under supervisor?
13. **Audit session driver & scheduler cron** — check if already done from March deploy.
14. **Schedule deploy window** — coordinate with Eran.

### Deploy-day tasks (execute the plan above)

Steps 0–18 from Part 2.

---

## Open Questions (need user decision before builder starts)

1. **Staging freshness** — Is `origin/staging` currently up to date with `main`? If not, when was it last updated? This determines how much staging-re-deploy work is needed before we can QA.

2. **Staging QA status** — Has the 46-item staging QA checklist been run at all? Partially? Needs re-run from scratch? This is the gating criterion.

3. **Off-server backup** — Should backups live only on the production VM (under `/var/backups/`) or also sync off-server (rsync to local machine, S3, Hetzner Storage Box, etc.)? **Risk:** if the VM is destroyed, local backups die with it. **Recommendation:** add an off-server destination as Phase 2, not blocking first deploy.

4. **Maintenance window acceptance** — The old plan accepted "brief errors during migration window (no downtime)". The refreshed plan uses `php artisan down` for the full 3–5 min deploy. Is that acceptable, or do you want zero-downtime (requires more complex blue/green setup, out of scope for v1)?

5. **Node on production** — Does the production server have Node installed? If not, we need to either (a) install it or (b) build assets locally and rsync `public/build/`. The March plan says `npm run build` on server, so presumably yes, but worth re-verifying.

6. **PHP-FPM version on server** — The OPcache reload step needs the exact service name (`php8.2-fpm` vs `php8.3-fpm`). Answer drives a script constant.

7. **Queue worker supervisor** — Is there a systemd/supervisor unit running `php artisan queue:work` on production? If so, `queue:restart` handles it. If not, there may be no worker processing `ShouldQueue` mailables, meaning the activity digest never actually sends. Worth confirming.

8. **DB password source** — The backup script should read from `.env`, but the existing `scripts/deploy-staging.sh` hardcodes the password on line 23. Should we (a) update the staging script to also read from `.env` (cleaner), or (b) accept the precedent and hardcode in the production backup script too (faster)? **Recommendation:** (a), do it right.

## Confirmed decisions

1. **Full rewind = DB + `.env` + `storage/app/` + code commit hash.** Not a full filesystem snapshot.
2. **Backup is reusable and nightly**, not tied to deploy time.
3. **Restore has a hard confirmation gate.** No `-y` flag.
4. **Maintenance mode** is used during deploy. Brief downtime accepted.
5. **The March deploy plan is superseded** by this one for future reference, but kept on disk for historical context.
