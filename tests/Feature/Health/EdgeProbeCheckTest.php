<?php

use App\Enums\HealthStatus;
use App\Services\Health\Checks\EdgeProbeCheck;
use App\Services\Health\HealthMarkers;

afterEach(fn () => fakeHostFacts(null));

function edgeChecks(?array $probe = null, array $facts = []): array
{
    fakeHostFacts(array_merge(['ts' => time(), 'tls_days_remaining' => 60], $facts));

    $probe === null
        ? HealthMarkers::store()->forget(HealthMarkers::PUBLIC_PROBE)
        : HealthMarkers::store()->put(HealthMarkers::PUBLIC_PROBE, $probe, 3600);

    return checksByKey(app(EdgeProbeCheck::class));
}

it('is stale before the probe has ever run', function () {
    $check = edgeChecks(null)['P1'];

    expect($check->status)->toBe(HealthStatus::STALE)
        ->and($check->value)->toBe('never run');
});

it('passes a fast successful probe', function () {
    $check = edgeChecks(['ok' => true, 'latency_ms' => 180, 'consec_fails' => 0])['P1'];

    expect($check->status)->toBe(HealthStatus::OK)
        ->and($check->value)->toBe('180ms');
});

it('warns on a slow but successful probe', function () {
    expect(edgeChecks(['ok' => true, 'latency_ms' => 800, 'consec_fails' => 0])['P1']->status)
        ->toBe(HealthStatus::WARN);
});

it('needs two consecutive failures before calling it an outage', function (int $fails, HealthStatus $expected) {
    // One blip during a deploy is not an outage; two in a row is. This is the
    // rule that stops the monitor crying wolf on every restart.
    expect(edgeChecks(['ok' => false, 'latency_ms' => 0, 'consec_fails' => $fails])['P1']->status)
        ->toBe($expected);
})->with([
    'one failure' => [1, HealthStatus::WARN],
    'two failures' => [2, HealthStatus::CRIT],
    'many failures' => [9, HealthStatus::CRIT],
]);

it('escalates as the TLS certificate approaches expiry', function (?int $days, HealthStatus $expected) {
    expect(edgeChecks(['ok' => true, 'latency_ms' => 10, 'consec_fails' => 0], ['tls_days_remaining' => $days])['P2']->status)
        ->toBe($expected);
})->with([
    'comfortable' => [60, HealthStatus::OK],
    'just above warn' => [22, HealthStatus::OK],
    'at warn' => [21, HealthStatus::WARN],
    'at crit' => [7, HealthStatus::CRIT],
    'expired' => [-1, HealthStatus::CRIT],
    'unknown' => [null, HealthStatus::STALE],
]);
