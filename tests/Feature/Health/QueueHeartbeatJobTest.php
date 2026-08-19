<?php

use App\Jobs\QueueHeartbeatJob;
use App\Services\Health\HealthMarkers;

it('writes its marker when it actually executes', function () {
    HealthMarkers::store()->forget(HealthMarkers::QUEUE_BEAT);

    (new QueueHeartbeatJob)->handle();

    expect(HealthMarkers::store()->get(HealthMarkers::QUEUE_BEAT))
        ->toBeInt()
        ->toBeGreaterThan(now()->getTimestamp() - 5);
});

it('advances the marker on a later run', function () {
    HealthMarkers::store()->put(HealthMarkers::QUEUE_BEAT, now()->subHour()->getTimestamp(), 3600);

    (new QueueHeartbeatJob)->handle();

    expect(HealthMarkers::store()->get(HealthMarkers::QUEUE_BEAT))
        ->toBeGreaterThan(now()->subMinute()->getTimestamp());
});

it('does not retry — a heartbeat worth retrying is already stale', function () {
    // Q3's whole value is proving the worker is CURRENT. A retried heartbeat
    // would report liveness at a time the worker was not actually live.
    expect((new QueueHeartbeatJob)->tries)->toBe(1)
        ->and((new QueueHeartbeatJob)->timeout)->toBe(10);
});

it('is dispatched through the queue, proving the worker consumes', function () {
    Queue::fake();

    QueueHeartbeatJob::dispatch();

    Queue::assertPushed(QueueHeartbeatJob::class);
});
