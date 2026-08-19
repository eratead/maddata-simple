<?php

namespace App\Services\Health\Checks;

use App\Dtos\HealthCheckResult;
use App\Enums\HealthStatus;
use App\Services\Health\HostFacts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Check d2 — are the runtimes we are standing on still getting security fixes?
 *
 * Versions are read live; the support windows come from
 * config/dependency_maintenance.php, which is a hand-maintained table because
 * no machine-readable feed covers all four products.
 *
 * That table is the weak point, so it has its own check: `reviewed_at` going
 * stale is reported as a finding of its own. A support table nobody revisits
 * reports "everything is supported" forever, with total confidence.
 *
 * A runtime whose branch is not in the table WARNS rather than passing. An
 * unknown runtime is not a supported one, and the fix is to add the row.
 */
class RuntimeEolCheck extends HealthCheck
{
    public function __construct(private readonly HostFacts $facts) {}

    public function run(): array
    {
        return [
            $this->runtime('php', 'PHP version', fn () => PHP_VERSION),
            $this->runtime('mysql', 'MySQL version', fn () => $this->mysqlVersion()),
            $this->runtime('redis', 'Redis version', fn () => $this->redisVersion()),
            $this->runtime('nginx', 'Nginx version', fn () => $this->facts->get('nginx_version')),
            $this->tableFreshness(),
        ];
    }

    private function runtime(string $product, string $label, callable $probe): HealthCheckResult
    {
        $key = 'd2-'.$product;

        return $this->guard($key, $label, 'platform', function () use ($product, $key, $label, $probe) {
            $version = $probe();

            if (! is_string($version) || $version === '' || $version === 'unknown') {
                // An unreachable feed is never GREEN — including the feed that
                // tells us what we are running.
                return new HealthCheckResult(
                    key: $key, label: $label, status: HealthStatus::STALE, node: 'platform',
                    value: 'version unavailable', threshold: 'security support window',
                );
            }

            $branch = $this->branch($version);
            $row = $this->row($product, $branch);

            if ($row === null) {
                return new HealthCheckResult(
                    key: $key, label: $label, status: HealthStatus::WARN, node: 'platform',
                    value: $version.' — branch not in the support table',
                    threshold: 'add a row to config/dependency_maintenance.php',
                );
            }

            $until = $row['security_support_until'] ?? null;

            if ($until === null) {
                /*
                 * The vendor publishes no dated window. Two very different
                 * cases, and collapsing them is how a monitor earns a permanent
                 * amber nobody can clear:
                 *
                 *  - `tracked_by` set: another check owns this runtime's
                 *    currency (nginx is patched by distro backports, which d3
                 *    measures). Reporting OK with a note is honest.
                 *  - `tracked_by` absent: we genuinely do not know. That warns.
                 */
                $trackedBy = $row['tracked_by'] ?? null;

                return new HealthCheckResult(
                    key: $key, label: $label,
                    status: $trackedBy ? HealthStatus::OK : HealthStatus::WARN,
                    node: 'platform',
                    value: $trackedBy
                        ? $version.' — no upstream window; currency tracked by '.$trackedBy
                        : $version.' — no published security window',
                    threshold: (string) ($row['source'] ?? ''),
                );
            }

            // Whole calendar days, both ends normalised. A fractional diff cast
            // to int truncates TOWARD ZERO, so a runtime that went EOL earlier
            // today came out as "0 days" and read WARN instead of CRIT.
            $end = CarbonImmutable::parse($until)->startOfDay();
            $days = (int) now()->startOfDay()->diffInDays($end, false);
            $warnDays = (int) config('dependency_maintenance.thresholds.eol_warn_days', 90);

            $status = match (true) {
                $days < 0 => HealthStatus::CRIT,
                $days <= $warnDays => HealthStatus::WARN,
                default => HealthStatus::OK,
            };

            $value = $days < 0
                ? sprintf('%s — security support ended %s (%d days ago)', $version, $until, abs($days))
                : sprintf('%s — supported until %s (%d days)', $version, $until, $days);

            return new HealthCheckResult(
                key: $key, label: $label, status: $status, node: 'platform',
                value: $value,
                threshold: sprintf('warn ≤%dd to end of security support · crit past it', $warnDays),
            );
        });
    }

    /** The table's own dead-man switch. */
    private function tableFreshness(): HealthCheckResult
    {
        return $this->guard('d2-table', 'Support table freshness', 'platform', function () {
            $reviewedAt = config('dependency_maintenance.reviewed_at');
            $months = (int) config('dependency_maintenance.thresholds.table_review_months', 6);

            if (! is_string($reviewedAt) || $reviewedAt === '') {
                return new HealthCheckResult(
                    key: 'd2-table', label: 'Support table freshness', status: HealthStatus::WARN,
                    node: 'platform', value: 'never reviewed',
                    threshold: "review every {$months} months",
                );
            }

            $reviewed = CarbonImmutable::parse($reviewedAt);
            $age = (int) $reviewed->diffInDays(now());
            $limit = $months * 30;

            return new HealthCheckResult(
                key: 'd2-table', label: 'Support table freshness',
                status: $age > $limit ? HealthStatus::WARN : HealthStatus::OK,
                node: 'platform',
                value: "reviewed {$reviewedAt} ({$age}d ago)",
                threshold: "review every {$months} months",
            );
        });
    }

    private function branch(string $version): string
    {
        preg_match('/^(\d+)\.(\d+)/', $version, $m);

        return isset($m[1], $m[2]) ? $m[1].'.'.$m[2] : $version;
    }

    /** '*' matches any branch — for products versioned without a support window. */
    private function row(string $product, string $branch): ?array
    {
        $fallback = null;

        foreach ((array) config('dependency_maintenance.runtimes', []) as $row) {
            if (($row['product'] ?? null) !== $product) {
                continue;
            }

            if (($row['branch'] ?? null) === $branch) {
                return $row;
            }

            if (($row['branch'] ?? null) === '*') {
                $fallback = $row;
            }
        }

        return $fallback;
    }

    private function mysqlVersion(): ?string
    {
        // The dedicated short-timeout connection, never the app's default: this
        // check must not be the thing that hangs on a sick MySQL.
        $row = DB::connection(config('health.mysql_connection'))->selectOne('select version() as v');

        return is_object($row) && isset($row->v) ? (string) $row->v : null;
    }

    private function redisVersion(): ?string
    {
        try {
            $info = Redis::connection(config('health.redis_connection'))->info('server');

            return $info['redis_version'] ?? $info['Server']['redis_version'] ?? null;
        } catch (Throwable) {
            return null;
        }
    }
}
