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
PKG_CACHE="$(dirname "$FACTS_OUT")/pkg-facts.cache"

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
#
# NOT apt-check. apt-check counts packages in the FULL-UPGRADE change set that
# have a security-pocket version, including packages that are not installed at
# all. On this droplet that produced a permanently-stuck "1 pending security
# update" — libabsl20220623t64, which is not installed and would only arrive as
# a new dependency of libgav1-1 during a full upgrade, so `apt-get install
# --only-upgrade` refuses it and unattended-upgrade reports "no packages found".
# An amber that no action can ever clear is how a monitor teaches people to
# ignore it.
#
# `apt-get -s upgrade` is the right question: it simulates upgrading INSTALLED
# packages only, and holds back anything that would need a new dependency —
# exactly the semantics of what can actually be applied. We then count only the
# lines whose archive is a -security pocket, matching inside the parenthesised
# version field so a package merely NAMED *-security cannot inflate the count.
#
# A count we cannot compute is written as null, never as 0: check d3 reads null
# as STALE, because a monitor that cannot count must not report "clean".
if [ ! -f "$SLOW_CACHE" ] || [ "$(( $(date +%s) - $(stat -c %Y "$SLOW_CACHE" 2>/dev/null || echo 0) ))" -ge "$APT_CHECK_INTERVAL" ]; then
    _sec="null"
    if command -v apt-get >/dev/null 2>&1; then
        _out=$(LC_ALL=C apt-get -s -o Debug::NoLocking=true upgrade 2>/dev/null) && \
            _sec=$(printf '%s\n' "$_out" | grep -c -E '^Inst .*\([^)]*-security' || true)
        [[ "$_sec" =~ ^[0-9]+$ ]] || _sec="null"
    fi
    _ngx=$(nginx -v 2>&1 | sed -n 's|.*nginx/\([0-9.]*\).*|\1|p')
    [ -z "$_ngx" ] && _ngx="unknown"
    printf '%s %s\n' "$_sec" "$_ngx" > "$SLOW_CACHE".tmp && mv -f "$SLOW_CACHE".tmp "$SLOW_CACHE"
fi
read -r pending_security nginx_version < "$SLOW_CACHE" 2>/dev/null || { pending_security="null"; nginx_version="unknown"; }
[[ "$pending_security" =~ ^[0-9]+$ ]] || pending_security="null"
[ -z "${nginx_version:-}" ] && nginx_version="unknown"

# ─── Installed package versions (hourly, cached) ─────────────────────────
# Check d2 needs to tell an upstream build from a distro-backported one, and a
# runtime's SELF-REPORTED version cannot always answer that. MySQL bakes the
# package suffix into `select version()` ("8.0.46-0ubuntu0.24.04.3"), but Redis
# reports a bare upstream semver ("7.0.15") even when the installed package is
# "5:7.0.15-1ubuntu0.24.04.4" — so d2 read Redis as CRIT-past-EOL on a box that
# is in fact receiving Canonical's backports.
#
# The package manager is the only authority on this, and the app is never
# allowed to ask it: PHP-FPM does not shell out. So root asks here, and the app
# reads the answer, exactly like every other fact in this file.
if [ ! -f "$PKG_CACHE" ] || [ "$(( $(date +%s) - $(stat -c %Y "$PKG_CACHE" 2>/dev/null || echo 0) ))" -ge "$APT_CHECK_INTERVAL" ]; then
    _pkgs=""
    if command -v dpkg-query >/dev/null 2>&1; then
        for _p in redis-server mysql-server mysql-server-8.0 mysql-server-8.4 nginx nginx-core; do
            _pv=$(LC_ALL=C dpkg-query -W -f='${Version}' "$_p" 2>/dev/null) || _pv=""
            # Quote-strip: these land inside JSON string values.
            _pv=${_pv//\"/}
            if [ -n "$_pv" ]; then
                [ -n "$_pkgs" ] && _pkgs="${_pkgs},"
                _pkgs="${_pkgs}\"${_p}\":\"${_pv}\""
            fi
        done
    fi
    printf '{%s}\n' "$_pkgs" > "$PKG_CACHE".tmp && mv -f "$PKG_CACHE".tmp "$PKG_CACHE"
fi
packages_json=$(cat "$PKG_CACHE" 2>/dev/null) || packages_json="{}"
# An empty or truncated cache must not make the whole facts file unparseable,
# which would blind every host check at once.
case "$packages_json" in
    '{'*'}') ;;
    *) packages_json="{}" ;;
esac

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
# num() turns anything non-numeric — including the literal "null" — into null,
# which is exactly what d3 needs to tell "no updates pending" from "could not
# ask". Do NOT let this default to 0.
pending_security=$(num "$pending_security")

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
  "packages": ${packages_json},
  "backups": ${backups_json}
}
JSON

# 640 root:www-data — the file names the OS, nginx version and count of
# unapplied security updates, which is a "how exploitable is this box" summary
# no other local user needs.
chgrp www-data "$TMP" 2>/dev/null || true
chmod 640 "$TMP"
mv -f "$TMP" "$FACTS_OUT"
