<?php

namespace App\Console\Commands;

use App\Dtos\HealthCheckResult;
use App\Dtos\HealthSnapshot;
use App\Enums\HealthStatus;
use App\Services\Health\SystemHealthService;
use Illuminate\Console\Command;

/**
 * `php artisan health:check` — the answer to "is production OK?" over SSH.
 *
 * Rebuilds by default rather than serving cache: someone who typed this wants
 * the current truth, and the rebuild warms the cache for the next reader.
 *
 * The exit code is the point of --fail-on. It makes the command composable
 * with cron, a deploy gate, or a one-line remote check:
 *
 *     ssh prod 'cd /var/www/maddata && php artisan health:check --fail-on=crit'
 */
class RunHealthCheck extends Command
{
    protected $signature = 'health:check
                            {--json : Emit the snapshot as JSON instead of a table}
                            {--cached : Use the cached snapshot instead of rebuilding}
                            {--fail-on=crit : Exit non-zero at this severity or worse (warn|crit)}';

    protected $description = 'Report system health; exits non-zero when unhealthy.';

    public function handle(SystemHealthService $health): int
    {
        $snapshot = $this->option('cached') ? $health->snapshot() : $health->refresh();

        if ($this->option('json')) {
            $this->line((string) json_encode($snapshot->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $this->exitCode($snapshot);
        }

        $this->renderTable($snapshot);

        return $this->exitCode($snapshot);
    }

    private function renderTable(HealthSnapshot $snapshot): void
    {
        $failing = $snapshot->failing();

        $this->newLine();
        $this->line(sprintf(
            ' %s  %s — %d checks, %d failing  (%s)',
            $this->dot($snapshot->overall),
            strtoupper($snapshot->overall->label()),
            count($snapshot->checks),
            count($failing),
            $snapshot->generatedAt->toDateTimeString(),
        ));
        $this->newLine();

        // Failing first: in an incident you should not have to scan for the
        // red row. Everything else follows, grouped by node.
        $rows = [];

        foreach ($failing as $check) {
            $rows[] = $this->row($check);
        }

        if ($failing !== []) {
            $rows[] = new \Symfony\Component\Console\Helper\TableSeparator;
        }

        foreach ($snapshot->checks as $check) {
            if (! $check->status->isFailing()) {
                $rows[] = $this->row($check);
            }
        }

        $this->table(['', 'Key', 'Check', 'Node', 'Value', 'Threshold'], $rows);
    }

    private function row(HealthCheckResult $check): array
    {
        return [
            $this->dot($check->status),
            $check->key,
            $check->label,
            $check->node,
            $check->value ?? '',
            $check->threshold ?? '',
        ];
    }

    private function dot(HealthStatus $status): string
    {
        $color = match ($status) {
            HealthStatus::OK => 'green',
            HealthStatus::WARN => 'yellow',
            HealthStatus::CRIT => 'red',
            HealthStatus::STALE => 'gray',
        };

        return "<fg={$color}>●</>";
    }

    private function exitCode(HealthSnapshot $snapshot): int
    {
        // --fail-on=warn means "anything that isn't green", which includes
        // STALE: not being able to see is a reason to stop a deploy too.
        $shouldFail = $this->option('fail-on') === 'warn'
            ? $snapshot->overall !== HealthStatus::OK
            : $snapshot->overall === HealthStatus::CRIT;

        return $shouldFail ? $snapshot->overall->exitCode() : self::SUCCESS;
    }
}
