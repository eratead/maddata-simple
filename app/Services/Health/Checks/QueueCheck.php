<?php

namespace App\Services\Health\Checks;

use App\Dtos\HealthCheckResult;
use App\Services\Health\HealthMarkers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Q1-Q3 — the queue.
 *
 * Q3 is the one that matters. systemd happily reports "active" for a worker
 * that is wedged, out of memory, or chewing on a poisoned job; only a job that
 * actually EXECUTES proves the pipeline works end to end. Q1 and Q2 tell you
 * how bad it got, Q3 tells you it happened.
 */
class QueueCheck extends HealthCheck
{
    public function run(): array
    {
        return [
            $this->depth(),
            $this->failedJobs(),
            $this->workerHeartbeat(),
        ];
    }

    /** Q1 — backlog on the default connection. */
    private function depth(): HealthCheckResult
    {
        return $this->guard('Q1', 'Queue depth', 'workers', function () {
            $thresholds = $this->thresholds('queue_depth');
            $size = Queue::size();

            return new HealthCheckResult(
                key: 'Q1',
                label: 'Queue depth',
                status: $this->evaluateOver($size, $thresholds),
                node: 'workers',
                value: $size.' queued',
                threshold: $this->describeThreshold($thresholds, ' jobs'),
            );
        });
    }

    /** Q2 — failures in the last 24h, not all-time: old failures are history. */
    private function failedJobs(): HealthCheckResult
    {
        return $this->guard('Q2', 'Failed jobs (24h)', 'workers', function () {
            $thresholds = $this->thresholds('failed_jobs_24h');

            $count = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->count();

            return new HealthCheckResult(
                key: 'Q2',
                label: 'Failed jobs (24h)',
                status: $this->evaluateOver($count, $thresholds),
                node: 'workers',
                value: $count.' failed',
                threshold: $this->describeThreshold($thresholds, ' in 24h'),
            );
        });
    }

    /** Q3 — proof the worker is consuming, not merely running. */
    private function workerHeartbeat(): HealthCheckResult
    {
        return $this->guard('Q3', 'Queue worker heartbeat', 'workers', function () {
            $ts = $this->store()->get(HealthMarkers::QUEUE_BEAT);

            return $this->ageResult(
                key: 'Q3',
                label: 'Queue worker heartbeat',
                node: 'workers',
                timestamp: is_numeric($ts) ? (int) $ts : null,
                thresholds: $this->thresholds('queue_beat_age'),
                missingValue: 'no heartbeat job has run',
            );
        });
    }
}
