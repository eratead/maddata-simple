# Performance Audit: System Health Monitor

**Date:** 2026-08-19
**Scope:** `git diff c3d486b..HEAD` (excl. `docs/*`)
**Target:** production droplet — 1 vCPU, Ubuntu 24.04, Nginx + PHP-FPM 8.4.24 + MySQL 8 + Redis
**Status:** already live

---

## Summary

**Have you put meaningful recurring load on a small production server? Yes — roughly 1.5–7.5% of your only core, every minute, forever. And about half of it is provably wasted.**

Three things to fix, in order:

1. **`apt-check` in `scripts/health-facts.sh` is the single most expensive item in the entire health budget** (~0.3–1.5s CPU/min, spiking to 3s+, plus ~80MB of page-cache churn parsing the APT cache) — and it populates `pending_security`, which **no check, no threshold and no view reads**. Same for `nginx_version` (a `nginx -v` fork per minute, never read). Delete both. This is a bigger win than everything else in this audit combined and it costs you nothing.
2. **`SELECT MAX(report_date) FROM campaign_data` does a full covering-index scan** — it cannot be optimised away, it runs 1,440×/day, and it scales with the table with no ceiling. At 1M rows it becomes ~0.3–0.7s of CPU per minute all by itself, ~20× the rest of the snapshot build. Two-line index migration fixes it permanently. **The EXPLAIN the spec asked for is still not done — commands are in §2.**
3. **`HEALTH_SNAPSHOT_TTL=30` against a 60-second rebuild cadence guarantees a 30-second cache hole every single minute.** The code comment claiming "a real request is always just a cache read" is false 50% of the time. Harmless today because the UI is not wired up; the moment the header pill ships it becomes 3 queries + a `DELETE` of a 6KB `mediumText` row on half of all page renders. Set `HEALTH_SNAPSHOT_TTL=300`. One env var.

**What is fine and should be left alone:** the `du -sb` loop, `vmstat 15 2`, `Queue::size()`, the unindexed `failed_jobs` scan, the 26 queries/min total, `HostFacts` (confirmed: exactly one file read per build), and the 60-second `refresh-snapshot` cadence. Details and reasoning in §"Explicit Non-Issues" — do not spend time on any of these.

---

## 1. Query count per snapshot build — the actual number

`Cache::store('database')` → every marker read is `SELECT ... FROM cache WHERE key IN (...)`; every write is `INSERT ... ON DUPLICATE KEY UPDATE` (Laravel 12 `DatabaseStore::putMany` uses `upsert`). `QUEUE_CONNECTION=database` → `Queue::size()` is a `COUNT(*)`.

### One `SystemHealthService::refresh()` = **12 statements**

| # | Origin | Statement | Table | Notes |
|---|--------|-----------|-------|-------|
| 1 | `EdgeProbeCheck` P1 | `SELECT` key `health:probe:public` | `cache` | PK lookup |
| 2 | `QueueCheck` Q1 | `SELECT count(*) FROM jobs WHERE queue='default'` | `jobs` | `queue` is indexed |
| 3 | `QueueCheck` Q2 | `SELECT count(*) FROM failed_jobs WHERE failed_at >= ?` | `failed_jobs` | full scan, see §3 |
| 4 | `QueueCheck` Q3 | `SELECT` key `health:queue:beat_at` | `cache` | PK lookup |
| 5 | `SchedulerCheck` S1 | `SELECT` key `health:scheduler:beat_at` | `cache` | PK lookup |
| 6 | `SchedulerCheck` S2a | `SELECT` key `health:campaigns_status_ok_at` | `cache` | PK lookup |
| 7 | `SchedulerCheck` S2b | `SELECT` key `health:activity_digest_ok_at` | `cache` | PK lookup |
| 8 | `DataStoreCheck` D1 | `select 1` | — | **on the `mysql_health` connection → a second PDO connect + auth handshake per build** |
| 9 | `DataStoreCheck` D3 | `select max(report_date) from campaign_data` | `campaign_data` | **full index scan, see §2** |
| 10 | `BackupCheck` B4 | `SELECT` key `health:restore_drill` | `cache` | PK lookup |
| 11 | `refresh()` | `upsert` `health:snapshot` (~6KB) | `cache` | 30s TTL |
| 12 | `refresh()` | `upsert` `health:snapshot:last` (~6KB) | `cache` | `forever` |

Plus, not DB: 1× `is_readable`+`file_get_contents`+`json_decode` of `/run/maddata/host-facts.json`, 1× the same on `/var/backups/maddata/backup-last.json`, 1× Redis connect + `INFO memory`.

**`Cache::lock()` is NOT in this path.** `RefreshHealthSnapshot` calls `refresh()` directly, which bypasses `snapshot()` entirely. The single-flight lock only fires on a cache miss inside `snapshot()` — i.e. from `health:alert` (rare) or a future request. So there are **zero** `cache_locks` queries in the steady-state minute. (This is also a small correctness gap — see 🟢 QW-3.)

### Full per-minute picture (steady state, non-alert minute)

| Process | Work | Queries |
|---------|------|---------|
| `schedule:run` | `health-refresh-snapshot` mutex (`add` = 1 SELECT + 1 INSERT IGNORE, `forget` = 1 DELETE) | 3 |
| | `health:refresh-snapshot` (table above) | 12 |
| | `health-probe` mutex | 3 |
| | `health:probe` (get + put `health:probe:public`) | 2 |
| | `Schedule::job(QueueHeartbeatJob)` → `INSERT INTO jobs` | 1 |
| | `health-scheduler-heartbeat` → `upsert` `health:scheduler:beat_at` | 1 |
| queue worker | reserve (`SELECT … FOR UPDATE` + `UPDATE`), `upsert` `health:queue:beat_at`, `DELETE FROM jobs` | ~4 |
| **Total** | | **~26/min** |

Every 5th minute add `health:alert`: mutex (3) + snapshot read (1, a hit — it runs seconds after the refresh in the same tick) + alert-state read (1) + alert-state `forever` write (1) = **~32 on alert minutes**.

**~26 queries/min = 0.43 queries/sec = ~37,000/day.**

### Verdict: is this a meaningful load?

**On CPU and MySQL throughput: no.** 0.43 q/s against a MySQL 8 that will do low thousands of simple queries/sec even on a shared vCPU is **~0.01–0.05% of capacity**. Ten of those 26 are single-row primary-key lookups on a table with a handful of rows — they are essentially free. Do not restructure anything on account of the query *count*.

**On write volume: mildly, and it is worth one check.** Statements 11 and 12 write the same ~6KB blob to two rows every 60 seconds. That is 12KB/min → ~17MB/day of row writes. MySQL 8 ships with `log_bin=ON` and `binlog_row_image=FULL` by default, so each of those `UPDATE`s writes a before-image *and* an after-image: **~24KB/min → ~34MB/day of binlog attributable to health snapshots, ~1GB at the default 30-day `binlog_expire_logs_seconds`.** On a droplet whose disk you are monitoring with this very tool, that deserves a `SHOW VARIABLES LIKE 'log_bin'` (see 🟡 M-2).

**The real cost is not the queries.** It is (a) `apt-check`, which is not a query at all, and (b) statement 9, which is one query that scans an unbounded number of rows. Everything else is noise.

---

## 2. `MAX(campaign_data.report_date)` — yes, this is a real problem

### Does it scan?

**Yes — a full covering-index scan.** Confirmed from `database/migrations/2025_05_12_060931_create_campaign_data_table.php`:

```php
$table->id();                                    // PRIMARY (id)
$table->foreignId('campaign_id')->constrained(); // FK, served by the unique below
$table->date('report_date');
$table->unique(['campaign_id', 'report_date']);  // the only other index
```

Available indexes: `PRIMARY(id)` and `campaign_data_campaign_id_report_date_unique(campaign_id, report_date)`.

`report_date` is not the leftmost column of any index, so MySQL **cannot** use the `MIN/MAX` optimisation (`Extra: Select tables optimized away`), which requires a single index-tree descent to the last key and needs the aggregated column to be an index prefix. MySQL 8's loose index skip scan does not apply to aggregates either — it only kicks in for range predicates.

What it *does* do is better than a clustered table scan: the unique index **covers** the query, so the optimizer picks `type: index, key: campaign_data_campaign_id_report_date_unique, Extra: Using index` and walks the narrow secondary index instead of the wide row data. That is the good news. The bad news is it is still O(n), it runs 1,440 times a day, and nothing bounds n.

### Cost as a function of table size

Secondary index entry ≈ 8B (`campaign_id`) + 3B (`report_date`) + 8B (PK) + record overhead ≈ ~30B effective at ~85% page fill → **~500 entries per 16KB page**.

| Rows | Index size | Pages touched | Est. scan time (shared vCPU, warm) | % of one core, per minute |
|------|-----------|---------------|-----------------------------------|---------------------------|
| 100K | ~3 MB | ~200 | 35–70 ms | 0.06–0.12% |
| 500K | ~16 MB | ~1,000 | 170–330 ms | 0.3–0.6% |
| 1M | ~32 MB | ~2,000 | 330–670 ms | **0.6–1.1%** |
| 5M | ~160 MB | ~10,000 | 1.7–3.5 s | **3–6%** |

Two things make this worse than the latency column suggests on *this* box:

1. **Buffer-pool pollution.** `innodb_buffer_pool_size` defaults to 128MB and is very likely untuned. Sweeping 16–32MB of index pages through the LRU **every 60 seconds** evicts hot `campaigns`, `clients`, `users` and `sessions` pages that real requests need. The damage lands on user-facing latency, not on the health check.
2. **It is unbounded.** `campaign_data` is append-only daily performance data with no pruning. Whatever it costs today, it costs strictly more every day.

### Do the EXPLAIN the spec asked for

```sql
SELECT COUNT(*) FROM campaign_data;

EXPLAIN SELECT MAX(report_date) FROM campaign_data;
-- expect NOW:   type=index, key=campaign_data_campaign_id_report_date_unique,
--               rows≈<table size>, Extra="Using index"
-- expect AFTER: Extra="Select tables optimized away", rows=NULL

SET profiling = 1;
SELECT MAX(report_date) FROM campaign_data;
SHOW PROFILES;
```

### Fix

One index. It is ~3–5MB per million rows, it makes the query O(1) forever, and it removes the buffer-pool sweep entirely. See §Index Recommendations for the migration.

> I checked whether this index earns its keep elsewhere: it does not, much. `CampaignController.php:103` (`whereIn(campaign_id) + where(report_date)`) and the `ReportApiController` correlated subquery are both already served perfectly by the existing composite. So this index exists to serve D3. **Do it anyway** — the cost is a few MB and one migration, and the alternative is an O(n) scan on a per-minute schedule with no upper bound.

---

## 3. `failed_jobs.failed_at` — not indexed, and it does not matter

`failed_jobs` has exactly `PRIMARY(id)` and `UNIQUE(uuid)`. `WHERE failed_at >= ?` therefore full-scans the clustered index.

**At realistic sizes this is a non-issue and you should not index it.** A healthy production `failed_jobs` holds 0–100 rows ever. Scanning 100 rows costs microseconds, and below roughly 10,000 rows MySQL would likely ignore the index anyway — a full scan of a small table beats an index lookup plus row fetches.

The one thing that *is* worth fixing is not the index, it is the absence of pruning. `queue:prune-failed` is not scheduled anywhere in `routes/console.php`. If the queue ever goes bad, `failed_jobs` grows without limit and Q2 gets progressively slower **precisely when the queue is unhealthy** — the check degrades in correlation with the thing it measures. Note also that the table carries two `longText` columns (`payload`, `exception`), so each row is wide and a scan is not as cheap per-row as the row count suggests.

**Fix: schedule the prune, skip the index.** One line, obviously correct, and it caps the problem instead of papering over it.

---

## 4. Per-minute scheduler cost, now that health runs in-process

### What changed

`routes/console.php` now uses `Schedule::call(fn () => Artisan::call('health:…'))` instead of `Schedule::command('health:…')`. `Schedule::command()` spawns a fresh `php artisan` per task; `Schedule::call()` runs in the already-booted `schedule:run` process. That removed **three** PHP CLI boots per minute.

### Why a PHP CLI boot is so expensive here

`opcache.enable_cli` is **0 by default**. Every `php artisan` invocation therefore re-parses and re-compiles ~1,200–1,800 PHP files from scratch, with no shared opcode cache to fall back on. That, not the application work, is what produced the earlier ~5s measurement: four boots × ~1.2s.

### Estimated cost now

| Item | CPU per minute |
|------|----------------|
| `schedule:run` PHP CLI boot (unavoidable, predates this feature) | **0.3 – 1.2 s** |
| `Artisan::call()` × 2 (command resolution + run, in-process) | ~0.02 s |
| `health:probe` — TLS handshake + a full FPM Laravel boot for `/up` **on the same core** | **0.08 – 0.15 s** |
| 22 queries | 0.01 – 0.03 s |
| 2× PDO connect (default + `mysql_health`), 1× Redis connect + `INFO` | ~0.01 s |
| Snapshot serialize (26 checks → ~6KB) ×2 | < 0.005 s |
| Queue worker executing the heartbeat job | ~0.01 s |
| **Total** | **~0.45 – 1.4 s / 60 s = 0.8 – 2.3% of the core** |

Down from ~5s (~8%). **The change already bought back roughly 70%, and it was the right change.**

### Is further reduction warranted?

**Dropping `health:refresh-snapshot` to every 2 minutes: no. Do not do this.** The `schedule:run` boot happens every minute regardless — it is the boot you are paying for, not the task. The refresh itself is ~15–25ms of in-process work. Halving its frequency saves ~12ms/min (0.02% of a core) and costs you a doubling of health-data staleness. That is textbook premature optimization. Leave it at 60 seconds.

**Dropping or slowing `health:probe`: yes, halve it.** This is the largest remaining health-attributable item, and the argument is about resolution, not cost:

- `health:alert` runs **every 5 minutes** and requires **2 consecutive non-OK observations** before it emails. So the alert path's real resolution is ~10 minutes.
- P1 needs **2 consecutive probe failures** (`probe_consec_fails: crit => 2`) to go CRIT.
- At a 60-second cadence you take 5 probe samples per alert interval. **Four of every five are never observed by anything.**
- At a 120-second cadence you still take 2–3 samples per alert interval — enough for `consec_fails` to reach 2 within the window.

Worst-case detection latency: today ~2 min (probe) + up to 10 min (alert) ≈ 12 min. At 2-minute cadence: ~4 min + up to 10 min ≈ 14 min. **You pay 2 minutes of worst-case latency on a 12-minute path to halve the most expensive health task.** Take it.

### The lever that actually matters

The dominant cost is the CLI boot, and it is not the health monitor's fault. If you want a real 2–5× on the scheduler tick (and on every `php artisan` you ever run):

```ini
; /etc/php/8.4/cli/conf.d/99-opcache-cli.ini
opcache.enable_cli=1
opcache.file_cache=/var/cache/php-opcache
opcache.file_cache_only=0
opcache.validate_timestamps=1
```
```bash
mkdir -p /var/cache/php-opcache && chown www-data:www-data /var/cache/php-opcache
```

Plus `php artisan config:cache` on deploy. **I verified this is safe: there are zero `env()` calls anywhere under `app/`** — every `env()` in this codebase is inside a `config/*.php` file, which is the only place it is legal once config is cached.

Two caveats:
- The current production deploy runbook runs `config:clear` and **never** `config:cache`, so production is booting uncached config on every request *and* every scheduler tick. Adding `config:cache` to the deploy is a bigger win than anything else in this document, and it is app-wide.
- `php artisan route:cache` will **fail** — `routes/web.php` contains 12 route closures, which cannot be serialized. That is a separate optional cleanup, not a blocker for `config:cache`.

---

## 5. `scripts/health-facts.sh` — the `du` is free, the thing next to it is not

### The `du -sb` question: definitively a non-issue

`du` cost scales with **inode count, not bytes**. The 1.6GB is irrelevant. From `scripts/backup-production.sh`, each nightly backup directory contains exactly four files:

```
manifest.txt   db.sql.gz   env.tar.gz   storage-app.tar.gz
```

So the loop is 7 × (`opendir` + `readdir` + ~5 `stat`) ≈ **~40 syscalls, sub-millisecond, page-cache resident**. It also does not touch file *contents*, so it causes zero page-cache pressure.

**Leave it exactly as it is.** Do not replace it with `stat`, do not cache it, do not move it out of the per-minute path. There is nothing to win.

### `vmstat 15 2`: also a non-issue

The process lives ~15 seconds but **sleeps for essentially all of it**. It reads `/proc/stat` twice and idles in between. Cost: ~1.5MB RSS and two procfile reads. The 15-second sample is the right call for a single-core box — a 1-second sample would be pure noise — and the `sleep 25` crontab offset correctly moves the sample away from the top of the minute where `schedule:run` boots PHP. **This is good engineering. Leave it.**

### The actual problem: `apt-check`

```bash
pending_security=$(/usr/lib/update-notifier/apt-check 2>&1 | cut -d';' -f2)
```

`apt-check` opens and parses the full APT package cache (`pkgcache.bin` + `srcpkgcache.bin`, ~40–80MB) to compute two integers. Typical cost **0.3–1.5s of CPU, spiking to 3s+** when those files have been evicted — which on a 1–2GB droplet running MySQL, Redis, PHP-FPM and Nginx is likely between runs.

**And nothing reads the result.** I grepped the entire `app/` tree: `pending_security` appears in the JSON output and in **zero** check classes, **zero** thresholds in `config/health.php`, and **zero** views. `HostCheck::run()` emits H1–H6 and none of them touch it. Same story for `nginx_version` (which costs a `nginx -v` fork every minute).

**This is the single most expensive item in the whole health monitor, it runs 1,440 times a day, and its output is discarded.**

One mitigating note: because `apt-check` runs *after* the 15-second `vmstat` sample (line 71 vs line 45), it does not contaminate the CPU reading it would otherwise inflate. Accidental, but fortunate.

### Process-spawn inventory per minute

`vmstat`, `awk`×3, `free`, `df`, `systemctl is-active`×6, `apt-check`, `openssl`, `date`×2, `find`, `sort`, `head`, `du`×7, `cut`×8, `hostname`, `nginx`, `sed`, `cat`, `chmod`, `mv` ≈ **~35 fork+exec per minute**. At ~1–3ms each that is ~50–100ms — acceptable, and dwarfed by `apt-check`.

**Total `health-facts.sh` cost: ~0.4–3.2s CPU/min (0.7–5.3% of the core), of which 80–90% is `apt-check`.**

That makes the shell script **more expensive than the entire Laravel scheduler tick**, for a field nobody consumes. This is the headline finding.

### Missing `flock`

Worst case runtime: 25s (`sleep`) + 15s (`vmstat`) + apt-check + 6× `systemctl` + `openssl` + `du` ≈ **42–50s**, and `apt-check` can spike past that during an `apt update` or on a slow disk. There is no overlap guard, so a slow run means two concurrent instances, two `vmstat` samples and two `apt-check`s fighting over one core. Wrap it in `flock -n`.

---

## 6. The 30s TTL vs 60s cadence window — real, and it will bite

**The window is real and it is exactly 30 seconds wide, every minute, 50% duty cycle.** `health:refresh-snapshot` writes `health:snapshot` with a 30-second TTL at T+0. From T+30 to T+60 that key is expired.

It is **harmless today** — there is no `/admin/monitor` route and no controller, `pillStatus()` has no caller, so nothing reads the snapshot from a request. But `HealthSnapshot`'s docblock documents a `/admin/monitor/data` endpoint and an Alpine poller, and `pillStatus()` is clearly built for a header pill on every authenticated page. The moment that lands:

**A request landing in the hole, per `DatabaseStore` (Laravel 12) semantics:**

1. `cached(SNAPSHOT)` → `many()` → 1 `SELECT`, row found but `expiration <= now` → partitioned as expired
2. → `forgetManyIfExpired()` → **1 `DELETE ... WHERE key IN (2 keys) AND expiration <= ?`** — a real write, removing a ~6KB `mediumText` row that the next refresh immediately re-inserts
3. → returns null → `store()->lock()` → `DatabaseLock::acquire()` = 1 `INSERT IGNORE` (+2% lottery `DELETE` on `cache_locks`)
4. → lock acquired → **full `refresh()` inline: 12 statements + a fresh `mysql_health` PDO connect + a Redis connect + the `campaign_data` index scan**, ~20–40ms **inside a user request**
5. → `lock->release()` = 1 `SELECT` (owner check) + 1 `DELETE`

**~18 statements and a full rebuild on a user request, once a minute, forever.** Concurrent requests in the same window are protected — they fall through to `SNAPSHOT_LAST`, which is `forever` and always present. So the stampede guard works. But the *first* request every minute pays.

For the header pill specifically, even the non-rebuild case is bad: `pillStatus()` in the hole is `SELECT` (expired) + `DELETE` + `SELECT` (`SNAPSHOT_LAST`) = **3 statements and a write on every page render**, half the time.

### The TTL is doing no safety work

The obvious objection is "the TTL protects me from serving a stale snapshot if the rebuilder dies." It does not, because you already detect that three better ways:

- `generated_at` is in the payload — the UI can render "as of 4m ago" directly
- **S1** (`SCHEDULER_BEAT`) goes WARN at 5 min if `schedule:run` stops
- **H1** (facts age) goes WARN at 3 min / CRIT at 10 min if the host cron stops

The TTL's only observable effect is to manufacture a rebuild hole.

**Fix: `HEALTH_SNAPSHOT_TTL=300`.** One env var. The key then always outlives the 60-second rebuild, `cached()` is a single indexed `SELECT` that always hits, the `DELETE` churn disappears, and no request ever rebuilds inline.

---

## 7. N+1s, redundant reads, serialization

### `HostFacts` — confirmed correct, one read per build ✅

Verified end to end:

- `AppServiceProvider::register()` → `$this->app->singleton(HostFacts::class)`
- `SystemHealthService::build()` calls `$this->facts->flush()` **once**, at the top
- `HostCheck`, `EdgeProbeCheck` and `BackupCheck` all constructor-inject `HostFacts` and therefore all receive **the same instance**
- `read()` guards on `$this->loaded`, so `get()` is memoized after the first call

**Exactly one `is_readable` + one `file_get_contents` + one `json_decode` per snapshot build.** No redundancy. This was done right.

`BackupCheck::marker()` is likewise called once at the top of `run()` and the result is threaded into `localAge()` and `offsite()` as a parameter — one read of `/var/backups/maddata/backup-last.json` per build. Also right.

### The one N+1 in the vertical

Six separate single-key cache reads, each its own `SELECT` round trip:

| Check | Key |
|-------|-----|
| P1 | `health:probe:public` |
| Q3 | `health:queue:beat_at` |
| S1 | `health:scheduler:beat_at` |
| S2a | `health:campaigns_status_ok_at` |
| S2b | `health:activity_digest_ok_at` |
| B4 | `health:restore_drill` |

`DatabaseStore::many()` already takes an array and builds a single `whereIn` — the store is *designed* for this and the code just is not using it. One pre-pass `$this->store()->many([...])` collapses 6 statements into 1, saving 5 statements/min (~7,200/day) and 5 round trips.

**Do this because it is a ~10-line change, not because it is urgent.** In absolute terms it saves maybe 2ms/min.

### `bootedSecondsAgo()` is not memoized

`HostFacts::bootedSecondsAgo()` re-reads `/proc/uptime` on every call, unlike `read()`. On the boot-grace path `HostCheck::factsAge()` calls `withinBootGrace()` (read 1) and then `bootedSecondsAgo()` again for the message (read 2). Two reads of a 30-byte procfile, only during the 3-minute post-boot window. Cosmetic; memoize it for symmetry when you are next in the file.

### Serialization

The snapshot is ~26 `HealthCheckResult`s → ~6KB serialized. `serialize()`/`unserialize()` on that is well under 1ms. **CPU is not the issue.** The issue is that you write it **twice** (`SNAPSHOT` then `SNAPSHOT_LAST`) every 60 seconds, unconditionally, even when nothing has changed — which is what drives the binlog volume in §1.

Storing the array rather than the DTO (per the `fromArray()` docblock — "a serialized DTO written before a deploy that changes this class would fatal on unserialize") is the correct call and costs nothing. Good decision, keep it.

### `mysql_health` connection

Each build opens a **second PDO connection** (`config/database.php:68`) to run `select 1`. On `127.0.0.1` that is a TCP connect + MySQL auth handshake, ~1–3ms, once a minute. **Non-issue at this frequency** — but it would be a per-request cost if the monitor page ever rebuilds inline, which is another reason to close the TTL hole in §6.

Separately, and more of a correctness note: `PDO::ATTR_TIMEOUT => 2` maps to `MYSQL_OPT_CONNECT_TIMEOUT` — it bounds the **connect**, not the query. It protects you against an unreachable MySQL (dropped SYNs), which is the common case, but **not** against a MySQL that accepts connections and then hangs. The docblock's claim that "without that cap, a MySQL outage would hang every admin page" is therefore only half true. If you want the full guarantee, add an init command:

```php
PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION max_execution_time = 2000',
```

`max_execution_time` applies to read-only `SELECT`s, which is exactly what D1 issues.

---

## 🔴 Critical Issues

| Issue | Location | Impact | Fix |
|-------|----------|--------|-----|
| `apt-check` runs every minute to populate `pending_security`, which no check, threshold or view reads | `scripts/health-facts.sh:71-75` | **0.3–1.5s CPU/min (spikes 3s+), ~80MB page-cache churn** — the largest single item in the health budget, 100% wasted. ~0.5–2.5% of your only core, permanently | Delete the block and the JSON field. If you want the metric later, run `apt-check` hourly from a separate cron into its own file |
| `SELECT MAX(report_date)` cannot use an index — full covering-index scan, 1,440×/day, unbounded growth | `app/Services/Health/Checks/DataStoreCheck.php:101` | 35–70ms @100K rows → **0.3–0.7s @1M rows**; sweeps 16–32MB through a likely-128MB buffer pool every minute, evicting hot pages that real requests need | Add `INDEX(report_date)` — see §Index Recommendations. Converts to `Select tables optimized away`. **Run the EXPLAIN in §2 first — it was specced and never done** |

## 🟠 High Priority

| Issue | Location | Impact | Fix |
|-------|----------|--------|-----|
| `snapshot_ttl` (30s) < rebuild cadence (60s) → guaranteed 30s cache hole every minute | `config/health.php:90` | Harmless today (no UI). Once the pill/monitor ships: ~18 statements + a full inline rebuild on one request/min, and 3 statements + a `DELETE` of a 6KB row on **half of all page renders** | `HEALTH_SNAPSHOT_TTL=300`. Freshness is already covered by `generated_at`, S1 and H1 |
| `CACHE_STORE` unset → app cache, sessions **and** scheduler mutexes all on MySQL, while a healthy Redis sits idle | prod `.env` (falls back to `config/cache.php:18`); `config/session.php:21` | App-wide, not health-specific. Moves 9 mutex queries/min plus every `Cache::` call and every session read/write off MySQL | Set `CACHE_STORE=redis`. **Keep `HEALTH_MARKER_STORE=database`** — see §Caching Recommendations for why that split is correct, not an oversight |
| `nginx -v` forked every minute for `nginx_version`, never read by anything | `scripts/health-facts.sh:109` | Small (~10ms/min) but pure waste, same class as `apt-check` | Delete, or hardcode from the deploy |

## 🟡 Medium Priority

| Issue | Location | Impact | Fix |
|-------|----------|--------|-----|
| `health:probe` runs 5× more often than anything that observes it | `routes/console.php:60-63` | ~80–150ms CPU/min for TLS + a full FPM Laravel boot; 4 of every 5 samples are never read, because `health:alert` is 5-minutely with 2-observation suppression | `->everyTwoMinutes()`. Costs ~2 min on a 12-min worst-case detection path; halves the most expensive health task |
| Two ~6KB cache upserts/min → binlog growth | `SystemHealthService::refresh()` (`app/Services/Health/SystemHealthService.php:78-83`) | With MySQL 8 defaults (`log_bin=ON`, `binlog_row_image=FULL`): ~34MB/day, **~1GB at the 30-day default retention**, on the disk you are monitoring | Verify `SHOW VARIABLES LIKE 'log_bin'` / `binlog_expire_logs_seconds`. Then: write `SNAPSHOT_LAST` only every 5th refresh or on status change; consider `binlog_expire_logs_seconds=604800` |
| `health-facts.sh` has no overlap guard; worst case ~42–50s against a 60s cron | `scripts/health-facts.sh` | A slow `apt-check` produces two concurrent instances, two `vmstat` samples and two apt-cache parses on one core | `flock -n /run/maddata/health-facts.lock` wrapper in the crontab line |
| `queue:prune-failed` is never scheduled | `routes/console.php` | Q2's `failed_jobs` scan degrades in proportion to queue sickness — the check gets slower exactly when it matters | `Schedule::command('queue:prune-failed --hours=168')->weekly();` |
| Production deploy runs `config:clear` and never `config:cache`; `opcache.enable_cli=0` | deploy runbook; PHP CLI ini | Adds ~20–40ms to **every FPM request** and is the main reason a `php artisan` boot costs ~1s. Dominates the per-minute scheduler cost | Add `php artisan config:cache` to deploy (**verified safe — zero `env()` calls outside `config/`**) and enable CLI opcache with `opcache.file_cache`. Note `route:cache` will fail: 12 closures in `routes/web.php` |

## 🟢 Quick Wins

| Issue | Location | Fix |
|-------|----------|-----|
| **QW-1** 6 separate single-key cache `SELECT`s per build — the only N+1 in the vertical | `EdgeProbeCheck`, `QueueCheck` Q3, `SchedulerCheck` ×3, `BackupCheck` B4 | Pre-pass `HealthMarkers::store()->many([...])` into an array the checks read from. `DatabaseStore::many()` already builds one `whereIn`. 6 statements → 1 |
| **QW-2** `bootedSecondsAgo()` re-reads `/proc/uptime` on every call | `app/Services/Health/HostFacts.php:50` | Memoize like `read()` does. Two reads → one on the boot-grace path |
| **QW-3** `refresh()` bypasses the single-flight lock that `snapshot()` uses | `app/Services/Health/SystemHealthService.php:72` | `withoutOverlapping` only guards refresh-vs-refresh; a scheduled rebuild and an inline request rebuild can still run concurrently. Mostly moot once the TTL is raised, but worth a comment or routing `refresh()` through the lock |
| **QW-4** `PDO::ATTR_TIMEOUT` is connect-only, not a query timeout | `config/database.php:85` | Add `PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION max_execution_time = 2000'` so D1 is bounded against a wedged-but-accepting MySQL |

---

## Explicit Non-Issues — do not spend time on these

| Thing | Why it is fine |
|-------|----------------|
| `du -sb` on 7 backup dirs, 1.6GB | `du` cost scales with **inodes, not bytes**. Each backup dir holds exactly 4 files (`manifest.txt`, `db.sql.gz`, `env.tar.gz`, `storage-app.tar.gz` — confirmed in `scripts/backup-production.sh`). **~40 syscalls, sub-millisecond, no content reads, zero page-cache pressure.** Leave it alone |
| `vmstat 15 2` living ~15s/min | It **sleeps** the whole time. ~0 CPU, ~1.5MB RSS, two `/proc/stat` reads. The 15s window is the correct call on a single core — a 1s sample would be noise. The `sleep 25` offset is also correct. Good engineering, keep it |
| `Queue::size()` per build | `jobs.queue` is indexed; steady-state depth is 0–1 rows. Microseconds |
| Unindexed `failed_jobs.failed_at` scan | 0–100 rows in a healthy system; below ~10K rows a full scan beats an index. **Adding the index would be premature.** Schedule the prune instead |
| ~26 queries/min total | 0.43 q/s ≈ 0.01–0.05% of MySQL's capacity even on a shared vCPU. The *count* is irrelevant; only statement #9 and `apt-check` matter |
| Redis connect + `INFO memory` per build | One connect, one command, ~1–2ms/min |
| `HostFacts` file reads | **Verified: exactly one read per snapshot build.** Singleton + `flush()`-at-build-start + `$loaded` memo, all three checks share the instance. No redundancy |
| Snapshot serialization CPU | ~6KB, well under 1ms. Storing the array rather than the DTO is the right deploy-safety call |
| `health:refresh-snapshot` at 60s | **Do not drop this to 2 minutes.** The `schedule:run` boot happens every minute anyway; the refresh itself is ~15–25ms. You would save ~12ms/min and double your staleness |
| 26 checks per snapshot | The count is not the cost. 20 of them are pure array lookups against the already-loaded facts file |

---

## Caching Recommendations

| Data | Current | Recommended | Expected gain |
|------|---------|-------------|---------------|
| `health:snapshot` | database, TTL 30s vs 60s cadence | database, **TTL 300s** | Eliminates the 30s hole: no inline rebuild in the request path, no `DELETE` churn on a 6KB row, pill reads become one indexed `SELECT` that always hits |
| `health:snapshot:last` | `forever`, rewritten unconditionally every 60s | Write every 5th refresh, or only when `overall`/`signature` changes | Halves health-attributable binlog volume (~34MB/day → ~17MB/day) |
| Health markers (6 keys) | 6 individual `get()`s | One `many()` per build | 6 statements → 1 (QW-1) |
| **Application cache + sessions** | **database** (`CACHE_STORE` unset) | **`CACHE_STORE=redis`** | Moves all app cache, all session reads/writes and the 9 scheduler-mutex queries/min off MySQL onto the Redis you are already running and paying for |
| **Health markers store** | `HEALTH_MARKER_STORE=database` | **Keep `database`. Do not change this.** | This split is correct and deliberate, not an oversight. If markers lived in Redis and Redis died, every age check would go STALE at once and you could not distinguish "Redis is down" from "the scheduler is down" — the monitor would lose exactly the discrimination it exists to provide. Keeping markers in MySQL isolates the monitor from a store it monitors, and 10 marker queries/min is genuinely nothing |

> Side observation: with `CACHE_STORE` unset, **the only consumer of Redis on this box is check D2, which measures Redis memory.** You are monitoring the memory pressure of a service nothing uses, while paying MySQL for the cache work Redis should be doing.

---

## Index Recommendations

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Health check D3 runs `SELECT MAX(report_date) FROM campaign_data` once a
     * minute. `report_date` is not the leftmost column of any existing index
     * (the only candidate is UNIQUE(campaign_id, report_date)), so MySQL cannot
     * apply the MIN/MAX optimisation and falls back to a full covering scan of
     * that index — O(n), 1,440 times a day, on a table that only ever grows.
     *
     * On a 1-core droplet with a default 128MB buffer pool, sweeping the whole
     * index every 60 seconds evicts hot pages that real requests need; that,
     * not the query's own latency, is the cost that matters.
     *
     * With this index the optimiser reports "Select tables optimized away" —
     * a single index-tree descent, zero rows read, constant time forever.
     */
    public function up(): void
    {
        Schema::table('campaign_data', function (Blueprint $table) {
            $table->index('report_date', 'campaign_data_report_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_data', function (Blueprint $table) {
            $table->dropIndex('campaign_data_report_date_index');
        });
    }
};
```

Raw SQL equivalent, and the verification either side of it:

```sql
-- BEFORE
EXPLAIN SELECT MAX(report_date) FROM campaign_data;
-- expect: type=index, key=campaign_data_campaign_id_report_date_unique, Extra="Using index"

ALTER TABLE campaign_data ADD INDEX campaign_data_report_date_index (report_date);

-- AFTER
EXPLAIN SELECT MAX(report_date) FROM campaign_data;
-- expect: Extra="Select tables optimized away", rows=NULL
```

**Deliberately NOT recommended:**

```sql
-- DO NOT ADD. failed_jobs holds 0-100 rows in a healthy system; below ~10k rows
-- a full scan beats an index lookup and MySQL would likely ignore this anyway.
-- Schedule `queue:prune-failed --hours=168` weekly instead.
ALTER TABLE failed_jobs ADD INDEX failed_jobs_failed_at_index (failed_at);
```

---

## Bottom Line

| | CPU per minute | % of the single core |
|---|---|---|
| Before the in-process change (4 PHP boots) | ~5 s | ~8% |
| **Today** | ~0.9 – 4.6 s | **~1.5 – 7.5%** |
| After 🔴 + 🟠 (drop `apt-check`/`nginx -v`, index `report_date`, `everyTwoMinutes` probe, TTL 300) | ~0.4 – 1.3 s | **~0.7 – 2.2%** |
| After `config:cache` + CLI opcache as well | ~0.2 – 0.6 s | **~0.3 – 1.0%** |

Roughly all of the residual is the `schedule:run` PHP CLI boot, which predates this feature and which you cannot remove without abandoning cron-driven Laravel scheduling.

**So: yes, you put meaningful recurring load on a small box — and the majority of it turned out to be `apt-check` collecting a number nobody reads, not anything in the PHP.** The in-process `Artisan::call` change was correct and already recovered ~70%. The remaining fixes are four config/env changes, one index and one deleted shell block. Nothing in the architecture needs to change.
