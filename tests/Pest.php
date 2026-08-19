<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| System health helpers
|--------------------------------------------------------------------------
|
| The health checks read two files written by root cron on the droplet. Tests
| point the config at a temp file instead — no test ever executes a shell
| command to produce them.
|
*/

function fakeHostFacts(?array $facts): string
{
    $path = sys_get_temp_dir().'/maddata-host-facts-'.getmypid().'.json';

    $facts === null
        ? @unlink($path)
        : file_put_contents($path, json_encode($facts));

    config(['health.facts_path' => $path]);
    app(App\Services\Health\HostFacts::class)->flush();

    return $path;
}

function fakeBackupMarker(?array $marker): string
{
    $path = sys_get_temp_dir().'/maddata-backup-marker-'.getmypid().'.json';

    $marker === null
        ? @unlink($path)
        : file_put_contents($path, json_encode($marker));

    config(['health.backup_marker_path' => $path]);

    return $path;
}

/** Index a check run by check key, so assertions read like the catalog. */
function checksByKey(App\Services\Health\Checks\HealthCheck $check): array
{
    $results = [];

    foreach ($check->run() as $result) {
        $results[$result->key] = $result;
    }

    return $results;
}

/**
 * Point checks d1/d4 at a fixture lock file instead of the repo's own, so their
 * assertions do not change every time a dependency is bumped.
 */
function fakeComposerLock(?array $lock): string
{
    $path = sys_get_temp_dir().'/maddata-composer-lock-'.getmypid().'.json';

    $lock === null
        ? @unlink($path)
        : file_put_contents($path, json_encode($lock));

    config(['health.composer_lock_path' => $path]);

    return $path;
}

/** Fake /proc/uptime so boot-grace behaviour is testable off Linux. */
function fakeUptime(?int $seconds): void
{
    $path = sys_get_temp_dir().'/maddata-uptime-'.getmypid();

    $seconds === null
        ? @unlink($path)
        : file_put_contents($path, $seconds.'.00 '.($seconds * 2).'.00');

    config(['health.uptime_path' => $path]);
    app(App\Services\Health\HostFacts::class)->flush();
}
