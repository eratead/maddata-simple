#!/bin/bash
#
# MadData Staging Deploy Script
# Loads production backup into staging DB, deploys code, runs migrations + seeds.
#
# Usage:
#   ./scripts/deploy-staging.sh
#
# Prerequisites:
#   - SSH key loaded: ssh-add ~/.ssh/id_rsa
#   - Production backup exists at /tmp/production_backup_20260323.sql on server
#   - All tests pass locally: composer run test
#

set -e  # Exit on any error

SERVER="root@207.154.253.28"
SSH_KEY="~/.ssh/id_rsa"
SSH="ssh -i $SSH_KEY $SERVER"
STAGING_PATH="/var/www/dev/maddata-simple"
STAGING_DB="maddata_simple"
DB_USER="webusr"
DB_PASS="cAP9r4FBwS"
BACKUP_FILE="/tmp/production_backup_20260323.sql"

echo "========================================="
echo "  MadData Staging Deploy"
echo "========================================="
echo ""

# ─── Step 0: Pre-flight checks ───────────────────────
echo "🔍 Step 0: Pre-flight checks..."

echo "  Checking SSH key..."
ssh-add -l > /dev/null 2>&1 || { echo "❌ SSH key not loaded. Run: ssh-add ~/.ssh/id_rsa"; exit 1; }

echo "  Checking backup file exists on server..."
$SSH "test -f $BACKUP_FILE" || { echo "❌ Backup file not found: $BACKUP_FILE"; exit 1; }

echo "  ✅ Pre-flight OK"
echo ""

# ─── Step 1: Push code to staging ─────────────────────
echo "📦 Step 1: Pushing code to staging branch..."
git push origin main:staging
echo "  ✅ Code pushed"
echo ""

# ─── Step 2: Load production backup into staging DB ───
echo "💾 Step 2: Loading production backup into staging DB..."
echo "  ⚠️  This will REPLACE the staging database with production data!"
read -p "  Continue? (y/N) " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "  Aborted."
    exit 1
fi

$SSH "mysql -u $DB_USER -p'$DB_PASS' -e 'DROP DATABASE IF EXISTS $STAGING_DB; CREATE DATABASE $STAGING_DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'"
$SSH "mysql -u $DB_USER -p'$DB_PASS' $STAGING_DB < $BACKUP_FILE"
echo "  ✅ Production backup loaded into staging DB"
echo ""

# ─── Step 3: Pull code on server ──────────────────────
echo "🔄 Step 3: Pulling code on staging server..."
$SSH "cd $STAGING_PATH && git fetch && git checkout staging && git pull origin staging"
echo "  ✅ Code pulled"
echo ""

# ─── Step 4: Install dependencies ─────────────────────
echo "📚 Step 4: Installing dependencies..."
$SSH "cd $STAGING_PATH && composer install --no-dev --optimize-autoloader"
echo "  ✅ Dependencies installed"
echo ""

# ─── Step 4b: Fix legacy migration files ──────────────
echo "🔧 Step 4b: Handling legacy migration files..."
# The server may have an old Sanctum migration (2025_07_16) published directly
# that's not in our repo. Mark it as run so it doesn't conflict.
$SSH "cd $STAGING_PATH && mysql -u $DB_USER -p'$DB_PASS' $STAGING_DB -e \"INSERT IGNORE INTO migrations (migration, batch) SELECT '2025_07_16_080843_create_personal_access_tokens_table', 2 WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = '$STAGING_DB' AND table_name = 'personal_access_tokens') AND NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2025_07_16_080843_create_personal_access_tokens_table');\" 2>/dev/null || true"
echo "  ✅ Legacy migrations handled"
echo ""

# ─── Step 5: Run migrations ───────────────────────────
echo "🗃️  Step 5: Running migrations..."
echo "  This will run all pending migrations including:"
echo "    - Create agencies, roles, activity_logs, audiences tables"
echo "    - Add columns to users, campaigns, clients"
echo "    - Migrate agency text data → agencies table"
echo "    - Drop legacy agency text column"
echo "    - Add performance indexes"
echo ""
$SSH "cd $STAGING_PATH && php artisan migrate --force"
echo "  ✅ Migrations complete"
echo ""

# ─── Step 6: Seed roles ──────────────────────────────
echo "👥 Step 6: Seeding roles and user assignments..."
$SSH "cd $STAGING_PATH && php artisan seed:staging-roles"
echo "  ✅ Roles seeded"
echo ""

# ─── Step 7: Clear caches ────────────────────────────
echo "🧹 Step 7: Clearing caches..."
$SSH "cd $STAGING_PATH && php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan route:clear"
echo "  ✅ Caches cleared"
echo ""

# ─── Step 8: Verify ──────────────────────────────────
echo "✅ Step 8: Post-deploy verification..."
$SSH "cd $STAGING_PATH && php artisan migrate:status | tail -20"
echo ""
$SSH "cd $STAGING_PATH && php artisan tinker --execute=\"echo 'Agencies: ' . \App\Models\Agency::count() . ', Roles: ' . \App\Models\Role::count() . ', Users: ' . \App\Models\User::count() . ', Clients: ' . \App\Models\Client::count();\""
echo ""

echo "========================================="
echo "  ✅ Staging deploy complete!"
echo ""
echo "  If something is wrong:"
echo "    1. Re-load production backup:"
echo "       ssh $SERVER \"mysql -u $DB_USER -p'$DB_PASS' $STAGING_DB < $BACKUP_FILE\""
echo "    2. Re-run this script"
echo "========================================="
