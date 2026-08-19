<?php

namespace App\Services\Health\Checks;

use App\Dtos\HealthCheckResult;
use App\Enums\HealthStatus;
use App\Services\Health\HealthFormat;
use App\Services\Health\HealthMarkers;
use Throwable;

/**
 * Check d4 — when did a human last actually patch this thing?
 *
 * The marker is written by `deps:mark-patch-run` and records the composer.lock
 * hash at the time. That second field is what makes this more than a calendar
 * reminder: if the deployed lock has since drifted from the one that was
 * patched AND d1 is reporting high-severity advisories, the recorded patch run
 * no longer describes what is deployed.
 *
 * Never marked reads WARN, not STALE. "Nobody has ever patched this box" is a
 * fact about the box, not missing data about it.
 */
class PatchRunFreshnessCheck extends HealthCheck
{
    public function run(): array
    {
        return [$this->guard('d4', 'Patch run freshness', 'platform', function () {
            $thresholds = $this->thresholds('patch_run_age');
            $described = $this->describeAgeThreshold($thresholds);

            $marker = $this->marker();

            if ($marker === null) {
                return $this->result(HealthStatus::WARN, 'never recorded — run deps:mark-patch-run', $described);
            }

            $age = max(0, now()->getTimestamp() - (int) ($marker['ts'] ?? 0));
            $status = $this->evaluateOver($age, $thresholds);
            $value = HealthFormat::age($age).' ago';

            if (! empty($marker['note'])) {
                $value .= ' ('.$marker['note'].')';
            }

            /*
             * Drift alone is not a finding. Warning on ANY lock change turns
             * this amber after every routine `composer require` — amber for the
             * good case, clearable only by running a command, which is the
             * un-actionable-warning pattern this vertical keeps designing
             * against. The spec couples it to d1: a changed lock matters when
             * there is something known-bad in it.
             */
            if ($status === HealthStatus::OK && $this->lockDrifted($marker) && $this->advisoriesAreBad()) {
                $status = HealthStatus::WARN;
                $value .= ' — composer.lock has changed since, and d1 reports high-severity advisories';
            }

            return $this->result($status, $value, $described);
        })];
    }

    private function marker(): ?array
    {
        try {
            $marker = $this->store()->get(HealthMarkers::PATCH_RUN);

            return is_array($marker) && isset($marker['ts']) ? $marker : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Whether d1 last reported something worth acting on. */
    private function advisoriesAreBad(): bool
    {
        try {
            return $this->store()->get(HealthMarkers::ADVISORIES_WORST) === HealthStatus::CRIT->value;
        } catch (Throwable) {
            return false;
        }
    }

    private function lockDrifted(array $marker): bool
    {
        $recorded = (string) ($marker['lock_sha'] ?? '');
        $path = config('health.composer_lock_path') ?: base_path('composer.lock');

        if ($recorded === '' || ! is_readable($path)) {
            return false;
        }

        return hash_file('sha256', $path) !== $recorded;
    }

    private function result(HealthStatus $status, string $value, string $threshold): HealthCheckResult
    {
        return new HealthCheckResult(
            key: 'd4', label: 'Patch run freshness', status: $status,
            node: 'platform', value: $value, threshold: $threshold,
        );
    }
}
