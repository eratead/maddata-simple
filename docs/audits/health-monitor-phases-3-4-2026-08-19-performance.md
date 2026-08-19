# Performance Audit — Health Monitor Phases 3 & 4

**Date:** 2026-08-19 · **Scope:** `a6ba5eb..f6a9ff3` · **Target:** single-core DO droplet, Nginx + PHP 8.4-FPM + MySQL 8 + Redis, one queue worker, www-data cron scheduler.

Everything is judged against that box, which has twice had the monitor's own overhead show up in the metrics it reports.

## Headline

1. **The `throttle` on `/admin/monitor/data` costs ~8 MySQL round trips per poll — roughly 8× the endpoint it protects, which issues zero queries.** `cache.limiter` was unset, so the rate limiter resolved the default store (`database` on prod): `attempts` SELECT, two `add` SELECTs, `increment` as BEGIN + `SELECT … FOR UPDATE` + UPDATE + COMMIT, `remaining` SELECT. One always-open tab ≈ **23,000 MySQL round trips and 2,880 locking transactions per day**, against a controller whose own work is one file read.
2. **`DependencyAdvisoriesCheck` is ~90% of the new per-minute rebuild cost** (~40–135 ms of ~45–140 ms) and redoes all of it every 60s *even on a cache hit*: a ~400 KB `json_decode`, a 400 KB sha256, and 300–600 uncached `Semver::satisfies()` parses. `Semver` memoizes the parser but not parsed constraints.
3. **Moving markers from `database` to `file` was a clear win** — the pill went from ~2–4 ms + one MySQL round trip per admin page render to ~0.4–0.9 ms and zero queries.

**Nothing currently lands inside the monitor's own CPU sample window.** `schedule:run` fires at `:00`; the facts cron is offset `sleep 25` and samples `vmstat 15 2` over `:25–:40`; worst-case rebuild ends ~`:14`. **Margin ~11 s — down from ~24 s before Phase 4.**

## High

| # | Finding | Impact |
|---|---|---|
| H1 | Rate limiter on the default (database) store | ~23,000 round trips/day/tab; fix is one config key |
| H2 | d1's `remember()` swallows cache-write failures silently | An unwritable cache turns d1 into 1,440 Packagist requests/day with no log and no status change — the root-vs-www-data trap in a new place |
| H3 | The `sleep 25` vs worst-case `schedule:run` invariant is undocumented, and Phase 4 halved its margin | Raising `HEALTH_ADVISORY_TIMEOUT` or the CPU sample walks the rebuild into the window and reproduces "100% CPU on a 98%-idle box" |
| H4 | A broken marker store made every poll do a full inline rebuild, outbound HTTPS included | 3 tabs = 6 Packagist calls/min inside FPM workers, up to 8 s each — pool exhaustion, i.e. the monitor taking the site down |

## Medium

- **M1 · d1 re-parses and re-evaluates on every cache hit** because the cache key is the lock hash, computed from the full file. ~40–135 ms every 60 s = 60–195 CPU-seconds/day. Fix: key on `hash_file()` (streaming) and cache the *evaluated result*, not the raw feed.
- **M2 · The read path round-trips through the DTO for nothing.** Cached array → 37 objects → array → JSON. ~0.5–0.8 ms per poll, ~0.3–0.5 ms per admin page render, and it scales with a check count that went 26 → 37 in one release.
- **M3 · Production runs with no cached config or routes.** Every deploy clears and nothing re-caches: 12 config files parsed and 87 routes compiled per request, ~10–25 ms. Pre-existing, but Phases 3–4 shipped the app's first *polled* route, converting a page-load cost into a permanent background one. `config:cache` verified safe (zero `env()` under `app/`).

## Quick wins

The 1-second ticker ran in backgrounded tabs; `poll()` had no in-flight guard and `visibilitychange` fired an un-debounced poll; `poll()` treated HTTP 429 as a fetch failure and raised the "live updates interrupted" banner for a throttle; X2's minute buckets leaked one file per minute containing a failed login.

## Assessed and fine as-is — do not change

`HealthPill::shouldRender()` adds **zero** queries (`User::$with` eager-loads the role; `hasPermission()` memoizes). The pill's ~0.4–0.9 ms is immaterial at a few hundred page views/day. `RecordFailedLogin`'s ~0.2–0.4 ms sits inside a request already spending 100–250 ms in password hashing, and is bounded by the login limiter firing *before* `Auth::attempt`. `FileStore::increment()` is non-atomic and X2 will undercount concurrent failures — accept: the thresholds are order-of-magnitude, and Redis-backed markers are deliberately rejected policy. `select version()` + `INFO server` + the token `count()` reuse open connections (~1.5 ms). **No index on `personal_access_tokens.expires_at`** — tens of rows; the optimizer would ignore it and the write cost is real. The facts script's `packages` block adds ~5–8 CPU-seconds/day and its hourly burst runs after the CPU sample. `sanctum:prune-expired` is free and fixes a check that would otherwise accumulate forever. Synchronous weekly digest mail is deliberate. Alpine's `x-for` cost is ~2–6 ms every 30 s, and per-effect tracking means the ticker does not re-run the loops. `document.hidden` handling genuinely stops all network work. The ~8–10 KB payload is fine — **explicitly rejecting** the obvious win of sending `threshold` once with the page, because 85 bytes/s is not a problem and it would create the second payload shape the spec forbids.

## Caching recommendations

| Data | Now | Recommended |
|---|---|---|
| Markers + snapshot | `file` store (changed this release) | **Keep.** ~3–6× faster than database here, removes a MySQL round trip from every admin page render, survives `cache:clear`, and depends on neither of the two services being monitored. |
| Rate limiter | unset → database | `CACHE_LIMITER=redis` on hosts with phpredis. Highest-value single change in this audit; independent of `CACHE_STORE`. |
| d1 evaluation | raw feed cached 24 h; evaluation re-run every 60 s | Cache the finished result keyed by `hash_file()` |
| App cache | unset → database | `CACHE_STORE=redis`, keeping the marker store separate |
| Config/routes/views | cleared, never re-cached | Append `config:cache` + `view:cache` to the deploy recipe |

## Index recommendations

**None. No migration should come out of this audit.** `personal_access_tokens.expires_at` rejected on scale; the rate-limiter table is already keyed and the fix is to stop using MySQL for it; `campaign_data(report_date)` already shipped from the prior audit.

## Recurring patterns

1. On this box, **the middleware protecting an endpoint routinely costs more than the endpoint**. Price the stack, not the controller.
2. "It's just a cache read" is never just a cache read on a `web`-group route — a poll is a full uncached boot, session, user lookup and 8 limiter queries around ~2% real work.
3. **DTO round-tripping on read paths** appeared twice in this release.
4. **Silent-degradation-to-expensive**: a swallowed failure converts a cached path into an uncached one, permanently, with no signal. *An empty `catch` around a cache write must log.*
5. The self-measurement invariant (`sleep N` vs worst-case `schedule:run`) has never been written down, and Phase 4 consumed half its margin.
