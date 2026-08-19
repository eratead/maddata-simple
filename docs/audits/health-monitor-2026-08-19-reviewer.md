# Review: System Health Monitor (Phases 1 & 2)

**Date:** 2026-08-19
**Scope:** `git diff c3d486b..HEAD`, excluding `docs/*` (docs reviewed for accuracy only)
**Spec:** [system-health-monitor.md](../specs/system-health-monitor.md) · **Runbook:** [health-monitor.md](../runbooks/health-monitor.md)
**Status:** ⚠️ Approved with comments — already in production; nothing here is a "roll it back", four things are "fix this week"

This is well above the bar for the codebase. The comments carry the incident that
caused them, the resilience property is tested rather than asserted, and every
threshold really is in config. The findings below are the places where the design's
own stated rules are not fully delivered by the code.

**Note on RBAC scope:** Phase 3 is not built. There are no new routes, controllers,
views or middleware in this diff (`routes/web.php` is untouched), so the multi-tenant
/ RBAC / Blade sections of the review checklist have no surface to evaluate. §7 of the
spec (admin-gating `/admin/monitor` and `/admin/monitor/data`, JSON endpoint tested
separately, `throttle:60,1`) must be re-reviewed when Phase 3 lands.

---

## Verdict on the four invariants

| # | Invariant | Holds? |
|---|---|---|
| 1 | `HealthCheck::run()` never throws | **Yes**, with two cosmetic escapes (L-3) and one theoretical hole in `snapshot()` (L-11) |
| 2 | Snapshot builds with MySQL / Redis / cache / facts file down | **Yes** — traced every branch of `snapshot()`; the lock path is correctly double-covered |
| 3 | Inclusive thresholds, none hardcoded | **Yes** — grepped every check class, found zero literal thresholds |
| 4 | Every result tagged to its real node | **Mostly** — H5 does this properly and is tested; the *failure* fallbacks do not (L-1, L-2) |

---

## Critical Issues (must fix)

### C-1 · `withoutOverlapping()` defaults to a 24-hour mutex on every-minute tasks
`routes/console.php:57, 63, 74`

`CallbackEvent::withoutOverlapping($expiresAt = 1440)` — **minutes**. Ordinary
exceptions do release it (`Event::finish()` → `finally { removeMutex() }`, verified in
vendor), but a fatal error, OOM kill or `kill -9` of `schedule:run` does not. On the
single-core droplet whose memory pressure was the entire justification for moving to
`Schedule::call`, that is the plausible failure — and it silences **`health:alert` for
24 hours** with no symptom, because `health-scheduler-heartbeat` (line 77) has no
mutex and keeps S1 green throughout.

```php
Schedule::call(fn () => Artisan::call('health:refresh-snapshot'))
    ->everyMinute()->name('health-refresh-snapshot')->withoutOverlapping(5);

Schedule::call(fn () => Artisan::call('health:probe'))
    ->everyMinute()->name('health-probe')->withoutOverlapping(5);

Schedule::call(fn () => Artisan::call('health:alert'))
    ->everyFiveMinutes()->name('health-alert')->withoutOverlapping(10);
```

### C-2 · The overlap mutex lives in the store the monitor exists to report on — a Redis outage silences alerting entirely
`routes/console.php:54-74`

`Event::shouldSkipDueToOverlapping()` → `CacheEventMutex::create()` →
`Cache::store($store)->lock(...)->acquire()`. With Redis down that **throws**;
`ScheduleRunCommand::runEvent()` catches it, reports it, and the event is **skipped**.
So for the duration of a Redis outage, `health:refresh-snapshot`, `health:probe` and
`health:alert` all silently stop running.

This defeats spec §2 rule 6 ("fail toward alerting") at a layer the design never
considered, and `SendHealthAlertTest`'s
`it('skips suppression and alerts immediately when its own state is unreadable')`
gives false confidence: it drives the command directly and never exercises the
scheduler that would have prevented the command from starting.

Fix — decouple the scheduler mutex from the monitored store:

```php
// routes/console.php, above the health block
Schedule::useCache(env('SCHEDULE_CACHE_STORE', 'file'));
```

Or simply drop `withoutOverlapping()` from `health-alert`: it is a 5-minute task that
finishes in about a second, and a duplicate email beats no email. Add a test that
resolves `app(Schedule::class)` with a throwing cache store and asserts the alert
event still runs.

### C-3 · `BackupCheck::marker()` is unreadable to `www-data`, which pins B1 to a permanent false CRIT
`app/Services/Health/Checks/BackupCheck.php:222-237`, `config/health.php:59`,
`scripts/backup-production.sh:39`

`is_readable()` uses the **real** uid. `www-data` cannot traverse a 0700 root-owned
directory, so the 0644 marker inside `/var/backups/maddata/` is unreachable no matter
what the file mode is. The backup script's own error message instructs
`chmod 700 $BACKUP_ROOT`, and the runbook's provisioning section never grants
traversal. Because the facts `backups` array *is* populated (written by root),
`localAge()` takes the `$marker === null && $backups !== []` branch and returns
**CRIT "marker missing (7 backup dirs on disk)"** — permanently. A permanently-red
check is how an operator learns to ignore the monitor.

The `health:check 25/26 green, exit 0` evidence in `docs/tasks/todo.md:833` does
**not** clear this: that was run over SSH as `root`, which can read the directory.
Verify the path that actually matters:

```bash
sudo -u www-data test -r /var/backups/maddata/backup-last.json; echo $?   # must be 0
crontab -l -u www-data; grep -r schedule:run /etc/cron*                    # who runs schedule:run?
```

Fix (either):
- Move the marker out of `BACKUP_ROOT`: `HEALTH_BACKUP_MARKER_PATH=/var/lib/maddata/backup-last.json`, dir 0755 root-owned. Keeps `/var/backups` at 0700. Update `config/health.php:59`, `scripts/backup-production.sh:214` and the runbook.
- Or `chmod 755 /var/backups/maddata` (traversal only; the timestamped subdirs stay 0700) and add that line to runbook step 3.

Whichever, add the check to the runbook's provisioning steps — this is exactly the
class of thing the runbook exists to prevent.

### C-4 · A stale or absent backup marker switches B2 off precisely when a truncated backup exists
`app/Services/Health/Checks/BackupCheck.php:200-212`

`completedOnly()` drops every backup dir newer than `marker.ts`, unconditionally. That
is correct for the in-flight case it was written for, and the test at
`BackupCheckTest:122` proves it. The ordering it misses:

`scripts/backup-production.sh` runs under `set -e` and writes the marker **last**
(line 218). Any failure after the dump — the `storage/app` tar, the manifest block's
`df`, the `sha256sum` loop — exits the script before the marker is written, leaving a
**partial directory on disk** and **last night's marker** in place. `completedOnly()`
then filters the partial dir out entirely and B2 reports OK. B1 only turns WARN 26
hours later, and says "age", not "the backup is broken". So the check whose stated
purpose is "mysqldump exiting 0 having written half a database" is silent in the most
likely truncation scenario.

Fix, both halves:

```php
// Only suppress a dir that is plausibly still being written RIGHT NOW.
private function completedOnly(array $backups, ?array $marker): array
{
    if (! isset($marker['ts']) || ! is_numeric($marker['ts'])) {
        return $backups;
    }

    $completedAt = (int) $marker['ts'];
    $inFlight = (int) config('health.backup_in_flight_seconds', 3600);
    $now = now()->getTimestamp();

    return array_values(array_filter($backups, function ($backup) use ($completedAt, $inFlight, $now) {
        $ts = (int) ($backup['ts'] ?? 0);

        // Newer than the marker AND recent enough to still be running: skip it.
        // Newer than the marker but hours old: that is an ABANDONED backup, and
        // it must count — it is the exact thing B2 exists to catch.
        return $ts <= $completedAt || ($now - $ts) > $inFlight;
    }));
}
```

And surface the abandoned-run state in B1, which is currently invisible: if
`backups[0].ts > marker.ts + in_flight_grace`, that is a backup that started and never
finished → CRIT, today, not in 26 hours.

Tests to add: (a) stale marker + truncated dir from tonight → B2 CRIT; (b) marker
older than the newest dir by > 1h → B1 CRIT "backup started but never completed".

---

## Suggestions (recommended improvements)

### M-1 · P1 can stay GREEN for an hour after `health:probe` dies
`app/Services/Health/Checks/EdgeProbeCheck.php:34-78`

`PublicProbe` writes `checked_at` (`PublicProbe.php:57`), the spec names it in the
marker contract (§4), and `EdgeProbeCheck` never reads it. The marker TTL is one hour
(`PublicProbe.php:62`), so between minute 1 and minute 60 after the probe stops
running, P1 reports the last successful latency **as current**. That is spec §2 rule 7
("an unreachable feed is never GREEN") violated by the check the rule was written for.

```php
// config/health.php thresholds
'probe_marker_age' => ['warn' => 300, 'crit' => 900],

// EdgeProbeCheck::publicProbe(), after the is_array() guard
$checkedAt = $marker['checked_at'] ?? null;
$ageThresholds = $this->thresholds('probe_marker_age');

if (is_numeric($checkedAt)
    && (now()->getTimestamp() - (int) $checkedAt) >= ($ageThresholds['warn'] ?? 300)) {
    return new HealthCheckResult(
        key: 'P1', label: 'Public HTTPS probe', status: HealthStatus::STALE, node: 'edge',
        value: 'probe last ran '.HealthFormat::age(now()->getTimestamp() - (int) $checkedAt).' ago',
        threshold: 'health:probe scheduled every minute',
    );
}
```

Test it in `PublicProbeTest` / `EdgeProbeCheckTest` — neither currently asserts
anything about `checked_at`.

### M-2 · A failed recovery email is lost forever
`app/Console/Commands/SendHealthAlert.php:122-129`

`handleRecovery()` discards `send()`'s return value and calls `clearState()`
unconditionally. If SMTP is down at that moment, the next tick sees no state and
prints "Healthy — nothing to report." The recovery notice is gone. That directly
contradicts the class's own rule 4 (`:35-36`) and the runbook's "does not record the
notification — the next tick retries" row (`health-monitor.md:163`). Untested.

```php
if (! $this->send(new HealthAlertMail(...))) {
    $this->warn('Recovery notice could not be sent — will retry on the next run.');

    return self::SUCCESS;   // keep the state so the next tick tries again
}

$this->clearState();
```

Add: `it('retries the recovery notice after a failed send')`.

### M-3 · A cache that fails *soft* suppresses alerting forever
`app/Console/Commands/SendHealthAlert.php:214-225`

`readState()` treats only a **throw** as "unreadable". A store that accepts writes and
returns null on reads is read as "readable, no state", so `$consecutive` is `0 + 1 = 1`
on every tick and the two-observation rule suppresses **every** observation,
permanently and silently. That state is entirely reachable: Redis at `maxmemory` with
`allkeys-lru` evicting the `forever` key immediately, or `HEALTH_MARKER_STORE` pinned
at a store that isn't what you think it is. Fail-toward-alerting only covers the loud
failure mode.

Cheapest robust fix — bound suppression by wall clock instead of trusting the counter
alone:

```php
$episodeAge = isset($state['episode_started_at'])
    ? now()->getTimestamp() - (int) $state['episode_started_at']
    : 0;

// Suppress a single observation, never a problem that has outlived one interval.
if ($stateReadable && $consecutive < 2 && $episodeAge < 600 && ! $this->option('force')) {
```

Alternative: read `ALERT_STATE` back immediately after `writeState()`; if it does not
come back, log and set `state_unreliable` so the next tick skips suppression.

### M-4 · `PDO::ATTR_TIMEOUT` is a *connect* timeout only — D1 can still hang
`config/database.php:85`

For `pdo_mysql`, `ATTR_TIMEOUT` maps to `MYSQL_OPT_CONNECT_TIMEOUT`. A MySQL that
accepts the TCP connection but stalls on the query (lock storm, disk full, swapping)
blocks for the driver default — the exact "hang every admin page instead of degrading
the pill" outcome this connection was created to prevent (spec §2 rule 3).

```php
'options' => extension_loaded('pdo_mysql') ? array_filter([
    PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
    PDO::ATTR_TIMEOUT => 2,
    PDO::MYSQL_ATTR_READ_TIMEOUT => 2,
]) : [],
```

(Watch the `array_filter` — it strips falsy values, so both timeouts must stay > 0.)
No test asserts the `mysql_health` connection exists or carries the timeout; a config
regression would go unnoticed. One assertion in `DataStoreCheckTest` closes that.

### M-5 · Missing index for D3 — spec §8 asked for this explicitly and it did not happen
`app/Services/Health/Checks/DataStoreCheck.php:101`

`SELECT MAX(report_date) FROM campaign_data` runs **every minute** via
`health:refresh-snapshot`. `UNIQUE(campaign_id, report_date)` cannot serve a *global*
`MAX(report_date)` — MySQL falls back to a full scan of the unique index. Spec §8
called this out by name ("`EXPLAIN` it — erate-v2 hit exactly this"), and the diff
contains no migration and no note that it was EXPLAINed.

```php
// database/migrations/xxxx_add_report_date_index_to_campaign_data_table.php
public function up(): void {
    Schema::table('campaign_data', fn (Blueprint $t) =>
        $t->index('report_date', 'campaign_data_report_date_index'));
}
public function down(): void {
    Schema::table('campaign_data', fn (Blueprint $t) =>
        $t->dropIndex('campaign_data_report_date_index'));
}
```

### M-6 · `SendHealthAlertTest` reads the real `/proc/uptime` and can flake in CI
`tests/Feature/Commands/SendHealthAlertTest.php:39-52`

`beforeEach` never calls `fakeUptime()`, so on Linux `config('health.uptime_path')`
stays `/proc/uptime`. On a CI runner that booted less than `boot_grace_seconds` (180)
ago, every test expecting a send fails via the boot-grace early return.
`HostCheckTest:41` already carries a comment showing the author hit exactly this class
of flake; the alert suite did not get the same treatment.

```php
beforeEach(function () {
    fakeUptime(86400);   // well clear of boot grace; individual tests override
    ...
});

afterEach(fn () => fakeUptime(null));   // the temp files are currently never cleaned up
```

### M-7 · Move the scheduler heartbeat to the top of the health block
`routes/console.php:77-79`

`Schedule::call` gives up **memory isolation**, not exception isolation (see the
answers section). An OOM inside `Artisan::call('health:refresh-snapshot')` kills the
whole `schedule:run` process, and since S1's heartbeat is registered **last**, it is
the first casualty. Registering it first costs nothing and means the marker is written
before anything that can kill the process. It also makes the failure legible: S1 green
+ stale snapshot is a different story from S1 red.

---

## Lower-severity findings

**L-1 · Invariant 4 is neither enforced nor tested.** `HealthSnapshot::fromResults()`
(`app/Dtos/HealthSnapshot.php:48-61`) iterates `config('health.nodes')` keys, so a
result tagged to a node that isn't in config is silently dropped from `$nodes` while
still counted by `worstOf` over `$checks`. Symptom: a red pill above a wall of green
node cards, with no error anywhere. Add a test over the *real* registry:
`expect(array_diff(array_unique(array_column($snapshot->toArray()['checks'], 'node')), array_keys(config('health.nodes'))))->toBe([])`.

**L-2 · The double-guard fallback tags to the wrong node.**
`SystemHealthService::runCheck():142-149` hardcodes `node: 'platform'`, so an exploding
`HostCheck` reddens Platform — the exact thing `HealthCheckResult`'s docblock forbids.
Fix: `public static function defaultNode(): string` on `HealthCheck`, overridden per
class, called as `$class::defaultNode()` (no instantiation needed, so it survives a
throwing constructor too).

**L-3 · Two probes escape `guard()`.** `BackupCheck::run():30-31` (`marker()`,
`backupsFromFacts()`) and `HostCheck::units():104-105` run outside any guard. Neither
can throw today — both are internally try/caught — so this is not a live bug. But the
base class contract is "wrap every probe in `guard()`", and a future edit to `marker()`
would land as a `platform` CRIT (L-2) instead of a `backups` one. Move them inside
their closures.

**L-4 · Dead fields in the alert state.** `SendHealthAlert::writeState():231-232` writes
`signature` and `status`; nothing reads them — only `notified_signature` /
`notified_status` are consumed. CLAUDE.md forbids dead code. Drop them, or use them
(they are the natural place to detect "signature changed but no new keys appeared").

**L-5 · `health-facts.sh` CPU parsing: right approach, two residual holes.**
`scripts/health-facts.sh:45-49`. Locating the `id` column by **header name** is exactly
right and does survive the procps column-index shifts it was written for — I traced the
awk against both the `procs -----memory-----` banner line and the `r  b  swpd ...`
header, and `idx` is guarded so an unset index yields `null`, not garbage. Two things
it does not survive:
- **Locale.** procps-ng ships translated headers. A host with `LANG` set in cron's environment yields no literal `id` → `cpu_pct=null` → H2 permanently STALE, silently. Add `export LC_ALL=C` at line 21.
- **`free` column count** (`:53`): `$7` assumes the `available` column (procps ≥ 3.3.10). On an older one `$7` is empty and memory computes to **100%** — a false CRIT rather than an honest null. Guard with `/^Mem:/ && NF >= 7`.

Also `:119` writes `"hostname"` into the facts file; §7 says the file holds "numbers,
unit states and version strings only". Low sensitivity, but it is a stated invariant.

**L-6 · Clock changes are unhandled in the alert state machine.**
`SendHealthAlert::reasonToNotify():156` — an NTP step backwards makes `$since` negative
and the re-alert timer silently stops until the clock catches up. `handleRecovery():119`
already uses `max(0, ...)`; do the same here and treat a negative `$since` as
"re-alert now" (fail toward alerting).

**L-7 · `snapshot()`'s trailing `build()` is outside the try.**
`SystemHealthService.php:65`. The class docblock says "It never throws" (`:18`). Every
path is covered except this one: if `config('health.nodes')` were ever non-iterable,
`fromResults()`'s `foreach` raises a warning that Laravel's `HandleExceptions` converts
into a thrown `ErrorException`, and it escapes. Requires a broken config, so it is
theoretical — but the guarantee is either total or it is not a guarantee. Restructure
so a single outer `try` wraps all three return paths.

**L-8 · Runbook drift.** `docs/runbooks/health-monitor.md:121` says
`cat /run/maddata/backup-last.json`; the marker moved to
`/var/backups/maddata/backup-last.json` (same doc, line 27; `config/health.php:59`).
Line 200 says "markers live in the database cache and survive [a reboot]" while step 1
(line 72) instructs `HEALTH_MARKER_STORE=redis` — with Redis and no persistence they do
not. Pick one and say it; this is the doc an operator reads at 3am.

**L-9 · `EdgeProbeCheck` puts an env-controlled URL into `link`.**
`EdgeProbeCheck.php:61, 76` → `config('health.probe_url')`. §7 says allow-list `link`
values before they reach an `href`. Not an XSS today (env, not user input), but Phase 3
renders it. Either allow-list on render, or point the link at the runbook like every
other check does.

**L-10 · `HealthMarkers::store()` re-resolves on every call.** One snapshot build
resolves `Cache::store()` roughly ten times. Trivially cheap, but a
`private static ?Repository $store` memo (cleared in a test hook) is free and removes
ten container lookups from the hot path.

**L-11 · `SchedulerCheck.php:25`** uses an inline `\App\Dtos\HealthCheckResult` return
type instead of a `use` import. Cosmetic, inconsistent with every sibling.

---

## Untested behaviour worth closing

CLAUDE.md requires a test for every behaviour change. The suite is strong; these are the
holes:

1. **`QueueHeartbeatJob` has no test at all.** Q3 is repeatedly called "the check that matters" and nothing asserts the job writes `QUEUE_BEAT`. One line: `QueueHeartbeatJob::dispatchSync(); expect(HealthMarkers::store()->get(HealthMarkers::QUEUE_BEAT))->toBeInt();`
2. **`routes/console.php` has no test.** The schedule was rewritten from `Schedule::command` to `Schedule::call` — a regression there kills the entire vertical and CI stays green. Assert over `app(Schedule::class)->events()` that the four entries exist with the right expressions and that the two business jobs carry `onSuccess` callbacks.
3. **The `completedOnly` stale-marker ordering** (C-4).
4. **P1 marker staleness** (M-1).
5. **The recovery-send-failure retry** (M-2).
6. **`HealthMarkers::record()` swallowing a write failure** — the "must never take down the job it was recording" promise at `HealthMarkers.php:59-61` is unasserted.
7. **`mysql_health` connection shape** (M-4).
8. **Invariant 4 over the real registry** (L-1).

---

## Answers to the specific questions

**`HealthMarkers::store()` as a static accessor — acceptable, keep it.** It is a
namespaced constant registry with one static accessor wrapping a facade that is itself
already a static accessor. Injecting a `Repository` into six check classes, four
commands and a job buys nothing: the store is process-wide configuration, not a per-call
collaborator, and the constructor noise would be pure ceremony. The Mockery
`Repository` double in `SendHealthAlertTest:195-215` is **not** evidence of bad
coupling — that test needs *one specific key* to throw while every other key still
works, and you would need a partial double for that under constructor injection too.
The only real cost is L-10. Leave the design alone.

**`Schedule::call(fn () => Artisan::call(...))` — the trade is sound, with two caveats
that are now C-1 and C-2.** Verified against vendor:
- **Exception isolation: not lost.** `CallbackEvent::execute()` catches `Throwable` itself and stores it (`CallbackEvent.php:113-126`); `ScheduleRunCommand::runEvent()` catches and reports (`ScheduleRunCommand.php:200-204`). One task throwing does not stop the others.
- **`withoutOverlapping` semantics: preserved.** `CallbackEvent::withoutOverlapping()` works and correctly *requires* `->name()`, which all three set. But the default expiry is 24h (C-1) and the mutex store is the monitored cache (C-2).
- **Timeout protection: there was never any.** Laravel's scheduler has no per-task timeout for either style. Nothing lost.
- **What IS lost: memory isolation.** `Artisan::call()` shares `schedule:run`'s `memory_limit` and its container. An OOM now kills the whole tick — hence M-7, register the S1 heartbeat first. Also, `Artisan::call()`'s exit code is discarded, so `->onSuccess()` / `->onFailure()` are unavailable for these three. Harmless today (all three always return 0), worth remembering if that changes.

**`SendHealthAlert` edge cases** — walked all of them:
- *Clock backwards*: re-alert timer stalls (L-6). *Forwards*: immediate re-alert, harmless.
- *Signature grows and shrinks in one tick*: handled correctly. `newFailingKeys()` uses `array_diff(current, previous)`, so `Q1:crit → Q1:warn + Q2:crit` alerts on Q2 while ignoring Q1's de-escalation. Good.
- *`notified_at` set but signature null*: only reachable from a pre-deploy state record. Degrades to "every current key is new" → immediate alert. Fails toward alerting. Correct.
- *Concurrent runs*: `withoutOverlapping` covers it — subject to C-1/C-2.
- *Cache eviction mid-episode*: state gone → `$consecutive` resets to 1 → one interval of silence, then a duplicate "New problem detected." Acceptable. The dangerous variant is **soft** eviction of every read, which suppresses forever — that is M-3, and `withoutOverlapping` does nothing for it.
- *Is `withoutOverlapping` sufficient?* For concurrency, yes. It is not the mechanism protecting any of the other cases, and C-2 shows it currently makes things worse.

**`HealthSnapshot::fromResults()` reaching into config — defensible, but pay the one-line
tax.** Node ordering is presentation configuration and the DTO is a presentation-shaped
aggregate, so this is not a real layering crime. The cost is concrete though: the DTO
cannot be constructed without a booted container, which is why `HealthSnapshotTest:7`
needs `uses(Tests\TestCase::class)` and why its comment at line 41 hardcodes the node
order — a comment that is **already stale** (it omits `platform`). Fix:

```php
public static function fromResults(array $checks, ?array $nodeLabels = null, ?CarbonImmutable $generatedAt = null): self
{
    $labels = $nodeLabels ?? config('health.nodes', []);
```

`SystemHealthService::build()` passes it explicitly; the DTO becomes pure; the unit test
stops depending on global state.

**`HostFacts` memoization — correct today, fragile by construction.** I traced every
consumer. `SystemHealthService::build():112` flushes first, and it is the only path that
reads facts. `QueueHeartbeatJob` does not touch them. `SendHealthAlert` uses
`withinBootGrace()`, which reads `/proc/uptime` fresh on every call and is not memoized.
Nothing else in a long-running process holds facts. So: **yes, correct.** The fragility
is that the memoization lives in `HostFacts` while the responsibility for invalidating it
lives in `SystemHealthService` — the moment Phase 3's controller or header pill resolves
the singleton directly, it silently gets a copy of unknown age with no error. Make it
self-expiring and the coupling disappears:

```php
private ?int $loadedAt = null;

public function read(): ?array
{
    if ($this->loadedAt !== null && (microtime(true) - $this->loadedAt) < 1.0) {
        return $this->facts;
    }
    $this->loadedAt = microtime(true);
    return $this->facts = $this->load();
}
```

`flush()` then becomes a test-only convenience rather than a load-bearing invariant.

**`BackupCheck::completedOnly()` — yes, there are orderings that hide a truncated
backup.** Full analysis in C-4. Short version: it trusts `marker.ts` as "the last
completed backup", but a `set -e` abort anywhere after the dump leaves a *partial*
directory on disk with the *previous* night's marker still in place, and the filter then
hides exactly the directory B2 was built to inspect. The normal path is safe — I checked
that the backup dir's mtime lands before the marker write (the last operation that
changes the directory's mtime is creating `storage-app.tar.gz` at line 126; the manifest
append at line 168 changes the *file's* mtime, not the directory's), so a healthy run is
never wrongly filtered.

**Test quality — genuinely good, not implementation-restating.** The assertions test
*behaviour at boundaries*, not internals: 69/70/71 and 84/85 on CPU, memory-warns-at-85
vs disk-crits-at-85 in one assertion, `HealthStatus` fixtures driven by data providers,
`it('renders the alert email')` rendering the Blade explicitly because `Mail::fake()`
does not. The regression tests carry their incident in the comment
(`BackupCheckTest:122`, `:160`; `HostCheckTest:96`), which is the single most valuable
thing a test comment can do. The confirmed no-shell rule holds — every fixture goes
through `fakeHostFacts` / `fakeBackupMarker` / `fakeUptime`. Gaps listed above; the
flakiness risk is M-6.

**`health-facts.sh` awk/vmstat — robust across procps versions, fragile across locales.**
See L-5.

---

## CLAUDE.md compliance

| Rule | Verdict |
|---|---|
| `docs/architecture_map.md` updated for every new file | **Pass.** §18 (lines 546-615) covers all 22 new files plus the four modified ones, with accurate one-to-four-sentence responsibilities. Best example of this rule in the repo. |
| Pest tests for every behaviour change | **Mostly pass.** 8 gaps listed above; `routes/console.php` and `QueueHeartbeatJob` are the two that matter. |
| Thresholds in config, never in a check class | **Pass.** Zero literals found. |
| No dead code / debug helpers | **One miss** — L-4. No `dd()`/`dump()` anywhere. |
| Blade/Alpine escaping, Tailwind only | **N/A.** The only new view is an email template, and its inline `<style>` matches the existing `activity_digest.blade.php` precedent. Re-check at Phase 3. |
| Migrations with `down()` | **N/A** (none in this diff) — but M-5 says one is owed. |
| Elegance / simplicity | **Pass.** `guard()` + `evaluateOver`/`evaluateUnder` + `ageResult()` is the right amount of base class: the check classes read like the spec's catalog table. `HealthFormat` is correctly a separate concern. No over-engineering found. |

---

## Praise

- **The resilience property is tested rather than asserted.** `SystemHealthServiceTest` covers cache-store-throws separately for `snapshot()`, `refresh()` and `pillStatus()`, plus a check class that throws. That is the hard part of this design and it got the attention it deserved.
- **Caching the array rather than the DTO** (`HealthSnapshot.php:94-97`), with the reason stated in-file. That decision only pays off during a deploy, which is exactly when nobody would have debugged it.
- **`HealthMarkers` as a constant registry** with the rationale written down — "a typo would show up as a permanently STALE check rather than an error" is precisely right, and it is the reason this vertical will still be debuggable in a year.
- **H5 tags each systemd unit to its own node** and has a test asserting it (`HostCheckTest:74`). This is invariant 4 done properly, and it is the detail that will make the Phase 3 map actually useful.
- **Boot grace read from `/proc/uptime` rather than shelling to `uptime`** — consistent with the zero-grant design instead of making an exception "just this once".
- **`HealthAlertMail` deliberately not `ShouldQueue`**, with the reason. Getting this wrong is the classic failure of homegrown monitors.
- **The comments carry production incidents, not restatements of the code.** `BackupCheckTest:122-126`, `config/health.php:51-58`, `routes/console.php:47-51`, `runbook:90-100`. Someone reading this in six months will understand *why*, which is the only documentation that survives.
