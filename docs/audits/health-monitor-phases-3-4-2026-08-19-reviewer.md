# Code Review — Health Monitor Phases 3 & 4

**Date:** 2026-08-19 · **Scope:** `a6ba5eb..f6a9ff3` · **Verdict:** approved with comments; one live issue worth fixing immediately, no rollback warranted.

## Critical

**C1 · The alert split silenced the node that catches *unexpected* failures.**
`SystemHealthService::runCheck()`'s catch-all tagged its fallback CRIT to `node: 'platform'`, which Phase 4 made non-alerting. Three consequences, worst first:

1. **A real outage can produce a "recovered" email.** With an episode open because `B1` is CRIT, if `BackupCheck::run()` then throws, `B1` disappears from the snapshot entirely and the only result is a `platform` CRIT that `alertable()` filters out. `alertStatus()` → OK → `handleRecovery()` → *"Recovered - all systems go"* and `clearState()`. The operator is told the backup problem healed; in fact the check that reports it died.
2. **The affected node vanishes from the map**, because `fromResults()` skips nodes with no checks. A thrown `HostCheck` removes the entire Host column.
3. It contradicts inherited rule 5 — a check's own failure must be tagged to its real node.

Reachable three ways: container resolution outside any guard, `HostCheck::units()`'s unguarded `$meta['node']` access, and `BackupCheck::run()`'s unguarded marker-unreadable branch. A fourth, quieter hole in the same method: a registry entry that resolves but is not a `HealthCheck` yields zero results, zero logs, zero signal.

## Answers to the specific questions

**Q1 — the split, beyond C1: correct.** No other input was found where a real outage fails to alert. `signature()`, `writeState()`, `newFailingKeys()` and `reasonToNotify()` all read `alertable()`/`alertStatus()` consistently — no half-application. Pre-change persisted state migrates cleanly, because every pre-Phase-4 check lives on a non-excluded node, so old `notified_signature` values are byte-identical under the new semantics. *Latent footgun:* **adding** a node to `alert_excluded_nodes` while an episode is open reads as a recovery and emails "Everything recovered" for problems still failing. Emptying it is always safe.

**Q2 — `snapshot() = cached() ?? refreshOnDemand()`: equivalent.** All four paths traced. The omitted `forget(SNAPSHOT)` is genuinely redundant. One deviation from the spec: the contended path omits the `SNAPSHOT` re-read, which matters only in the narrow window where `put(SNAPSHOT)` succeeded and `forever(SNAPSHOT_LAST)` threw.

**Q3 — thresholds and edges.** No siblings of the truncation bug that matter. Timezones consistent. Two real edges: `OsPatchCheck::trackSince()` returning null on a cache failure made a 30-day backlog read **OK**; and X1's threshold would pin amber if configured to 0.

**Q4 — d1.** Cache strategy is right and could not be broken. The dev-cap is defeatable only theoretically — a package in both lock sections ends `dev => true` — since composer never emits that. When the marker store is unwritable, `remember()` swallows silently and d1 re-POSTs Packagist every 60s.

**Q5 — Alpine/Blade.** Correct on the paths that matter; the "one bad click disables the button forever" bug is specifically absent. Two real issues: the **stale badge depends on the client's clock**, so a slow laptop clock hides it entirely — defeating a badge the spec calls load-bearing — and the badge reads "…12m ago old". Smaller: no debounce on `visibilitychange`, and a blocking `window.alert()` where the page uses inline banners elsewhere.

**Q6 — test quality.** The suite is strong; `SendHealthAlertTest` is the best file in the changeset. Problems: **fixtures that cannot occur in production** — `RuntimeEolCheckTest` fed nginx `1.24.0 (Ubuntu)`, which the facts script's own sed strips, making the fallback assertion vacuous; **`HealthPillTest` hit packagist.org for real** on every run, breaking hermeticity in a suite whose own rule is fixtures-only; `PatchRunFreshnessCheckTest` locked in behaviour that differs from the spec; `OsPatchCheckTest` could not distinguish a written marker from a throwing store. Uncovered: `alertStatus()` for a crashed check, listener-count assertion, cold start with the lock held.

**Q7 — consistency.** Good. Thin controller, no invented Form Request or Policy, `toArray()` contract correctly not wrapped in a Resource, architecture map updated, zero migrations.

## Medium

**M1 · Production's steady state is permanently amber, so the pill has no signal left.**
MySQL 8.0 and Redis 7.0 are past their upstream windows and clearable only by an OS migration, so on `overall` the header pill reads amber on *every admin page* indefinitely — and a genuine new warning produces no visible change. This is the un-actionable-amber failure the spec argues against twice, applied to the glanceable surface. The page-is-pull/alerts-are-push distinction is right, but **the pill is not the page**: it is a push surface embedded in every pull page and needs the same filtering the alert channel got.

**M2 · `distroPackaged()` matches third-party Ubuntu builds, so the documented escalation does not hold.**
Oracle's MySQL APT repo versions packages `8.0.36-1ubuntu22.04`; the substring test matches, so a runtime Canonical is *not* backporting for reports "distro package, fixes backported". The fact that determines backports is the package's **archive origin**, not its version string — `apt-cache policy` in the facts script would settle it honestly.

**M3 · `HealthPillTest` hits packagist.org.** See Q6.

**M4 · d4 warns on any lock drift, not "drift while d1 shows highs".**
Both the parent catalog and §11 couple drift to d1. The implementation warned unconditionally, so every routine `composer require` turned d4 amber — amber for the *good* case — until someone ran a command.

## Suggestions

`SecurityPostureCheck`'s docblock cites `App\Listeners\RecordFailedLogin`, the exact path §11a forbids. `SendDependencyDigest` omits the specified `{--force}`. `writeState()` persists a `status` key nothing reads. Every `link` is labelled "runbook", including X1's `/tokens`. `HealthMarkerStoreTest` writes to the real file store.

## Praise

The comments earn their length — nearly every non-obvious line names the production incident that produced it. `writeState()` returning a boolean to disable suppression is a subtle correctness win the spec did not ask for. Keeping the episode open when a recovery notice fails to send is the version of that code most people get wrong. `SendHealthAlertTest` names the failure each test prevents and renders the real Blade. The `tracked_by` split for null EOL windows is exactly the right instinct — and it is the same instinct M1 and M4 need applied one level up. `HealthSnapshot::partitionFailing()` makes the partition provably exhaustive, and the signature-stability test is the single most important test in Phase 4. Correct on the small things that are easy to miss: explicit digest timezone, `withoutOverlapping(N)`, POST-not-GET with per-route throttles, markers off MySQL and Redis, digest sending on all-clear.
