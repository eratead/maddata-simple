#!/bin/bash
#
# MadData Production Backup
#
# Snapshots DB + .env + storage/app + current commit hash into a timestamped
# directory under /var/backups/maddata. Rotates: keeps last 7 backups.
#
# Runs standalone (cron-safe) — not tied to deploy time. Designed for use as:
#   ./scripts/backup-production.sh            (ad-hoc)
#   0 3 * * * /var/www/maddata/scripts/backup-production.sh  (nightly)
#
# Reads DB credentials from the project's own .env file. Never hardcodes
# passwords.

set -euo pipefail

# ─── Configuration ──────────────────────────────────────────────────────
PROJECT_DIR="${PROJECT_DIR:-/var/www/maddata}"
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/maddata}"
RETENTION="${BACKUP_RETENTION:-7}"

# ─── Pre-flight ─────────────────────────────────────────────────────────
if [ ! -d "$PROJECT_DIR" ]; then
    echo "ERROR: PROJECT_DIR not found: $PROJECT_DIR" >&2
    exit 1
fi

ENV_FILE="$PROJECT_DIR/.env"
if [ ! -f "$ENV_FILE" ]; then
    echo "ERROR: .env file not found: $ENV_FILE" >&2
    exit 1
fi

if [ ! -d "$BACKUP_ROOT" ]; then
    echo "ERROR: BACKUP_ROOT not found: $BACKUP_ROOT" >&2
    echo "Create it with: mkdir -p $BACKUP_ROOT && chmod 700 $BACKUP_ROOT" >&2
    exit 1
fi

# ─── Read DB credentials from .env ──────────────────────────────────────
get_env() {
    local key="$1"
    local value
    value=$(grep -E "^${key}=" "$ENV_FILE" | head -1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/' | sed -E "s/^'(.*)'\$/\\1/")
    printf '%s' "$value"
}

DB_USER=$(get_env DB_USERNAME)
DB_PASS=$(get_env DB_PASSWORD)
DB_NAME=$(get_env DB_DATABASE)
DB_HOST=$(get_env DB_HOST)
DB_PORT=$(get_env DB_PORT)

: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=3306}"

if [ -z "$DB_USER" ] || [ -z "$DB_PASS" ] || [ -z "$DB_NAME" ]; then
    echo "ERROR: DB credentials missing from $ENV_FILE" >&2
    exit 1
fi

# ─── Create backup dir ──────────────────────────────────────────────────
TIMESTAMP=$(date -u +%Y%m%d_%H%M%S)
BACKUP_DIR="$BACKUP_ROOT/$TIMESTAMP"
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

MANIFEST="$BACKUP_DIR/manifest.txt"

{
    echo "MadData Production Backup"
    echo "========================="
    echo "Timestamp (UTC):    $TIMESTAMP"
    echo "Hostname:           $(hostname)"
    echo "Project dir:        $PROJECT_DIR"
    echo "Backup dir:         $BACKUP_DIR"
    echo ""
} > "$MANIFEST"

# ─── Git commit hash + branch ───────────────────────────────────────────
if cd "$PROJECT_DIR" 2>/dev/null && git rev-parse --git-dir >/dev/null 2>&1; then
    GIT_COMMIT=$(git rev-parse HEAD 2>/dev/null || echo unknown)
    GIT_BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo unknown)
    GIT_SHORT=$(git rev-parse --short HEAD 2>/dev/null || echo unknown)
    GIT_MSG=$(git log -1 --format=%s 2>/dev/null || echo unknown)
    {
        echo "Git commit:         $GIT_COMMIT ($GIT_SHORT)"
        echo "Git branch:         $GIT_BRANCH"
        echo "Git message:        $GIT_MSG"
        echo ""
    } >> "$MANIFEST"
else
    echo "Git commit:         not a git repository" >> "$MANIFEST"
fi

# ─── 1. DB dump ─────────────────────────────────────────────────────────
echo "[1/3] Dumping database: $DB_NAME"
DB_ARCHIVE="$BACKUP_DIR/db.sql.gz"
MYSQL_PWD="$DB_PASS" mysqldump \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USER" \
    --single-transaction \
    --quick \
    --lock-tables=false \
    --routines \
    --triggers \
    --default-character-set=utf8mb4 \
    "$DB_NAME" 2>/dev/null | gzip > "$DB_ARCHIVE"

if [ ! -s "$DB_ARCHIVE" ]; then
    echo "ERROR: DB dump is empty — credentials wrong or DB empty?" >&2
    exit 1
fi

# ─── 2. .env tarball ────────────────────────────────────────────────────
echo "[2/3] Archiving .env"
ENV_ARCHIVE="$BACKUP_DIR/env.tar.gz"
tar -czf "$ENV_ARCHIVE" -C "$PROJECT_DIR" .env

# ─── 3. storage/app tarball ─────────────────────────────────────────────
echo "[3/3] Archiving storage/app"
STORAGE_ARCHIVE="$BACKUP_DIR/storage-app.tar.gz"
if [ -d "$PROJECT_DIR/storage/app" ]; then
    tar -czf "$STORAGE_ARCHIVE" -C "$PROJECT_DIR/storage" app
else
    echo "WARN: storage/app not found — creating empty archive" >&2
    tar -czf "$STORAGE_ARCHIVE" -C /tmp --files-from /dev/null
fi

# ─── Manifest: sizes + sha256 ───────────────────────────────────────────
{
    echo "Archives:"
    for f in "$DB_ARCHIVE" "$ENV_ARCHIVE" "$STORAGE_ARCHIVE"; do
        size=$(stat -c%s "$f" 2>/dev/null || stat -f%z "$f" 2>/dev/null)
        sha=$(sha256sum "$f" 2>/dev/null | awk '{print $1}')
        printf '  %-40s %10s bytes  sha256=%s\n' "$(basename "$f")" "$size" "$sha"
    done
    echo ""
    echo "Disk usage:"
    df -h "$BACKUP_ROOT" | tail -1
} >> "$MANIFEST"

# ─── Rotation: keep last N ──────────────────────────────────────────────
echo "[rotate] Keeping last $RETENTION backups"
cd "$BACKUP_ROOT"
# shellcheck disable=SC2012
ls -1t | grep -E '^[0-9]{8}_[0-9]{6}$' | tail -n +"$((RETENTION + 1))" | while read -r old; do
    echo "  removing: $old"
    rm -rf "${BACKUP_ROOT:?}/$old"
done

# ─── Final report ───────────────────────────────────────────────────────
echo ""
echo "=== Backup complete ==="
cat "$MANIFEST"
echo ""
echo "Backup ready at: $BACKUP_DIR"
