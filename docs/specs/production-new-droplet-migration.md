# Spec: Production Migration to New DigitalOcean Droplet

**Created:** 2026-04-09
**Supersedes:** `docs/specs/production-backup-and-deploy-v2.md` (in-place deploy on old droplet — abandoned)
**Status:** Planning

## Goal

Migrate the live production MadData app (`ad.maddata.media`) from the current shared DigitalOcean droplet (`207.154.253.28`, 99% full, coexists with legacy `maddata`) to a **new, clean DO droplet in fra1**. Rename the project from `maddata-simple` to just `maddata` on disk and in the database. Bring up a modern Nginx + PHP-FPM stack from day one with queue worker, scheduler, and nightly backups configured properly. Cut over via DNS with a brief (~30 min) maintenance window.

### Why new droplet, not in-place upgrade
- **Disk pressure:** current droplet is 99% full (350 MB free on 25 GB). Cleanup is possible but fragile.
- **Stack drift:** current droplet runs Apache2 + `mod_php` (good for legacy `maddata`, suboptimal for modern Laravel). New droplet can run Nginx + PHP 8.4-FPM from day one.
- **Coexistence risk:** four projects under `/var/www/prod/` on the old droplet. A bad Apache vhost change could break legacy media-buying integrations.
- **Deploy safety:** rollback on new droplet = DNS revert (seconds, zero data risk). Rollback on in-place = full DB + files restore (minutes, risk of partial state).
- **Legacy isolation:** old droplet continues to serve legacy `maddata` with its eskimi/outbrain/taboola crons, untouched.

The cost of a second droplet (~$12–18/mo) is negligible compared to the reduction in migration and operational risk.

## Confirmed Decisions

1. **Provider & region:** DigitalOcean, fra1 (same region as current droplet — shortest migration path).
2. **Droplet size:** **`s-1vcpu-2gb`** ($12/mo, 1 vCPU, 2 GB RAM, 50 GB SSD). This doubles disk vs. current at the same price tier. Can resize to `s-2vcpu-2gb` ($18/mo) later if CPU pressure appears under load — DO allows resize without reprovisioning.
3. **OS:** Ubuntu 24.04 LTS.
4. **Web stack:** Nginx + PHP 8.4-FPM + MySQL 8 + Node 20 LTS + Redis (for cache, optionally queue later).
5. **DB name:** `maddata` (dropping `_simple_prod` suffix).
6. **Project path on disk:** `/var/www/maddata` (dropping `-simple` suffix).
7. **GitHub repo:** stays as `eratead/maddata-simple`. Disk directory name ≠ remote name.
8. **DB user:** stays as `webusr` (no gratuitous rename).
9. **Temp subdomain for testing:** `new.ad.maddata.media` (A record → new droplet IP, TLS via Certbot).
10. **Staging:** stays on the current old droplet for now (`/var/www/dev/maddata-simple`). Scope discipline. Can move in a later pass.
11. **DNS:** managed at DigitalOcean. `ad.maddata.media` TTL is already **60 seconds** — no pre-lowering needed.
12. **Cutover window:** up to 30 minutes of `php artisan down` is acceptable.
13. **Old `maddata_simple_prod` DB on old droplet:** keep for **2 weeks** after cutover as cold backup, then drop.
14. **Old `/var/www/prod/maddata-simple` files on old droplet:** keep for 2 weeks, then archive/delete.
15. **Legacy `/var/www/prod/maddata` on old droplet:** untouched. Old droplet continues to host it indefinitely.
16. **Queue worker:** systemd unit (`maddata-queue.service`) from day one — fixes the current silent production bug where `database` queue had no worker.
17. **Scheduler:** cron running `schedule:run` every minute — missing on current prod, installed cleanly on new.
18. **Backups:** `/var/backups/maddata` on the new droplet itself. Off-server backup destination is out of scope for this phase (Phase 7 or later).
19. **Session driver:** keep `file` for now to avoid invalidating sessions during cutover. Can flip to `database` in a later maintenance window.

## Architecture

### Stack on new droplet
```
Nginx (TLS via Let's Encrypt)
  ↓
PHP 8.4-FPM (pool tuning appropriate for 2GB droplet)
  ↓
Laravel 12 app at /var/www/maddata
  ↓
MySQL 8 (local, DB = maddata)
Redis (local, cache driver)
systemd: maddata-queue.service  → php artisan queue:work
cron:    schedule:run every minute
cron:    backup-production.sh nightly at 03:00
```

### PHP extensions required
`php8.4-{cli,fpm,mbstring,xml,bcmath,mysql,curl,zip,gd,intl,redis,imagick}` — verify against [composer.json](../../composer.json) `require.ext-*` entries before provisioning.

### FFmpeg / ffprobe
The app uses `ffprobe` for video metadata (see [Phase 2 P7-P10 commit](../../app/Services/CampaignMetricsService.php)). Install `ffmpeg` package on new droplet.

## Phase 1 — Provision Droplet

**Goal:** bring up a hardened, reachable droplet ready for software installation.

Steps (executed by server agent or operator):
- Create droplet via DO console: Ubuntu 24.04 LTS, `s-1vcpu-2gb`, region fra1, SSH key `id_rsa` added, hostname `maddata-prod`
- Add A record: `new.ad.maddata.media` → new droplet IP, TTL 60s
- SSH in, initial hardening:
  - `apt update && apt upgrade -y`
  - Disable password authentication: `sed -i 's/^#*PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config && systemctl reload ssh`
  - Install UFW: allow 22, 80, 443 only. Deny everything else inbound.
  - Install `fail2ban` with default jails (sshd)
  - Enable `unattended-upgrades` for security patches
  - Set hostname, timezone (Europe/Berlin or UTC — UTC is standard, we already configured `app.display_timezone` for users)
  - Create swap file (2 GB) — small droplet needs it
- Verify: `ssh root@NEW_IP 'uname -a; df -h /'`

**Exit criteria:** can SSH in with key, firewall blocks non-web/ssh ports, system is patched.

## Phase 2 — Install Stack

**Goal:** all services installed and runnable.

- Add Ondřej Surý's PHP PPA (for PHP 8.4 on Ubuntu 24.04 if not in base repos)
- Install: `nginx php8.4-fpm php8.4-{cli,mbstring,xml,bcmath,mysql,curl,zip,gd,intl,redis,imagick} mysql-server redis-server ffmpeg certbot python3-certbot-nginx git composer unzip`
- Install Node 20 LTS via NodeSource
- Enable & start all services: `systemctl enable --now nginx php8.4-fpm mysql redis-server`
- Verify versions match expectations, no service in failed state

## Phase 3 — Configure Services

**Goal:** services are configured for production use with proper tuning.

### MySQL
- Run `mysql_secure_installation`
- Create DB and user (read `DB_PASSWORD` value to share with .env later):
  ```sql
  CREATE DATABASE maddata CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'webusr'@'localhost' IDENTIFIED BY '<generated-strong-password>';
  GRANT ALL PRIVILEGES ON maddata.* TO 'webusr'@'localhost';
  FLUSH PRIVILEGES;
  ```
- MySQL tuning for 2 GB droplet: `innodb_buffer_pool_size=512M`, disable `performance_schema` (saves ~200 MB RAM)

### PHP-FPM pool
- Edit `/etc/php/8.4/fpm/pool.d/www.conf`:
  - `pm = dynamic`
  - `pm.max_children = 15` (conservative for 2 GB droplet)
  - `pm.start_servers = 3`
  - `pm.min_spare_servers = 2`
  - `pm.max_spare_servers = 5`
- `php.ini`: `memory_limit = 256M`, `upload_max_filesize = 50M`, `post_max_size = 55M`, `opcache.memory_consumption = 128`, `opcache.max_accelerated_files = 10000`, `opcache.validate_timestamps = 0` (prod — must reload FPM on code change)

### Nginx vhost
- `/etc/nginx/sites-available/maddata.conf` — standard Laravel vhost template:
  - `root /var/www/maddata/public;`
  - PHP-FPM unix socket
  - `try_files $uri $uri/ /index.php?$query_string;`
  - gzip, security headers already provided by Laravel middleware (CSP from Phase 3 H commit)
  - `client_max_body_size 55M;` (for creative uploads)
- Initially bind to `server_name new.ad.maddata.media;`
- `ln -s` to `sites-enabled`, `nginx -t && systemctl reload nginx`

### Let's Encrypt TLS
- `certbot --nginx -d new.ad.maddata.media --non-interactive --agree-tos --email <ops-email>`
- Auto-renewal cron installed by certbot

### Backup directory
- `mkdir -p /var/backups/maddata && chmod 700 /var/backups/maddata`

**Exit criteria:** `curl -v https://new.ad.maddata.media/` returns Nginx default (or 502 until Laravel is deployed). TLS valid.

## Phase 4 — Deploy Application Code

**Goal:** Laravel app running on new droplet, pointed at fresh (empty) `maddata` DB.

- `mkdir -p /var/www/maddata && chown www-data:www-data /var/www/maddata`
- As `www-data` (or clone as root then chown): `git clone git@github.com:eratead/maddata-simple.git /var/www/maddata`
  - ⚠️ deploy key: add new droplet's SSH pub key to GitHub repo's deploy keys as read-only. Never store GitHub PAT on the droplet.
- `cd /var/www/maddata && composer install --no-dev --optimize-autoloader`
- `npm install && npm run build`
- Copy `.env` template, edit for production values:
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - `APP_URL=https://new.ad.maddata.media` *(temporary — flip to `https://ad.maddata.media` at cutover)*
  - `DB_DATABASE=maddata`
  - `DB_USERNAME=webusr`
  - `DB_PASSWORD=<the strong password from Phase 3>`
  - `QUEUE_CONNECTION=database` (worker added in Phase 5)
  - `SESSION_DRIVER=file` (don't flip during migration)
  - `CACHE_DRIVER=redis`
  - `APP_DISPLAY_TIMEZONE=Asia/Jerusalem`
  - All API keys, SMTP creds, etc. — **opportunity to rotate any secrets you want fresh**
- `php artisan key:generate`
- `php artisan migrate --force` (runs against empty DB — fast, clean)
- `php artisan seed:staging-roles` (creates the 4 roles, idempotent)
- `php artisan storage:link`
- `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- Fix permissions: `chown -R www-data:www-data /var/www/maddata/storage /var/www/maddata/bootstrap/cache`

**Exit criteria:** `curl https://new.ad.maddata.media/` returns the Laravel login page. No 500 errors in `storage/logs/laravel.log`.

## Phase 5 — System Services (Queue, Scheduler, Backups)

**Goal:** all the background infrastructure that was missing on old droplet.

### Queue worker systemd unit
Create `/etc/systemd/system/maddata-queue.service`:
```ini
[Unit]
Description=MadData Laravel Queue Worker
After=network.target mysql.service redis.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/maddata
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
StandardOutput=append:/var/log/maddata-queue.log
StandardError=append:/var/log/maddata-queue.log

[Install]
WantedBy=multi-user.target
```
- `systemctl daemon-reload && systemctl enable --now maddata-queue.service`
- Verify: `systemctl status maddata-queue` → active (running)
- Smoke test: `php artisan tinker` → dispatch a test job → verify worker picks it up within 3 s

### Scheduler cron
- `crontab -u www-data -e` → add: `* * * * * cd /var/www/maddata && php artisan schedule:run >> /dev/null 2>&1`

### Nightly backup cron
- Deploy `scripts/backup-production.sh` to `/var/www/maddata/scripts/` (see separate script spec below)
- `crontab -e` (root) → add: `0 3 * * * /var/www/maddata/scripts/backup-production.sh >> /var/log/maddata-backup.log 2>&1`

### Logrotate for Laravel log
- `/etc/logrotate.d/maddata`:
  ```
  /var/www/maddata/storage/logs/laravel.log {
    daily
    rotate 14
    compress
    missingok
    notifempty
    copytruncate
    su www-data www-data
  }
  /var/log/maddata-queue.log {
    weekly
    rotate 4
    compress
    missingok
    notifempty
  }
  ```

**Exit criteria:** queue worker draining, scheduler firing (wait 1 min, check log), first manual backup run successful (manifest shows DB + env + storage-app archives, sha256 matches).

## Phase 6 — Data Migration Rehearsal & Verification

**Goal:** bring real production data onto new droplet, run full QA, prove the new system works with the real data before cutover.

### Dry-run data import (not cutover yet — users still on old droplet)

- **On old droplet:** `mysqldump --single-transaction --quick --routines --triggers -u webusr -p maddata_simple_prod | gzip > /tmp/maddata_rehearsal.sql.gz`
- **Transfer:** `scp /tmp/maddata_rehearsal.sql.gz root@NEW_IP:/tmp/`
- **On new droplet:**
  - `mysql -u webusr -p -e 'DROP DATABASE maddata; CREATE DATABASE maddata CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'`
  - `gunzip < /tmp/maddata_rehearsal.sql.gz | mysql -u webusr -p maddata`
  - `php artisan migrate --force` (should run any migrations in this repo not yet in the imported dump — might apply the last couple since old prod was behind)
  - `php artisan seed:staging-roles` (idempotent, safe re-run)
- **Transfer storage/app:** `rsync -av -e 'ssh -i ~/.ssh/id_rsa' root@OLD_IP:/var/www/prod/maddata-simple/storage/app/ /var/www/maddata/storage/app/`
  - Only 2.2 MB today — fast
- **Fix permissions:** `chown -R www-data:www-data /var/www/maddata/storage`
- **Clear caches:** `php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan cache:clear`

### Verification on `new.ad.maddata.media`

Run the full [docs/qa/staging-qa-checklist.md](../qa/staging-qa-checklist.md) (46 items) against `new.ad.maddata.media`. Additional migration-specific checks:
- [ ] Admin login works with real admin credentials from old prod
- [ ] Real agencies list matches (same count & names as old prod)
- [ ] Real users list matches
- [ ] A known campaign dashboard renders with correct data (spot check 2-3)
- [ ] A known creative file renders/downloads (proves `storage/app` rsync worked)
- [ ] Upload a test report → ingest succeeds → dashboard updates
- [ ] Trigger an `ActivityDigestMail` manually (via tinker) → verify queue worker processes it → SMTP sends the email
- [ ] Verify activity log timestamps render in Israel time (today's change)
- [ ] Verify by-date report table has no "Visible" column (today's change)
- [ ] Wait 90 seconds, verify scheduler fired at least once (check `storage/logs/laravel.log`)
- [ ] Run `./scripts/backup-production.sh` manually → verify manifest + archives
- [ ] Run `./scripts/restore-production.sh <timestamp> --all` **on a scratch copy, not this droplet** — or skip restore test until a later rehearsal

**If anything fails:** fix on new droplet. The old droplet is untouched and still serving real users. No pressure.

**Exit criteria:** all 46 QA items pass. Sign-off to schedule cutover.

## Phase 7 — Cutover

**Goal:** switch real traffic from old droplet to new droplet. Target: <30 min user-facing downtime.

### Pre-cutover (day before)
- [ ] Confirm `ad.maddata.media` DNS TTL = 60s ✅ (already)
- [ ] Email users: "Brief maintenance window on [date/time], approx 30 min"
- [ ] Verify queue on old droplet is effectively empty (even though no worker is draining, the backlog should be minimal — `SELECT COUNT(*) FROM jobs;`)
- [ ] Take a full backup of old droplet `maddata_simple_prod` for safety
- [ ] Take a full backup of new droplet `maddata` for safety (should be identical content after Phase 6 rehearsal)

### Cutover execution
Step-by-step, expected total ~20–30 min:

1. **Enable maintenance mode on old droplet** (T+0):
   `ssh old 'cd /var/www/prod/maddata-simple && php artisan down --render="errors::503" --retry=600'`

2. **Final mysqldump on old droplet** (T+0, ~30s):
   `ssh old 'mysqldump --single-transaction --quick --routines --triggers -u webusr -p maddata_simple_prod | gzip > /tmp/maddata_final.sql.gz'`

3. **Transfer dump to new droplet** (T+1, ~30s for 200 MB over fra1 internal):
   Direct: `ssh old 'scp /tmp/maddata_final.sql.gz root@NEW_IP:/tmp/'`

4. **Drop & reimport DB on new droplet** (T+2, ~1 min):
   ```
   ssh new 'mysql -u webusr -p -e "DROP DATABASE maddata; CREATE DATABASE maddata CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"'
   ssh new 'gunzip < /tmp/maddata_final.sql.gz | mysql -u webusr -p maddata'
   ssh new 'cd /var/www/maddata && php artisan migrate --force'
   ```

5. **Final rsync of storage/app** (T+3, ~10s for 2.2 MB):
   `rsync -av --delete -e 'ssh -J root@OLD_IP' root@OLD_IP:/var/www/prod/maddata-simple/storage/app/ root@NEW_IP:/var/www/maddata/storage/app/`
   (or direct via intermediary — whatever routing is simplest)

6. **Clear caches & restart queue on new droplet** (T+4):
   ```
   ssh new 'cd /var/www/maddata && php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan route:clear'
   ssh new 'cd /var/www/maddata && php artisan config:cache && php artisan route:cache && php artisan view:cache'
   ssh new 'systemctl restart maddata-queue.service'
   ssh new 'systemctl reload php8.4-fpm'
   ```

7. **Flip APP_URL on new droplet** (T+5):
   `ssh new 'sed -i "s|APP_URL=https://new.ad.maddata.media|APP_URL=https://ad.maddata.media|" /var/www/maddata/.env && cd /var/www/maddata && php artisan config:clear && php artisan config:cache'`

8. **Issue TLS cert for `ad.maddata.media` on new droplet** (T+6, ~30s):
   `ssh new 'certbot --nginx -d ad.maddata.media --non-interactive --agree-tos --email <ops-email>'`
   Certbot updates the nginx vhost to serve both `new.` and the real hostname under valid TLS.

9. **DNS switch** (T+7):
   In DigitalOcean networking panel, edit the `ad.maddata.media` A record:
   - Old value: `207.154.253.28`
   - New value: `<new droplet IP>`
   - Save
   - With TTL=60s, most clients see the new IP within 1–2 min.

10. **Maintenance mode off on new droplet** (T+8):
    `ssh new 'cd /var/www/maddata && php artisan up'`

11. **Verify** (T+9):
    - `curl -v https://ad.maddata.media/` → resolves to new droplet IP, returns login page
    - Log in as admin → dashboard loads → check a real campaign
    - Check `storage/logs/laravel.log` for errors
    - Check `systemctl status maddata-queue` → still active

12. **Monitor** (T+10 to T+40):
    - `tail -f /var/www/maddata/storage/logs/laravel.log`
    - Watch for 500s, queue failures, DB errors
    - Hit a few endpoints manually
    - Ask a trusted user to log in and confirm

**Downtime window:** ~8–10 min of `php artisan down` on old droplet + ~1–2 min DNS propagation for users whose resolvers had the record cached just before the switch. Total worst-case user-visible downtime: **~15 min**. Well under the 30 min budget.

### If something goes wrong during cutover
**Before DNS switch (T < 7):**
- `ssh old 'cd /var/www/prod/maddata-simple && php artisan up'`
- Users are back on old droplet with zero data change (no writes happened during maintenance window)
- Debug new droplet at leisure, retry cutover later

**After DNS switch (T ≥ 7):**
- Revert the A record in DO: `<new IP>` → `207.154.253.28`
- 1–2 min later, users are back on old droplet
- Old droplet still has `php artisan up` run → works fine
- ⚠️ **Data loss risk:** any writes that happened on new droplet between T+7 and T+rollback are lost. That's why we keep the cutover window short and monitor immediately.

## Phase 8 — Post-Cutover

### T+1 hour
- [ ] Spot-check a few user-visible pages again
- [ ] Check queue table: `SELECT COUNT(*) FROM jobs;` → should be 0 or near-0 (worker draining)
- [ ] Check first nightly backup runs at 03:00 (next day)

### T+24 hours
- [ ] Review `storage/logs/laravel.log` for any new error patterns
- [ ] Verify nightly backup ran, manifest looks good
- [ ] Disk use on new droplet < 20% (sanity)

### T+1 week
- [ ] No reported issues from users
- [ ] Backup rotation working (should be 7 backups after 7 nights)
- [ ] Consider re-enabling any Phase-2 work (e.g., flipping session driver to `database`)

### T+2 weeks
- [ ] Drop `maddata_simple_prod` DB on old droplet
- [ ] Archive `/var/www/prod/maddata-simple` on old droplet → `/tmp/maddata-simple-archive-YYYYMMDD.tar.gz`, then `rm -rf` the directory
- [ ] Remove old droplet's vhost for `ad.maddata.media` from Apache config (if still present)
- [ ] Reclaim disk on old droplet (~200 MB from DB + ~??? from files)
- [ ] Old droplet continues to host legacy `maddata` untouched

## Backup & Restore Scripts (to be written as part of Phase 5)

### `scripts/backup-production.sh` (runs on new droplet)
- Reads `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE` from `/var/www/maddata/.env`
- Dumps DB → `/var/backups/maddata/YYYYMMDD_HHMMSS/db.sql.gz`
- Tars `.env` → `env.tar.gz`
- Tars `storage/app/` → `storage-app.tar.gz`
- Writes `manifest.txt`: timestamp, git commit, branch, sha256 of each archive
- Rotates: keeps last 7
- Cron-safe (no interactive prompts, non-zero exit on failure)

### `scripts/restore-production.sh` (runs on new droplet)
- Usage: `./restore-production.sh <timestamp> [--db|--files|--code|--all]`
- Hard confirmation gate: requires typing `RESTORE`
- Wraps destructive work in `php artisan down` / `up`
- Post-restore hooks: cache clears, `queue:restart`, `systemctl reload php8.4-fpm`
- Verifies sha256 before extracting
- Refuses to proceed on manifest checksum mismatch

Both scripts follow the design in the abandoned v2 spec, Part 1 — just targeted at the new droplet paths (`/var/www/maddata`, DB `maddata`, PHP 8.4-FPM service).

## Rollback Strategy

| Failure point | Rollback action | Data loss risk |
|---|---|---|
| During Phase 1–6 (before cutover) | Stop work on new droplet. Old droplet untouched, users still served. | None |
| During cutover T+0 to T+6 (before DNS switch) | `php artisan up` on old droplet. | None |
| During cutover T+7 to T+12 (after DNS switch, within 10 min) | Revert DNS A record to `207.154.253.28`. `php artisan up` still in effect on old droplet. | Any writes on new droplet during the window are lost. |
| After cutover confirmed stable (T+1h+) | Full new-droplet restore from backup (backup-production.sh + restore script). Or, as a last resort, revert DNS and re-import fresh `maddata` dump from new → old droplet. | Writes on new since last backup may be lost. |

**The fact that DNS is the primary rollback mechanism is a major safety win.** DNS revert is idempotent, fast, and doesn't require data operations.

## Dependencies / Unknowns

- **Deploy key for GitHub** — new droplet needs its SSH pub key added as a read-only deploy key on `eratead/maddata-simple`. User must do this via GitHub UI.
- **SMTP credentials** — the new `.env` needs valid SMTP creds. Confirm we have them and they're not tied to old droplet IP (some providers allow-list IPs).
- **Anthropic API key** — AI assistant features need `ANTHROPIC_API_KEY` in new `.env`. Confirm availability.
- **Any IP allow-listing on third-party services** — e.g., if Taboola/Facebook APIs allow-list the old droplet IP, the new droplet IP must be added. This app doesn't currently integrate with those (legacy `maddata` does), so should be a non-issue for `maddata-simple`.
- **`.env` secrets we want to rotate** — user should decide before Phase 4.
- **Imagick for image processing** — confirm `ext-imagick` is actually required by composer.json before installing. If not, drop it from the install list.

## Open Questions (for user before we start)

1. **Deploy key or PAT for GitHub?** — Preference for how the new droplet authenticates to pull code. Deploy key is more secure.
2. **SMTP credentials** — same as old droplet, or new ones? Do any existing SMTP creds have IP allow-listing?
3. **Anthropic API key** — available for transfer to new droplet?
4. **`.env` secrets rotation** — rotate `APP_KEY`? No — rotating `APP_KEY` invalidates all encrypted data (like session cookies, remembered tokens). **Keep `APP_KEY` identical.** Rotate only secrets you know aren't used to encrypt persisted data.
5. **Ops email for Let's Encrypt** — what address should Certbot use for expiry warnings?
6. **Swap file size** — 2 GB default, or different?
7. **Cutover date/time** — pick a low-traffic window, communicate with users.

## Cost Summary

| Item | Monthly |
|---|---|
| New droplet `s-1vcpu-2gb` fra1 | $12 |
| Backup storage (first 2 weeks: 2x backups on each droplet) | $0 (on-droplet) |
| DigitalOcean DNS | $0 (included) |
| Let's Encrypt TLS | $0 |
| **Total ongoing** | **$12/mo** |

Plus one-time provisioning time (server agent work).

Can upgrade to `s-2vcpu-2gb` ($18) or `s-2vcpu-4gb` ($24) later via `doctl compute droplet-action resize` without reprovisioning.
