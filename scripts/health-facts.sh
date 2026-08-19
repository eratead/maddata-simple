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

# ─── Configuration ──────────────────────────────────────────────────────
FACTS_OUT="${HEALTH_FACTS_PATH:-/run/maddata/host-facts.json}"
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/maddata}"
TLS_CERT="${HEALTH_TLS_CERT:-/etc/letsencrypt/live/ad.maddata.media/fullchain.pem}"
UNITS="${HEALTH_UNITS:-nginx php8.4-fpm mysql redis-server cron maddata-queue}"

# How long to average CPU over. A 1-second sample on a small droplet is noise:
# it reports whatever happened to run in that second. The crontab should also
# offset this script away from the top of the minute, because that is when
# `schedule:run` boots PHP and briefly saturates a single-core box — sampling
# there measures the monitoring's own overhead. See docs/runbooks/health-monitor.md.
CPU_SAMPLE_SECONDS="${HEALTH_CPU_SAMPLE_SECONDS:-15}"

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

# ─── Pending security updates + reboot flag ─────────────────────────────
pending_security=0
if [ -x /usr/lib/update-notifier/apt-check ]; then
    # apt-check prints "total;security" on stderr
    pending_security=$(/usr/lib/update-notifier/apt-check 2>&1 | cut -d';' -f2)
    [[ "$pending_security" =~ ^[0-9]+$ ]] || pending_security=0
fi

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

# ─── Versions ───────────────────────────────────────────────────────────
nginx_version=$(nginx -v 2>&1 | sed -n 's|.*nginx/\([0-9.]*\).*|\1|p')
[ -z "$nginx_version" ] && nginx_version="unknown"
os_name=$( (. /etc/os-release 2>/dev/null && echo "$PRETTY_NAME") || echo "unknown")

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

chmod 644 "$TMP"
mv -f "$TMP" "$FACTS_OUT"
