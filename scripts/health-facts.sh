#!/bin/bash
#
# MadData Host Facts
#
# Collects the things PHP must never collect itself — systemd unit states,
# apt security counts, TLS expiry, real CPU utilization, backup-dir stats —
# and writes them as JSON for the health monitor to READ.
#
# Runs as root from OS cron, NOT the Laravel scheduler: it has to keep firing
# when the app itself is broken (that is the entire point of a host-down
# detector). The app only ever reads the output file; nothing in the request
# path executes a shell, holds a sudo grant, or talks to systemd.
#
#   * * * * * /var/www/maddata/scripts/health-facts.sh
#
# Output contains numbers, unit states and version strings only — no IPs, no
# credentials, no .env values. Script is 700 root; output is 644 so www-data
# can read it. Lives on tmpfs (/run) so it can never survive a reboot as
# stale truth.

set -uo pipefail

# awk's %.1f honours LC_NUMERIC: under a comma-decimal locale it emits "85,3",
# which would make this file invalid JSON and blind EVERY host check at once.
# Cron runs under C, but the runbook tells operators to run this by hand, and
# an interactive session carries their locale.
export LC_ALL=C

# ─── Configuration ──────────────────────────────────────────────────────
FACTS_OUT="${HEALTH_FACTS_PATH:-/run/maddata/host-facts.json}"
mkdir -p "$(dirname "$FACTS_OUT")" 2>/dev/null || true
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/maddata}"
TLS_CERT="${HEALTH_TLS_CERT:-/etc/letsencrypt/live/ad.maddata.media/fullchain.pem}"
UNITS="${HEALTH_UNITS:-nginx php8.4-fpm mysql redis-server cron maddata-queue}"

# How long to average CPU over. A 1-second sample on a small droplet is noise:
# it reports whatever happened to run in that second. The crontab should also
# offset this script away from the top of the minute, because that is when
# `schedule:run` boots PHP and briefly saturates a single-core box — sampling
# there measures the monitoring's own overhead. See docs/runbooks/health-monitor.md.
CPU_SAMPLE_SECONDS="${HEALTH_CPU_SAMPLE_SECONDS:-15}"

# apt-check parses the whole APT package cache (~40-80MB) and costs 0.3-1.8s of
# CPU. Security-update counts do not change minute to minute, so it is sampled
# hourly and cached; the per-minute run just reads the cached number.
APT_CHECK_INTERVAL="${HEALTH_APT_CHECK_INTERVAL:-3600}"
SLOW_CACHE="$(dirname "$FACTS_OUT")/slow-facts.cache"

# Worst case this script runs ~40s (CPU sample + apt-check) against a 60s cron.
# Without a lock a slow run produces two concurrent instances fighting over the
# same temp files.
# Degrade rather than disappear: a host without flock keeps the previous
# (unlocked) behaviour. Exiting here would mean no facts at all, which is a
# far worse failure than an occasional overlap.
if command -v flock >/dev/null 2>&1; then
    if exec 9>"$(dirname "$FACTS_OUT")/.health-facts.lock" 2>/dev/null; then
        flock -n 9 || { echo "another health-facts run is in progress" >&2; exit 0; }
    fi
fi

mkdir -p "$(dirname "$FACTS_OUT")" 2>/dev/null || true

# ─── CPU: real utilization, NOT loadavg ─────────────────────────────────
# loadavg/nproc runs well above true CPU under I/O concurrency and produces
# false CRITs. vmstat's idle column is the honest number. Parse the column by
# HEADER NAME — its index shifts between procps versions.
#
# vmstat prints the since-boot average first, then one row per interval; we
# take the LAST row, which is the interval we actually asked for.
cpu_pct=$(vmstat "$CPU_SAMPLE_SECONDS" 2 2>/dev/null | awk '
    /r[ \t]+b/ { for (i = 1; i <= NF; i++) if ($i == "id") idx = i; next }
    { last = $idx }
    END { if (idx && last != "") printf "%.1f", 100 - last; else print "" }
')
[ -z "$cpu_pct" ] && cpu_pct="null"

# ─── Memory ─────────────────────────────────────────────────────────────
mem_pct=$(free 2>/dev/null | awk '/^Mem:/ { if ($2 > 0) printf "%.1f", ($2 - $7) / $2 * 100 }')
[ -z "$mem_pct" ] && mem_pct="null"

# ─── Disk (root filesystem) ─────────────────────────────────────────────
disk_pct=$(df -P / 2>/dev/null | awk 'NR==2 { gsub("%", "", $5); print $5 }')
[ -z "$disk_pct" ] && disk_pct="null"

# ─── systemd units ──────────────────────────────────────────────────────
units_json=""
for unit in $UNITS; do
    state=$(systemctl is-active "$unit" 2>/dev/null || true)
    [ -z "$state" ] && state="unknown"
    [ -n "$units_json" ] && units_json="${units_json},"
    units_json="${units_json}\"${unit}\":\"${state}\""
done

# ─── Pending security updates + nginx version (hourly, cached) ──────────
# Both are expensive relative to everything else here and neither changes on a
# per-minute timescale. Recomputed only when the cache has aged out.
if [ ! -f "$SLOW_CACHE" ] || [ "$(( $(date +%s) - $(stat -c %Y "$SLOW_CACHE" 2>/dev/null || echo 0) ))" -ge "$APT_CHECK_INTERVAL" ]; then
    _sec=0
    if [ -x /usr/lib/update-notifier/apt-check ]; then
        _sec=$(/usr/lib/update-notifier/apt-check 2>&1 | cut -d';' -f2)
        [[ "$_sec" =~ ^[0-9]+$ ]] || _sec=0
    fi
    _ngx=$(nginx -v 2>&1 | sed -n 's|.*nginx/\([0-9.]*\).*|\1|p')
    [ -z "$_ngx" ] && _ngx="unknown"
    printf '%s %s\n' "$_sec" "$_ngx" > "$SLOW_CACHE".tmp && mv -f "$SLOW_CACHE".tmp "$SLOW_CACHE"
fi
read -r pending_security nginx_version < "$SLOW_CACHE" 2>/dev/null || { pending_security=0; nginx_version="unknown"; }
[[ "$pending_security" =~ ^[0-9]+$ ]] || pending_security=0
[ -z "${nginx_version:-}" ] && nginx_version="unknown"

reboot_required=0
[ -f /var/run/reboot-required ] && reboot_required=1

# ─── TLS certificate days remaining ─────────────────────────────────────
tls_days="null"
if [ -r "$TLS_CERT" ]; then
    end_date=$(openssl x509 -enddate -noout -in "$TLS_CERT" 2>/dev/null | cut -d= -f2)
    if [ -n "$end_date" ]; then
        end_epoch=$(date -d "$end_date" +%s 2>/dev/null || echo "")
        if [ -n "$end_epoch" ]; then
            tls_days=$(( (end_epoch - $(date +%s)) / 86400 ))
        fi
    fi
fi

# ─── Backup directory cross-check ───────────────────────────────────────
# Independent of the marker the backup script writes: a marker that NEVER
# appears must be detectable, and only the filesystem can prove that.
backups_json="[]"
if [ -d "$BACKUP_ROOT" ]; then
    entries=""
    while read -r ts path; do
        [ -z "$path" ] && continue
        bytes=$(du -sb "$path" 2>/dev/null | cut -f1)
        [[ "$bytes" =~ ^[0-9]+$ ]] || bytes=0
        [ -n "$entries" ] && entries="${entries},"
        entries="${entries}{\"ts\":${ts%.*},\"bytes\":${bytes}}"
    done < <(find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' 2>/dev/null | sort -rn | head -7)
    [ -n "$entries" ] && backups_json="[${entries}]"
fi

# ─── OS name ────────────────────────────────────────────────────────────
os_name=$( (. /etc/os-release 2>/dev/null && echo "$PRETTY_NAME") || echo "unknown")

# ─── Numeric guard ──────────────────────────────────────────────────────
# These four are interpolated into JSON *number* positions. Anything
# non-numeric makes the whole file unparseable, which blinds H1-H6, P2 and B2
# simultaneously — the single most load-bearing failure mode in the monitor.
num() { [[ "${1:-}" =~ ^-?[0-9]+(\.[0-9]+)?$ ]] && printf '%s' "$1" || printf 'null'; }
cpu_pct=$(num "$cpu_pct"); mem_pct=$(num "$mem_pct")
disk_pct=$(num "$disk_pct"); tls_days=$(num "$tls_days")

# ─── Atomic write ───────────────────────────────────────────────────────
# Write to a temp file in the SAME directory then mv, so a reader can never
# observe a half-written file and conclude the host is unhealthy.
TMP="${FACTS_OUT}.tmp.$$"
cat > "$TMP" <<JSON
{
  "ts": $(date +%s),
  "hostname": "$(hostname)",
  "os": "${os_name}",
  "cpu_pct": ${cpu_pct},
  "cpu_sample_seconds": ${CPU_SAMPLE_SECONDS},
  "mem_pct": ${mem_pct},
  "disk_root_pct": ${disk_pct},
  "units": {${units_json}},
  "pending_security": ${pending_security},
  "reboot_required": ${reboot_required},
  "tls_days_remaining": ${tls_days},
  "nginx_version": "${nginx_version}",
  "backups": ${backups_json}
}
JSON

# 640 root:www-data — the file names the OS, nginx version and count of
# unapplied security updates, which is a "how exploitable is this box" summary
# no other local user needs.
chgrp www-data "$TMP" 2>/dev/null || true
chmod 640 "$TMP"
mv -f "$TMP" "$FACTS_OUT"
