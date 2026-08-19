<?php

use App\Dtos\HealthCheckResult;
use App\Dtos\HealthSnapshot;
use App\Enums\HealthStatus;
use App\Services\Health\Checks\HealthCheck;
use App\Services\Health\HealthMarkers;
use App\Services\Health\SystemHealthService;
use Illuminate\Support\Facades\Cache;

/** A check that blows up — the failure mode the whole design guards against. */
class ExplodingCheck extends HealthCheck
{
    public function run(): array
    {
        throw new RuntimeException('the thing I monitor is on fire');
    }
}

/** A well-behaved check that counts how often it was asked to run. */
class CountingCheck extends HealthCheck
{
    public static int $runs = 0;

    public static HealthStatus $status = HealthStatus::OK;

    public function run(): array
    {
        self::$runs++;

        return [new HealthCheckResult(
            key: 'T1',
            label: 'Counting check',
            status: self::$status,
            node: 'data',
            value: 'counted',
        )];
    }
}

beforeEach(function () {
    CountingCheck::$runs = 0;
    CountingCheck::$status = HealthStatus::OK;
    HealthMarkers::store()->forget(HealthMarkers::SNAPSHOT);
    HealthMarkers::store()->forget(HealthMarkers::SNAPSHOT_LAST);
    config(['health.checks' => [CountingCheck::class]]);
});

it('builds a snapshot from the configured checks', function () {
    $snapshot = app(SystemHealthService::class)->refresh();

    expect($snapshot->overall)->toBe(HealthStatus::OK)
        ->and($snapshot->checks)->toHaveCount(1)
        ->and($snapshot->checks[0]->key)->toBe('T1');
});

it('still builds a snapshot when a check class throws', function () {
    config(['health.checks' => [ExplodingCheck::class, CountingCheck::class]]);

    $snapshot = app(SystemHealthService::class)->refresh();

    // Resilience is THE property: one exploding check costs us that check,
    // never the other twenty. The monitor has to work when the system doesn't.
    $keys = array_map(fn ($c) => $c->key, $snapshot->checks);

    expect($keys)->toContain('T1')
        ->and($keys)->toContain('ExplodingCheck')
        ->and($snapshot->overall)->toBe(HealthStatus::CRIT);

    $exploded = collect($snapshot->checks)->firstWhere('key', 'ExplodingCheck');
    expect($exploded->status)->toBe(HealthStatus::CRIT)
        ->and($exploded->value)->toBe('check failed');
});

it('does not rebuild while a fresh snapshot is cached', function () {
    $service = app(SystemHealthService::class);

    $service->refresh();
    expect(CountingCheck::$runs)->toBe(1);

    $service->snapshot();
    $service->snapshot();

    expect(CountingCheck::$runs)->toBe(1);
});

it('serves the last known good snapshot while another process is rebuilding', function () {
    $service = app(SystemHealthService::class);
    $service->refresh();

    // Simulate a rebuild in flight elsewhere: TTL key gone, lock held.
    HealthMarkers::store()->forget(HealthMarkers::SNAPSHOT);
    $lock = HealthMarkers::store()->lock(HealthMarkers::SNAPSHOT_LOCK, 20);
    expect($lock->get())->toBeTrue();

    CountingCheck::$runs = 0;
    $snapshot = $service->snapshot();

    // N open admin tabs must not stampede a rebuild that costs a file read,
    // a MySQL round trip and a Redis INFO.
    expect(CountingCheck::$runs)->toBe(0)
        ->and($snapshot->checks[0]->key)->toBe('T1');

    $lock->release();
});

it('keeps a last known good snapshot with no expiry', function () {
    app(SystemHealthService::class)->refresh();

    expect(HealthMarkers::store()->get(HealthMarkers::SNAPSHOT))->toBeArray()
        ->and(HealthMarkers::store()->get(HealthMarkers::SNAPSHOT_LAST))->toBeArray();
});

it('caches the array form rather than the object', function () {
    app(SystemHealthService::class)->refresh();

    // A serialized DTO written before a deploy that changes the class would
    // fatal on unserialize; the monitor must survive its own deploys.
    $cached = HealthMarkers::store()->get(HealthMarkers::SNAPSHOT);

    expect($cached)->toBeArray()
        ->and(HealthSnapshot::fromArray($cached)->overall)->toBe(HealthStatus::OK);
});

it('reports an unknown pill instead of rebuilding when nothing is cached', function () {
    $status = app(SystemHealthService::class)->pillStatus();

    // Rendering a pill must never trigger a rebuild and slow an unrelated page.
    expect($status)->toBe(HealthStatus::STALE)
        ->and(CountingCheck::$runs)->toBe(0);
});

it('collapses a stale pill to warn once a snapshot exists', function () {
    CountingCheck::$status = HealthStatus::STALE;
    app(SystemHealthService::class)->refresh();

    expect(app(SystemHealthService::class)->pillStatus())->toBe(HealthStatus::WARN);
});

it('never throws from the pill even when the cache is broken', function () {
    Cache::shouldReceive('store')->andThrow(new RuntimeException('cache is down'));

    expect(app(SystemHealthService::class)->pillStatus())->toBe(HealthStatus::STALE);
});

it('returns the computed snapshot even when it cannot be cached', function () {
    $service = app(SystemHealthService::class);

    Cache::shouldReceive('store')->andThrow(new RuntimeException('cache is down'));

    // A cache write failing is itself a signal, but it must not swallow the
    // answer we just spent a full rebuild computing.
    expect($service->refresh()->checks[0]->key)->toBe('T1');
});

it('still builds a snapshot when the cache store is entirely unavailable', function () {
    Cache::shouldReceive('store')->andThrow(new RuntimeException('redis is down'));

    // Redis being down is one of the things this monitor exists to report.
    // A broken cache must never be the reason it goes silent about the
    // broken cache.
    $snapshot = app(SystemHealthService::class)->snapshot();

    expect($snapshot->checks)->toHaveCount(1)
        ->and($snapshot->checks[0]->key)->toBe('T1');
});
