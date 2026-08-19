# Security Audit — Health Monitor Phases 3 & 4

**Date:** 2026-08-19 · **Scope:** `a6ba5eb..f6a9ff3` · **Status at audit time:** already live on production.
Every claim was traced end to end in code; three candidate findings were dropped during verification and are recorded below.

## Critical
None. The authorization model holds; no unauthenticated or cross-tenant path to health data was constructible.

## High

**H-1 · Root cron executes a script from the www-data-writable deploy tree.**
Root's crontab runs `/var/www/maddata/scripts/health-facts.sh` every 60s, and the deploy log records that `git pull` alone updates the live copy — the root-executed file *is* the repo file, in a tree owned by `www-data`. Anyone reaching code execution as `www-data` overwrites it (or replaces the parent directory, which `chmod 700` on the file does not prevent) and has root within a minute. Inherited from Phase 1; Phase 4 modified this script and shipped it straight into the root-executed path.
*Fix:* `install -o root -g root -m 700 …/scripts/health-facts.sh /usr/local/sbin/maddata-health-facts.sh`, point the crontab there, and make the install an explicit deploy step. **Verify ownership first** — if the tree is in fact root-owned, this drops to Low.

**H-2 · Production is running packages with known critical/high advisories, and nothing emails.**
d1 reports 2 critical / 13 high on the deployed lock. Dev-only findings cap at WARN, so a CRIT is only reachable from a production-installed package. d4 has never been recorded, and `platform` is digest-only — so this has never alerted. This is d1 doing its job; nobody has acted on it.

## Medium

**M-1 · A check class that crashes was silently routed to the weekly digest.**
`SystemHealthService::runCheck()`'s catch-all tagged its fallback CRIT to `node: 'platform'`, which Phase 4 made non-alerting. A crashed `HostCheck` or `BackupCheck` — the "monitor is broken" signal — reached nobody by email. Before Phase 4 it alerted.

**M-2 · X2 (failed-login burst) could not raise an alert.**
X2 is the only check in the catalog that detects an attack in progress, and it sat on the digest-only `platform` node. Thresholds of 20 and 100 failures in 15 minutes are meaningless if the result waits until Monday.

## Low / informational

- **L-1 · Cache files defeat the facts file's deliberate `0640`.** `slow-facts.cache`, `pkg-facts.cache` and the pre-`chmod` temp file were created at the default umask (0644), holding the same OS/package inventory the facts file is restricted for. Do **not** fix with a blanket `umask 077` — `/run/maddata` is recreated on every boot and must stay traversable by www-data.
- **L-2 · X2's minute buckets are never reclaimed.** Buckets are read at minutes 0–14 but expire at 20, and the file store only unlinks on read — so every minute containing a failed login leaves a permanent file. Bounded by wall-clock (~1440/day), not request rate.
- **L-3 · `HealthCheckResult::$link` reaches an Alpine `:href` with no scheme allow-list.** All six producers are config/env constants, so **not exploitable today** — but `probe_url` is env-derived, which already contradicts the config comment promising these are static.
- **L-4 · dpkg version strings were quote-stripped but not charset-restricted** before JSON interpolation. Debian's version charset makes a break-out unreachable; structurally closing it costs the same one line.
- **L-5 · The weekly digest mails the box's exploitability summary off-host** — exact runtime versions, patch counts, advisory breakdown. Intended; noted so it is a decision rather than a surprise.
- **L-6 · d1's outbound disclosure is well-chosen.** It POSTs package *names* only; version matching happens locally. Packagist learns the inventory, not which versions are vulnerable.
- **L-7 · The "no new permission key" rationale is wrong, but the decision is right.** Legacy `is_admin` users are already full admins, so a new key would grant them nothing — the stated escalation argument does not hold. The decision stands for a better reason: reusing `hasPermission('is_admin')` makes the pill's `shouldRender()` and `EnsureUserIsAdmin` literally the same predicate, so access and visibility cannot drift. The real cost is that delegating read-only health visibility requires granting full admin.

## Rejected during verification

1. **"Non-admins can see the pill's data."** No — the compiled Blade runs `if ($component->shouldRender())` *before* `data()`/`render()`, so a non-admin triggers zero cache reads and gets zero markup. Verified in the framework source.
2. **"`:href` is an XSS sink."** It is a sink, but all producers are constants. Downgraded to L-3.
3. **"dpkg version strings break out of the JSON literal."** Quote-stripping plus Debian's charset makes it unreachable. Downgraded to L-4.

## Verified clean (abbreviated)

All three monitor routes are inside the `admin` group nested in `auth`; the JSON endpoint was checked separately and has its own 403 test. `shouldRender()` cannot be bypassed. Sanctum tokens cannot reach the page (session guard). All dynamic values render through `x-text`/`{{ }}`; no `x-html`, no `{!! !!}`. `@js()` escaping is correct. Exception messages never reach a rendered value. No shell execution anywhere in PHP. `apt-get -s upgrade` is quoted and injection-free; the dpkg loop iterates a fixed literal list. Atomic write + `flock` are correct. Nothing untrusted is deserialised — the advisory feed decodes to arrays only. Cache keys are never attacker-influenced. The refresh POST is CSRF-protected, admin-gated and single-flighted. `RecordFailedLogin` is registered once, swallows its own failures, and is not an amplification vector. No secrets in any log, mail or cached payload.
