# Production Deploy Plan

**Created:** 2026-03-23
**Status:** Waiting for staging QA approval

## Prerequisites

- [ ] Staging QA passed — all features tested manually
- [ ] All tests pass locally: `composer run test` (251 tests)
- [ ] All staging fixes committed and pushed
- [ ] SSH key loaded: `ssh-add ~/.ssh/id_rsa`
- [ ] Coordinate with Eran — brief downtime during migration

## Server Details

| | Staging | Production |
|---|---------|-----------|
| **Path** | `/var/www/dev/maddata-simple` | `/var/www/prod/maddata-simple` |
| **DB name** | `maddata_simple` | `maddata_simple_prod` |
| **DB user** | `webusr` | `webusr` |
| **Branch** | `staging` | `main` |
| **Host** | 207.154.253.28 | 207.154.253.28 (same server) |

## What This Deploy Changes

### Database (28 new migrations)
- Creates 9 new tables: `agencies`, `agency_user`, `roles`, `activity_logs`, `audiences`, `campaign_audience`, `campaign_locations`, `creatives`, `creative_files`
- Adds columns to `users`: `role_id`, `receive_activity_notifications`, `google2fa_secret`, `is_active`
- Adds columns to `campaigns`: `status`, `required_sizes`, `creative_optimization`, `targeting_rules`
- Migrates legacy `clients.agency` text → `agencies` table + `clients.agency_id` FK
- Drops `clients.agency` text column (data preserved in agencies table)
- Adds 7 performance indexes
- Creates `personal_access_tokens` table guard (already exists, skip)

### Roles & Permissions
- Creates 4 roles: Admin, Agency Manager, Viewer Campaign + Budget, Third Party Communicator
- Assigns Admin to user IDs 1, 2 (Michael, Eran)
- Assigns Viewer Campaign + Budget to all other users

### Code
- Security hardening (auth, escalation prevention, tenant scoping)
- Performance optimization (query consolidation, caching, indexes)
- Agency user management system
- New routes under `/admin/*` for users and clients (moved from root)
- New routes under `/agency/{id}/*` for agency manager CRUD
- Registration disabled (admin-only user creation)

## Known Issues to Handle

### 1. Legacy Sanctum Migration File
The server has `2025_07_16_080843_create_personal_access_tokens_table.php` which is not in our repo but exists as a file on disk. The production `migrations` table recorded it as `2025_08_28_103256`. Our deploy script handles this by inserting it into the migrations table before running `php artisan migrate`.

### 2. No Data Loss
- Agency text data is migrated to the `agencies` table INSIDE the `drop_agency_string` migration — it reads the text, creates agencies, assigns `agency_id`, THEN drops the column
- All user data preserved
- All campaign/client/placement data preserved
- `client_user` pivot preserved

## Deploy Steps

### Step 0: Backup Production DB
```bash
ssh -i ~/.ssh/id_rsa root@207.154.253.28

# Backup production DB
mysqldump -u webusr -p'PASSWORD' maddata_simple_prod > /tmp/production_deploy_backup_$(date +%Y%m%d_%H%M%S).sql

# Verify backup
ls -lh /tmp/production_deploy_backup_*.sql
```

### Step 1: Push Code
```bash
# From local machine — push main to production
git push origin main
```

### Step 2: Pull Code on Server
```bash
ssh -i ~/.ssh/id_rsa root@207.154.253.28 "cd /var/www/prod/maddata-simple && git fetch && git pull origin main"
```

### Step 3: Install Dependencies
```bash
ssh -i ~/.ssh/id_rsa root@207.154.253.28 "cd /var/www/prod/maddata-simple && composer install --no-dev --optimize-autoloader"
```

### Step 4: Handle Legacy Migration File
```bash
ssh -i ~/.ssh/id_rsa root@207.154.253.28 "mysql -u webusr -p'PASSWORD' maddata_simple_prod -e \"INSERT IGNORE INTO migrations (migration, batch) SELECT '2025_07_16_080843_create_personal_access_tokens_table', 2 WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'maddata_simple_prod' AND table_name = 'personal_access_tokens') AND NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2025_07_16_080843_create_personal_access_tokens_table');\""
```

### Step 5: Run Migrations
```bash
ssh -i ~/.ssh/id_rsa root@207.154.253.28 "cd /var/www/prod/maddata-simple && php artisan migrate --force"
```

**Expected:** 28 migrations, including inline agency data migration.

### Step 6: Seed Roles
```bash
ssh -i ~/.ssh/id_rsa root@207.154.253.28 "cd /var/www/prod/maddata-simple && php artisan seed:staging-roles"
```

**Note:** The command name says "staging" but it works for production too — it's idempotent (uses `firstOrCreate`).

### Step 7: Clear Caches & Build Assets
```bash
ssh -i ~/.ssh/id_rsa root@207.154.253.28 "cd /var/www/prod/maddata-simple && php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan route:clear && npm run build"
```

### Step 7.5: Switch Session Driver to Database
```bash
ssh -i ~/.ssh/id_rsa root@207.154.253.28 "cd /var/www/prod/maddata-simple && sed -i 's/SESSION_DRIVER=file/SESSION_DRIVER=database/' .env && php artisan config:clear"
```
**Note:** All existing sessions will be invalidated — users will need to re-login once.

### Step 7.6: Set Up Laravel Scheduler Cron
```bash
ssh -i ~/.ssh/id_rsa root@207.154.253.28 "(crontab -l 2>/dev/null; echo '* * * * * cd /var/www/prod/maddata-simple && php artisan schedule:run >> /dev/null 2>&1') | sort -u | crontab -"
```
This runs Laravel's scheduler every minute. Currently scheduled:
- `campaigns:generate-status` — daily at midnight, auto-pauses campaigns past their end date

### Step 7.7: Run Campaign Status Command
```bash
ssh -i ~/.ssh/id_rsa root@207.154.253.28 "cd /var/www/prod/maddata-simple && php artisan campaigns:generate-status"
```
This immediately pauses any campaigns already past their end date.

### Step 8: Verify
```bash
ssh -i ~/.ssh/id_rsa root@207.154.253.28 "cd /var/www/prod/maddata-simple && php artisan tinker --execute=\"
echo 'Agencies: ' . \App\Models\Agency::count();
echo PHP_EOL;
echo 'Roles: ' . \App\Models\Role::count();
echo PHP_EOL;
echo 'Users with roles: ' . \App\Models\User::whereNotNull('role_id')->count();
echo PHP_EOL;
echo 'Clients with agency: ' . \App\Models\Client::whereNotNull('agency_id')->count();
\""
```

**Expected:**
- Agencies: 10 (ARLO, OCEAN, McCann, TABRY, AFAK, GAL-OREN, Azrieli College, GO, SCALA, Lapam)
- Roles: 4
- Users with roles: 16 (all users)
- Clients with agency: 34 (all clients)

### Step 9: Smoke Test
- [ ] Login as admin (Michael) — verify dashboard loads
- [ ] Check agencies list at `/admin/agencies`
- [ ] Check users list at `/admin/users` — verify roles assigned
- [ ] Check a campaign dashboard — verify data displays correctly
- [ ] Login as a non-admin user — verify they see only their clients' campaigns

## Rollback Plan

If anything goes wrong:

### Option A: Rollback Database Only
```bash
# Restore from backup
ssh -i ~/.ssh/id_rsa root@207.154.253.28 "mysql -u webusr -p'PASSWORD' maddata_simple_prod < /tmp/production_deploy_backup_TIMESTAMP.sql"

# Rollback code
ssh -i ~/.ssh/id_rsa root@207.154.253.28 "cd /var/www/prod/maddata-simple && git checkout PREVIOUS_COMMIT_HASH"

# Clear caches
ssh -i ~/.ssh/id_rsa root@207.154.253.28 "cd /var/www/prod/maddata-simple && php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan route:clear"
```

### Option B: Just Rollback Code (if DB migration succeeded but code has bugs)
```bash
ssh -i ~/.ssh/id_rsa root@207.154.253.28 "cd /var/www/prod/maddata-simple && git checkout PREVIOUS_COMMIT_HASH && composer install --no-dev --optimize-autoloader && php artisan config:clear && php artisan cache:clear"
```

### Get Previous Commit Hash
```bash
# Before deploying, note the current production commit:
ssh -i ~/.ssh/id_rsa root@207.154.253.28 "cd /var/www/prod/maddata-simple && git rev-parse HEAD"
```

## Estimated Downtime

- **Migrations:** ~2-5 seconds (28 migrations, mostly creating tables and adding columns)
- **Total deploy time:** ~2-3 minutes
- **User impact:** Brief — users might see errors during migration window. No data loss.

## Post-Deploy Tasks

After confirming production is stable:
- [ ] Monitor Laravel logs for 24 hours: `tail -f storage/logs/laravel.log`
- [ ] Verify agency data is correct with a few spot checks
- [ ] Assign Agency Manager role to specific agency users as needed (via admin UI)
- [ ] Set up agency-user relationships via admin user edit
