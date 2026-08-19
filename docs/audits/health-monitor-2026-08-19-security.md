# Security Audit — System Health Monitor (2026-08-19)

**Scope:** `c3d486b..HEAD`, excluding `docs/*`. Code was already live on production at audit time.
**Companion reports:** [reviewer](health-monitor-2026-08-19-reviewer.md) · [performance](health-monitor-2026-08-19-performance.md) · [disposition](health-monitor-2026-08-19-SUMMARY.md)

Every finding below was independently verified against the production droplet before being accepted or rejected. Verification commands and results are recorded with each item.

## Confirmed and fixed

### The monitor's answer depended on which user asked (fixed, `08ce81f`)
Moving the backup marker to `/var/backups/maddata` (commit `cdde032`) placed it inside a directory the backup script itself prescribes as `700 root:root`. `www-data` cannot traverse that, so `BackupCheck::marker()` returned null while the facts file — written by the **root** cron — still listed backup directories. That combination hits the branch reporting `CRIT "marker missing (N backup dirs on disk)"`.

Verified on production:

```
drwx------ root root  /var/backups/maddata
sudo -u www-data test -r .../backup-last.json   -> NOT READABLE
sudo -u www-data php artisan health:check       -> OUTAGE, B1 CRIT
sudo php artisan health:check                   -> UNKNOWN, B1 OK
```

The scheduler, PHP-FPM and the alerter all run as `www-data`; it fired a false alert at 10:15 UTC. Every green reported that day had been measured as root.

**Fix:** `chown root:www-data` + `chmod 750` on `BACKUP_ROOT`, re-applied by the backup script on every run so a hand-created directory cannot silently break B1. Backup *subdirectories* stay `700 root:root`, so the archived plaintext `.env` remains unreadable to the web user. `BackupCheck` now distinguishes *unreadable* (STALE, naming the user and directory) from *missing* (CRIT). The runbook's verification step runs as `www-data` and explains why root's answer is the one that does not matter.

Independently found by the reviewer audit as C-3.

### Alerting could be silenced permanently, or flood (fixed, `02364dd`)
Three paths, all verified by reading the code against its own documented rules:
- A cache store that accepts reads but drops writes held the "hold one interval" branch forever, silencing the alerter without ever saying so. The class docblock claimed unreadable **or unwritable** state disables suppression; only the read half was implemented.
- A failed recovery email was discarded and the episode cleared, contradicting rule 4 and leaving the operator believing the system was still broken.
- No floor existed between "new failing check" emails, so two checks straddling their thresholds and alternating would email every five minutes indefinitely.

**Fix:** `writeState()` reports failure and unpersistable state disables suppression; a failed recovery keeps the episode open to retry; a minimum-interval floor guards the flood vector, with escalations explicitly exempt so a worsening system is never delayed.

### Facts file hardening (fixed, `02364dd`)
`/run/maddata/host-facts.json` was `0644`, readable by every local UID, and names the OS, nginx version and count of unapplied security updates — a concise "how exploitable is this box" summary. Now `0640 root:www-data`. Verified: `www-data` reads it, `nobody` does not.

Four numeric fields were interpolated into JSON *number* positions without validation; one malformed value invalidates the file and blinds H1–H6, P2 and B2 simultaneously. Guarded, and `LC_ALL=C` is exported because `awk`'s `%.1f` honours `LC_NUMERIC` and an operator running the script by hand carries their own locale.

## Confirmed, not fixed — needs a decision

### DO Spaces credentials are readable from `/proc/<pid>/cmdline`
`scripts/backup-production.sh` passes the access key and secret as `s3cmd` argv. Verified on production: `/proc` is mounted without `hidepid`, and `www-data` **can** read another process's `cmdline`. During the nightly upload of a ~233 MB backup that is a multi-minute window in which any `www-data` foothold can lift keys granting read and delete on the bucket containing `env.tar.gz` — the full production `.env`.

Pre-existing since `931b4ff`, not introduced by this work. **Fixing it requires rotating the keys**, so it is the operator's call. The fix itself is small: write a `600` temp config and pass `--config=`, mirroring the `MYSQL_PWD` pattern already used correctly for the database password on line 102.

## Rejected after verification

### "A missing `.env` key aborts the backup script under `set -e`"
Claimed that `get_env`'s failing `grep` propagates through `pipefail` and kills the script before rotation and the marker write. **Reproduced the exact code path in isolation: it does not abort.** Execution continued past the missing key, exit 0. Production corroborates — rotation is healthy at exactly 7 directories, and all four `DO_` keys are present regardless.

### "The `.env` archive may be publicly readable in the CDN-named bucket"
Reasonable concern given the bucket is also used for CDN assets and object paths are predictable. Tested directly: **HTTP 403** on both the object and a bucket listing. Private and correct. The defence-in-depth suggestion — encrypt before upload so a bucket-ACL mistake is not fatal — remains worthwhile and is filed as a backlog item, not a live issue.

## Verified as sound

- **Zero-grant holds.** No `exec`, `shell_exec`, `proc_open`, `passthru`, `system` or backticks anywhere in `app/`. The only inputs to the request path are two `file_get_contents` calls on config-fixed paths.
- **No HTTP surface exists yet.** Phase 3 must gate `/admin/monitor` behind `auth` + `EnsureUserIsAdmin` + `throttle`, as the spec already specifies.
- **The alert email is fully escaped** — `{{ }}` throughout, no `{!! !!}`. Recipients come from config, never from request data.
- **The destructive remote prune is allow-listed** (`^[0-9]{8}_[0-9]{6}$`) before any value reaches an S3 path.
- **`HostFacts` fails closed** — missing, unreadable, empty or malformed input all yield null, surfaced as "monitoring blind" rather than an exception. No user-controlled path component, so no traversal risk.
- **`mysql_health` introduces no new secret material** — it reuses the same `env()` values as `mysql`.

## Carried into Phase 4

- **No check may put a client name, campaign name or per-tenant identifier into a result `value`.** The snapshot has no tenant scoping and never will. D3 is currently the only check deriving from tenant data (an unscoped `MAX(report_date)`), and a bare date is acceptable; anything richer is not.
- **`link` becomes an `href` in Phase 3.** `EdgeProbeCheck` sets it from an env-driven URL, and `{{ }}` does not neutralise a `javascript:` scheme in an attribute. Enforce the spec's allow-list before rendering.
