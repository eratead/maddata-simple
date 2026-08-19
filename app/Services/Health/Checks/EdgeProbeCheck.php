<?php

namespace App\Services\Health\Checks;

use App\Dtos\HealthCheckResult;
use App\Enums\HealthStatus;
use App\Services\Health\HealthFormat;
use App\Services\Health\HealthMarkers;
use App\Services\Health\HostFacts;

/**
 * P1-P2 — the edge, seen from outside.
 *
 * P1 is the only check that exercises Nginx config, TLS, DNS and the FPM socket
 * the way a real user does. It READS the marker that health:probe writes; the
 * service never curls inline, because a snapshot build must not be able to
 * block on an external network call.
 */
class EdgeProbeCheck extends HealthCheck
{
    public function __construct(private readonly HostFacts $facts) {}

    public function run(): array
    {
        return [
            $this->publicProbe(),
            $this->tlsExpiry(),
        ];
    }

    /** P1 — two consecutive failures, not one blip, is what turns this red. */
    private function publicProbe(): HealthCheckResult
    {
        return $this->guard('P1', 'Public HTTPS probe', 'edge', function () {
            $marker = $this->store()->get(HealthMarkers::PUBLIC_PROBE);

            if (! is_array($marker)) {
                return new HealthCheckResult(
                    key: 'P1',
                    label: 'Public HTTPS probe',
                    status: HealthStatus::STALE,
                    node: 'edge',
                    value: 'never run',
                    threshold: 'health:probe scheduled every minute',
                );
            }

            // checked_at was written but never read: if health:probe itself dies,
            // the marker lingers for its whole TTL and P1 keeps reporting the last
            // good latency. A stale probe is unknown, not healthy.
            $checkedAt = $marker['checked_at'] ?? null;
            $maxAge = (int) config('health.thresholds.probe_marker_age.warn', 300);

            if (is_numeric($checkedAt) && (now()->getTimestamp() - (int) $checkedAt) > $maxAge) {
                return new HealthCheckResult(
                    key: 'P1',
                    label: 'Public HTTPS probe',
                    status: HealthStatus::STALE,
                    node: 'edge',
                    value: 'last probe '.HealthFormat::age(now()->getTimestamp() - (int) $checkedAt).' ago — is health:probe running?',
                    threshold: 'expects a probe every minute',
                );
            }

            $failThresholds = $this->thresholds('probe_consec_fails');
            $fails = (int) ($marker['consec_fails'] ?? 0);
            $latency = $marker['latency_ms'] ?? null;
            $url = config('health.probe_url');

            if ($fails > 0) {
                return new HealthCheckResult(
                    key: 'P1',
                    label: 'Public HTTPS probe',
                    status: $this->evaluateOver($fails, $failThresholds),
                    node: 'edge',
                    value: $fails === 1 ? '1 failed probe' : "{$fails} consecutive failures",
                    threshold: 'crit at '.($failThresholds['crit'] ?? 2).' consecutive',
                    link: $url,
                );
            }

            $latencyThresholds = $this->thresholds('probe_latency_ms');

            return new HealthCheckResult(
                key: 'P1',
                label: 'Public HTTPS probe',
                status: is_numeric($latency)
                    ? $this->evaluateOver((float) $latency, $latencyThresholds)
                    : HealthStatus::OK,
                node: 'edge',
                value: is_numeric($latency) ? HealthFormat::ms((float) $latency) : 'ok',
                threshold: $this->describeThreshold($latencyThresholds, 'ms'),
                link: $url,
            );
        });
    }

    /** P2 — certbot renewal failing silently is a real single-host risk. */
    private function tlsExpiry(): HealthCheckResult
    {
        return $this->guard('P2', 'TLS certificate', 'edge', function () {
            $days = $this->facts->get('tls_days_remaining');
            $thresholds = $this->thresholds('tls_days');

            if (! is_numeric($days)) {
                return new HealthCheckResult(
                    key: 'P2',
                    label: 'TLS certificate',
                    status: HealthStatus::STALE,
                    node: 'edge',
                    value: 'no data',
                    threshold: $this->describeThreshold($thresholds, 'd', '≤'),
                );
            }

            return new HealthCheckResult(
                key: 'P2',
                label: 'TLS certificate',
                status: $this->evaluateUnder((float) $days, $thresholds),
                node: 'edge',
                value: (int) $days.'d remaining',
                threshold: $this->describeThreshold($thresholds, 'd', '≤'),
            );
        });
    }
}
