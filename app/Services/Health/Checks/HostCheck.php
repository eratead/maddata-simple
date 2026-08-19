<?php

namespace App\Services\Health\Checks;

use App\Dtos\HealthCheckResult;
use App\Enums\HealthStatus;
use App\Services\Health\HealthFormat;
use App\Services\Health\HostFacts;

/**
 * H1-H6 — everything that comes from scripts/health-facts.sh.
 *
 * H1 is the load-bearing one: if the facts file has stopped being written,
 * every other check in here is reporting history, not health. That has to be
 * loud, because a monitor that has quietly gone blind is worse than no monitor.
 */
class HostCheck extends HealthCheck
{
    public function __construct(private readonly HostFacts $facts) {}

    public function run(): array
    {
        return [
            $this->factsAge(),
            $this->resource('H2', 'CPU', 'cpu_pct', 'cpu'),
            $this->resource('H3', 'Memory', 'mem_pct', 'memory'),
            $this->resource('H4', 'Disk (root)', 'disk_root_pct', 'disk'),
            ...$this->units(),
            $this->rebootRequired(),
        ];
    }

    /** H1 — am I monitoring blind? A missing file is CRIT, not STALE. */
    private function factsAge(): HealthCheckResult
    {
        return $this->guard('H1', 'Host facts freshness', 'host', function () {
            $ts = $this->facts->get('ts');

            return $this->ageResult(
                key: 'H1',
                label: 'Host facts freshness',
                node: 'host',
                timestamp: is_numeric($ts) ? (int) $ts : null,
                thresholds: $this->thresholds('facts_age'),
                missingStatus: HealthStatus::CRIT,
                missingValue: 'no facts file — is the cron running?',
                link: config('health.links.monitor'),
            );
        });
    }

    /** H2-H4 — percentage resources, higher is worse. */
    private function resource(string $key, string $label, string $factKey, string $thresholdKey): HealthCheckResult
    {
        return $this->guard($key, $label, 'host', function () use ($key, $label, $factKey, $thresholdKey) {
            $value = $this->facts->get($factKey);
            $thresholds = $this->thresholds($thresholdKey);

            if (! is_numeric($value)) {
                return new HealthCheckResult(
                    key: $key,
                    label: $label,
                    status: HealthStatus::STALE,
                    node: 'host',
                    value: 'no data',
                    threshold: $this->describeThreshold($thresholds, '%'),
                );
            }

            return new HealthCheckResult(
                key: $key,
                label: $label,
                status: $this->evaluateOver((float) $value, $thresholds),
                node: 'host',
                value: HealthFormat::percent((float) $value),
                threshold: $this->describeThreshold($thresholds, '%'),
            );
        });
    }

    /**
     * H5 — one result per systemd unit, each tagged to the node it actually
     * belongs to. A dead MySQL unit must redden the data column, not the host
     * column, or the map stops pointing at the problem.
     *
     * @return array<int, HealthCheckResult>
     */
    private function units(): array
    {
        $configured = (array) config('health.systemd_units', []);
        $states = $this->facts->get('units');
        $results = [];

        foreach ($configured as $unit => $meta) {
            $node = $meta['node'] ?? 'host';
            $label = $meta['label'] ?? $unit;
            $key = 'H5-'.$unit;

            $results[] = $this->guard($key, $label, $node, function () use ($key, $label, $node, $unit, $states) {
                $state = is_array($states) ? ($states[$unit] ?? null) : null;

                if ($state === null) {
                    return new HealthCheckResult(
                        key: $key,
                        label: $label,
                        status: HealthStatus::STALE,
                        node: $node,
                        value: 'no data',
                        threshold: 'expects active',
                    );
                }

                return new HealthCheckResult(
                    key: $key,
                    label: $label,
                    status: match ($state) {
                        'active' => HealthStatus::OK,
                        'activating', 'reloading', 'deactivating' => HealthStatus::WARN,
                        default => HealthStatus::CRIT,
                    },
                    node: $node,
                    value: $state,
                    threshold: 'expects active',
                );
            });
        }

        return $results;
    }

    /** H6 — a pending reboot is a warning, never an outage. */
    private function rebootRequired(): HealthCheckResult
    {
        return $this->guard('H6', 'Reboot required', 'host', function () {
            $flag = $this->facts->get('reboot_required');

            if ($flag === null) {
                return new HealthCheckResult(
                    key: 'H6',
                    label: 'Reboot required',
                    status: HealthStatus::STALE,
                    node: 'host',
                    value: 'no data',
                );
            }

            return new HealthCheckResult(
                key: 'H6',
                label: 'Reboot required',
                status: (int) $flag === 1 ? HealthStatus::WARN : HealthStatus::OK,
                node: 'host',
                value: (int) $flag === 1 ? 'pending' : 'no',
            );
        });
    }
}
