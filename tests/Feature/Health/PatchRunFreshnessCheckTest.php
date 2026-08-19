<?php

use App\Enums\HealthStatus;
use App\Services\Health\Checks\PatchRunFreshnessCheck;
use App\Services\Health\HealthMarkers;

function d4(): App\Dtos\HealthCheckResult
{
    return checksByKey(app(PatchRunFreshnessCheck::class))['d4'];
}

function markPatchRun(int $daysAgo, ?string $lockSha = null): void
{
    HealthMarkers::store()->forever(HealthMarkers::PATCH_RUN, [
        'ts' => now()->subDays($daysAgo)->getTimestamp(),
        'lock_sha' => $lockSha ?? hash_file('sha256', config('health.composer_lock_path')),
        'note' => '',
    ]);
}

beforeEach(function () {
    HealthMarkers::store()->forget(HealthMarkers::PATCH_RUN);
    HealthMarkers::store()->forget(HealthMarkers::ADVISORIES_WORST);
    fakeComposerLock(['packages' => [], 'packages-dev' => []]);
});

it('warns rather than going stale when nobody has ever patched', function () {
    // "Nobody has ever patched this box" is a fact about the box, not missing
    // data about it — so it warns instead of reading as no-data.
    expect(d4()->status)->toBe(HealthStatus::WARN)
        ->and(d4()->status)->not->toBe(HealthStatus::STALE)
        ->and(d4()->value)->toContain('never recorded');
});

it('passes a recent patch run', function () {
    markPatchRun(daysAgo: 5);

    expect(d4()->status)->toBe(HealthStatus::OK);
});

it('warns after five weeks', function () {
    markPatchRun(daysAgo: 36);

    expect(d4()->status)->toBe(HealthStatus::WARN);
});

it('goes critical after two months', function () {
    markPatchRun(daysAgo: 61);

    expect(d4()->status)->toBe(HealthStatus::CRIT);
});

it('ignores lock drift while nothing known-bad is in the tree', function () {
    markPatchRun(daysAgo: 5, lockSha: 'a-lock-that-is-no-longer-deployed');

    // Every `composer require` changes the lock. Warning on that alone would
    // turn d4 amber for the GOOD case and keep it there until someone ran a
    // command — the un-actionable warning this vertical keeps designing out.
    expect(d4()->status)->toBe(HealthStatus::OK);
});

it('warns when the lock has drifted AND d1 reports high-severity advisories', function () {
    HealthMarkers::store()->put(HealthMarkers::ADVISORIES_WORST, HealthStatus::CRIT->value, now()->addDay());
    markPatchRun(daysAgo: 5, lockSha: 'a-lock-that-is-no-longer-deployed');

    // Now the recorded patch run demonstrably does not describe what is
    // deployed, and what is deployed has known criticals in it.
    expect(d4()->status)->toBe(HealthStatus::WARN)
        ->and(d4()->value)->toContain('composer.lock has changed');
});

it('records the lock hash when the marker command runs', function () {
    $this->artisan('deps:mark-patch-run', ['--note' => 'security round'])->assertSuccessful();

    expect(d4()->status)->toBe(HealthStatus::OK)
        ->and(d4()->value)->toContain('security round');
});
