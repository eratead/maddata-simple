#!/bin/bash
#
# MadData Production Restore
#
# Restores DB + .env + storage/app + code from a backup directory created by
# backup-production.sh. Hard confirmation gate required — this is destructive
# and supervised. No -y flag.
#
# Usage:
#   ./restore-production.sh                    (list available backups)
#   ./restore-production.sh <timestamp>        (restore all: db + files + code)
#   ./restore-production.sh <timestamp> --db   (DB only)
#   ./restore-production.sh <timestamp> --files (.env + storage/app only)
#   ./restore-production.sh <timestamp> --code  (git checkout only)
#   ./restore-production.sh <timestamp> --all   (explicit form of default)
#
# Wraps destructive work in `php artisan down` / `up`. On failure, leaves the
# site in maintenance mode so an operator can intervene.

set -euo pipefail

# ─── Configuration ──────────────────────────────────────────────────────
PROJECT_DIR="${PROJECT_DIR:-/var/www/maddata}"
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/maddata}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.4-fpm}"
QUEUE_SERVICE="${QUEUE_SERVICE:-maddata-queue.service}"

# ─── Helpers ────────────────────────────────────────────────────────────
err() { echo "ERROR: $*" >&2; }
info() { echo "[info] $*"; }

usage() {
    cat <<EOF
Usage:
  $0                        List available backups
  $0 <timestamp> [scope]    Restore from backup

Scope (default --all):
  --db      Restore database only
  --files   Restore .env + storage/app only
  --code    Git checkout of commit hash only
  --all     Everything above

Examples:
  $0 20260409_143000 --all
  $0 20260409_143000 --db
EOF
}

list_backups() {
    echo "Available backups in $BACKUP_ROOT:"
    if [ ! -d "$BACKUP_ROOT" ]; then
        echo "  (none — backup root does not exist)"
        return 1
    fi
    # shellcheck disable=SC2012
    ls -1t "$BACKUP_ROOT" 2>/dev/null | grep -E '^[0-9]{8}_[0-9]{6}$' | while read -r t; do
        if [ -f "$BACKUP_ROOT/$t/manifest.txt" ]; then
            size=$(du -sh "$BACKUP_ROOT/$t" 2>/dev/null | awk '{print $1}')
            printf '  %s  (%s)\n' "$t" "$size"
        fi
    done
}

get_env() {
    local key="$1"
    local file="$2"
    local value
    value=$(grep -E "^${key}=" "$file" | head -1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/' | sed -E "s/^'(.*)'\$/\\1/")
    printf '%s' "$value"
}

verify_sha256() {
    local file="$1"
    local expected="$2"
    local actual
    actual=$(sha256sum "$file" | awk '{print $1}')
    if [ "$actual" != "$expected" ]; then
        err "sha256 mismatch for $(basename "$file")"
        err "  expected: $expected"
        err "  actual:   $actual"
        return 1
    fi
}

# ─── Argument parsing ───────────────────────────────────────────────────
if [ $# -eq 0 ]; then
    list_backups
    echo ""
    usage
    exit 0
fi

TIMESTAMP="$1"
SCOPE="${2:---all}"

BACKUP_DIR="$BACKUP_ROOT/$TIMESTAMP"
MANIFEST="$BACKUP_DIR/manifest.txt"

if [ ! -d "$BACKUP_DIR" ]; then
    err "Backup not found: $BACKUP_DIR"
    echo ""
    list_backups
    exit 1
fi

if [ ! -f "$MANIFEST" ]; then
    err "Manifest not found: $MANIFEST"
    exit 1
fi

DB_ARCHIVE="$BACKUP_DIR/db.sql.gz"
ENV_ARCHIVE="$BACKUP_DIR/env.tar.gz"
STORAGE_ARCHIVE="$BACKUP_DIR/storage-app.tar.gz"

case "$SCOPE" in
    --db|--files|--code|--all) ;;
    *)
        err "Unknown scope: $SCOPE"
        usage
        exit 1
        ;;
esac

# ─── Show manifest & confirmation gate ──────────────────────────────────
cat "$MANIFEST"
echo ""
echo "════════════════════════════════════════════════════════════════════"
echo "  DESTRUCTIVE RESTORE OPERATION"
echo "════════════════════════════════════════════════════════════════════"
echo "  Target project:  $PROJECT_DIR"
echo "  Scope:           $SCOPE"
echo ""
echo "  This will overwrite production data. Any changes since the backup"
echo "  was taken will be LOST."
echo ""
echo "  Type RESTORE (all uppercase) to proceed, or anything else to abort."
echo "════════════════════════════════════════════════════════════════════"
printf "> "
read -r CONFIRM
if [ "$CONFIRM" != "RESTORE" ]; then
    info "Aborted by operator."
    exit 2
fi

# ─── Parse manifest for sha256 expectations ─────────────────────────────
sha_db=$(grep -E 'db\.sql\.gz.*sha256=' "$MANIFEST" | sed -E 's/.*sha256=([a-f0-9]+).*/\1/' || true)
sha_env=$(grep -E 'env\.tar\.gz.*sha256=' "$MANIFEST" | sed -E 's/.*sha256=([a-f0-9]+).*/\1/' || true)
sha_storage=$(grep -E 'storage-app\.tar\.gz.*sha256=' "$MANIFEST" | sed -E 's/.*sha256=([a-f0-9]+).*/\1/' || true)

if [[ "$SCOPE" == "--db" || "$SCOPE" == "--all" ]] && [ -n "$sha_db" ]; then
    info "Verifying db.sql.gz checksum"
    verify_sha256 "$DB_ARCHIVE" "$sha_db"
fi
if [[ "$SCOPE" == "--files" || "$SCOPE" == "--all" ]]; then
    [ -n "$sha_env" ] && { info "Verifying env.tar.gz checksum"; verify_sha256 "$ENV_ARCHIVE" "$sha_env"; }
    [ -n "$sha_storage" ] && { info "Verifying storage-app.tar.gz checksum"; verify_sha256 "$STORAGE_ARCHIVE" "$sha_storage"; }
fi

# ─── Enter maintenance mode ─────────────────────────────────────────────
cd "$PROJECT_DIR"
info "Entering maintenance mode"
php artisan down --render="errors::503" --retry=60 || true

# On any error beyond this point, leave in maintenance mode for manual intervention
trap 'err "Restore failed — site is in MAINTENANCE MODE. Fix manually then run: cd $PROJECT_DIR && php artisan up"; exit 3' ERR

# ─── Restore: DB ────────────────────────────────────────────────────────
if [[ "$SCOPE" == "--db" || "$SCOPE" == "--all" ]]; then
    info "Restoring database"
    ENV_FILE="$PROJECT_DIR/.env"
    DB_USER=$(get_env DB_USERNAME "$ENV_FILE")
    DB_PASS=$(get_env DB_PASSWORD "$ENV_FILE")
    DB_NAME=$(get_env DB_DATABASE "$ENV_FILE")
    DB_HOST=$(get_env DB_HOST "$ENV_FILE")
    : "${DB_HOST:=127.0.0.1}"
    if [ -z "$DB_USER" ] || [ -z "$DB_PASS" ] || [ -z "$DB_NAME" ]; then
        err "DB credentials missing from $ENV_FILE — cannot restore DB"
        exit 3
    fi
    gunzip < "$DB_ARCHIVE" | MYSQL_PWD="$DB_PASS" mysql --host="$DB_HOST" --user="$DB_USER" "$DB_NAME"
    info "Database restored"
fi

# ─── Restore: files ─────────────────────────────────────────────────────
if [[ "$SCOPE" == "--files" || "$SCOPE" == "--all" ]]; then
    info "Restoring .env"
    # Check if backed-up .env is older than 14 days — warn but proceed
    ENV_AGE_DAYS=$(( ( $(date +%s) - $(stat -c%Y "$ENV_ARCHIVE") ) / 86400 ))
    if [ "$ENV_AGE_DAYS" -gt 14 ]; then
        echo "WARN: .env backup is $ENV_AGE_DAYS days old. Any secrets rotated since then will be reverted."
    fi
    tar -xzf "$ENV_ARCHIVE" -C "$PROJECT_DIR"

    info "Restoring storage/app"
    # Remove current storage/app before extracting to get a clean state
    rm -rf "$PROJECT_DIR/storage/app.restore-bak"
    mv "$PROJECT_DIR/storage/app" "$PROJECT_DIR/storage/app.restore-bak" 2>/dev/null || true
    tar -xzf "$STORAGE_ARCHIVE" -C "$PROJECT_DIR/storage"
    rm -rf "$PROJECT_DIR/storage/app.restore-bak"
    chown -R www-data:www-data "$PROJECT_DIR/storage"
fi

# ─── Restore: code ──────────────────────────────────────────────────────
if [[ "$SCOPE" == "--code" || "$SCOPE" == "--all" ]]; then
    GIT_COMMIT=$(grep -E '^Git commit:' "$MANIFEST" | awk '{print $3}')
    if [ -z "$GIT_COMMIT" ] || [ "$GIT_COMMIT" = "unknown" ]; then
        err "Git commit hash not found in manifest — cannot restore code"
        exit 3
    fi
    info "Checking out git commit $GIT_COMMIT"
    cd "$PROJECT_DIR"
    git fetch origin
    git checkout -f "$GIT_COMMIT"
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction
fi

# ─── Post-restore hooks ─────────────────────────────────────────────────
info "Clearing caches"
cd "$PROJECT_DIR"
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

info "Rebuilding config/route/view cache"
php artisan config:cache
php artisan route:cache
php artisan view:cache

info "Restarting queue worker"
systemctl restart "$QUEUE_SERVICE" 2>/dev/null || info "  queue service not running, skipping"

info "Reloading PHP-FPM (OPcache flush)"
systemctl reload "$PHP_FPM_SERVICE" 2>/dev/null || info "  $PHP_FPM_SERVICE reload failed, skipping"

# ─── Exit maintenance mode ──────────────────────────────────────────────
trap - ERR
info "Exiting maintenance mode"
php artisan up

echo ""
echo "=== Restore complete ==="
echo "Scope:     $SCOPE"
echo "From:      $TIMESTAMP"
echo "Verify:    curl -sI https://new.ad.maddata.media/"
