<?php

use App\Enums\HealthStatus;
use App\Services\Health\Checks\QueueCheck;
use App\Services\Health\HealthMarkers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

function queueChecks(): array
{
    return checksByKey(app(QueueCheck::class));
}

it('escalates on queue depth', function (int $size, HealthStatus $expected) {
    Queue::shouldReceive('size')->andReturn($size);

    expect(queueChecks()['Q1']->status)->toBe($expected);
})->with([
    'idle' => [0, HealthStatus::OK],
    'below warn' => [499, HealthStatus::OK],
    'at warn' => [500, HealthStatus::WARN],
    'at crit' => [5000, HealthStatus::CRIT],
]);

it('counts only failures from the last 24 hours', function () {
    DB::table('failed_jobs')->insert([
        ['uuid' => 'old', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => '', 'failed_at' => now()->subDays(3)],
        ['uuid' => 'recent', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => '', 'failed_at' => now()->subHours(2)],
    ]);

    $check = queueChecks()['Q2'];

    // Old failures are history, not health — one recent failure is the signal.
    expect($check->value)->toBe('1 failed')
        ->and($check->status)->toBe(HealthStatus::WARN);
});

it('passes when nothing has failed recently', function () {
    expect(queueChecks()['Q2']->status)->toBe(HealthStatus::OK);
});

it('escalates when the worker stops consuming', function (?int $ageSeconds, HealthStatus $expected) {
    $ageSeconds === null
        ? HealthMarkers::store()->forget(HealthMarkers::QUEUE_BEAT)
        : HealthMarkers::store()->put(HealthMarkers::QUEUE_BEAT, time() - $ageSeconds, 3600);

    expect(queueChecks()['Q3']->status)->toBe($expected);
})->with([
    'beating' => [30, HealthStatus::OK],
    'at warn' => [300, HealthStatus::WARN],
    'at crit' => [900, HealthStatus::CRIT],
    'never run' => [null, HealthStatus::STALE],
]);
