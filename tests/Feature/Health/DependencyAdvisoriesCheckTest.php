<?php

use App\Enums\HealthStatus;
use App\Services\Health\Checks\DependencyAdvisoriesCheck;
use App\Services\Health\HealthMarkers;
use Illuminate\Support\Facades\Http;

function lockWith(array $prod = [], array $dev = []): void
{
    $section = fn (array $pkgs) => array_map(
        fn ($version, $name) => ['name' => $name, 'version' => $version],
        $pkgs,
        array_keys($pkgs),
    );

    fakeComposerLock(['packages' => $section($prod), 'packages-dev' => $section($dev)]);
}

/**
 * One stub, registered once, reading mutable state.
 *
 * Http::fake() MERGES stubs and the first match wins, so re-faking the same URL
 * mid-test silently keeps serving the original response — which looks exactly
 * like a caching bug in the check under test.
 */
class FakeAdvisoryFeed
{
    /** advisories array, or 'down' (503) or 'throw' (connection failure) */
    public static array|string $state = [];
}

function advisoryFeed(array|string $advisories): void
{
    FakeAdvisoryFeed::$state = $advisories;
}

function d1(): App\Dtos\HealthCheckResult
{
    return checksByKey(app(DependencyAdvisoriesCheck::class))['d1'];
}

beforeEach(function () {
    HealthMarkers::store()->clear();
    lockWith(prod: ['vendor/thing' => '1.0.0']);
    advisoryFeed([]);

    Http::fake(['packagist.org/*' => function () {
        return match (FakeAdvisoryFeed::$state) {
            'down' => Http::response('nope', 503),
            'throw' => throw new RuntimeException('network down'),
            default => Http::response(['advisories' => FakeAdvisoryFeed::$state], 200),
        };
    }]);
});

it('passes when nothing installed is affected', function () {
    advisoryFeed([]);

    expect(d1()->status)->toBe(HealthStatus::OK)
        ->and(d1()->value)->toBe('no known advisories');
});

it('ignores an advisory that does not match the installed version', function () {
    advisoryFeed(['vendor/thing' => [
        ['affectedVersions' => '<0.9.0', 'severity' => 'critical'],
    ]]);

    expect(d1()->status)->toBe(HealthStatus::OK);
});

it('goes critical on a high-severity advisory in a deployed package', function () {
    advisoryFeed(['vendor/thing' => [
        ['affectedVersions' => '>=1.0.0,<1.2.0', 'severity' => 'high'],
    ]]);

    expect(d1()->status)->toBe(HealthStatus::CRIT)
        ->and(d1()->value)->toContain('1 high');
});

it('treats an unrated advisory as critical', function () {
    // An advisory nobody scored is not an advisory nobody needs to read.
    advisoryFeed(['vendor/thing' => [
        ['affectedVersions' => '>=1.0.0', 'severity' => null],
    ]]);

    expect(d1()->status)->toBe(HealthStatus::CRIT)
        ->and(d1()->value)->toContain('1 unrated');
});

it('only warns on a medium advisory', function () {
    advisoryFeed(['vendor/thing' => [
        ['affectedVersions' => '>=1.0.0', 'severity' => 'medium'],
    ]]);

    expect(d1()->status)->toBe(HealthStatus::WARN);
});

it('caps a critical in a dev-only package at warn', function () {
    lockWith(prod: ['vendor/thing' => '1.0.0'], dev: ['vendor/testkit' => '2.0.0']);

    advisoryFeed(['vendor/testkit' => [
        ['affectedVersions' => '>=2.0.0', 'severity' => 'critical'],
    ]]);

    // Production installs --no-dev, so it is not on the box — but it is on
    // every developer machine and in CI, so it is not nothing either.
    expect(d1()->status)->toBe(HealthStatus::WARN)
        ->and(d1()->value)->toContain('dev-only');
});

it('warns rather than passing when the feed is unreachable and nothing is cached', function () {
    advisoryFeed('down');

    // An unreachable feed is never GREEN. "We could not ask" is not "clean".
    expect(d1()->status)->toBe(HealthStatus::WARN)
        ->and(d1()->value)->toBe('advisory feed unreachable');
});

it('serves the last known result when the feed goes down, and says it is old', function () {
    advisoryFeed(['vendor/thing' => [
        ['affectedVersions' => '>=1.0.0', 'severity' => 'high'],
    ]]);
    expect(d1()->status)->toBe(HealthStatus::CRIT);

    // Same finding, but now we cannot confirm it is still current.
    HealthMarkers::store()->forget(HealthMarkers::ADVISORIES.':'.substr(hash('sha256', file_get_contents(config('health.composer_lock_path'))), 0, 16));
    advisoryFeed('throw');

    expect(d1()->status)->toBe(HealthStatus::CRIT)
        ->and(d1()->value)->toContain('last known result');
});

it('re-queries as soon as the lock changes instead of serving a day-old clean bill', function () {
    advisoryFeed([]);
    expect(d1()->status)->toBe(HealthStatus::OK);

    // A deploy adds a vulnerable package. The 24h cache must not hide it.
    advisoryFeed(['vendor/other' => [
        ['affectedVersions' => '>=3.0.0', 'severity' => 'critical'],
    ]]);
    lockWith(prod: ['vendor/thing' => '1.0.0', 'vendor/other' => '3.1.0']);

    expect(d1()->status)->toBe(HealthStatus::CRIT);
});

it('counts an unmatchable constraint instead of silently passing it', function () {
    advisoryFeed(['vendor/thing' => [
        ['affectedVersions' => 'not-a-constraint', 'severity' => 'critical'],
    ]]);

    expect(d1()->status)->toBe(HealthStatus::WARN)
        ->and(d1()->value)->toContain('unmatched');
});

it('is stale, not clean, when the lock file cannot be read', function () {
    fakeComposerLock(null);
    advisoryFeed([]);

    expect(d1()->status)->toBe(HealthStatus::STALE);
});
