# Tri-audit disposition — System Health Monitor (2026-08-19)

Three audits over `c3d486b..HEAD`: [security](health-monitor-2026-08-19-security.md), [reviewer](health-monitor-2026-08-19-reviewer.md), [performance](health-monitor-2026-08-19-performance.md). Every finding was verified against production before being accepted; two were rejected on the evidence.

## Fixed and deployed

| Finding | Found by | Commit |
|---|---|---|
| Monitor reported OUTAGE as `www-data` and green as root — backup marker unreadable through a `700` directory. Fired a false alert. | security + reviewer (independently) | `08ce81f` |
| Scheduler mutexes resolved through the app cache (`database` on prod) — a MySQL outage would make the scheduler *skip* `health:alert` entirely | reviewer | `02364dd` |
| `withoutOverlapping()` defaults to 1440 **minutes** — one SIGKILL wedges a task for a day | reviewer | `02364dd` |
| Unpersistable alert state silenced alerting permanently and silently | security | `02364dd` |
| A failed recovery email was lost forever, contradicting the class's own rule | reviewer | `02364dd` |
| No floor between "new failing check" emails — alternating checks could email every 5 min forever | security | `02364dd` |
| P1 stayed GREEN for a full TTL after `health:probe` died (`checked_at` written, never read) | reviewer | `02364dd` |
| B2's in-flight filter would also hide a partial dir from a *failed* backup — the truncation it exists to catch | reviewer | `02364dd` |
| `PDO::ATTR_TIMEOUT` bounds connect only; D1 could still hang on a MySQL that accepts then stalls | all three | `02364dd` |
| `apt-check` burned ~1.8s CPU/min (measured) for a field no check reads | performance | `02364dd` |
| Snapshot TTL 30s against a 60s rebuild — a 30-second cache hole every minute | performance | `02364dd` |
| `MAX(campaign_data.report_date)` scanned the full index; now `Select tables optimized away` | performance | `02364dd` |
| No `flock`; worst-case runtime approached the cron interval | performance | `02364dd` |
| Facts file `0644` and unvalidated numeric interpolation; missing `LC_ALL=C` | security | `02364dd` |
| `queue:prune-failed` never scheduled — Q2 slowed in proportion to queue sickness | performance | `02364dd` |
| `HostFacts` memo invalidated externally — fragile for Phase 3 | reviewer | `02364dd` |
| `QueueHeartbeatJob` untested; alert tests read the real `/proc/uptime` | reviewer | `02364dd` |

## Rejected on the evidence

| Claim | Verification |
|---|---|
| A missing `.env` key aborts the backup script under `set -e`, stopping rotation | Reproduced the exact code path: does not abort, exit 0. Rotation healthy at exactly 7 dirs; all four `DO_` keys present. |
| The `.env` archive may be publicly readable in the CDN bucket | HTTP 403 on both the object and the bucket listing. |
| Add an index on `failed_jobs.failed_at` | Performance audit's own advice: below ~10K rows a scan beats an index. Premature. |

## Open — needs an operator decision

- **DO Spaces keys are visible in `/proc/<pid>/cmdline`** during the nightly upload (confirmed exploitable: no `hidepid`, `www-data` can read it). Pre-existing since `931b4ff`. Fixing means **rotating the keys**.
- **`CACHE_STORE` is unset on production**, so app cache, sessions and scheduler mutexes all run on MySQL while Redis sits idle. Setting `CACHE_STORE=redis` is the largest single win available — but keep `HEALTH_MARKER_STORE=database` deliberately: markers in Redis would make every age check go STALE together during a Redis outage, destroying the ability to tell "Redis down" from "scheduler down".
- **Encrypt `env.tar.gz` before off-site upload** so a bucket-ACL mistake is not fatal.
- **One unreproduced flaky test run** (1 failure in one run, then 4 consecutive clean full-suite runs). Not identified; recorded rather than claimed fixed.

## Deliberate non-changes

`du` on few-file backup dirs, `vmstat N 2` (it sleeps, ~0 CPU), the probe's per-minute cadence, and the `HealthMarkers::store()` static accessor — all reviewed and judged correct as they stand.
