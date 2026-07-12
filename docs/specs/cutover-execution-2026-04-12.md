# Spec: Production Cutover Execution — 2026-04-12

**Parent spec:** [production-new-droplet-migration.md](production-new-droplet-migration.md)
**Status:** Ready to execute
**Covers:** Phase 6 (fresh data) + Phase 7 (cutover) + soft-launch via admin-only mode

## Context

- **Old prod:** 207.154.253.28 → `/var/www/prod/maddata-simple`, DB `maddata_simple_prod`
- **New prod:** 164.90.233.136 → `/var/www/maddata`, DB `maddata`
- **Temp domain:** `new.ad.maddata.media` (already live on new droplet, TLS valid)
- **Production domain:** `ad.maddata.media` (currently points to old droplet, TTL 60s)
- Pre-cutover health check passed: all services running, zero errors, DB rehearsal data loaded, 14% disk used, backups running nightly.
- Delta since rehearsal: 3 rows in `campaign_data`, 75 rows in `placements_data` — resolved by fresh dump.
- No creative files on either server (`storage/app` is empty on both).

## Cutover Sequence

This is a **modified Phase 7** from the parent spec. Key difference: instead of going straight from maintenance → DNS flip → open to all, we use the app's built-in **admin-only mode** as a soft-launch gate. This gives admins time to QA with real data on the real domain before regular users see it.

### Step 1 — Fresh dump from old prod (T+0, ~1 min)

```bash
# On old droplet — dump BEFORE artisan down so users aren't waiting
ssh root@207.154.253.28 'mysqldump --single-transaction --quick --routines --triggers -u webusr -p maddata_simple_prod | gzip > /tmp/maddata_final.sql.gz && ls -lh /tmp/maddata_final.sql.gz'
```

### Step 2 — Maintenance mode on old prod (T+1)

```bash
ssh root@207.154.253.28 'cd /var/www/prod/maddata-simple && php artisan down --render="errors::503" --retry=600'
```

Users on old prod now see 503. Clock starts.

### Step 3 — Enable admin-only mode on new prod (T+1)

Via UI: go to `https://new.ad.maddata.media/admin/system-status` → toggle admin-only mode ON.

Or via CLI:
```bash
ssh root@164.90.233.136 'cd /var/www/maddata && php artisan tinker --execute="Cache::forever(\"admin_only_login\", true); echo \"Admin-only mode: ON\";"'
```

This ensures that when DNS flips, non-admin users who hit the new server get a maintenance message at login.

### Step 4 — Transfer dump to new droplet (T+2, ~30s)

```bash
scp root@207.154.253.28:/tmp/maddata_final.sql.gz root@164.90.233.136:/tmp/
```

### Step 5 — Import fresh data on new droplet (T+3, ~1-2 min)

```bash
ssh root@164.90.233.136 'mysql -u webusr -p -e "DROP DATABASE maddata; CREATE DATABASE maddata CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" && gunzip < /tmp/maddata_final.sql.gz | mysql -u webusr -p maddata && cd /var/www/maddata && php artisan migrate --force && php artisan seed:staging-roles'
```

### Step 6 — Clear caches & restart services (T+5)

```bash
ssh root@164.90.233.136 'cd /var/www/maddata && php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan route:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache && systemctl restart maddata-queue.service && systemctl reload php8.4-fpm'
```

**Important:** `cache:clear` will wipe the `admin_only_login` flag. Re-enable it immediately:

```bash
ssh root@164.90.233.136 'cd /var/www/maddata && php artisan tinker --execute="Cache::forever(\"admin_only_login\", true); echo \"Admin-only mode: re-enabled\";"'
```

### Step 7 — Flip APP_URL in .env (T+6)

```bash
ssh root@164.90.233.136 'sed -i "s|APP_URL=https://new.ad.maddata.media|APP_URL=https://ad.maddata.media|" /var/www/maddata/.env && cd /var/www/maddata && php artisan config:clear && php artisan config:cache'
```

### Step 8 — Issue TLS cert for ad.maddata.media (T+7, ~30s)

```bash
ssh root@164.90.233.136 'certbot --nginx -d ad.maddata.media -d new.ad.maddata.media --non-interactive --agree-tos --email ops@maddata.media'
```

Update Nginx server_name to accept both domains:
```bash
ssh root@164.90.233.136 'sed -i "s/server_name new.ad.maddata.media;/server_name ad.maddata.media new.ad.maddata.media;/" /etc/nginx/sites-available/maddata.conf && nginx -t && systemctl reload nginx'
```

**Note:** Certbot needs DNS to already point to the new server for HTTP-01 challenge. If the cert fails here, do it after the DNS flip in Step 9.

### Step 9 — DNS switch (T+8)

In DigitalOcean Networking panel:
- Edit A record for `ad.maddata.media`
- Change from `207.154.253.28` to `164.90.233.136`
- Save

TTL is 60s — propagation within 1-2 minutes.

### Step 10 — Admin QA on real domain (T+10 to T+30)

Admins log in at `https://ad.maddata.media` and verify:
- [ ] Login works with real admin credentials
- [ ] Dashboard loads, campaigns render with correct data
- [ ] Agency list shows all 10 agencies
- [ ] User list shows all 16 users with correct roles
- [ ] Campaign data spot-check: pick 2-3 campaigns, verify impression/click numbers
- [ ] Activity logs show timestamps in Israel time
- [ ] By-date table has no "Visible" column
- [ ] Upload a test report → verify it ingests correctly
- [ ] Check `storage/logs/laravel.log` for errors
- [ ] Non-admin login attempt → should see maintenance message

### Step 11 — Review user roles

Before opening to all users, verify the role assignments are correct:
```bash
ssh root@164.90.233.136 'cd /var/www/maddata && php artisan tinker --execute="
\$users = App\Models\User::with(\"userRole\")->get();
foreach(\$users as \$u) {
    echo \$u->name . \" | \" . \$u->email . \" | Role: \" . (\$u->userRole?->name ?? \"NONE\") . \" | Active: \" . (\$u->is_active ? \"yes\" : \"NO\") . \"\n\";
}
"'
```

Confirm:
- [ ] Admin users (Michael, Eran) have Admin role
- [ ] Agency managers have Agency Manager role
- [ ] All other users have appropriate viewer roles
- [ ] No orphaned role assignments
- [ ] Disabled users are still disabled

### Step 12 — Open to all users (T+30+)

Once admin QA passes and roles are confirmed:

Via UI: `https://ad.maddata.media/admin/system-status` → toggle admin-only mode OFF.

Or via CLI:
```bash
ssh root@164.90.233.136 'cd /var/www/maddata && php artisan tinker --execute="Cache::forever(\"admin_only_login\", false); echo \"Admin-only mode: OFF — open to all users\";"'
```

### Step 13 — Terminate stale sessions from old server

Any sessions that carried over from old prod won't work (different `APP_KEY` or session files). But to be safe:
```bash
ssh root@164.90.233.136 'cd /var/www/maddata && php artisan tinker --execute="DB::table(\"sessions\")->truncate(); echo \"All sessions cleared — users will need to re-login\";"'
```

Only do this if session driver is `database`. Since it's `file`, old sessions simply don't exist on the new server — users will get a fresh login page automatically.

## Rollback

| When | Action |
|------|--------|
| Before DNS switch (Steps 1-8) | `ssh root@207.154.253.28 'cd /var/www/prod/maddata-simple && php artisan up'` — users back on old prod, zero data loss |
| After DNS switch (Step 9+) | Revert DNS A record to `207.154.253.28` in DO panel. `php artisan up` on old prod. Any writes on new since DNS flip are lost. |

## Post-Cutover (same day)

- [ ] T+1h: spot-check pages, check `jobs` table count, tail laravel.log
- [ ] Verify backup runs at 03:00 tonight with the fresh data
- [ ] Monitor for 24h before considering cutover stable

## Post-Cutover (T+2 weeks)

- [ ] Drop `maddata_simple_prod` DB on old droplet
- [ ] Archive `/var/www/prod/maddata-simple` on old droplet
- [ ] Remove old Apache vhost for `ad.maddata.media`
