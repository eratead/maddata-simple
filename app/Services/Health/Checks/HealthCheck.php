<?php

namespace App\Services\Health\Checks;

use App\Dtos\HealthCheckResult;
use App\Enums\HealthStatus;
use App\Services\Health\HealthFormat;
use App\Services\Health\HealthMarkers;
use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base class for every health check.
 *
 * THE CONTRACT: run() must never throw. A check that cannot determine its own
 * answer reports CRIT "unreachable" tagged to its real node — the snapshot must
 * still build when half the system is on fire. Wrap every probe in guard().
 *
 * Threshold comparison is inclusive (>= for over-checks, <= for under-checks),
 * so a configured warn of 70 means 70 is already amber.
 */
abstract class HealthCheck
{
    /** @return array<int, HealthCheckResult> */
    abstract public function run(): array;

    /**
     * Run one probe. Any throwable becomes a CRIT result rather than escaping.
     *
     * @param  Closure(): HealthCheckResult  $probe
     */
    protected function guard(string $key, string $label, string $node, Closure $probe): HealthCheckResult
    {
        try {
            return $probe();
        } catch (Throwable $e) {
            Log::warning('Health check failed', [
                'check' => $key,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return new HealthCheckResult(
                key: $key,
                label: $label,
                status: HealthStatus::CRIT,
                node: $node,
                value: 'unreachable',
                threshold: class_basename($e),
            );
        }
    }

    /** Higher is worse: CPU, disk, queue depth, latency. */
    protected function evaluateOver(float $value, array $thresholds): HealthStatus
    {
        if (isset($thresholds['crit']) && $value >= $thresholds['crit']) {
            return HealthStatus::CRIT;
        }

        if (isset($thresholds['warn']) && $value >= $thresholds['warn']) {
            return HealthStatus::WARN;
        }

        return HealthStatus::OK;
    }

    /** Lower is worse: TLS days remaining, backup size as % of median. */
    protected function evaluateUnder(float $value, array $thresholds): HealthStatus
    {
        if (isset($thresholds['crit']) && $value <= $thresholds['crit']) {
            return HealthStatus::CRIT;
        }

        if (isset($thresholds['warn']) && $value <= $thresholds['warn']) {
            return HealthStatus::WARN;
        }

        return HealthStatus::OK;
    }

    protected function thresholds(string $key): array
    {
        return config("health.thresholds.{$key}", []);
    }

    /** Human-readable threshold for the UI, e.g. "warn ≥70% · crit ≥85%". */
    protected function describeThreshold(array $thresholds, string $unit = '', string $direction = '≥'): string
    {
        $parts = [];

        foreach (['warn', 'crit'] as $level) {
            if (isset($thresholds[$level])) {
                $parts[] = "{$level} {$direction}{$thresholds[$level]}{$unit}";
            }
        }

        return implode(' · ', $parts);
    }

    /**
     * Age-since-marker check, the shape most of this catalog uses.
     * A missing marker is STALE (no data), not CRIT (bad data) — except where
     * the caller overrides, because "the backup marker never appeared" IS bad.
     */
    protected function ageResult(
        string $key,
        string $label,
        string $node,
        ?int $timestamp,
        array $thresholds,
        HealthStatus $missingStatus = HealthStatus::STALE,
        ?string $missingValue = 'never recorded',
        ?string $link = null,
    ): HealthCheckResult {
        $described = $this->describeAgeThreshold($thresholds);

        if ($timestamp === null) {
            return new HealthCheckResult(
                key: $key,
                label: $label,
                status: $missingStatus,
                node: $node,
                value: $missingValue,
                threshold: $described,
                link: $link,
            );
        }

        $age = max(0, now()->getTimestamp() - $timestamp);

        return new HealthCheckResult(
            key: $key,
            label: $label,
            status: $this->evaluateOver($age, $thresholds),
            node: $node,
            value: HealthFormat::age($age),
            threshold: $described,
            link: $link,
        );
    }

    /** Age thresholds are configured in seconds but must never be DISPLAYED that way. */
    protected function describeAgeThreshold(array $thresholds): string
    {
        return $this->describeThreshold([
            'warn' => isset($thresholds['warn']) ? HealthFormat::age((int) $thresholds['warn']) : null,
            'crit' => isset($thresholds['crit']) ? HealthFormat::age((int) $thresholds['crit']) : null,
        ], '', 'age ≥');
    }

    /** The store holding every health marker. */
    protected function store(): Repository
    {
        return HealthMarkers::store();
    }
}
