<?php

namespace App\Console\Commands;

use App\Services\Health\HealthMarkers;
use Illuminate\Console\Command;

/**
 * Records that a restore drill was actually performed (check B4).
 *
 * Run this at the end of docs/specs/backup-restore-runbook.md. Nothing else
 * writes this marker — the check nags, a human runs the drill.
 */
class MarkRestoreDrill extends Command
{
    protected $signature = 'health:mark-restore-drill {--note= : What was restored and where}';

    protected $description = 'Record a completed backup restore drill for the health monitor.';

    public function handle(): int
    {
        HealthMarkers::store()->forever(HealthMarkers::RESTORE_DRILL, [
            'ts' => now()->getTimestamp(),
            'note' => (string) $this->option('note'),
        ]);

        $this->info('Restore drill recorded at '.now()->toDateTimeString().'.');

        return self::SUCCESS;
    }
}
