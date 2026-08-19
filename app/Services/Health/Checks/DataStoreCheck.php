<?php

namespace App\Services\Health\Checks;

use App\Dtos\HealthCheckResult;
use App\Enums\HealthStatus;
use App\Services\Health\HealthFormat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * D1-D3 — the data plane.
 *
 * D1 runs on the dedicated short-timeout connection (config/database.php
 * 'mysql_health'). Without that cap, a MySQL outage would hang every admin page
 * for the driver's default timeout instead of degrading the status pill.
 */
class DataStoreCheck extends HealthCheck
{
    public function run(): array
    {
        return [
            $this->mysql(),
            $this->redis(),
            $this->campaignDataFreshness(),
        ];
    }

    /** D1 — reachability first, latency second. Unreachable is CRIT via guard(). */
    private function mysql(): HealthCheckResult
    {
        return $this->guard('D1', 'MySQL reachable', 'data', function () {
            $connection = config('health.mysql_connection');
            $thresholds = $this->thresholds('mysql_latency_ms');

            $start = microtime(true);
            DB::connection($connection)->select('select 1');
            $ms = (microtime(true) - $start) * 1000;

            return new HealthCheckResult(
                key: 'D1',
                label: 'MySQL reachable',
                status: $this->evaluateOver($ms, $thresholds),
                node: 'data',
                value: HealthFormat::ms($ms),
                threshold: $this->describeThreshold($thresholds, 'ms'),
            );
        });
    }

    /**
     * D2 — reachable, plus memory against its own ceiling. With no maxmemory
     * configured there is no ceiling to be near, so the value is informational.
     */
    private function redis(): HealthCheckResult
    {
        return $this->guard('D2', 'Redis reachable', 'data', function () {
            $info = Redis::connection(config('health.redis_connection'))->info('memory');

            $used = (int) ($info['used_memory'] ?? $info['Memory']['used_memory'] ?? 0);
            $max = (int) ($info['maxmemory'] ?? $info['Memory']['maxmemory'] ?? 0);
            $thresholds = $this->thresholds('redis_memory_pct');

            if ($max <= 0) {
                return new HealthCheckResult(
                    key: 'D2',
                    label: 'Redis reachable',
                    status: HealthStatus::OK,
                    node: 'data',
                    value: HealthFormat::bytes($used).' used (no ceiling set)',
                    threshold: $this->describeThreshold($thresholds, '% of maxmemory'),
                );
            }

            $pct = $used / $max * 100;

            return new HealthCheckResult(
                key: 'D2',
                label: 'Redis reachable',
                status: $this->evaluateOver($pct, $thresholds),
                node: 'data',
                value: HealthFormat::bytes($used).' / '.HealthFormat::bytes($max).' ('.HealthFormat::percent($pct).')',
                threshold: $this->describeThreshold($thresholds, '% of maxmemory'),
            );
        });
    }

    /**
     * D3 — campaign data freshness.
     *
     * INFORMATIONAL BY DESIGN: CampaignData arrives by manual upload through
     * ReportImportService, not an automated feed, so "stale" often just means
     * nobody uploaded this week. It warns; it never goes CRIT. A check that
     * cries wolf gets ignored, and an ignored check is worse than no check.
     * (Spec open question 1 — revisit as per-active-campaign scoping.)
     */
    private function campaignDataFreshness(): HealthCheckResult
    {
        return $this->guard('D3', 'Campaign data freshness', 'data', function () {
            $thresholds = $this->thresholds('campaign_data_age_days');
            $latest = DB::table('campaign_data')->max('report_date');

            if ($latest === null) {
                return new HealthCheckResult(
                    key: 'D3',
                    label: 'Campaign data freshness',
                    status: HealthStatus::OK,
                    node: 'data',
                    value: 'no campaign data yet',
                    threshold: $this->describeThreshold($thresholds, 'd'),
                );
            }

            $days = now()->startOfDay()->diffInDays(\Illuminate\Support\Carbon::parse($latest)->startOfDay());
            $days = (int) abs($days);

            return new HealthCheckResult(
                key: 'D3',
                label: 'Campaign data freshness',
                status: $this->evaluateOver($days, ['warn' => $thresholds['warn'] ?? null]),
                node: 'data',
                value: $days === 0 ? 'today' : "{$days}d old (latest {$latest})",
                threshold: $this->describeThreshold($thresholds, 'd').' — informational, never critical',
            );
        });
    }
}
