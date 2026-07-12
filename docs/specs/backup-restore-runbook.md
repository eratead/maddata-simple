# Backup & Restore Runbook — MadData Production

Last verified by a full restore drill: **2026-07-12** (all steps below executed successfully).

## What gets backed up

`scripts/backup-production.sh` runs nightly at **03:00 UTC** (root cron on the prod droplet) and produces a timestamped directory containing:

| File | Contents |
|------|----------|
| `db.sql.gz` | Full mysqldump of `maddata` (single-transaction, routines, triggers, utf8mb4) |
| `env.tar.gz` | `/var/www/maddata/.env` — includes `APP_KEY` (without it, encrypted data is lost) |
| `storage-app.tar.gz` | `/var/www/maddata/storage/app` (creative uploads etc.) |
| `manifest.txt` | Git commit, file sizes, SHA-256 checksums, disk usage |

**Locations:**
- **Local**: `/var/backups/maddata/<YYYYMMDD_HHMMSS>/` on the prod droplet (164.90.233.136) — last 7 kept
- **Off-site**: `s3://maddata-cdn/backups/<YYYYMMDD_HHMMSS>/` — DO Spaces, region `fra1`, last 30 days kept

Credentials for Spaces are in the prod `.env`: `DO_BUCKET`, `DO_ACCESS_KEY_ID`, `DO_SECRET_ACCESS_KEY`, `DO_SPACES_ENDPOINT`. The key (`maddata-backup-key`) is scoped to the `maddata-cdn` bucket only.

## MySQL access on the droplet

- `webusr` (app user) is scoped to the `maddata` DB only — it **cannot** create scratch databases.
- For restore work use the maintenance account: `mysql --defaults-file=/etc/mysql/debian.cnf`
- MySQL root has no socket auth on this droplet.

## A. Download a backup from Spaces

```bash
ssh root@164.90.233.136
mkdir -p /tmp/restore && cd /tmp/restore
source <(grep '^DO_' /var/www/maddata/.env | sed 's/^/export /')

s3() {
    s3cmd --access_key="$DO_ACCESS_KEY_ID" --secret_key="$DO_SECRET_ACCESS_KEY" \
          --host="$DO_SPACES_ENDPOINT" --host-bucket="%(bucket)s.$DO_SPACES_ENDPOINT" "$@"
}

s3 ls s3://$DO_BUCKET/backups/                      # pick a timestamp
TS=20260712_052844                                  # <-- the one you picked
s3 get --force "s3://$DO_BUCKET/backups/$TS/db.sql.gz" \
               "s3://$DO_BUCKET/backups/$TS/env.tar.gz" \
               "s3://$DO_BUCKET/backups/$TS/storage-app.tar.gz" \
               "s3://$DO_BUCKET/backups/$TS/manifest.txt" .
```

**Verify integrity before restoring anything:**

```bash
grep sha256 manifest.txt | sed -E 's/^ +([^ ]+) +[0-9]+ bytes +sha256=(.+)$/\2  \1/' | sha256sum -c
# expect: db.sql.gz: OK / env.tar.gz: OK / storage-app.tar.gz: OK
```

## B. Restore drill (safe — never touches the live DB)

The dump contains **no `USE` or `CREATE DATABASE` statements**, so it only loads into the DB you name explicitly. Confirm anyway:

```bash
zcat db.sql.gz | grep -cE '^(USE |CREATE DATABASE)'   # must output 0
```

Restore into a scratch DB and compare against live:

```bash
mysql --defaults-file=/etc/mysql/debian.cnf -e \
  'CREATE DATABASE maddata_restore_drill CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
zcat db.sql.gz | mysql --defaults-file=/etc/mysql/debian.cnf maddata_restore_drill

# table count should match live (24 as of 2026-07-12)
mysql --defaults-file=/etc/mysql/debian.cnf -N -e \
  "SELECT (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='maddata'),
          (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='maddata_restore_drill');"

# spot-check row counts
for t in users clients campaigns campaign_data creatives creative_files roles activity_logs agencies; do
  live=$(mysql --defaults-file=/etc/mysql/debian.cnf -N -e "SELECT COUNT(*) FROM maddata.$t")
  rest=$(mysql --defaults-file=/etc/mysql/debian.cnf -N -e "SELECT COUNT(*) FROM maddata_restore_drill.$t")
  printf '%-20s live=%-8s restored=%-8s %s\n' $t $live $rest $([ "$live" = "$rest" ] && echo MATCH || echo DIFF)
done
```

Check the archives extract:

```bash
mkdir -p extract
tar -xzf env.tar.gz -C extract && grep -q '^APP_KEY=' extract/.env && echo 'APP_KEY present'
tar -xzf storage-app.tar.gz -C extract && find extract/app -type f | wc -l   # compare with live:
find /var/www/maddata/storage/app -type f | wc -l
```

**Cleanup (always):**

```bash
mysql --defaults-file=/etc/mysql/debian.cnf -e 'DROP DATABASE maddata_restore_drill;'
rm -rf /tmp/restore
```

Note: row counts drift between the nightly backup and the moment you run the drill — small `DIFF`s on active tables (`activity_logs`, `campaign_data`) are expected on a live system. Identical counts require comparing against a backup taken moments earlier.

## C. Real disaster recovery (droplet lost)

On a fresh droplet with the stack installed (Nginx, PHP 8.4-FPM, MySQL 8, Redis — see `docs/specs/production-new-droplet-migration.md`):

1. Download + verify the latest backup (section A) — the Spaces credentials are inside the backed-up `env.tar.gz`; keep a copy of them (or a DO login) somewhere outside the droplet.
2. Clone the repo at the commit recorded in `manifest.txt`:
   ```bash
   git clone git@github.com:eratead/maddata-simple.git /var/www/maddata
   cd /var/www/maddata && git checkout <commit-from-manifest>
   composer install --no-dev --optimize-autoloader && npm ci && npm run build
   ```
3. Restore `.env`: `tar -xzf env.tar.gz -C /var/www/maddata` (adjust `DB_*` if credentials differ on the new box).
4. Create DB + user, then load the dump **into the real DB this time**:
   ```bash
   mysql -e "CREATE DATABASE maddata CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -e "CREATE USER 'webusr'@'localhost' IDENTIFIED BY '<password-from-env>';
             GRANT ALL PRIVILEGES ON maddata.* TO 'webusr'@'localhost'; FLUSH PRIVILEGES;"
   zcat db.sql.gz | mysql maddata
   ```
5. Restore storage: `tar -xzf storage-app.tar.gz -C /var/www/maddata/storage/` then `chown -R www-data:www-data /var/www/maddata/storage`.
6. `php artisan config:clear && php artisan cache:clear && php artisan storage:link`
7. Re-create services: nginx vhost, `certbot --nginx -d ad.maddata.media`, queue worker service, cron entries (scheduler + backup).
8. Point DNS `ad.maddata.media` at the new droplet.
9. Verify: login, campaign list, creative downloads, `php artisan queue:work --once`.

## D. Drill cadence

Run section B (takes ~5 minutes) after any change to the backup script, and otherwise **quarterly**. A backup that hasn't been restore-tested is a hope, not a backup.

| Date | Result | Notes |
|------|--------|-------|
| 2026-07-12 | ✅ PASS | 24 tables, all row counts matched live; APP_KEY present; 492 storage files matched |
