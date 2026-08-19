# Tri-audit disposition — Health Monitor Phases 3 & 4 (2026-08-19)

Three audits over `a6ba5eb..f6a9ff3` (7 commits, 53 files, ~4,000 lines):
[security](health-monitor-phases-3-4-2026-08-19-security.md) ·
[reviewer](health-monitor-phases-3-4-2026-08-19-reviewer.md) ·
[performance](health-monitor-phases-3-4-2026-08-19-performance.md).

The code was already deployed to production when the audits ran, so these were live issues rather than pre-merge notes.

**The headline: the Phase 4 alerting split silenced three things it was never meant to touch.** Routing the `platform` node to the weekly digest correctly stopped composer advisories paging anyone — and incidentally stopped a crashed check class, a live credential-stuffing attack, and (via the pill) every future warning from reaching anybody. Two auditors found the first independently; the second and third came from the reviewer.

## Fixed

| Finding | Found by | What it was |
|---|---|---|
| A crashed check class was tagged `platform` and therefore digest-only — and because its own results vanish from the snapshot, an open episode could drop to all-clear and send **"Everything recovered"** for a problem still failing | security + reviewer (independently) | `SystemHealthService::runCheck()` now tags `app`, labels the row "Health check crashed: X", and logs the previously-silent not-a-HealthCheck case |
| X2 (failed-login burst) could not raise an alert — the only check that sees an attack in progress was digest-only | security | Moved to `app`. X1 stays on `platform`, where housekeeping belongs |
| The header pill read amber permanently, so no new warning could ever change it | reviewer | `pillStatus()` now uses `alertStatus()`; the page and CLI stay fully honest |
| Every poll did a full inline rebuild — outbound HTTPS included — when the marker store was unreadable | performance | `data()` is now a pure cache read; rebuilding belongs to the scheduler and the CLI |
| `OsPatchCheck` reported **OK** for a 30-day backlog whenever the since-marker was unreadable | reviewer | "Cannot tell for how long" is now a WARN — not being able to measure is not measuring zero |
| d4 warned on *any* `composer.lock` drift, so every routine `composer require` turned it amber for the good case | reviewer | Coupled to d1 per the spec: drift matters when something known-bad is in the tree |
| d1's cache-write failure was swallowed silently, turning it into an outbound call every 60 s forever | performance | Logged |
| X2's minute buckets leaked one file per minute containing a failed login | security + performance | Aged-out buckets are now reclaimed |
| The stale badge depended on the **browser's** clock — a slow laptop hid it entirely, defeating the one guard against a stale-green page | reviewer | `age_seconds` added to the contract, server-computed |
| `HealthPillTest` POSTed the real `composer.lock` package list to packagist.org on every run | reviewer | Now uses a fixture check registry |
| Two test fixtures asserted states the production data path cannot produce (`nginx_version: "1.24.0 (Ubuntu)"`, which the facts script's own sed strips) | reviewer | Rewritten against strings the script can emit — the same defect `docs/lessons.md` was written about, found twice more |
| dpkg version strings quote-stripped but not charset-restricted before JSON interpolation; cache files at 0644 defeating the facts file's deliberate 0640; a world-readable window on the temp file | security | All closed in `scripts/health-facts.sh` |
| X1 would pin amber forever if its threshold were configured to 0; a package in both lock sections kept dev severity | reviewer | `max(1, …)` and `??=` |
| Front end: ticker ran in hidden tabs, no in-flight guard, HTTP 429 rendered as "monitoring interrupted", blocking `window.alert()` | performance + reviewer | All fixed |

## Needs an operator decision

- **The rate limiter runs on MySQL.** `cache.limiter` was unset, so `throttle` resolved the database store: ~8 round trips per poll, ~23,000/day per open monitor tab, against an endpoint that issues none of its own. A `limiter` config key now exists; **set `CACHE_LIMITER=redis` on production** to claim it. Left unset by default because staging has no phpredis and would break outright.
- **The root cron executes a script from the www-data-owned deploy tree** (security H-1). Anyone reaching code execution as www-data has root within 60 seconds. Inherited from Phase 1. Fix is an `install` to `/usr/local/sbin` plus a crontab edit — but **verify the actual ownership first**; if the tree is root-owned this is Low, not High.
- **d1's finding is real and unactioned**: 2 critical / 13 high against the deployed lock. This is the check working. `phpoffice/phpspreadsheet` 1.30.2 carries both criticals and is also what exhausts the test suite's memory limit.
- **`distroPackaged()` matches third-party Ubuntu builds** (reviewer M2), so the spec's claim that an Oracle-repo MySQL "re-escalates to CRIT with nobody having to remember anything" does not hold — Oracle versions its packages `…-1ubuntu22.04`. The honest evidence is the package's archive *origin* (`apt-cache policy`), which needs another facts-script change and redeploy. **Recorded rather than fixed**, after three iterations on this one check; the spec's overclaim has been corrected.
- **`config:cache` + `view:cache` are missing from the deploy recipe** (~10–25 ms per request, now paid by every poll). `config:cache` verified safe.
- **The self-measurement invariant is undocumented**: `sleep 25` must exceed the worst-case `schedule:run`. Phase 4 cut the margin from ~24 s to ~11 s.

## Rejected on the evidence

| Claim | Verification |
|---|---|
| Non-admins can see the pill or its data | The compiled Blade runs `shouldRender()` before `data()`/`render()` — a non-admin triggers zero cache reads and gets zero markup. Confirmed in the framework source, not inferred. |
| `:href` on a check link is an XSS sink | It is a sink, but all six producers are config/env constants, traced individually. Kept as a hardening note. |
| dpkg version strings break out of the JSON literal | Quote-stripping plus Debian's version charset makes it unreachable. Closed structurally anyway, since it cost the same one line. |
| Add an index on `personal_access_tokens.expires_at` | Tens of rows. The optimizer would ignore it and the write cost on token issue is real. Same reasoning that correctly rejected the prior audit's index suggestion. |
| Send `threshold` once with the page instead of in every payload | 28% of the payload, but 85 bytes/s is not a problem and it would create the second payload shape the spec forbids. |

## Deliberate non-changes

The file-backed marker store (non-atomic `increment` accepted — X2's thresholds are order-of-magnitude, and Redis-backed markers are rejected policy because they would make every age check go STALE together during a Redis outage). Synchronous ops mail. No new permission key — though the *rationale* in the spec was wrong and has been corrected: legacy `is_admin` users are already full admins, so the escalation argument never held; the decision stands because it keeps route access and pill visibility the same predicate.

## Suite

826 passing, 1 skipped (was 818 before the audit fixes).
