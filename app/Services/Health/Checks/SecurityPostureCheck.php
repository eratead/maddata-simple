<?php

namespace App\Services\Health\Checks;

use App\Dtos\HealthCheckResult;
use App\Enums\HealthStatus;
use App\Services\Health\HealthMarkers;
use Illuminate\Support\Facades\DB;

/**
 * X1-X2 — two cheap security-posture signals.
 *
 * X1 is housekeeping and never reaches CRIT: an expired token is already
 * refused by CheckTokenExpiry, so leftovers are untidy rather than dangerous.
 *
 * X2 counts the one-minute buckets written by App\Listeners\RecordFailedLogin.
 */
class SecurityPostureCheck extends HealthCheck
{
    public function run(): array
    {
        return [
            $this->expiredTokens(),
            $this->failedLogins(),
        ];
    }

    private function expiredTokens(): HealthCheckResult
    {
        return $this->guard('X1', 'Expired API tokens', 'platform', function () {
            $count = DB::connection(config('health.mysql_connection'))
                ->table('personal_access_tokens')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->count();

            $warn = (int) ($this->thresholds('expired_tokens')['warn'] ?? 1);

            return new HealthCheckResult(
                key: 'X1',
                label: 'Expired API tokens',
                status: $count >= $warn ? HealthStatus::WARN : HealthStatus::OK,
                node: 'platform',
                value: $count === 0 ? 'none' : $count.' past expiry',
                threshold: 'warn ≥'.$warn.' — housekeeping, never critical',
                link: '/tokens',
            );
        });
    }

    private function failedLogins(): HealthCheckResult
    {
        $minutes = max(1, (int) config('health.failed_login_window_minutes', 15));

        return $this->guard('X2', "Failed logins ({$minutes}m)", 'platform', function () use ($minutes) {
            $store = $this->store();
            $total = 0;

            for ($i = 0; $i < $minutes; $i++) {
                $total += (int) $store->get(
                    HealthMarkers::AUTH_FAIL_PREFIX.now()->subMinutes($i)->format('YmdHi'),
                    0
                );
            }

            $thresholds = $this->thresholds('failed_login_burst');

            return new HealthCheckResult(
                key: 'X2',
                label: "Failed logins ({$minutes}m)",
                status: $this->evaluateOver($total, $thresholds),
                node: 'platform',
                value: $total === 0 ? 'none' : $total." in {$minutes}m",
                threshold: $this->describeThreshold($thresholds),
            );
        });
    }
}
