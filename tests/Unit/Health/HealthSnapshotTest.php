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

/*
|--------------------------------------------------------------------------
| The alerting split (Phase 4)
|--------------------------------------------------------------------------
|
| A check's NODE decides its delivery channel. The property that has to hold is
| that routing a node away from alerting cannot silence anything that was
| alerting before — so these assert the partition AND the signature stability.
|
*/

beforeEach(function () {
    config(['health.alert_excluded_nodes' => ['platform']]);
});

it('splits failing checks into the ones that page and the ones that do not', function () {
    $snapshot = HealthSnapshot::fromResults([
        result('D1', HealthStatus::CRIT, 'data'),
        result('d1', HealthStatus::CRIT, 'platform'),
        result('Q1', HealthStatus::OK, 'workers'),
    ]);

    expect(array_map(fn ($c) => $c->key, $snapshot->alertable()))->toBe(['D1'])
        ->and(array_map(fn ($c) => $c->key, $snapshot->digestable()))->toBe(['d1']);
});

it('keeps overall honest even though alerting ignores the platform node', function () {
    $snapshot = HealthSnapshot::fromResults([
        result('D1', HealthStatus::OK, 'data'),
        result('d1', HealthStatus::CRIT, 'platform'),
    ]);

    // The page and the pill still go red. The page is pull; only push is filtered.
    expect($snapshot->overall)->toBe(HealthStatus::CRIT)
        ->and($snapshot->alertStatus())->toBe(HealthStatus::OK);
});

it('takes the worst of the alertable half only', function () {
    $snapshot = HealthSnapshot::fromResults([
        result('D1', HealthStatus::WARN, 'data'),
        result('d1', HealthStatus::CRIT, 'platform'),
    ]);

    expect($snapshot->alertStatus())->toBe(HealthStatus::WARN);
});

it('does not move the alert signature when a digest-only check flips', function () {
    $before = HealthSnapshot::fromResults([
        result('D1', HealthStatus::CRIT, 'data'),
    ])->signature();

    $after = HealthSnapshot::fromResults([
        result('D1', HealthStatus::CRIT, 'data'),
        result('d1', HealthStatus::CRIT, 'platform'),
    ])->signature();

    // If this moved, a new advisory would read as "something new broke" and
    // page someone about a dependency at 2am.
    expect($after)->toBe($before);
});

it('reads as all-ok for alerting when only digest-owned checks are failing', function () {
    $snapshot = HealthSnapshot::fromResults([
        result('d1', HealthStatus::CRIT, 'platform'),
    ]);

    expect($snapshot->signature())->toBe('all-ok');
});

it('alerts on everything when the exclusion list is emptied', function () {
    config(['health.alert_excluded_nodes' => []]);

    $snapshot = HealthSnapshot::fromResults([
        result('d1', HealthStatus::CRIT, 'platform'),
    ]);

    // The whole reversal, in one config line.
    expect($snapshot->alertStatus())->toBe(HealthStatus::CRIT)
        ->and($snapshot->digestable())->toBe([]);
});

/*
|--------------------------------------------------------------------------
| The split must not silence the monitor's own failures (audit M-1/M-2)
|--------------------------------------------------------------------------
|
| Routing the `platform` node to the weekly digest was meant to stop composer
| advisories paging anyone. It also, unintentionally, silenced two things that
| must page: a check class crashing, and a live credential-stuffing attack.
|
*/

it('alerts when a check class crashes, rather than filing it under dependencies', function () {
    // SystemHealthService tags a crashed check class with this node. If that
    // node is digest-only, "the monitor is broken" waits until Monday.
    $snapshot = HealthSnapshot::fromResults([
        result('HostCheck', HealthStatus::CRIT, 'app'),
    ]);

    expect($snapshot->alertStatus())->toBe(HealthStatus::CRIT)
        ->and($snapshot->digestable())->toBe([]);
});

it('alerts on a failed-login burst', function () {
    // X2 is the only check in the catalog that detects an attack in progress.
    $snapshot = HealthSnapshot::fromResults([
        result('X2', HealthStatus::CRIT, 'app'),
    ]);

    expect($snapshot->alertStatus())->toBe(HealthStatus::CRIT);
});
