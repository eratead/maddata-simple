<?php

use App\Dtos\HealthCheckResult;
use App\Dtos\HealthSnapshot;
use App\Enums\HealthStatus;

uses(Tests\TestCase::class);

function result(string $key, HealthStatus $status, string $node = 'data'): HealthCheckResult
{
    return new HealthCheckResult(
        key: $key,
        label: $key.' label',
        status: $status,
        node: $node,
        value: 'v',
        threshold: 't',
    );
}

it('rolls each node up to its worst check', function () {
    $snapshot = HealthSnapshot::fromResults([
        result('A', HealthStatus::OK, 'data'),
        result('B', HealthStatus::CRIT, 'data'),
        result('C', HealthStatus::OK, 'edge'),
    ]);

    $byKey = collect($snapshot->nodes)->keyBy('key');

    expect($byKey['data']['status'])->toBe(HealthStatus::CRIT)
        ->and($byKey['edge']['status'])->toBe(HealthStatus::OK)
        ->and($snapshot->overall)->toBe(HealthStatus::CRIT);
});

it('emits nodes in config order and omits nodes with no checks', function () {
    $snapshot = HealthSnapshot::fromResults([
        result('A', HealthStatus::OK, 'backups'),
        result('B', HealthStatus::OK, 'edge'),
    ]);

    // asserted against config('health.nodes') order, whatever it is — the
    // point is that node order follows config and skips empty nodes
    expect(array_column($snapshot->nodes, 'key'))->toBe(['edge', 'backups']);
});

it('orders failing checks worst first', function () {
    $snapshot = HealthSnapshot::fromResults([
        result('ok', HealthStatus::OK),
        result('warn', HealthStatus::WARN),
        result('crit', HealthStatus::CRIT),
        result('stale', HealthStatus::STALE),
    ]);

    expect(array_map(fn ($c) => $c->key, $snapshot->failing()))
        ->toBe(['crit', 'warn', 'stale']);
});

it('builds a stable signature from the failing set', function () {
    $a = HealthSnapshot::fromResults([result('B', HealthStatus::CRIT), result('A', HealthStatus::WARN)]);
    $b = HealthSnapshot::fromResults([result('A', HealthStatus::WARN), result('B', HealthStatus::CRIT)]);

    // Same problems reported in a different order must not read as a new alert.
    expect($a->signature())->toBe($b->signature());

    $healthy = HealthSnapshot::fromResults([result('A', HealthStatus::OK)]);
    expect($healthy->signature())->toBe('all-ok');
});

it('changes signature when the problem set changes', function () {
    $one = HealthSnapshot::fromResults([result('A', HealthStatus::WARN)]);
    $worse = HealthSnapshot::fromResults([result('A', HealthStatus::CRIT)]);
    $more = HealthSnapshot::fromResults([result('A', HealthStatus::WARN), result('B', HealthStatus::WARN)]);

    expect($one->signature())->not->toBe($worse->signature())
        ->and($one->signature())->not->toBe($more->signature());
});

it('survives a round trip through the cached array form', function () {
    $original = HealthSnapshot::fromResults([
        result('A', HealthStatus::WARN, 'data'),
        result('B', HealthStatus::OK, 'edge'),
    ]);

    $restored = HealthSnapshot::fromArray($original->toArray());

    expect($restored->toArray())->toBe($original->toArray())
        ->and($restored->overall)->toBe(HealthStatus::WARN)
        ->and($restored->checks[0]->status)->toBe(HealthStatus::WARN);
});
