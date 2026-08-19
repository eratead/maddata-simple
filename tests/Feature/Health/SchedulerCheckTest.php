<?php

use App\Enums\HealthStatus;
use App\Services\Health\Checks\SchedulerCheck;
use App\Services\Health\HealthMarkers;

function schedulerChecks(array $markers): array
{
    foreach ([
        HealthMarkers::SCHEDULER_BEAT,
        HealthMarkers::CAMPAIGN_STATUS_OK,
        HealthMarkers::ACTIVITY_DIGEST_OK,
    ] as $key) {
        array_key_exists($key, $markers) && $markers[$key] !== null
            ? HealthMarkers::store()->put($key, time() - $markers[$key], 86400)
            : HealthMarkers::store()->forget($key);
    }

    return checksByKey(app(SchedulerCheck::class));
}

it('escalates when cron stops reaching laravel', function (?int $age, HealthStatus $expected) {
    expect(schedulerChecks([HealthMarkers::SCHEDULER_BEAT => $age])['S1']->status)->toBe($expected);
})->with([
    'beating' => [30, HealthStatus::OK],
    'at warn' => [300, HealthStatus::WARN],
    'at crit' => [900, HealthStatus::CRIT],
    'never' => [null, HealthStatus::STALE],
]);

it('escalates on the daily campaign status job', function (?int $age, HealthStatus $expected) {
    expect(schedulerChecks([HealthMarkers::CAMPAIGN_STATUS_OK => $age])['S2a']->status)->toBe($expected);
})->with([
    'ran today' => [3600, HealthStatus::OK],
    'missed a day' => [93600, HealthStatus::WARN],
    'missed two days' => [180000, HealthStatus::CRIT],
]);

it('escalates on the two-hourly digest job', function (?int $age, HealthStatus $expected) {
    expect(schedulerChecks([HealthMarkers::ACTIVITY_DIGEST_OK => $age])['S2b']->status)->toBe($expected);
})->with([
    'recent' => [1800, HealthStatus::OK],
    'missed a run' => [10800, HealthStatus::WARN],
    'missed several' => [21600, HealthStatus::CRIT],
]);

it('reports a never-run job as stale, not as an outage', function () {
    $checks = schedulerChecks([]);

    // A fresh deploy has not run the daily job yet. That is unknown, not broken.
    expect($checks['S2a']->status)->toBe(HealthStatus::STALE)
        ->and($checks['S2a']->value)->toBe('has never completed');
});

it('tags scheduler checks to the workers node', function () {
    foreach (schedulerChecks([]) as $check) {
        expect($check->node)->toBe('workers');
    }
});
