<?php

/*
|--------------------------------------------------------------------------
| Runtime support windows (check d2)
|--------------------------------------------------------------------------
|
| Spec: docs/specs/health-monitor-phases-3-4.md §10.
|
| Every date here is copied from the vendor's own published policy and carries
| its source. An EOL table whose dates nobody sourced is worse than no table at
| all: it reads green with authority.
|
| `reviewed_at` is this table's dead-man switch. A support table nobody revisits
| reports "all supported" forever, so d2 warns when this date goes stale
| regardless of what the rows say. Update it when you actually re-check the
| sources, not when you edit an unrelated line.
|
*/

return [

    'reviewed_at' => '2026-08-19',

    /*
    | Matched on major.minor of the live version. A branch that is not listed
    | reads WARN ("not in the table") rather than OK — an unknown runtime is not
    | a supported one, and the fix is to add the row.
    |
    | security_support_until => null means the vendor publishes no dated window.
    | That is NOT automatically a warning: see `tracked_by` below.
    */
    'runtimes' => [

        // https://www.php.net/supported-versions.php
        ['product' => 'php', 'branch' => '8.3', 'security_support_until' => '2027-12-31', 'source' => 'php.net/supported-versions'],
        ['product' => 'php', 'branch' => '8.4', 'security_support_until' => '2028-12-31', 'source' => 'php.net/supported-versions'],
        ['product' => 'php', 'branch' => '8.5', 'security_support_until' => '2029-12-31', 'source' => 'php.net/supported-versions'],

        // Oracle Lifetime Support Policy, via endoflife.date/mysql.
        // 8.0 is an LTS that reached end of extended support on 2026-04-30 —
        // if production is still on it, d2 is supposed to say so.
        ['product' => 'mysql', 'branch' => '8.0', 'security_support_until' => '2026-04-30', 'source' => 'endoflife.date/mysql (Oracle Lifetime Support)'],
        ['product' => 'mysql', 'branch' => '8.4', 'security_support_until' => '2032-04-30', 'source' => 'endoflife.date/mysql (Oracle Lifetime Support, LTS)'],

        // https://endoflife.date/redis
        // 7.0 is what Ubuntu 24.04 ships and what production runs. Upstream
        // ended it on 2024-07-29, so d2 reports it — but as a WARN, because the
        // deployed build is the distro package and Canonical backports fixes to
        // 24.04 main. The check detects that from the version string itself.
        ['product' => 'redis', 'branch' => '7.0', 'security_support_until' => '2024-07-29', 'source' => 'endoflife.date/redis'],
        ['product' => 'redis', 'branch' => '7.2', 'security_support_until' => '2029-12-01', 'source' => 'endoflife.date/redis'],
        ['product' => 'redis', 'branch' => '7.4', 'security_support_until' => '2029-12-01', 'source' => 'endoflife.date/redis'],
        ['product' => 'redis', 'branch' => '8.0', 'security_support_until' => '2026-12-01', 'source' => 'endoflife.date/redis'],
        ['product' => 'redis', 'branch' => '8.2', 'security_support_until' => '2030-09-01', 'source' => 'endoflife.date/redis'],

        /*
        | nginx publishes no dated security window per branch — a branch is
        | simply superseded by the next one, and the packaged build we run gets
        | its security fixes backported by the distro. So an EOL date here would
        | be invented, and a permanent "no published window" amber would be
        | un-actionable: nothing you could do would ever clear it, which is
        | precisely how check d3's apt-check trap taught us to make people
        | ignore a monitor.
        |
        | `tracked_by` says so explicitly: this runtime's currency is answered by
        | another check, so d2 reports it OK-with-a-note instead of amber. A null
        | window with NO tracked_by still warns — that is the honest "we do not
        | know" case, and it must never read green.
        */
        ['product' => 'nginx', 'branch' => '*', 'security_support_until' => null, 'tracked_by' => 'd3', 'source' => 'nginx publishes no per-branch security window; distro backports, covered by d3'],
    ],

    'thresholds' => [
        'eol_warn_days' => 90,
        'table_review_months' => 6,
    ],

    /*
    | Check d1 — Packagist's security advisories API.
    |
    | The user agent is not optional: Packagist throttles anonymous/default
    | agents. The timeout is not optional either — this call happens inside a
    | snapshot rebuild that the scheduler runs every 60 seconds.
    */
    'advisories' => [
        'endpoint' => env('HEALTH_ADVISORY_ENDPOINT', 'https://packagist.org/api/security-advisories/'),
        'cache_hours' => (int) env('HEALTH_ADVISORY_CACHE_HOURS', 24),
        'user_agent' => env('HEALTH_ADVISORY_UA', 'MadData-HealthMonitor/1.0 (+https://ad.maddata.media)'),
        'timeout' => (int) env('HEALTH_ADVISORY_TIMEOUT', 8),
    ],
];
