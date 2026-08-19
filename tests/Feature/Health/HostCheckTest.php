<?php

use App\Enums\HealthStatus;
use App\Services\Health\Checks\HostCheck;

afterEach(fn () => fakeHostFacts(null));

function hostChecks(array $overrides = []): array
{
    fakeHostFacts(array_merge([
        'ts' => time(),
        'cpu_pct' => 10,
        'mem_pct' => 10,
        'disk_root_pct' => 10,
        'units' => ['nginx' => 'active', 'mysql' => 'active'],
        'reboot_required' => 0,
    ], $overrides));

    return checksByKey(app(HostCheck::class));
}

it('escalates cpu exactly at the configured boundary', function (int $cpu, HealthStatus $expected) {
    expect(hostChecks(['cpu_pct' => $cpu])['H2']->status)->toBe($expected);
})->with([
    'below warn' => [69, HealthStatus::OK],
    'at warn' => [70, HealthStatus::WARN],
    'above warn' => [71, HealthStatus::WARN],
    'below crit' => [84, HealthStatus::WARN],
    'at crit' => [85, HealthStatus::CRIT],
]);

it('escalates memory and disk on their own thresholds', function () {
    $checks = hostChecks(['mem_pct' => 85, 'disk_root_pct' => 85]);

    expect($checks['H3']->status)->toBe(HealthStatus::WARN)   // memory warns at 85
        ->and($checks['H4']->status)->toBe(HealthStatus::CRIT); // disk crits at 85
});

it('reports host facts as critical when the file is missing entirely', function () {
    fakeHostFacts(null);
    fakeUptime(3600); // pinned: on a freshly-booted CI container this would otherwise hit boot grace

    $checks = checksByKey(app(HostCheck::class));

    // A monitor that has quietly gone blind is worse than no monitor, so H1
    // is CRIT rather than STALE — but the readings it feeds are only STALE,
    // because "no data" is not the same claim as "bad data".
    expect($checks['H1']->status)->toBe(HealthStatus::CRIT)
        ->and($checks['H1']->value)->toContain('no facts file')
        ->and($checks['H2']->status)->toBe(HealthStatus::STALE)
        ->and($checks['H4']->status)->toBe(HealthStatus::STALE);
});

it('escalates as the facts file goes stale', function (int $ageSeconds, HealthStatus $expected) {
    expect(hostChecks(['ts' => time() - $ageSeconds])['H1']->status)->toBe($expected);
})->with([
    'fresh' => [10, HealthStatus::OK],
    'at warn' => [180, HealthStatus::WARN],
    'at crit' => [600, HealthStatus::CRIT],
]);

it('maps systemd unit states to statuses', function (string $state, HealthStatus $expected) {
    $checks = hostChecks(['units' => ['nginx' => $state]]);

    expect($checks['H5-nginx']->status)->toBe($expected);
})->with([
    'active' => ['active', HealthStatus::OK],
    'activating' => ['activating', HealthStatus::WARN],
    'reloading' => ['reloading', HealthStatus::WARN],
    'inactive' => ['inactive', HealthStatus::CRIT],
    'failed' => ['failed', HealthStatus::CRIT],
]);

it('tags each unit to the node it actually belongs to', function () {
    $checks = hostChecks();

    // A dead MySQL unit has to redden the data column, not the host column,
    // or the map stops pointing at where the problem is.
    expect($checks['H5-mysql']->node)->toBe('data')
        ->and($checks['H5-nginx']->node)->toBe('edge')
        ->and($checks['H2']->node)->toBe('host');
});

it('reports an unknown unit as stale rather than down', function () {
    $checks = hostChecks(['units' => ['nginx' => 'active']]);

    expect($checks['H5-mysql']->status)->toBe(HealthStatus::STALE)
        ->and($checks['H5-mysql']->value)->toBe('no data');
});

it('warns but never crits on a pending reboot', function () {
    expect(hostChecks(['reboot_required' => 1])['H6']->status)->toBe(HealthStatus::WARN)
        ->and(hostChecks(['reboot_required' => 0])['H6']->status)->toBe(HealthStatus::OK);
});

it('does not call a just-booted host a dead cron', function () {
    // The production case: a reboot wipes the tmpfs facts file, so for the
    // first minute or two there is nothing to read. Reporting that as CRIT
    // turned a routine reboot into a false outage alert.
    fakeHostFacts(null);
    fakeUptime(30);

    $check = checksByKey(app(HostCheck::class))['H1'];

    expect($check->status)->toBe(HealthStatus::STALE)
        ->and($check->value)->toContain('booted')
        ->and($check->value)->toContain('awaiting first write');
});

it('does call it a dead cron once the grace window has passed', function () {
    fakeHostFacts(null);
    fakeUptime(3600);

    $check = checksByKey(app(HostCheck::class))['H1'];

    expect($check->status)->toBe(HealthStatus::CRIT)
        ->and($check->value)->toContain('no facts file');
});

it('still reports missing facts as critical when uptime is unknowable', function () {
    fakeHostFacts(null);
    fakeUptime(null);

    // Off Linux there is no /proc/uptime. Fail toward reporting the problem.
    expect(checksByKey(app(HostCheck::class))['H1']->status)->toBe(HealthStatus::CRIT);
});
