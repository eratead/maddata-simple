<?php

use App\Enums\HealthStatus;
use App\Services\Health\Checks\OsPatchCheck;
use App\Services\Health\HealthMarkers;

function osFacts(array $overrides = []): void
{
    fakeHostFacts(array_merge([
        'ts' => now()->getTimestamp(),
        'pending_security' => 0,
        'reboot_required' => 0,
    ], $overrides));
}

function d3(string $key = 'd3'): App\Dtos\HealthCheckResult
{
    return checksByKey(app(OsPatchCheck::class))[$key];
}

beforeEach(function () {
    HealthMarkers::store()->forget(HealthMarkers::OS_PATCH_SINCE);
});

it('passes when nothing is pending', function () {
    osFacts(['pending_security' => 0]);

    expect(d3()->status)->toBe(HealthStatus::OK)
        ->and(d3()->value)->toBe('none pending');
});

it('reports a fresh backlog without crying about it', function () {
    osFacts(['pending_security' => 3]);

    // unattended-upgrades runs daily. Pending-since-this-morning is normal.
    expect(d3()->status)->toBe(HealthStatus::OK)
        ->and(d3()->value)->toContain('3 pending');
});

it('warns once a backlog has sat for a week', function () {
    osFacts(['pending_security' => 3]);
    HealthMarkers::store()->forever(HealthMarkers::OS_PATCH_SINCE, [
        'count' => 3,
        'first_seen' => now()->subDays(8)->getTimestamp(),
    ]);

    expect(d3()->status)->toBe(HealthStatus::WARN);
});

it('goes critical when a backlog has sat for a month', function () {
    osFacts(['pending_security' => 3]);
    HealthMarkers::store()->forever(HealthMarkers::OS_PATCH_SINCE, [
        'count' => 3,
        'first_seen' => now()->subDays(31)->getTimestamp(),
    ]);

    // Thirty days of pending security updates means unattended-upgrades is
    // broken, which is a different problem from being a bit behind.
    expect(d3()->status)->toBe(HealthStatus::CRIT);
});

it('restarts the clock when the backlog shrinks', function () {
    osFacts(['pending_security' => 3]);
    HealthMarkers::store()->forever(HealthMarkers::OS_PATCH_SINCE, [
        'count' => 9,
        'first_seen' => now()->subDays(20)->getTimestamp(),
    ]);

    // Patches are landing. The clock should not keep running on progress.
    expect(d3()->status)->toBe(HealthStatus::OK);
});

it('clears the marker once the backlog is gone', function () {
    HealthMarkers::store()->forever(HealthMarkers::OS_PATCH_SINCE, [
        'count' => 3,
        'first_seen' => now()->subDays(40)->getTimestamp(),
    ]);
    osFacts(['pending_security' => 0]);

    expect(d3()->status)->toBe(HealthStatus::OK)
        ->and(HealthMarkers::store()->get(HealthMarkers::OS_PATCH_SINCE))->toBeNull();
});

it('is stale, never clean, when the count could not be computed', function () {
    osFacts(['pending_security' => null]);

    // THE test for this check. The facts script writes null when apt cannot be
    // asked; a monitor that cannot count must not report "clean".
    expect(d3()->status)->toBe(HealthStatus::STALE)
        ->and(d3()->status)->not->toBe(HealthStatus::OK)
        ->and(d3()->value)->toBe('count unavailable');
});

it('is stale when there are no facts at all', function () {
    fakeHostFacts(null);

    expect(d3()->status)->toBe(HealthStatus::STALE);
});

it('warns when the facts have gone stale rather than reporting old numbers as current', function () {
    osFacts(['ts' => now()->subDays(3)->getTimestamp(), 'pending_security' => 0]);

    expect(d3()->status)->toBe(HealthStatus::WARN)
        ->and(d3()->value)->toContain('old');
});

it('warns while a reboot is owed', function () {
    osFacts(['reboot_required' => 1]);

    // A kernel patch that has not been booted into is not applied.
    expect(d3('d3b')->status)->toBe(HealthStatus::WARN)
        ->and(d3('d3b')->value)->toBe('reboot required');
});

it('passes when no reboot is owed', function () {
    osFacts(['reboot_required' => 0]);

    expect(d3('d3b')->status)->toBe(HealthStatus::OK);
});
