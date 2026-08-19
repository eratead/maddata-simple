<?php

use App\Services\Health\HealthMarkers;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Where markers live
|--------------------------------------------------------------------------
|
| Health markers are RECORDS OF PAST EVENTS — "the nightly job last succeeded
| at X", "the last restore drill was Y". Keeping them in the app cache store
| meant `php artisan cache:clear`, which every deploy runs, erased them: four
| deploys in one day left S2a/S2b reporting "has never completed" for jobs that
| were running fine, and B4 reporting no restore drill had ever happened.
|
| Exactly the mistake already made once with the backup marker on tmpfs, where
| a reboot ate it. A record of the past has to outlive routine maintenance.
|
*/

it('ships a dedicated health store that is not the app cache', function () {
    expect(config('cache.stores.health.driver'))->toBe('file')
        ->and(config('cache.stores.health.path'))->not->toBe(config('cache.stores.file.path'));
});

it('defaults markers to the health store rather than the app cache', function () {
    // Read the shipped default directly: the test environment pins this to
    // `array` for isolation, which would otherwise hide a regression here.
    $shipped = file_get_contents(base_path('config/health.php'));

    expect($shipped)->toContain("env('HEALTH_MARKER_STORE', 'health')");
});

it('keeps a recorded marker through a cache:clear', function () {
    config(['health.marker_store' => 'health']);
    HealthMarkers::store()->forget(HealthMarkers::CAMPAIGN_STATUS_OK);

    HealthMarkers::record(HealthMarkers::CAMPAIGN_STATUS_OK);
    expect(HealthMarkers::store()->get(HealthMarkers::CAMPAIGN_STATUS_OK))->not->toBeNull();

    Artisan::call('cache:clear');

    // The property that was broken in production: a deploy must not be able to
    // make a healthy scheduled job look like it has never run.
    expect(HealthMarkers::store()->get(HealthMarkers::CAMPAIGN_STATUS_OK))->not->toBeNull();

    HealthMarkers::store()->forget(HealthMarkers::CAMPAIGN_STATUS_OK);
});

it('does not depend on MySQL or Redis to remember anything', function () {
    // Both are things the monitor reports on. When markers lived on the
    // database store, a MySQL outage made every age check unreadable at the
    // moment they mattered most.
    expect(config('cache.stores.health.driver'))->not->toBe('database')
        ->and(config('cache.stores.health.driver'))->not->toBe('redis');
});

/*
|--------------------------------------------------------------------------
| Recording a drill that happened in the past
|--------------------------------------------------------------------------
*/

it('records a restore drill at the date it actually happened', function () {
    $this->artisan('health:mark-restore-drill', ['--at' => '2026-07-12', '--note' => 'staging restore'])
        ->assertSuccessful();

    $marker = App\Services\Health\HealthMarkers::store()->get(App\Services\Health\HealthMarkers::RESTORE_DRILL);

    // Stamping it "now" would overstate how recently the backups were proven
    // restorable — the one thing this marker exists to answer.
    expect(Carbon\CarbonImmutable::createFromTimestamp($marker['ts'])->toDateString())->toBe('2026-07-12');
});

it('refuses a restore drill dated in the future', function () {
    $this->artisan('health:mark-restore-drill', ['--at' => now()->addWeek()->toDateString()])->assertFailed();
});

it('defaults a restore drill to now when no date is given', function () {
    $this->artisan('health:mark-restore-drill')->assertSuccessful();

    $marker = App\Services\Health\HealthMarkers::store()->get(App\Services\Health\HealthMarkers::RESTORE_DRILL);

    expect($marker['ts'])->toBeGreaterThanOrEqual(now()->subMinute()->getTimestamp());
});
