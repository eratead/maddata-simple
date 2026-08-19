<?php

namespace App\Services\Health\Checks;

use App\Services\Health\HealthMarkers;

/**
 * S1-S2 — cron and the jobs it is supposed to run.
 *
 * S1 proves cron reaches Laravel at all. S2a/S2b prove the individual scheduled
 * commands are still completing: a scheduler that runs but whose jobs all throw
 * looks identical to a healthy one from the outside.
 */
class SchedulerCheck extends HealthCheck
{
    public function run(): array
    {
        return [
            $this->marker('S1', 'Scheduler heartbeat', HealthMarkers::SCHEDULER_BEAT, 'scheduler_beat_age', 'cron may not be running'),
            $this->marker('S2a', 'Campaign status job', HealthMarkers::CAMPAIGN_STATUS_OK, 'campaign_status_age', 'has never completed'),
            $this->marker('S2b', 'Activity digest job', HealthMarkers::ACTIVITY_DIGEST_OK, 'activity_digest_age', 'has never completed'),
        ];
    }

    private function marker(string $key, string $label, string $markerKey, string $thresholdKey, string $missing): \App\Dtos\HealthCheckResult
    {
        return $this->guard($key, $label, 'workers', function () use ($key, $label, $markerKey, $thresholdKey, $missing) {
            $ts = $this->store()->get($markerKey);

            return $this->ageResult(
                key: $key,
                label: $label,
                node: 'workers',
                timestamp: is_numeric($ts) ? (int) $ts : null,
                thresholds: $this->thresholds($thresholdKey),
                missingValue: $missing,
            );
        });
    }
}
