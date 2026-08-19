<?php

namespace App\Console\Commands;

use App\Services\Health\SystemHealthService;
use Illuminate\Console\Command;

/**
 * Rebuilds the health snapshot off-path every minute.
 *
 * Without this, the first admin to open the monitor after a TTL expiry pays for
 * the whole rebuild — a file read, a MySQL round trip, a Redis INFO and a queue
 * count. With it, a real request is always just a cache read.
 */
class RefreshHealthSnapshot extends Command
{
    protected $signature = 'health:refresh-snapshot';

    protected $description = 'Rebuild the cached system health snapshot.';

    public function handle(SystemHealthService $health): int
    {
        $snapshot = $health->refresh();

        $this->info(sprintf(
            'Snapshot rebuilt: %s (%d checks, %d failing).',
            $snapshot->overall->value,
            count($snapshot->checks),
            count($snapshot->failing()),
        ));

        return self::SUCCESS;
    }
}
