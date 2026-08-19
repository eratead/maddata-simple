<?php

use App\Jobs\QueueHeartbeatJob;
use App\Services\Health\HealthMarkers;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
| The scheduler's own overlap mutexes must NOT live in the store the app uses.
| On production CACHE_STORE is unset, so it resolves to `database`: a MySQL
| outage would make CacheEventMutex::create() throw, ScheduleRunCommand would
| skip the event, and health:alert would go silent about the very outage it
| exists to report. A file mutex has no such dependency.
*/
Schedule::useCache(env('SCHEDULE_CACHE_STORE', 'file'));

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

// Check S1 first, deliberately: these tasks share one process, so whichever
// is registered last is the first casualty of an OOM. The scheduler heartbeat
// is the cheapest and the most load-bearing — if cron stops reaching Laravel,
// nothing else here runs at all.
Schedule::call(fn () => HealthMarkers::record(HealthMarkers::SCHEDULER_BEAT))
    ->everyMinute()
    ->name('health-scheduler-heartbeat');

// These run IN-PROCESS via Schedule::call rather than Schedule::command.
// Schedule::command() spawns a fresh `php artisan` process per task, and
// production is a single-core droplet: three extra PHP boots a minute were
// enough to saturate the core for several seconds and make the CPU check
// report on its own overhead. The commands still exist for manual use.

// Rebuild off-path so a real admin request is always just a cache read.
Schedule::call(fn () => Artisan::call('health:refresh-snapshot'))
    ->everyMinute()
    ->name('health-refresh-snapshot')
    ->withoutOverlapping(5);   // minutes — the default is 1440, so one SIGKILL would wedge this for a day

// Check P1 — the stack as an outside user sees it.
Schedule::call(fn () => Artisan::call('health:probe'))
    ->everyMinute()
    ->name('health-probe')
    ->withoutOverlapping(5);

// Check Q3 — proof the worker CONSUMES, not merely that systemd says "active".
Schedule::job(new QueueHeartbeatJob)->everyMinute();

// Phase 2 — transition-based alerting. Every five minutes rather than every
// minute: the two-observation suppression rule below it means a deploy blip
// needs to survive ten minutes before anyone's phone buzzes.
Schedule::call(fn () => Artisan::call('health:alert'))
    ->everyFiveMinutes()
    ->name('health-alert')
    ->withoutOverlapping(10);

// Keeps check Q2's 24h window cheap: without pruning, failed_jobs grows without
// bound and the check slows down in proportion to how sick the queue is.
Schedule::command('queue:prune-failed --hours=168')->weekly();

// Check X1 counts expired tokens still sitting in the table. Nothing was ever
// deleting them, so the count only ever went up — a warning that accumulates on
// its own is a warning nobody will act on. Sanctum already refuses these tokens;
// this just stops them piling up. Retains a week so an expiry is still visible
// for a few days after the fact.
Schedule::command('sanctum:prune-expired --hours=168')->weekly();

/*
| Phase 4 — the other half of the alerting split. Checks on the nodes
| health:alert deliberately ignores (config health.alert_excluded_nodes) are
| reported here once a week instead. A composer advisory is real and is not a
| 2am page.
|
| The timezone is explicit and must stay that way: the app runs in UTC
| (config/app.php), so a bare "08:00" would arrive mid-morning Israel time and
| drift by an hour twice a year.
*/
$digestDays = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];

Schedule::call(fn () => Artisan::call('deps:digest'))
    ->weeklyOn(
        $digestDays[strtolower((string) config('health.deps_digest_day', 'monday'))] ?? 1,
        (string) config('health.deps_digest_hour', '08:00'),
    )
    ->timezone(config('app.display_timezone'))
    ->name('deps-digest')
    ->withoutOverlapping(10);
