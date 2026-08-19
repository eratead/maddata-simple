<?php

namespace App\Services\Health\Checks;

use App\Dtos\HealthCheckResult;
use App\Enums\HealthStatus;
use App\Services\Health\HealthFormat;
use App\Services\Health\HealthMarkers;
use App\Services\Health\HostFacts;
use Throwable;

/**
 * Check d3 — unapplied OS security updates, and whether a reboot is owed.
 *
 * Both numbers come from the facts file; nothing here shells out.
 *
 * The interesting part is what "pending" means over time. apt only ever reports
 * the CURRENT count, so a box that has been unpatched for a month looks exactly
 * like one that has been unpatched since lunchtime — and only one of those means
 * unattended-upgrades is broken. The since-marker below is what tells them apart.
 *
 * A null count is STALE, never 0. The facts script writes null when it cannot
 * compute the count, and a monitor that cannot count must not report "clean".
 */
class OsPatchCheck extends HealthCheck
{
    public function __construct(private readonly HostFacts $facts) {}

    public function run(): array
    {
        return [
            $this->securityUpdates(),
            $this->rebootRequired(),
        ];
    }

    private function securityUpdates(): HealthCheckResult
    {
        return $this->guard('d3', 'OS security updates', 'platform', function () {
            $thresholds = $this->thresholds('os_patch_sustained');
            $described = 'warn pending ≥7d · crit pending ≥30d';

            if ($this->facts->read() === null) {
                return $this->result(HealthStatus::STALE, 'no facts file', $described);
            }

            // Facts that have gone stale mean we are reporting on a snapshot of
            // the past. Bounded separately from H1 because this one tolerates
            // more: the count is refreshed hourly by design.
            $factsAge = $this->facts->ageSeconds();
            $ageThresholds = $this->thresholds('os_patch_facts_age');

            if ($factsAge !== null && $this->evaluateOver($factsAge, $ageThresholds) !== HealthStatus::OK) {
                return $this->result(
                    $this->evaluateOver($factsAge, $ageThresholds),
                    'facts '.HealthFormat::age($factsAge).' old',
                    $described,
                );
            }

            $pending = $this->facts->get('pending_security');

            if (! is_numeric($pending)) {
                return $this->result(HealthStatus::STALE, 'count unavailable', $described);
            }

            $pending = (int) $pending;
            $since = $this->trackSince($pending);

            if ($pending === 0) {
                return $this->result(HealthStatus::OK, 'none pending', $described);
            }

            $waiting = $since === null ? 0 : max(0, now()->getTimestamp() - $since);

            return $this->result(
                $this->evaluateOver($waiting, $thresholds),
                sprintf('%d pending%s', $pending, $waiting > 0 ? ' for '.HealthFormat::age($waiting) : ''),
                $described,
            );
        });
    }

    /**
     * Remember when the current backlog started.
     *
     * Reset on zero, and reset when the count SHRINKS: a smaller set means
     * patches are landing, so the clock should not keep running on progress.
     *
     * @return int|null first-seen timestamp
     */
    private function trackSince(int $pending): ?int
    {
        try {
            $state = $this->store()->get(HealthMarkers::OS_PATCH_SINCE);
            $state = is_array($state) ? $state : null;

            if ($pending === 0) {
                if ($state !== null) {
                    $this->store()->forget(HealthMarkers::OS_PATCH_SINCE);
                }

                return null;
            }

            $previous = (int) ($state['count'] ?? 0);
            $firstSeen = (int) ($state['first_seen'] ?? 0);

            if ($state === null || $firstSeen === 0 || $pending < $previous) {
                $firstSeen = now()->getTimestamp();
            }

            $this->store()->forever(HealthMarkers::OS_PATCH_SINCE, [
                'count' => $pending,
                'first_seen' => $firstSeen,
            ]);

            return $firstSeen;
        } catch (Throwable) {
            // No memory means no sustained-window judgement, which is a smaller
            // failure than losing the check: report the raw count instead.
            return null;
        }
    }

    private function rebootRequired(): HealthCheckResult
    {
        return $this->guard('d3b', 'Pending reboot', 'platform', function () {
            if ($this->facts->read() === null) {
                return new HealthCheckResult(
                    key: 'd3b', label: 'Pending reboot', status: HealthStatus::STALE,
                    node: 'platform', value: 'no facts file', threshold: 'warn when set',
                );
            }

            // Never urgent, never nothing: a kernel patch that has not been
            // booted into is not applied.
            $required = (int) $this->facts->get('reboot_required', 0) === 1;

            return new HealthCheckResult(
                key: 'd3b', label: 'Pending reboot',
                status: $required ? HealthStatus::WARN : HealthStatus::OK,
                node: 'platform',
                value: $required ? 'reboot required' : 'none',
                threshold: 'warn when set',
            );
        });
    }

    private function result(HealthStatus $status, string $value, string $threshold): HealthCheckResult
    {
        return new HealthCheckResult(
            key: 'd3', label: 'OS security updates', status: $status,
            node: 'platform', value: $value, threshold: $threshold,
        );
    }
}
