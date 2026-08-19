#!/bin/bash
#
# MadData Production Backup
#
# Snapshots DB + .env + storage/app + current commit hash into a timestamped
# directory under /var/backups/maddata. Rotates: keeps last 7 backups.
# If DO_BUCKET / DO_ACCESS_KEY_ID / DO_SECRET_ACCESS_KEY are set in .env,
# also uploads the backup off-site to DO Spaces (remote retention: 30 days).
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
REMOTE_RETENTION_DAYS="${BACKUP_REMOTE_RETENTION_DAYS:-30}"

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
echo "[1/4] Dumping database: $DB_NAME"
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
echo "[2/4] Archiving .env"
ENV_ARCHIVE="$BACKUP_DIR/env.tar.gz"
tar -czf "$ENV_ARCHIVE" -C "$PROJECT_DIR" .env

# ─── 3. storage/app tarball ─────────────────────────────────────────────
echo "[3/4] Archiving storage/app"
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

# ─── 4. Off-site upload to DO Spaces (optional) ─────────────────────────
DO_BUCKET=$(get_env DO_BUCKET)
DO_ACCESS_KEY_ID=$(get_env DO_ACCESS_KEY_ID)
DO_SECRET_ACCESS_KEY=$(get_env DO_SECRET_ACCESS_KEY)
DO_SPACES_ENDPOINT=$(get_env DO_SPACES_ENDPOINT)
: "${DO_SPACES_ENDPOINT:=fra1.digitaloceanspaces.com}"

REMOTE_OK=false
REMOTE_BYTES=0

if [ -n "$DO_BUCKET" ] && [ -n "$DO_ACCESS_KEY_ID" ] && [ -n "$DO_SECRET_ACCESS_KEY" ] && command -v s3cmd >/dev/null; then
    echo "[4/4] Uploading off-site to s3://$DO_BUCKET/backups/$TIMESTAMP/"
    s3() {
        s3cmd --access_key="$DO_ACCESS_KEY_ID" \
              --secret_key="$DO_SECRET_ACCESS_KEY" \
              --host="$DO_SPACES_ENDPOINT" \
              --host-bucket="%(bucket)s.$DO_SPACES_ENDPOINT" \
              --no-progress "$@"
    }
    if s3 put "$DB_ARCHIVE" "$ENV_ARCHIVE" "$STORAGE_ARCHIVE" "$MANIFEST" \
          "s3://$DO_BUCKET/backups/$TIMESTAMP/"; then
        echo "Off-site upload:    s3://$DO_BUCKET/backups/$TIMESTAMP/" >> "$MANIFEST"
        REMOTE_OK=true
        # || echo 0 matters: set -e + pipefail would abort the whole backup
        # run if du hiccuped, purely to record a stat nobody depends on.
        REMOTE_BYTES=$(du -cb "$DB_ARCHIVE" "$ENV_ARCHIVE" "$STORAGE_ARCHIVE" "$MANIFEST" 2>/dev/null | tail -1 | cut -f1 || echo 0)
        [[ "$REMOTE_BYTES" =~ ^[0-9]+$ ]] || REMOTE_BYTES=0

        # Remote retention: delete backup prefixes older than N days
        CUTOFF=$(date -u -d "-$REMOTE_RETENTION_DAYS days" +%Y%m%d_%H%M%S)
        s3 ls "s3://$DO_BUCKET/backups/" | awk -F/ '/DIR/ {print $(NF-1)}' | while read -r remote; do
            if [[ "$remote" =~ ^[0-9]{8}_[0-9]{6}$ ]] && [[ "$remote" < "$CUTOFF" ]]; then
                echo "  removing remote: $remote"
                s3 del --recursive "s3://$DO_BUCKET/backups/$remote/"
            fi
        done || echo "WARN: remote retention pruning failed" >&2
    else
        echo "WARN: off-site upload failed — local backup is still intact" >&2
    fi
else
    echo "[4/4] Off-site upload skipped (DO_* credentials not configured)"
fi

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

# ─── Health marker ──────────────────────────────────────────────────────
# Checks B1-B3 read this. Written last, so it only ever records a backup that
# actually finished — the health monitor cross-checks it against the backup
# directory itself, so a marker that never appears is detectable.
MARKER_PATH="${HEALTH_BACKUP_MARKER_PATH:-/run/maddata/backup-last.json}"
LOCAL_BYTES=$(du -sb "$BACKUP_DIR" 2>/dev/null | cut -f1 || echo 0)
[[ "$LOCAL_BYTES" =~ ^[0-9]+$ ]] || LOCAL_BYTES=0

if mkdir -p "$(dirname "$MARKER_PATH")" 2>/dev/null; then
    MARKER_TMP="${MARKER_PATH}.tmp.$$"
    cat > "$MARKER_TMP" <<MARKER
{
  "ts": $(date +%s),
  "backup_dir": "$BACKUP_DIR",
  "local_bytes": $LOCAL_BYTES,
  "remote_ok": $REMOTE_OK,
  "remote_bytes": $REMOTE_BYTES
}
MARKER
    chmod 644 "$MARKER_TMP"
    mv -f "$MARKER_TMP" "$MARKER_PATH"
    echo "Health marker written: $MARKER_PATH"
fi
