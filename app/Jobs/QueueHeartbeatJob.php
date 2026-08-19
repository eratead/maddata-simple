<?php

namespace App\Jobs;

use App\Services\Health\HealthMarkers;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Proof of life for the queue worker (check Q3).
 *
 * Dispatched every minute by the scheduler; writes its marker only when it
 * actually EXECUTES on a worker. That distinction is the whole value: systemd
 * reports "active" for a worker that is wedged, out of memory, or stuck on a
 * poisoned job, and only a job that completes disproves all three.
 *
 * Deliberately does no work and holds no state — it must never be the job that
 * poisons the queue it is watching.
 */
class QueueHeartbeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** One attempt: a heartbeat worth retrying is already a stale heartbeat. */
    public int $tries = 1;

    public int $timeout = 10;

    public function handle(): void
    {
        HealthMarkers::record(HealthMarkers::QUEUE_BEAT);
    }
}
