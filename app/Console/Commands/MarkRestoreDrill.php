<?php

namespace App\Console\Commands;

use App\Services\Health\HealthMarkers;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Records that a restore drill was actually performed (check B4).
 *
 * Run this at the end of docs/specs/backup-restore-runbook.md. Nothing else
 * writes this marker — the check nags, a human runs the drill.
 */
class MarkRestoreDrill extends Command
{
    protected $signature = 'health:mark-restore-drill
        {--note= : What was restored and where}
        {--at= : When the drill actually happened (Y-m-d), if not today}';

    protected $description = 'Record a completed backup restore drill for the health monitor.';

    public function handle(): int
    {
        /*
         * --at exists so a drill that happened weeks ago can be recorded
         * truthfully. Stamping it as "now" would overstate how recently the
         * backups were proven restorable, which is the single thing this marker
         * is for — the same reason deps:mark-patch-run must never be run to
         * silence d4.
         */
        try {
            $at = $this->option('at') ? CarbonImmutable::parse($this->option('at')) : now();
        } catch (Throwable) {
            $this->error('Could not read --at — use a date like 2026-07-12.');

            return self::FAILURE;
        }

        if ($at->isFuture()) {
            $this->error('A restore drill cannot have happened in the future.');

            return self::FAILURE;
        }

        HealthMarkers::store()->forever(HealthMarkers::RESTORE_DRILL, [
            'ts' => $at->getTimestamp(),
            'note' => (string) $this->option('note'),
        ]);

        $this->info('Restore drill recorded at '.$at->toDateTimeString().'.');

        return self::SUCCESS;
    }
}
