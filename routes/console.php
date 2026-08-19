<?php

use App\Jobs\QueueHeartbeatJob;
use App\Services\Health\HealthMarkers;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Business jobs
|--------------------------------------------------------------------------
|
| onSuccess() records the health marker rather than the command doing it
| itself: the marker then means "this job completed successfully", which is
| what checks S2a/S2b claim to measure. A job that starts and then throws must
| not leave a success marker behind.
|
*/

Schedule::command('campaigns:generate-status')
    ->daily()
    ->onSuccess(fn () => HealthMarkers::record(HealthMarkers::CAMPAIGN_STATUS_OK));

Schedule::command('digest:send-activity')
    ->everyTwoHours()
    ->onSuccess(fn () => HealthMarkers::record(HealthMarkers::ACTIVITY_DIGEST_OK));

/*
|--------------------------------------------------------------------------
| System health monitor
|--------------------------------------------------------------------------
|
| Spec: docs/specs/system-health-monitor.md
|
| The heartbeats below are deliberately Laravel-scheduled — they prove that
| cron reaches Laravel and that the queue worker consumes. The HOST facts they
| sit alongside come from an OS cron running scripts/health-facts.sh, which
| must keep firing even when the app itself is broken.
|
*/

// These run IN-PROCESS via Schedule::call rather than Schedule::command.
// Schedule::command() spawns a fresh `php artisan` process per task, and
// production is a single-core droplet: three extra PHP boots a minute were
// enough to saturate the core for several seconds and make the CPU check
// report on its own overhead. The commands still exist for manual use.

// Rebuild off-path so a real admin request is always just a cache read.
Schedule::call(fn () => Artisan::call('health:refresh-snapshot'))
    ->everyMinute()
    ->name('health-refresh-snapshot')
    ->withoutOverlapping();

// Check P1 — the stack as an outside user sees it.
Schedule::call(fn () => Artisan::call('health:probe'))
    ->everyMinute()
    ->name('health-probe')
    ->withoutOverlapping();

// Check Q3 — proof the worker CONSUMES, not merely that systemd says "active".
Schedule::job(new QueueHeartbeatJob)->everyMinute();

// Phase 2 — transition-based alerting. Every five minutes rather than every
// minute: the two-observation suppression rule below it means a deploy blip
// needs to survive ten minutes before anyone's phone buzzes.
Schedule::call(fn () => Artisan::call('health:alert'))
    ->everyFiveMinutes()
    ->name('health-alert')
    ->withoutOverlapping();

// Check S1 — proof cron reaches Laravel at all.
Schedule::call(fn () => HealthMarkers::record(HealthMarkers::SCHEDULER_BEAT))
    ->everyMinute()
    ->name('health-scheduler-heartbeat');
