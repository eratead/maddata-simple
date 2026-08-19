<?php

use App\Enums\HealthStatus;

it('picks the worst status', function (array $input, HealthStatus $expected) {
    expect(HealthStatus::worstOf(...$input))->toBe($expected);
})->with([
    'empty is healthy' => [[], HealthStatus::OK],
    'all ok' => [[HealthStatus::OK, HealthStatus::OK], HealthStatus::OK],
    'warn beats ok' => [[HealthStatus::OK, HealthStatus::WARN], HealthStatus::WARN],
    'crit beats warn' => [[HealthStatus::WARN, HealthStatus::CRIT], HealthStatus::CRIT],
    'stale beats ok' => [[HealthStatus::OK, HealthStatus::STALE], HealthStatus::STALE],
    'warn beats stale' => [[HealthStatus::STALE, HealthStatus::WARN], HealthStatus::WARN],
    'crit survives stale' => [[HealthStatus::STALE, HealthStatus::CRIT], HealthStatus::CRIT],
    'order does not matter' => [[HealthStatus::CRIT, HealthStatus::OK, HealthStatus::WARN], HealthStatus::CRIT],
]);

it('never lets missing data mask a real outage', function () {
    // The subtle one: STALE outranks OK so it is visible, but must never
    // outrank CRIT, or one blind check would hide a genuine failure.
    expect(HealthStatus::worstOf(HealthStatus::STALE, HealthStatus::CRIT))->toBe(HealthStatus::CRIT)
        ->and(HealthStatus::STALE->severity())->toBeLessThan(HealthStatus::CRIT->severity())
        ->and(HealthStatus::STALE->severity())->toBeGreaterThan(HealthStatus::OK->severity());
});

it('collapses stale to warn for the pill', function () {
    expect(HealthStatus::STALE->forPill())->toBe(HealthStatus::WARN)
        ->and(HealthStatus::OK->forPill())->toBe(HealthStatus::OK)
        ->and(HealthStatus::WARN->forPill())->toBe(HealthStatus::WARN)
        ->and(HealthStatus::CRIT->forPill())->toBe(HealthStatus::CRIT);
});

it('treats anything but ok as failing', function () {
    expect(HealthStatus::OK->isFailing())->toBeFalse()
        ->and(HealthStatus::WARN->isFailing())->toBeTrue()
        ->and(HealthStatus::CRIT->isFailing())->toBeTrue()
        ->and(HealthStatus::STALE->isFailing())->toBeTrue();
});

it('maps statuses to shell exit codes', function () {
    expect(HealthStatus::OK->exitCode())->toBe(0)
        ->and(HealthStatus::WARN->exitCode())->toBe(1)
        ->and(HealthStatus::STALE->exitCode())->toBe(1)
        ->and(HealthStatus::CRIT->exitCode())->toBe(2);
});
