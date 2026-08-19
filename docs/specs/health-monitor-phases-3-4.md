# System Health Monitor — Phases 3 & 4

**Status:** Design, ready to build.
**Date:** 2026-08-19
**Author:** Architect
**Parent spec:** [system-health-monitor.md](system-health-monitor.md) — §3 check catalog, §4 contracts, §6 UI sketch, §7 security model, and the seven inherited rules in §2. This document does **not** restate them. It specifies only what Phases 3 and 4 add on top of what is already live on production.
**Live today:** 26 checks across `edge/app/workers/data/host/backups`, `php artisan health:check`, and transition-based email alerting (`health:alert`, every 5 min). Deployed 2026-08-19, tri-audited, [disposition here](../audits/health-monitor-2026-08-19-SUMMARY.md).

---

## 1. Decisions taken before design

| # | Decision | Consequence for this build |
|---|---|---|
| **D1** | **No history.** The parent spec's §1 non-goal holds. | No `health_events` table, no snapshots table, no charts, no sparklines. **Phases 3 and 4 add zero migrations.** "Was it red overnight?" is answered by alert mail. |
| **D2** | **Read-only page plus one "Refresh now" POST.** | Exactly one write route, and it writes only cache. `health:mark-restore-drill` and `deps:mark-patch-run` stay CLI-only — no state a mis-click can corrupt. |
| **D3** | **d1–d4 are digest-only, not transition alerts.** | Structural: a check's *node* now decides its delivery channel (§9). A composer advisory is not a 2am page. |
| **D4** | **Pill top-right in the header, admins only; the page admin-only.** | Pill renders into `layouts/app.blade.php`, which every authenticated page uses — so the gate is a component `shouldRender()`, not a template `@if` (§4, §16). |

## 2. Goals / Non-goals for these two phases

**Goals**
- Answer *where* the problem is in one look, from any admin page, without SSH.
- Make the monitor's own degradation **visible**: never blank, never a spinner, and never stale-green.
- Give "is the platform current?" a standing answer nobody has to remember to ask.
- Keep slow-moving currency signals from eroding trust in outage alerting.

**Non-goals**
- No migrations. No new composer/npm packages. **No JS libraries** — no Chart.js, no Alpine plugins, no DataTables on this page.
- No new permission key (parent §7). The `admin` middleware is the whole authorization model.
- No auto-remediation. Phase 4 *reports* currency; applying patches stays a human, runbooked act.
- The page never does work the scheduler should be doing — the one exception is the explicit refresh button, and it is single-flighted (§4).

---

# Phase 3 — Admin surface

## 3. Endpoints

| Method | URI | Route name | Middleware | Returns |
|---|---|---|---|---|
| GET | `/admin/monitor` | `admin.monitor.index` | `auth`, `admin` | Blade page. Seeds the first snapshot via `@js()` so it is useful before the first poll. |
| GET | `/admin/monitor/data` | `admin.monitor.data` | `auth`, `admin`, `throttle:60,1` | `HealthSnapshot::toArray()` — the existing contract, unchanged. |
| POST | `/admin/monitor/refresh` | `admin.monitor.refresh` | `auth`, `admin`, `throttle:6,1` | The same JSON shape, after a forced off-path rebuild. |

Registered inside the existing `admin`-middleware group in `routes/web.php`.

- `data()` is the only polled admin route in the application, so it carries its own throttle.
- `refresh()` is **POST, never GET**: it mutates cache state, and a GET that mutates is prefetchable by a browser link preview and cacheable by anything in front of it.
- **No API Resource class.** `HealthSnapshot::toArray()` is already the documented JSON contract (it is docblocked as such in the DTO). Wrapping it in a `JsonResource` would create two places to change one shape.

## 4. Class contracts

`app/Http/Controllers/Admin/MonitorController.php`

```php
final class MonitorController extends Controller
{
    public function __construct(private readonly SystemHealthService $health) {}

    public function index(): View;          // ['snapshot' => array, 'kpiKeys' => array]
    public function data(): JsonResponse;   // $this->health->snapshot()->toArray()
    public function refresh(): JsonResponse;// $this->health->refreshOnDemand()->toArray()
}
```

Thin by contract: no threshold logic, no formatting, no queries. If this controller ever needs a conditional, the conditional belongs in the service or in a check class.

`app/Services/Health/SystemHealthService.php` — **one added method**:

```php
public function refreshOnDemand(): HealthSnapshot;
```

Semantics — the single-flight rule must survive a button:

1. Try `SNAPSHOT_LOCK` (`health.snapshot_lock_seconds`).
2. Acquired → `forget(SNAPSHOT)`, `refresh()`, release, return the fresh snapshot.
3. Not acquired → return `cached(SNAPSHOT) ?? cached(SNAPSHOT_LAST) ?? build()`. A second admin clicking during a rebuild waits for nothing and gets the in-flight result.
4. Lock store unavailable → `build()` directly, same rationale as `snapshot()`.

Never throws — the class-wide guarantee holds.

**Do not let the controller call `refresh()` directly.** That is the whole reason this method exists: two tabs on a sick box would otherwise stampede exactly the MySQL round-trip and Redis `INFO` that are expensive *because* MySQL and Redis are what's sick.

`app/View/Components/HealthPill.php`

```php
final class HealthPill extends Component
{
    public function __construct(private readonly SystemHealthService $health) {}

    public function shouldRender(): bool;  // auth()->user()?->hasPermission('is_admin') === true
    public function render(): View;        // components.health-pill, with HealthStatus $status
}
```

- The gate is `shouldRender()`, so the layout carries no `@if` and no page can render it unguarded by accident.
- Same predicate as `EnsureUserIsAdmin` (`hasPermission('is_admin')`), so page access and pill visibility can never disagree.
- Calls `pillStatus()` only — cache-only, never rebuilds, returns `STALE` on any failure. Cost per admin page render: one cache read (one MySQL row on production, where `HEALTH_MARKER_STORE=database`).
- For non-admins it renders **nothing at all** — not a hidden element. `x-show` would ship system status into the DOM of every client-facing page.

## 5. Blade structure

| File | Responsibility |
|---|---|
| `resources/views/admin/monitor.blade.php` | The page: header strip, KPI row, node grid, check list. One `x-data` root. |
| `resources/views/components/monitor/node-card.blade.php` | One node column: label, status dot, its checks. |
| `resources/views/components/monitor/kpi-tile.blade.php` | One `[CPU 24%]` tile. |
| `resources/views/components/monitor/check-row.blade.php` | Key, label, value, threshold, optional runbook link. |
| `resources/views/components/health-pill.blade.php` | Pill markup. |
| `app/View/Components/HealthPill.php` | Pill component class (§4). |

**Modified:** `layouts/app.blade.php` (pill slot in the `h-14` header, right of `@stack('page-actions')`), `components/sidebar.blade.php` (Monitor nav link — note the admin-group active condition at line 95 already matches `admin/system-status*` and must be extended, not replaced), `admin/system_status/index.blade.php` (cross-link to the monitor).

**Rendering rules**

- **Everything renders from the Alpine state object**, not from Blade. Blade seeds it once via `@js($snapshot)`; the poller replaces it wholesale. Two rendering paths for one shape is exactly how the "initial" and "refreshed" views drift apart.
- Status → colour goes through **one Alpine map** keyed by the enum's existing `colorToken()` values (`emerald/amber/red/gray`). The map holds **complete class literals** (`'bg-emerald-500'`), never `` `bg-${token}-500` `` — Tailwind's JIT cannot see interpolated names and would ship the page with no colours at all. This is the single most likely way to get this page wrong.
- Node card status is `nodes[].status`, already computed server-side. **The UI never recomputes a rollup** — a second implementation of worst-of is a second thing to get wrong.
- Failing checks render first and auto-expanded; the rest collapsed by node.
- `STALE` renders gray with a striped treatment visibly distinct from OK-green. "I can't see" must never look like "fine".
- Tailwind + design-system tokens only (accent `#F97316`, sidebar `#111827`), per CLAUDE.md.

## 6. Alpine behaviour — the rules that make it trustworthy

- Poll `/admin/monitor/data` every `health.ui.poll_seconds` (default 30). The rebuild is off-path every 60s; a poll is only a cache read.
- `document.hidden` → skip the poll; resume **and poll immediately** on `visibilitychange`. A backgrounded tab must not keep the marker store busy all night, and a foregrounded one must not show 40-minute-old data for 30 seconds.
- Fetch failure → keep the last good state and show an amber banner: *"live updates interrupted — showing data from HH:MM"*. Never blank, never a spinner that stays forever.
- **Stale-snapshot badge (load-bearing).** If `generated_at` is older than `health.ui.stale_seconds` (default 180), the header says *"snapshot is N minutes old — the scheduler may be down"*, regardless of what `overall` says. `snapshot_ttl` is **300s** and `SNAPSHOT_LAST` has no TTL at all, so a dead scheduler otherwise renders **stale green** — the worst failure a monitor has. S1 covers this as a check, but the header must not depend on the reader noticing a check.
- "Refresh now" → POST with the CSRF token; button disabled with a spinner while in flight; re-enabled on **both** success and failure. On 429, show *"refreshing too fast"*, not an error state.
- The pill never polls. It is a page-load render; navigating refreshes it.

## 7. Config additions — `config/health.php`

```php
'ui' => [
    'poll_seconds'  => (int) env('HEALTH_UI_POLL_SECONDS', 30),
    'stale_seconds' => (int) env('HEALTH_UI_STALE_SECONDS', 180),
    'kpi_keys'      => ['H2', 'H4', 'Q1', 'Q2', 'B1', 'P1'],
],
```

KPI tiles are selected **by check key from config**, not hardcoded in Blade: adding a tile is a config line, and a renamed check surfaces as a missing tile in one place rather than a silently blank box.

## 8. Tests — Phase 3

`tests/Feature/Health/MonitorControllerTest.php`

- Admin `GET /admin/monitor` → 200, contains the seeded snapshot.
- Admin `GET /admin/monitor/data` → 200 and the **exact** JSON shape (`overall`, `generated_at`, `nodes[].{key,label,status,check_keys}`, `checks[].{key,label,status,node,value,threshold,link}`).
- **Agency manager (`can_manage_users`, not admin) → 403 on the page, on `/data`, and on `/refresh` — three separate assertions.** The JSON endpoint is the one that actually leaks; it does not inherit the page's test.
- Guest → 302 on all three.
- `POST /refresh` → returns a snapshot whose `generated_at` is newer than the cached one.
- `POST /refresh` while `SNAPSHOT_LOCK` is held → returns the cached snapshot and does **not** rebuild (assert `generated_at` unchanged).
- `data()` never 500s when every dependency throws — bind failing store/DB and assert **200 with a CRIT payload**. Resilience is the property this vertical exists for; assert it at the HTTP boundary, not only in the service unit test.
- `throttle:60,1` on `/data` and `throttle:6,1` on `/refresh` actually bite (61st / 7th request → 429).

`tests/Feature/Health/HealthPillTest.php`

- Renders for an admin on an **unrelated** page (campaigns index) — that is where it lives, so that is where it is tested.
- **Absent** from the HTML for a non-admin (`assertDontSee` the pill's test id) — absent, not merely hidden.
- `pillStatus()` throwing → the unrelated page still returns 200 and the pill reads "Unknown".

---

# Phase 4 — Dependency & version currency

## 9. The structural change: alerting splits in two

D3 means **a check's node now decides its delivery channel.**

Added to `config/health.php`:

```php
'alert_excluded_nodes'   => ['platform'],
'deps_digest_recipients' => // env, comma-separated; falls back to alert_recipients
'deps_digest_day'        => 'monday',
'deps_digest_hour'       => '08:00',
```

Added to `app/Dtos/HealthSnapshot.php` — the DTO already owns the rollups, so this belongs here and not in the command:

```php
public function alertable(): array;           // failing() minus checks whose node is in health.alert_excluded_nodes
public function alertStatus(): HealthStatus;  // worstOf(alertable()) — what health:alert decides on
public function digestable(): array;          // failing() checks IN the excluded nodes — what deps:digest reports
public function signature(): string;          // CHANGED: computed from alertable(), not failing()
```

Modified in `app/Console/Commands/SendHealthAlert.php`: every `overall` / `failing()` decision point switches to `alertStatus()` / `alertable()`. `HealthAlertMail` lists `alertable()`; when `digestable()` is non-empty it gets **one muted footer line** ("3 platform items — see the weekly digest") so the two channels cross-reference each other instead of each hiding the other's contents.

**Why node membership and not a per-check flag:** a `$alerts = false` property on each check would need setting in eleven places and would drift the first time someone adds a twelfth. The node is already the thing the UI groups by, and `alert_excluded_nodes` is one config list to audit.

**Stated consequence, accepted:** `overall` stays honest, so a CRIT advisory turns the page and the pill **red** while nothing emails. That is the intended trade — the page is pull, alerts are push — and it is one config line to reverse (`'alert_excluded_nodes' => []`) if the permanently-red pill turns out to be the thing that gets ignored.

## 10. `config/dependency_maintenance.php`

```php
return [
    'reviewed_at' => '2026-08-19',        // d2's dead-man switch — see below
    'runtimes' => [
        ['product' => 'php',   'branch' => '8.4', 'security_support_until' => 'YYYY-MM-DD', 'source' => 'php.net/supported-versions'],
        ['product' => 'mysql', 'branch' => '8.0', 'security_support_until' => 'YYYY-MM-DD', 'source' => '...'],
        ['product' => 'redis', 'branch' => 'X.Y', 'security_support_until' => 'YYYY-MM-DD', 'source' => '...'],
        ['product' => 'nginx', 'branch' => 'X.Y', 'security_support_until' => 'YYYY-MM-DD', 'source' => '...'],
    ],
    'thresholds' => ['eol_warn_days' => 90, 'table_review_months' => 6],
    'advisories' => [
        'endpoint'    => 'https://packagist.org/api/security-advisories/',
        'cache_hours' => 24,
        'user_agent'  => 'MadData-HealthMonitor/1.0 (+https://ad.maddata.media)',
        'timeout'     => 8,
    ],
];
```

The builder fills the real dates from each vendor's published policy **at build time** and records the source in the `source` key. An EOL table whose dates nobody sourced is worse than no table — it reads green with authority. Where a vendor publishes no dated security window, the entry must be an explicit `null` that d2 reads as **WARN/unknown**, never a guessed date.

**Refined during build (2026-08-19).** That rule, applied literally, produces
exactly the failure HM-4.5 warns about. nginx publishes no dated security window
per branch at all: a branch is simply superseded by the next, and the packaged
build gets its fixes from distro backports. A permanent "no published window"
amber on nginx could never be cleared by any action — which is how a monitor
trains people to ignore it. So a null window now splits in two:

- `tracked_by` set (nginx → `d3`): another check owns this runtime's currency.
  Reports **OK with a note**.
- `tracked_by` absent: we genuinely do not know. Reports **WARN**, as originally
  specified.

Both are explicit in config rather than inferred, so the distinction stays
auditable. Dates sourced at build time: PHP from php.net/supported-versions,
MySQL and Redis from endoflife.date (Oracle Lifetime Support / Redis release
policy).

## 11. Check contracts

All five extend `HealthCheck`, return `array<HealthCheckResult>` from `run()`, and **never throw** — every probe goes through `guard()`. All results carry `node: 'platform'`. Registered by appending to `config('health.checks')`.

### d1 — `DependencyAdvisoriesCheck`

- Reads the **deployed** `base_path('composer.lock')`, both `packages` and `packages-dev`.
- Matches installed versions against each advisory's affected constraint with `composer/semver` (`Semver::satisfies()`) — already in the vendor tree, no new package.
- **Cache keyed by the lock's sha256**, TTL 24h. A deploy that changes the lock re-queries immediately instead of serving up to 24 hours of stale "clean".
- **Feed down** → serve last-known-good from a no-TTL companion key; if there is none, **WARN "advisory feed unreachable"**. Never OK on a dead feed (inherited rule 7).
- Unrated severity counts as **high** → CRIT.
- `Http::timeout(8)->withUserAgent(config(...))`. Packagist throttles empty/default user agents, and a hung feed must never stretch a snapshot rebuild toward the 60s cron interval.
- **Dev packages are capped at WARN**, labelled `(dev)`. Production installs with `--no-dev`, so a critical in PHPUnit is not deployed — but it *is* on developer machines and in CI, so it is not nothing either. This refines the parent spec's flat "packages + packages-dev"; see open question 1.

### d2 — `RuntimeEolCheck`

- PHP: `PHP_VERSION`. MySQL: `select version()` on the **`mysql_health`** connection (already bounded at 2s — never the default connection). Redis: `INFO server` → `redis_version`. Nginx: facts `nginx_version`.
- Branch match on `major.minor` against the §10 table.
- WARN under `eol_warn_days` to `security_support_until`; CRIT past it; **STALE when a version cannot be determined** (an unreachable feed is never GREEN).
- **Separate result: table freshness.** WARN when `reviewed_at` is older than `table_review_months`. Without it a stale table reports "all supported" forever — this is the table's own dead-man switch.

### d3 — `OsPatchCheck`

Reads `pending_security` and `reboot_required` from the facts file.

> **The apt-check trap — found on production 2026-08-19, do not rebuild it.** `apt-check` counts packages in the *full-upgrade change set* that have a security-pocket version, **including packages that are not installed**. Production's single "pending security update" was `libabsl20220623t64`, which isn't installed and would only arrive as a new dependency of `libgav1-1` during a full upgrade — so `apt-get install --only-upgrade` refuses it and `unattended-upgrade` reports "no packages found". A naive d3 shows a permanently-stuck amber that **no action can ever clear**, which is precisely how a monitor teaches people to ignore it.
>
> `scripts/health-facts.sh` must instead count **installed packages whose candidate version comes from a `-security` pocket and is upgradable via `--only-upgrade`**. If that count cannot be computed, write `null`. **d3 reads `null` as STALE, never as 0** — a monitor that cannot count must not report "clean".

- Sustained window: marker `health:os_patch:since` = `{count, first_seen}`. Reset when the count reaches 0 or the set shrinks. WARN sustained >7d; CRIT sustained >30d (that means unattended-upgrades is broken). Tracked check-side because the facts only ever report *now*.
- `reboot_required` → WARN, always. Not urgent, but never nothing.
- Facts stale >48h → WARN; >7d → CRIT.

### d4 — `PatchRunFreshnessCheck` + `deps:mark-patch-run`

- Marker `HealthMarkers::PATCH_RUN` = `{ts, lock_sha, note}`; `lock_sha = hash_file('sha256', base_path('composer.lock'))`.
- Command: `deps:mark-patch-run {--note=}`.
- **Never marked → WARN "never recorded", not STALE.** "Nobody has ever patched this box" is a fact, not missing data.
- WARN >35d, or `lock_sha` drift while d1 reports high/critical; CRIT >60d.

### X1 / X2 — `SecurityPostureCheck`

- **X1:** count `personal_access_tokens` where `expires_at < now()`. WARN >0, never CRIT — it is housekeeping. `link` → `/tokens`.
- **X2 needs a feed that does not exist today.** There is no failed-login table; `LoginRequest` uses only the RateLimiter.
  **Design:** a listener on `Illuminate\Auth\Events\Failed` increments a per-minute cache bucket `health:auth:fail:{YmdHi}` with a 20-minute TTL; X2 sums the last 15 buckets. No table, no migration (D1 holds), self-expiring, 15 reads per snapshot.
  The listener **wraps its own write in try/catch and swallows** — a cache outage must never be able to break logging in. Same rule as `HealthMarkers::record()`.
  WARN ≥20 failures in 15 min; CRIT ≥100.
  New file `app/Listeners/RecordFailedLogin.php`, **registered explicitly** with `Event::listen(Failed::class, RecordFailedLogin::class)` in `AppServiceProvider::boot()` — see §11a.

## 11a. Listener registration — resolved (open question 5)

**Auto-discovery is active in this repo, and we are deliberately not using it.**

What's actually true, verified in the vendor tree:

- `Application::configure()` auto-chains `->withEvents()` ([Application.php:243](../../vendor/laravel/framework/src/Illuminate/Foundation/Application.php)), so the framework's `EventServiceProvider` is registered even though `bootstrap/app.php` never mentions events.
- That provider sets `$shouldDiscoverEvents = true`, and `shouldDiscoverEvents()` returns true here because the app defines no `App\Providers\EventServiceProvider` subclass.
- The default discovery path is `app/Listeners`, **which does not exist yet**. The repo has zero listeners and zero `Event::listen` calls; `RecordFailedLogin` would be the first.

So dropping the file in would work. Register it explicitly anyway, in `AppServiceProvider::boot()` — **and put the class outside `app/Listeners`**:

1. **It matches the only precedent in the repo.** All three observers are wired explicitly there (`Campaign::observe(...)`) rather than via `#[ObservedBy]`. One convention, one place to look.
2. **X2's failure mode under discovery is a confident zero.** A renamed method or a dropped type-hint silently un-registers the listener; the buckets stay empty; X2 reports "0 failed logins" in green forever. That is exactly the stale-green class of failure this whole vertical is designed against — and unlike a dead feed, nothing about it looks wrong.
3. **Discovery is not free and not cached here.** It scans the directory and reflects on every class at boot unless `event:cache` runs — and no deploy script does (`deploy-staging.sh` and the production flow run `composer install --optimize-autoloader` plus config/route/view clears, nothing more). Small, but this is the droplet where health tasks were moved in-process specifically to stop paying for extra PHP boots.
4. **Grep-ability.** One `Event::listen(Failed::class, ...)` line an operator can find; no "where is this even wired up" hunt.

If a later change adds `php artisan event:cache` to the deploy flow, points 3 and 4 weaken but point 2 does not.

**Found while building this (2026-08-19):** explicit registration of a class that
*also* sits in `app/Listeners` registers it **twice** — discovery finds the typed
`handle()`, and `Event::listen()` adds a second binding. Every failed login was
counted twice until a test caught it. The class therefore lives at
`app/Services/Health/Listeners/RecordFailedLogin.php`, outside the discovery
path, which makes the double registration structurally impossible and puts it
with the rest of the health vertical. Verify with
`app('events')->getListeners(Failed::class)` — it must be 1.

## 12. `deps:digest` — the weekly channel

`app/Console/Commands/SendDependencyDigest.php`

```php
protected $signature = 'deps:digest {--force}';
```

- Scheduled weekly, `deps_digest_day` at `deps_digest_hour`, **explicitly `->timezone(config('app.display_timezone'))`** — `config/app.php` runs the app in **UTC** with `display_timezone` = `Asia/Jerusalem`, so an un-timezoned `08:00` would land at 10:00 or 11:00 Israel time depending on DST. **In-process via `Schedule::call()`** — production is a single-core droplet and the existing comment in `routes/console.php` explains why `Schedule::command()` is not used for health work.
- Reads `SystemHealthService::snapshot()` → `digestable()` plus the platform node's passing checks, so the mail says what is fine as well as what is not.
- **Always sends, including all-clear.** A digest that appears only when something is wrong is indistinguishable from a digest that has stopped working — the same argument that put recovery notices in Phase 2.
- `DependencyDigestMail` follows the existing `ActivityDigestMail` pattern.
- Mail failures logged, never thrown. Empty recipients → log and return SUCCESS, same as `health:alert` on an unconfigured box.

## 13. Ops steps (not code)

- **`unattended-upgrades`** on production: security pocket only, `Automatic-Reboot "false"`, keep a `.bak` of the prior config. This is provisioning, documented in the runbook — the deploy flow never runs `apt`.
- The **facts-script change for d3** (§11) ships with Phase 4 and requires a root-cron redeploy, not just a `git pull`. Runbook step, easy to forget, and forgetting it leaves d3 reporting on the old broken count.

## 14. Tests — Phase 4

- **`HealthSnapshotAlertSplitTest`** (unit): `alertable()`/`digestable()` partition by node; `alertStatus()` ignores platform; `signature()` is unchanged by a platform check flipping. This is the test that proves the split cannot silence outage alerting.
- **`SendHealthAlertTest`** (extend the existing file): a CRIT confined to `platform` sends **nothing**; a CRIT on `data` still sends; a platform CRIT alongside a data WARN sends the data WARN with the muted footer line.
- **d1:** advisory matched → CRIT; unrated → CRIT; dev-only → WARN; feed 500/timeout with cache → last-known-good; feed down with no cache → WARN, never OK; lock sha change busts the cache. `Http::fake()` throughout.
- **d2:** inside window → OK; <90d → WARN; past date → CRIT; undeterminable version → STALE; `reviewed_at` older than 6 months → WARN even when every runtime is fine.
- **d3:** count 0 → OK; count >0 fresh → OK-with-value; sustained 8d → WARN; sustained 31d → CRIT; count returns to 0 → since-marker resets; **`null` count → STALE, explicitly asserted not OK**; `reboot_required` → WARN.
- **d4:** never marked → WARN "never recorded"; 36d → WARN; 61d → CRIT; lock drift + d1 highs → WARN.
- **X1/X2:** expired token present → WARN; the `Failed` listener increments the bucket; 19/20/99/100 boundaries; **listener with a throwing cache store does not break login** (assert the login response, not the marker).
- **`SendDependencyDigestTest`:** sends on all-clear; sends with findings; unset recipients → SUCCESS and no mail; throwing mailer → command still SUCCESS.
- No test may execute a shell command; facts come from fixture JSON (inherited rule).

---

## 15. Database changes

**None. Both phases add zero migrations.** Everything is config, cache markers, and the existing facts file (D1). If a table starts to look necessary during the build, that is a spec change and a conversation, not a judgment call.

## 16. Multi-tenant impact

None — **and that absence is the risk.** Health is system-level: no Agency, Client or User relationship scopes it, so the `admin` middleware is the only thing between an agency manager and the droplet's internals. Every new route and the pill therefore use the same `hasPermission('is_admin')` predicate as `EnsureUserIsAdmin`, and the 403 assertions are **per endpoint**, not per feature.

No new permission key (parent §7): `hasPermission()` returns true for *every* key when legacy `is_admin` is set, so a `can_view_system_health` key would create a new escalation surface for a page only admins want.

The one genuinely new exposure is the pill. It renders into `layouts/app.blade.php`, which every authenticated page uses, including client-facing ones — hence `shouldRender()` rather than a template `@if`, and hence a test asserting the markup is *absent* for non-admins rather than merely hidden.

## 17. Dependencies & sequencing

- **Phase 3 depends on nothing** beyond what is deployed today. Independently shippable.
- **Phase 4 §9 (the alert split) must land with its tests in the same change.** A half-applied split silences outage alerting — the one regression this vertical cannot afford.
- Within Phase 4: §10 table before d2; the facts-script change before d3 reports anything true; the `Failed` listener before X2.
- **HM-0.1 (external uptime watcher) still outranks both.** Nothing designed here can report a dead droplet.

## 18. Open questions — all resolved 2026-08-19

| # | Question | Resolution |
|---|---|---|
| 1 | Dev-package advisories capped at WARN? | **Confirmed.** Dev-only advisories report WARN labelled `(dev)`, never CRIT. Production installs `--no-dev`; the package is not on the box. |
| 2 | EOL dates sourced at build time, `null` reads WARN? | **Confirmed.** Every date carries a `source`. No published dated window → explicit `null` reading WARN. Never a guessed date reading green. |
| 3 | Digest cadence Monday 08:00 Israel? | **Confirmed** — and it needs `->timezone(config('app.display_timezone'))`, because the app runs UTC (§12). |
| 4 | Monitor beside System Status, not merged? | **Confirmed.** Separate pages with cross-links. Destructive controls do not belong on a page left open and polling. |
| 5 | Listener auto-discovery or explicit? | **Resolved: explicit**, though discovery is active and would have worked. Full reasoning in §11a — the deciding one is that X2 fails silently green under discovery. The class must live outside `app/Listeners` or it registers twice; see the build note in §11a. |

## 19. Built — 2026-08-19

Both phases are implemented and the suite is 807 passing. Not yet deployed, and
two server-side steps remain (facts-script redeploy, `unattended-upgrades`) — see
`docs/tasks/todo.md` HM-3.11 and HM-4.12.

Three things changed from this design during the build, each recorded above where
it belongs: the nginx null-window split (§10), the listener's location (§11a),
and `SystemHealthService::snapshot()` collapsing to
`cached() ?? refreshOnDemand()` so the single-flight lock logic exists once (§4).

One rule was proven right the hard way: d2's first real run reported the local
MySQL as `9.3.0 — branch not in the support table`. That is the
warn-on-unknown-branch rule doing its job rather than quietly passing a runtime
nobody had considered.
