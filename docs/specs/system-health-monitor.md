# System Health Monitor (MadData)

**Status:** Phases 1 and 2 built and deployed to production (2026-08-19). Phases 3–4 designed in detail in [health-monitor-phases-3-4.md](health-monitor-phases-3-4.md) — that document supersedes §6 and the Phase 3/4 rows of §10 where they differ, and records the four decisions taken before design (no history, read-only page + one refresh POST, dependency checks digest-only, admin-only header pill).
**Author:** Architect
**Date:** 2026-08-19
**Prior art:** `erate-v2/docs/specs/system-health-monitor.md` (built & battle-tested 2026-07-23 → hardened 2026-07-29) and `erate-v2/docs/specs/dependency-security-maintenance.md`. This spec deliberately reuses that vertical's **names, contracts and hard-won rules** so one operator carries one mental model across both projects. It scales the design down from a 6-host fleet to MadData's single droplet.

## 1. Goals / Non-Goals

**Goals**
- Answer "is production OK?" without SSH — from a CLI (`php artisan health:check`), from an admin page, and unprompted by email when something breaks.
- Cover the four areas requested: infra basics, app/data freshness, backups & security, **and OS/PHP/dependency currency**.
- Resilient by construction: the monitor MUST render (degraded) when the thing it monitors is down. A Redis or MySQL outage turns nodes red; it must never 500 a page or hang a request.
- Zero elevated grants in the request path. PHP-FPM never calls `shell_exec`, `sudo`, `systemctl`, or `apt`.

**Non-Goals**
- Not a graphs/history dashboard. This is a *checks/status* page — worst-of rollup, current values, links out. No time-series, no charts, no `health_snapshots` table.
- Not a replacement for an **external** watcher. Nothing running on the droplet can report that the droplet is dead. See §9.
- No new composer/npm packages. No Pulse, no Sentry, no agent daemons.
- Health data is never exposed to clients or agency managers (§7).

## 2. Architecture

```
root OS cron ──1 min──▶ scripts/health-facts.sh ──atomic write──▶ /run/maddata/host-facts.json
backup cron  ──03:00──▶ scripts/backup-production.sh (+1 block) ─▶ /run/maddata/backup-last.json
laravel sched ──1 min──▶ health:probe (curl https://ad.maddata.media/up) ─▶ cache marker
laravel sched ──1 min──▶ QueueHeartbeatJob (onto the real queue) ───────▶ cache marker
laravel sched ──1 min──▶ health:refresh-snapshot ──▶ SystemHealthService::refresh()
                                     ├─ HostFacts (read JSON file — no Redis, no shell)
                                     ├─ MySQL via dedicated 2s-timeout `mysql_health` connection
                                     ├─ Redis INFO
                                     ├─ queue depth / failed_jobs / heartbeat + scheduler markers
                                     ├─ backup markers
                                     └─ dependency & EOL checks (24h-cached)
                                          ▼
                                   HealthSnapshot ──▶ php artisan health:check (exit code)
                                                 ├──▶ /admin/monitor (Blade map, Alpine polls /data every 30s)
                                                 ├──▶ header pill (admins only)
                                                 └──▶ health:alert (transition-based email)
```

**The one structural change vs erate-v2.** erate-v2 ships host facts over Redis because they cross six machines. MadData is one machine, so facts go through a **plain JSON file on tmpfs** instead. This is strictly better here: the host-facts path has *zero* dependency on Redis, MySQL, or the Laravel app, so it still reports correctly when those are exactly what's broken. Everything else about the zero-grant design carries over verbatim — a root cron writes, the app only ever reads.

**Rules inherited from erate-v2 (do not relitigate during build):**
1. **Facts scripts are OS cron + bash, never the Laravel scheduler** — they must fire when the app is broken.
2. **CPU is real utilization via `vmstat`, not `loadavg/nproc`** — loadavg runs 1.5–2× high under I/O concurrency and produces false CRITs.
3. **MySQL is probed on its own short-timeout connection** — otherwise a MySQL outage hangs every admin page instead of degrading the pill.
4. **Single-flight lock + last-known-good fallback** — N open tabs must not stampede the rebuild; a failed rebuild serves the previous snapshot rather than blank.
5. **A check class NEVER throws.** Its own failure is a CRIT result tagged to its real node. An exception that escapes is a bug.
6. **Fail toward alerting.** If the flap-suppression state itself can't be read, skip suppression and report raw — the correlated-failure case is exactly what the check exists for.
7. **An unreachable feed is never GREEN.**

## 3. Check Catalog

Statuses: `OK` / `WARN` / `CRIT` / `STALE`. Rollup = worst-of; STALE collapses to WARN for the pill, renders gray-striped on the map. All thresholds live in `config/health.php`, env-overridable.

### Host — source: `/run/maddata/host-facts.json` (node `host`)

| # | Check | WARN | CRIT |
|---|---|---|---|
| H1 | Facts file age — "am I monitoring blind?" | >180s | >600s or missing |
| H2 | CPU % (vmstat) | ≥70% | ≥85% |
| H3 | Memory % | ≥85% | ≥95% |
| H4 | Disk root % | ≥75% | ≥85% |
| H5 | systemd units: `nginx`, `php8.4-fpm`, `mysql`, `redis-server`, `cron`, queue worker unit | `activating`/restart-loop | any not `active` |
| H6 | `/var/run/reboot-required` present | set | — |

### Edge (node `edge`)

| # | Check | WARN | CRIT |
|---|---|---|---|
| P1 | Public HTTPS probe — `health:probe` curls `https://ad.maddata.media/up` (3s timeout) | latency >800ms, or 1 fail | 2 **consecutive** fails |
| P2 | TLS cert days-to-expiry (`openssl x509 -enddate`, from facts) | <21d | <7d |

P1 is the only check that sees the stack the way a user does — Nginx config, TLS, DNS, FPM socket. It is written by a scheduled command and *read* by the service; the service never curls inline.

### App & workers (nodes `app`, `workers`)

| # | Check | WARN | CRIT |
|---|---|---|---|
| Q1 | Queue depth (configured connection) | >500 | >5,000 |
| Q2 | `failed_jobs` created in last 24h | ≥1 | ≥25 |
| Q3 | **Queue worker heartbeat** — `QueueHeartbeatJob` dispatched every minute, writes marker on execution | >5 min | >15 min |
| S1 | Scheduler heartbeat — `Schedule::call()` marker | >5 min | >15 min |
| S2a | `campaigns:generate-status` last success | >26h | >50h |
| S2b | `digest:send-activity` last success | >3h | >6h |

Q3 is the check that matters: systemd reports `active` for a worker that is wedged, out of memory, or holding a poisoned job. Only a job that actually *executes* proves the pipeline works end to end.

### Data (node `data`)

| # | Check | WARN | CRIT |
|---|---|---|---|
| D1 | MySQL reachable — timed `select 1` on `mysql_health` (2s PDO timeout) | >100ms | unreachable |
| D2 | Redis reachable + `used_memory` vs `maxmemory` | ≥90% of ceiling | unreachable |
| D3 | Campaign data freshness — `MAX(campaign_data.report_date)` age | configurable, default >3d | **never CRIT** — see open question 1 |

### Backups (node `backups`) — source: `/run/maddata/backup-last.json` + facts cross-check

| # | Check | WARN | CRIT |
|---|---|---|---|
| B1 | Last local backup age | >26h | >50h, or marker missing while the backup dir exists |
| B2 | Backup size sanity — newest dump vs median of last 7 | <70% | <50% (silent truncation / partial dump) |
| B3 | Off-site DO Spaces upload — last success age + reported byte count | >26h, or last run reported remote failure | >50h |
| B4 | Restore-drill age — `health:mark-restore-drill` marker (last drill 2026-07-12) | >120d | >210d |

B1–B3 read a marker that `scripts/backup-production.sh` writes on completion, with the facts script independently `stat`-ing `/var/backups/maddata` as a cross-check — a marker that never appears at all must read as CRIT, not silence. B2 is the check that catches the failure mode a naive "backup ran" check misses: mysqldump exiting 0 having written half a database.

### Dependencies & versions (node `platform`) — mirrors erate-v2 `d1`–`d4`

| # | Check | WARN | CRIT |
|---|---|---|---|
| d1 | Composer advisories — deployed `composer.lock` (packages + packages-dev) vs Packagist security-advisories API, matched with `composer/semver`; 24h cached | any `medium` | any `high`/`critical`/**unrated** |
| d2 | Runtime EOL — PHP (`PHP_VERSION`), MySQL (`SELECT VERSION()`), Redis (`INFO server`), Nginx (facts) vs the static support-window table in `config/dependency_maintenance.php` | <90d to end of security support; **or** the table's own `reviewed_at` >6 months old | past end of security support |
| d3 | OS security patches — `pending_security` + `reboot_required` from `apt-check` (facts) | pending >0 sustained >7d, or facts stale >48h | pending >0 sustained >30d (unattended-upgrades broken), or facts stale >7d |
| d4 | Patch-run freshness — `deps:mark-patch-run` marker `{ts, lock_sha, note}` | >35d, or `lock_sha` ≠ deployed lock while d1 shows highs | >60d |

Feed-down behavior for d1: serve the last-known-good cached result if one exists, else WARN "advisory feed unreachable". Never GREEN on a dead feed. "Sustained >N days" in d3 is tracked **check-side** in a persisted since-marker, because `apt-check` only reports the current count.

### Security (node `platform`)

| # | Check | WARN | CRIT |
|---|---|---|---|
| X1 | Sanctum tokens past expiry still present (housekeeping) | >0 | — |
| X2 | Failed-login burst in last 15 min | ≥20 | ≥100 |

Per-check output DTO: `HealthCheckResult { key, label, status, value, threshold, node, link? }` — `link` deep-links to a runbook or the relevant admin page.

## 4. File Structure & Class Contracts

Signatures only — no implementations here.

| File | Responsibility |
|---|---|
| `app/Enums/HealthStatus.php` | `OK/WARN/CRIT/STALE`; `worstOf(...$s): self`, `forPill(): self` (STALE→WARN), `colorToken(): string`, `label(): string` |
| `app/Dtos/HealthCheckResult.php` | readonly DTO per §3; `toArray(): array` |
| `app/Dtos/HealthSnapshot.php` | `__construct(HealthStatus $overall, array $nodes, array $checks, CarbonImmutable $generatedAt)`; `fromResults(HealthCheckResult ...$r): self`; `toArray(): array` — **this array is the JSON contract the UI polls; document it in-file** |
| `app/Services/Health/SystemHealthService.php` | `snapshot(): HealthSnapshot` (30s cache, `Cache::lock` single-flight, no-TTL `health:snapshot:last` fallback); `refresh(): HealthSnapshot` (off-path rebuild for the scheduler); `pillStatus(): HealthStatus` (never throws) |
| `app/Services/Health/HostFacts.php` | `read(): ?array`, `ageSeconds(): ?int` — the only reader of the facts file; no shell, no exceptions |
| `app/Services/Health/Checks/HealthCheck.php` | abstract base: `run(): array` (of `HealthCheckResult`, **never throws**); `protected guard(string $key, string $label, string $node, Closure $probe): HealthCheckResult` wraps every probe |
| `app/Services/Health/Checks/HostCheck.php` | H1–H6 |
| `app/Services/Health/Checks/EdgeProbeCheck.php` | P1–P2 (reads markers; never curls) |
| `app/Services/Health/Checks/QueueCheck.php` | Q1–Q3 |
| `app/Services/Health/Checks/SchedulerCheck.php` | S1–S2b |
| `app/Services/Health/Checks/DataStoreCheck.php` | D1–D3 |
| `app/Services/Health/Checks/BackupCheck.php` | B1–B4 |
| `app/Services/Health/Checks/DependencyAdvisoriesCheck.php` | d1 |
| `app/Services/Health/Checks/RuntimeEolCheck.php` | d2 |
| `app/Services/Health/Checks/OsPatchCheck.php` | d3 |
| `app/Services/Health/Checks/PatchRunFreshnessCheck.php` | d4 |
| `app/Services/Health/Checks/SecurityPostureCheck.php` | X1–X2 |
| `app/Jobs/QueueHeartbeatJob.php` | writes `health:queue:beat_at`; proves the worker executes |
| `app/Console/Commands/RunHealthCheck.php` | `health:check {--json} {--fail-on=warn|crit}` — human table or JSON; **exit code reflects worst status** so it composes with cron and SSH |
| `app/Console/Commands/RefreshHealthSnapshot.php` | `health:refresh-snapshot` — everyMinute; rebuild off-path so no real request ever observes a cache miss |
| `app/Console/Commands/PublicProbe.php` | `health:probe` — everyMinute; curl `/up`, write `{ok, latency_ms, checked_at, consec_fails}` |
| `app/Console/Commands/SendHealthAlert.php` | `health:alert` — everyFiveMinutes; transition-based mail (§5) |
| `app/Console/Commands/MarkRestoreDrill.php` | `health:mark-restore-drill {--note=}` → B4 |
| `app/Console/Commands/MarkPatchRun.php` | `deps:mark-patch-run` → d4 |
| `app/Mail/HealthAlertMail.php` | mailable; follows the existing `ActivityDigestMail` pattern |
| `app/Http/Controllers/Admin/MonitorController.php` | thin: `index()` → view, `data()` → `HealthSnapshot::toArray()` JSON |
| `config/health.php` | node labels, all thresholds (env-overridable), facts/marker paths, probe URL, alert recipients + re-alert interval |
| `config/dependency_maintenance.php` | static EOL table + `reviewed_at` |
| `scripts/health-facts.sh` | root cron, 1 min: vmstat CPU, mem, disk, systemd unit states, reboot-required, `apt-check`, TLS `-enddate`, backup-dir stat → **atomic** write to `/run/maddata/host-facts.json` (write temp + `mv`), mode 644, contains numbers and states only |
| `docs/runbooks/health-monitor.md` | provisioning: tmpfs dir, crontab lines, alert-recipient env, what each CRIT means and what to do |
| `resources/views/admin/monitor.blade.php` + `components/monitor/{node-card,kpi-tile}.blade.php` | the map (§6) |

**Modified files:** `routes/console.php` (4 scheduler entries + scheduler heartbeat marker; +1 success-marker line each in `UpdateCampaignStatuses` and `SendActivityDigest`), `routes/web.php` (2 routes), `config/database.php` (`mysql_health` connection: clone of `mysql` + `PDO::ATTR_TIMEOUT => 2`), `scripts/backup-production.sh` (write `/run/maddata/backup-last.json` on completion), the admin layout header (pill), `resources/views/admin/system_status/index.blade.php` (cross-link to the monitor).

## 5. Alerting

`health:alert` runs everyFiveMinutes and is deliberately dumb:

- Signature = sorted non-OK check keys + worst status. State `{signature, status, first_seen, last_notified}` in the persistent cache store.
- **Fire** when the signature transitions to a worse status, or when still-failing and `last_notified` is older than `health.realert_hours` (default 6). **Recovery notice** on the transition back to all-OK — a silent recovery leaves you unsure whether it healed or the alerter died.
- **Flap suppression:** the **first notification of any episode** requires **2 consecutive** non-OK observations. A deploy restarting FPM or the queue worker resolves inside one interval; a real outage still alerts within ~10 minutes. If the suppression counter's own read/write throws, skip suppression and report raw (rule 6, §2).

  *Deviation from the original draft, decided during build:* the draft applied the two-observation rule to CRIT only. Applying it to WARN as well is one rule instead of two and strictly safer — a warning that resolves within five minutes was never worth an email. Escalations inside an episode are unaffected, because by then the counter is already ≥2.
- Mail failures are logged, never thrown — a broken mailer must not break the scheduler.
- Recipients from `config('health.alert_recipients')` (env, comma-separated). Not the `receive_activity_notifications` user flag — health is operator mail, not product mail, and must reach someone even when the DB is the thing that's sick.

**Honest limit:** if the droplet is off, the network is gone, or SMTP is down, nothing here fires. The outermost ring must be **external** — a free uptime monitor (UptimeRobot / healthchecks.io / DO's own monitoring) hitting `https://ad.maddata.media/up` every minute. That is a ~5-minute setup task and is included as HM-0 precisely because it is the single highest-value item in this whole spec.

`/up` stays exactly as it is — cheap, public, framework-boot proof. It must NOT run checks: it is the endpoint an anonymous external monitor hits every minute, so making it heavy is both a DoS vector and a way to make the outer ring flap on inner-ring warnings.

## 6. UI — "one look tells you where the problem is"

```
┌─ ● ALL SYSTEMS GO / ● DEGRADED (2 warnings) / ● OUTAGE ────── refreshed 12s ago ⟳ ┐
│ [CPU 24%] [Disk 61%] [Queue 0] [Failed 0] [Backup 7h] [Probe 180ms]               │
├────────────────────────────────────────────────────────────────────────────────────┤
│   EDGE            APP            WORKERS          DATA           PLATFORM          │
│  ┌─────────┐   ┌─────────┐    ┌──────────┐    ┌─────────┐    ┌────────────┐        │
│  │ nginx ● │──▶│ php-fpm●│ ──▶│ queue  ● │ ──▶│ mysql  ●│    │ host     ● │        │
│  │ tls   ● │   │ laravel●│    │ schedule●│    │ redis  ●│    │ backups  ● │        │
│  │ probe ● │   └─────────┘    └──────────┘    └─────────┘    │ deps     ● │        │
│  └─────────┘                                                 └────────────┘        │
├────────────────────────────────────────────────────────────────────────────────────┤
│ ▸ Failing/warning checks first (auto-expanded), then all checks grouped by node    │
└────────────────────────────────────────────────────────────────────────────────────┘
```

- **Color is the interface.** Node card = worst-of its checks; a healthy system is a wall of green and any problem is one colored card in the column that names the layer. Numbers are secondary.
- Alpine polls `/admin/monitor/data` every 30s, `document.hidden`-aware. A fetch failure shows a banner and keeps the last snapshot — the monitor degrading must be *visible*, not blank.
- Header pill (admins only) reads the same cached snapshot; any exception → gray "Unknown". It must never break an unrelated page.
- Tailwind + existing design-system tokens only (accent `#F97316`, sidebar `#111827`). No JS libraries. Blade components per CLAUDE.md; all dynamic data into Alpine via `@js()`.

## 7. Multi-Tenant Impact & Security

- Health is **system-level, not tenant-scoped** — no Agency/Client/User relationship touches it. That absence is precisely why it must be admin-gated: there is no scoping to protect it.
- `/admin/monitor` and `/admin/monitor/data` sit behind `auth` + the existing `admin` middleware. Non-admin (including agency managers with `can_manage_users`) → **403, test-covered**. Guest → 302. The JSON endpoint is the one that actually leaks, so it gets its own test, not just the page.
- No new permission key. `EnsureUserIsAdmin` already gates `/admin/system-status`, and a `can_view_system_health` key would be a new escalation surface (`hasPermission` returns true for every key when legacy `is_admin` is set) for a page only admins want. Revisit only if a non-admin ops person appears.
- **No secrets anywhere in the pipeline.** The facts file holds numbers, unit states and version strings — no IPs, no credentials, no `.env` values. It is written by root and read by the app; the app never writes it and never executes anything.
- `scripts/health-facts.sh` is mode 700 root-owned; its output file is 644 (readable by `www-data`) and lives on tmpfs (`/run`) so it cannot accumulate or survive a reboot as stale truth.
- Rate-limit `/admin/monitor/data` (`throttle:60,1`) — it is polled, so it is the one admin route with a real request rate.
- If `link` values ever become dynamic, allow-list them before rendering into `href`.

## 8. Database Changes

**None required.** All state lives in cache markers and files. In particular there is no `health_snapshots` table: history is an explicit non-goal (§1), and adding a write-per-minute table to get graphs nobody asked for is the wrong trade.

One thing to verify during build: D3's `MAX(campaign_data.report_date)`. The table's `UNIQUE(campaign_id, report_date)` index cannot serve a *global* `MAX(report_date)`, so `EXPLAIN` it — if it scans, add a migration for a single-column index on `report_date` (erate-v2 hit exactly this and needed an index migration for its freshness check).

## 9. Testing

Pest v3, `tests/Feature/Health/` + `tests/Unit/Health/`, mirroring erate-v2's shipped suite.

- `SystemHealthServiceTest` — seeded fake state → expected status per check; worst-of rollup; **each dependency throwing → CRIT-unreachable on the right node with the snapshot still built.** Resilience is THE property; test it explicitly rather than assuming it.
- One test per check class, covering threshold boundaries (69/70/71%), STALE paths, and missing-marker paths.
- `MonitorControllerTest` — admin 200 + JSON shape; agency manager 403; guest 302.
- `RunHealthCheckTest` — exit codes for OK/WARN/CRIT under `--fail-on`.
- `SendHealthAlertTest` — transition fires, repeat inside window does not, re-alert after the window does, recovery notice fires, single CRIT observation is suppressed and two are not, unreadable suppression state still alerts.
- `PublicProbeTest` — `Http::fake()` success/fail/timeout → marker state incl. `consec_fails` increment and reset.
- Facts-file tests use fixture JSON; no test may execute a shell command.

## 10. Phasing

| Phase | Content | Why this order |
|---|---|---|
| **0** | External uptime monitor on `/up` | 5 minutes of work, catches total death, needs no code |
| **1** ✅ | Spine (enum, DTOs, service, base check) + facts script + H/D/Q/S/P/B checks + `health:check` CLI | The CLI alone answers today's question over SSH, and everything else builds on the spine |
| **2** ✅ | `health:alert` + mailable | Highest operational value for a solo operator: problems find you |
| **3** ✅ | `/admin/monitor` page + header pill | Glanceable surface once the data is trustworthy |
| **4** ✅ | d1–d4 dependency/version checks + `config/dependency_maintenance.php` + X1/X2 | Reuses the spine; slower-moving signals, so last |

Phases 3 and 4 were built 2026-08-19 to [health-monitor-phases-3-4.md](health-monitor-phases-3-4.md).
Phase 4 added a structural change this document did not anticipate: the
`platform` node is routed **away from `health:alert`** and into a weekly
`deps:digest`, so §3's catalog now spans two delivery channels rather than one.

Phases 1–3 are independently shippable. Phase 4 is where the "is PHP/OS up to date" question gets its standing answer.

## 11. Open Questions (non-blocking — decide during build)

1. **D3 campaign-data freshness.** `CampaignData` arrives by manual upload through `ReportImportService`, not by an automated feed, so "stale" may just mean "nobody uploaded this week" — a check that cries wolf gets ignored, and an ignored check is worse than no check. Default: informational, WARN-only, threshold in config, never CRIT. Alternative worth considering: scope it per-active-campaign instead of globally, so it reads "3 active campaigns have no data in 7 days" — more actionable, slightly more query.
2. **Queue/cache driver on prod.** `config/queue.php` defaults to `database` and `config/cache.php` to `database`, but the prod stack includes Redis. Q1 and the marker store must follow whatever prod actually runs — confirm from the prod `.env` during Phase 1 and pin the marker store explicitly rather than inheriting the default.
3. **Queue worker systemd unit name** — needed verbatim for H5. Confirm on the droplet.
4. **Alert channel.** Email only for Phase 2, or add Telegram/Slack? Email is the smaller build; a chat webhook is more likely to actually be seen at 2am.
5. **Staging.** Same monitor on `msdev.maddata.media`? Default: no — prod only. Staging noise trains you to ignore the alerts that matter.
