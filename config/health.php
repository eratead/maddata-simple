<?php

/*
|--------------------------------------------------------------------------
| System Health Monitor
|--------------------------------------------------------------------------
|
| Every threshold the health checks use lives here — never hardcoded in a
| check class. Spec: docs/specs/system-health-monitor.md §3.
|
| Statuses escalate OK → WARN → CRIT. A "warn" value is the point at which
| the check turns amber; "crit" is where it turns red.
|
*/

return [

    /*
    | Alerting (Phase 2). Recipients are operator addresses from the env, NOT
    | the receive_activity_notifications user flag: health mail is operations,
    | not product, and it has to reach someone even when the database is the
    | thing that is sick.
    */
    'alert_recipients' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('HEALTH_ALERT_RECIPIENTS', ''))
    ))),

    /*
    | Nodes whose failing checks are reported but NOT alerted on — they go to
    | the weekly dependency digest instead (deps:digest). d1-d4 and X1/X2 all
    | live on `platform`: a composer advisory or a runtime nearing EOL is real
    | and is not a 2am page.
    |
    | Emptying this list turns dependency findings back into transition alerts,
    | which is the whole reversal, in one line.
    */
    'alert_excluded_nodes' => ['platform'],

    /* Weekly digest for the excluded nodes. Falls back to alert_recipients. */
    'deps_digest_recipients' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('HEALTH_DEPS_DIGEST_RECIPIENTS', ''))
    ))),

    /*
    | The app runs in UTC (config/app.php) with display_timezone Asia/Jerusalem,
    | so this is scheduled with an explicit timezone — a bare "08:00" would land
    | mid-morning Israel time, and would drift by an hour twice a year.
    */
    'deps_digest_day' => env('HEALTH_DEPS_DIGEST_DAY', 'monday'),
    'deps_digest_hour' => env('HEALTH_DEPS_DIGEST_HOUR', '08:00'),

    /* How long a problem stays quiet before it nags again. */
    'realert_hours' => (int) env('HEALTH_REALERT_HOURS', 6),

    /* Hard floor between any two alerts, whatever the reason. */
    'min_notify_interval_minutes' => (int) env('HEALTH_MIN_NOTIFY_INTERVAL_MINUTES', 15),

    /* How long a backup directory newer than the marker may be assumed still
       in flight before it counts as a failed leftover (check B2). */
    'backup_in_flight_grace_seconds' => (int) env('HEALTH_BACKUP_IN_FLIGHT_GRACE', 3600),

    /*
    | The check registry. Order here is the order results appear in the CLI
    | table; the map groups them by node instead. Phase 4 appends to this list.
    */
    'checks' => [
        App\Services\Health\Checks\HostCheck::class,
        App\Services\Health\Checks\EdgeProbeCheck::class,
        App\Services\Health\Checks\DataStoreCheck::class,
        App\Services\Health\Checks\QueueCheck::class,
        App\Services\Health\Checks\SchedulerCheck::class,
        App\Services\Health\Checks\BackupCheck::class,

        // Phase 4 — slow-moving currency signals. All report on node `platform`,
        // which health.alert_excluded_nodes routes to the weekly digest rather
        // than to transition alerts.
        App\Services\Health\Checks\DependencyAdvisoriesCheck::class,
        App\Services\Health\Checks\RuntimeEolCheck::class,
        App\Services\Health\Checks\OsPatchCheck::class,
        App\Services\Health\Checks\PatchRunFreshnessCheck::class,
        App\Services\Health\Checks\SecurityPostureCheck::class,
    ],

    /*
    | Where the root cron scripts leave their output. The app only ever READS
    | these files — it never writes them and never shells out. See §7.
    */
    'facts_path' => env('HEALTH_FACTS_PATH', '/run/maddata/host-facts.json'),

    /*
    | The backup marker deliberately does NOT live on tmpfs alongside the facts
    | file. The facts are a live SAMPLE — losing them on reboot is correct, and
    | keeping a stale one would be actively wrong. The marker is a RECORD of a
    | past event, and losing it made B1 report "backups are unverifiable" for
    | the seventeen hours between a reboot and the next nightly run. It lives
    | next to the backups it describes, and survives restarts with them.
    */
    'backup_marker_path' => env('HEALTH_BACKUP_MARKER_PATH', '/var/backups/maddata/backup-last.json'),

    /*
    | Cache store holding the snapshot and all check markers.
    |
    | Defaults to the dedicated `health` store (config/cache.php), NOT the app
    | cache. Markers are records of past events, and `php artisan cache:clear` —
    | which every deploy runs — clears the default store. Sharing it meant each
    | deploy erased the monitor's memory: S2a/S2b reported "has never completed"
    | for healthy jobs, and B4 forgot the last restore drill. It also decouples
    | the markers from MySQL and Redis, two of the things being monitored.
    |
    | Do not point this back at the app cache store.
    */
    'marker_store' => env('HEALTH_MARKER_STORE', 'health'),

    /*
    | Boot grace. For the first minute or two after a restart the facts cron
    | has not run yet, so every host-derived check has no data and the system
    | looks exactly like an outage. Read from /proc/uptime — a plain file read,
    | no shell. Checks stay honest ("recently booted"), and alerting holds its
    | tongue until the window closes.
    */
    'uptime_path' => env('HEALTH_UPTIME_PATH', '/proc/uptime'),
    'boot_grace_seconds' => (int) env('HEALTH_BOOT_GRACE_SECONDS', 180),

    /*
    | Connections the data-store checks probe. 'mysql_health' is the
    | short-timeout clone defined in config/database.php; tests point these at
    | whatever the test suite actually runs on.
    */
    'mysql_connection' => env('HEALTH_MYSQL_CONNECTION', 'mysql_health'),
    'redis_connection' => env('HEALTH_REDIS_CONNECTION', 'default'),

    /*
    | Snapshot caching. The scheduler rebuilds every minute off-path, so a
    | real request should never observe a miss; the TTL is the backstop.
    */
    /*
    | Must be comfortably LONGER than the 60s rebuild cadence that fills it. At
    | 30s it expired for half of every minute, so the first request each minute
    | paid for a full inline rebuild. Freshness is reported by `generated_at`
    | and policed by checks H1 and S1 — the TTL is not doing that job.
    */
    'snapshot_ttl' => (int) env('HEALTH_SNAPSHOT_TTL', 300),
    'snapshot_lock_seconds' => (int) env('HEALTH_SNAPSHOT_LOCK_SECONDS', 20),

    /*
    | Public HTTPS probe — the only check that sees the stack the way a user
    | does (Nginx config, TLS, DNS, FPM socket).
    */
    'probe_url' => env('HEALTH_PROBE_URL', 'https://ad.maddata.media/up'),
    'probe_timeout' => (int) env('HEALTH_PROBE_TIMEOUT', 3),

    /*
    | Node labels. Order here is the order the UI renders columns in.
    */
    'nodes' => [
        'edge' => 'Edge',
        'app' => 'Application',
        'workers' => 'Workers',
        'data' => 'Data',
        'host' => 'Host',
        'backups' => 'Backups',
        'platform' => 'Platform',
    ],

    /*
    | systemd units the facts script reports on, mapped to the node each one
    | actually belongs to — a CRIT tagged to the wrong node is invisible on
    | the map. Unit names must match the droplet exactly (HM-0.2).
    */
    'systemd_units' => [
        'nginx' => ['label' => 'Nginx', 'node' => 'edge'],
        env('HEALTH_FPM_UNIT', 'php8.4-fpm') => ['label' => 'PHP-FPM', 'node' => 'app'],
        'mysql' => ['label' => 'MySQL service', 'node' => 'data'],
        'redis-server' => ['label' => 'Redis service', 'node' => 'data'],
        'cron' => ['label' => 'Cron', 'node' => 'workers'],
        env('HEALTH_QUEUE_UNIT', 'maddata-queue') => ['label' => 'Queue worker service', 'node' => 'workers'],
    ],

    'thresholds' => [

        // H1 — facts file age. "Am I monitoring blind?"
        'facts_age' => ['warn' => 180, 'crit' => 600],

        // H2-H4 — host resources (percent).
        'cpu' => ['warn' => 70, 'crit' => 85],
        'memory' => ['warn' => 85, 'crit' => 95],
        'disk' => ['warn' => 75, 'crit' => 85],

        // P1 — public probe. consec_fails is what turns it red, not one blip.
        'probe_latency_ms' => ['warn' => 800],
        'probe_consec_fails' => ['warn' => 1, 'crit' => 2],

        /* P1 marker staleness — a probe that stopped running is unknown, not healthy. */
        'probe_marker_age' => ['warn' => 300],

        // P2 — TLS certificate days remaining.
        'tls_days' => ['warn' => 21, 'crit' => 7],

        // Q1-Q3 — queue.
        'queue_depth' => ['warn' => 500, 'crit' => 5000],
        'failed_jobs_24h' => ['warn' => 1, 'crit' => 25],
        'queue_beat_age' => ['warn' => 300, 'crit' => 900],

        // S1-S2 — scheduler and its jobs (seconds).
        'scheduler_beat_age' => ['warn' => 300, 'crit' => 900],
        'campaign_status_age' => ['warn' => 93600, 'crit' => 180000],   // 26h / 50h
        'activity_digest_age' => ['warn' => 10800, 'crit' => 21600],    // 3h / 6h

        // D1-D2 — data stores.
        'mysql_latency_ms' => ['warn' => 100],
        'redis_memory_pct' => ['warn' => 90],

        // D3 — campaign data freshness. Manual-upload driven, so this is
        // informational: it warns, it never goes CRIT. Spec open question 1.
        'campaign_data_age_days' => ['warn' => (int) env('HEALTH_CAMPAIGN_DATA_WARN_DAYS', 3)],

        // B1-B4 — backups (seconds, except size which is a percentage of the
        // trailing median — the silent-truncation detector).
        'backup_age' => ['warn' => 93600, 'crit' => 180000],            // 26h / 50h
        'backup_size_pct_of_median' => ['warn' => 70, 'crit' => 50],
        'restore_drill_age_days' => ['warn' => 120, 'crit' => 210],

        /*
        | d3 — OS security patches. "Sustained" is tracked check-side against a
        | since-marker, because apt only ever reports the CURRENT count: a box
        | unpatched for a month looks identical to one unpatched since lunchtime.
        */
        'os_patch_sustained' => ['warn' => 604800, 'crit' => 2592000],   // 7d / 30d
        'os_patch_facts_age' => ['warn' => 172800, 'crit' => 604800],    // 48h / 7d

        // d4 — how long since anyone actually patched (seconds).
        'patch_run_age' => ['warn' => 3024000, 'crit' => 5184000],       // 35d / 60d

        // X1 — expired Sanctum tokens still sitting in the table. Housekeeping.
        'expired_tokens' => ['warn' => 1],

        // X2 — failed logins in the last 15 minutes.
        'failed_login_burst' => ['warn' => 20, 'crit' => 100],
    ],

    /*
    | Which composer.lock d1 and d4 read. Null means the deployed one, which is
    | always right in production — this exists so tests can point at a fixture
    | instead of asserting against whatever the repo happens to have installed.
    */
    'composer_lock_path' => env('HEALTH_COMPOSER_LOCK_PATH'),

    /* X2 sums this many one-minute buckets. */
    'failed_login_window_minutes' => (int) env('HEALTH_FAILED_LOGIN_WINDOW', 15),

    /*
    | The /admin/monitor page (Phase 3).
    |
    | KPI tiles are chosen BY CHECK KEY from here rather than hardcoded in the
    | Blade: adding a tile is a config line, and a renamed check shows up as one
    | missing tile instead of a silently blank box.
    |
    | stale_seconds is load-bearing. snapshot_ttl is 300s and SNAPSHOT_LAST has
    | no TTL at all, so a dead scheduler would otherwise render a confidently
    | green page forever — the worst failure a monitor has. Past this age the
    | header says so regardless of what `overall` claims.
    */
    'ui' => [
        'poll_seconds' => (int) env('HEALTH_UI_POLL_SECONDS', 30),
        'stale_seconds' => (int) env('HEALTH_UI_STALE_SECONDS', 180),
        'kpi_keys' => ['H2', 'H4', 'Q1', 'Q2', 'B1', 'P1'],
    ],

    /*
    | Deep links rendered next to a failing check. Static only — if these ever
    | become dynamic they must be allow-listed before reaching an href (§7).
    */
    'links' => [
        'backups' => '/docs/specs/backup-restore-runbook.md',
        'monitor' => '/docs/runbooks/health-monitor.md',
    ],
];
