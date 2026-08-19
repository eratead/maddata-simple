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

            // max(1, …): a configured 0 makes `$count >= 0` always true and pins
            // this amber forever — an un-clearable warning is worse than none.
            $warn = max(1, (int) ($this->thresholds('expired_tokens')['warn'] ?? 1));

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

        // `app`, not `platform`: X1 is housekeeping and belongs on the weekly
        // digest, but X2 is the only check in the catalog that sees an attack
        // happening NOW. Thresholds of 20 and 100 failures in fifteen minutes
        // mean nothing if the result waits until Monday morning.
        return $this->guard('X2', "Failed logins ({$minutes}m)", 'app', function () use ($minutes) {
            $store = $this->store();
            $total = 0;

            for ($i = 0; $i < $minutes; $i++) {
                $total += (int) $store->get(
                    HealthMarkers::AUTH_FAIL_PREFIX.now()->subMinutes($i)->format('YmdHi'),
                    0
                );
            }

            /*
             * Reclaim the buckets that have aged out of the window. The file
             * store only unlinks an expired entry when something reads it, and
             * nothing ever reads past minute 14 — so every minute containing a
             * failed login otherwise leaves a file behind forever.
             */
            for ($i = $minutes; $i < $minutes + 6; $i++) {
                $store->forget(HealthMarkers::AUTH_FAIL_PREFIX.now()->subMinutes($i)->format('YmdHi'));
            }

            $thresholds = $this->thresholds('failed_login_burst');

            return new HealthCheckResult(
                key: 'X2',
                label: "Failed logins ({$minutes}m)",
                status: $this->evaluateOver($total, $thresholds),
                node: 'app',
                value: $total === 0 ? 'none' : $total." in {$minutes}m",
                threshold: $this->describeThreshold($thresholds),
            );
        });
    }
}
