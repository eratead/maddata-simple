<?php

namespace App\Console\Commands;

use App\Services\Health\HealthMarkers;
use Illuminate\Console\Command;

/**
 * Records that dependencies were actually patched (check d4).
 *
 * Also stamps the composer.lock hash at the time, which is what lets d4 notice
 * that the deployed lock has drifted away from the one that was last patched.
 * Nothing else writes this marker — the check nags, a human does the work.
 */
class MarkPatchRun extends Command
{
    protected $signature = 'deps:mark-patch-run {--note= : What was patched}';

    protected $description = 'Record a completed dependency/OS patch run for the health monitor.';

    public function handle(): int
    {
        $lock = config('health.composer_lock_path') ?: base_path('composer.lock');

        HealthMarkers::store()->forever(HealthMarkers::PATCH_RUN, [
            'ts' => now()->getTimestamp(),
            'lock_sha' => is_readable($lock) ? hash_file('sha256', $lock) : '',
            'note' => (string) $this->option('note'),
        ]);

        $this->info('Patch run recorded at '.now()->toDateTimeString().'.');

        return self::SUCCESS;
    }
}
