<?php

use App\Enums\HealthStatus;
use App\Services\Health\Checks\BackupCheck;
use App\Services\Health\HealthMarkers;

afterEach(function () {
    fakeHostFacts(null);
    fakeBackupMarker(null);
});

function backupChecks(?array $marker, array $backups = []): array
{
    fakeHostFacts(['ts' => time(), 'backups' => $backups]);
    fakeBackupMarker($marker);

    return checksByKey(app(BackupCheck::class));
}

it('passes on a recent backup with a successful off-site upload', function () {
    $checks = backupChecks([
        'ts' => time() - 3600,
        'local_bytes' => 1_000_000,
        'remote_ok' => true,
        'remote_bytes' => 1_000_000,
    ]);

    expect($checks['B1']->status)->toBe(HealthStatus::OK)
        ->and($checks['B3']->status)->toBe(HealthStatus::OK)
        ->and($checks['B3']->value)->toContain('976.6KB');
});

it('escalates as the last backup ages', function (int $age, HealthStatus $expected) {
    expect(backupChecks(['ts' => time() - $age, 'remote_ok' => true])['B1']->status)->toBe($expected);
})->with([
    'last night' => [3600 * 8, HealthStatus::OK],
    'missed one run' => [93600, HealthStatus::WARN],
    'missed two runs' => [180000, HealthStatus::CRIT],
]);

it('is critical when backups exist on disk but no marker was written', function () {
    $checks = backupChecks(null, [
        ['ts' => time() - 3600, 'bytes' => 1_000_000],
    ]);

    // An unverifiable backup is not a backup: the dump ran but nothing
    // confirmed it completed, so we cannot claim the system is protected.
    expect($checks['B1']->status)->toBe(HealthStatus::CRIT)
        ->and($checks['B1']->value)->toContain('marker missing');
});

it('is stale when there are no backups and no marker at all', function () {
    $checks = backupChecks(null, []);

    expect($checks['B1']->status)->toBe(HealthStatus::STALE)
        ->and($checks['B3']->status)->toBe(HealthStatus::STALE);
});

it('warns when the off-site upload failed but the local backup survived', function () {
    $checks = backupChecks(['ts' => time() - 3600, 'remote_ok' => false]);

    expect($checks['B1']->status)->toBe(HealthStatus::OK)
        ->and($checks['B3']->status)->toBe(HealthStatus::WARN)
        ->and($checks['B3']->value)->toBe('last upload failed');
});

it('catches a dump that silently shrank', function (int $newestBytes, HealthStatus $expected) {
    // mysqldump can exit 0 having written half a database. A dump that
    // suddenly weighs a fraction of the last few is the only signal of that.
    $history = array_map(fn () => ['ts' => time(), 'bytes' => 1_000_000], range(1, 4));

    $backups = array_merge([['ts' => time(), 'bytes' => $newestBytes]], $history);

    expect(backupChecks(['ts' => time(), 'remote_ok' => true], $backups)['B2']->status)->toBe($expected);
})->with([
    'normal' => [1_000_000, HealthStatus::OK],
    'slightly smaller' => [900_000, HealthStatus::OK],
    'at warn' => [700_000, HealthStatus::WARN],
    'at crit' => [500_000, HealthStatus::CRIT],
    'truncated' => [10_000, HealthStatus::CRIT],
]);

it('does not judge backup size without enough history', function () {
    $checks = backupChecks(['ts' => time(), 'remote_ok' => true], [
        ['ts' => time(), 'bytes' => 10_000],
        ['ts' => time(), 'bytes' => 1_000_000],
    ]);

    // Two samples is not a median, it is noise. Better silent than flapping.
    expect($checks['B2']->status)->toBe(HealthStatus::OK)
        ->and($checks['B2']->value)->toContain('insufficient history');
});

it('warns when no restore drill has ever been recorded', function () {
    HealthMarkers::store()->forget(HealthMarkers::RESTORE_DRILL);

    $check = backupChecks(['ts' => time(), 'remote_ok' => true])['B4'];

    // A restore path nobody has walked is a hope, not a plan — so this warns
    // rather than sitting silently at STALE.
    expect($check->status)->toBe(HealthStatus::WARN)
        ->and($check->value)->toContain('health:mark-restore-drill');
});

it('escalates as the last restore drill ages', function (int $days, HealthStatus $expected) {
    HealthMarkers::store()->forever(HealthMarkers::RESTORE_DRILL, ['ts' => time() - $days * 86400]);

    expect(backupChecks(['ts' => time(), 'remote_ok' => true])['B4']->status)->toBe($expected);
})->with([
    'recent drill' => [30, HealthStatus::OK],
    'at warn' => [120, HealthStatus::WARN],
    'at crit' => [210, HealthStatus::CRIT],
]);

it('records a drill through the artisan command', function () {
    $this->artisan('health:mark-restore-drill', ['--note' => 'restored to staging'])
        ->assertSuccessful();

    expect(backupChecks(['ts' => time(), 'remote_ok' => true])['B4']->status)->toBe(HealthStatus::OK);
});

it('ignores a backup that is still being written', function () {
    // The exact production failure: the facts cron stats the backup directory
    // every minute, so during the nightly run the newest entry is a
    // half-written directory. Before this fix, B2 reported a CRIT "the dump
    // shrank to 33% of median" every single night at 03:00.
    $completedAt = now()->getTimestamp() - 60;

    $backups = [
        ['ts' => now()->getTimestamp(), 'bytes' => 80_000_000],   // in flight, 1/3 written
        ['ts' => $completedAt, 'bytes' => 240_000_000],
        ['ts' => $completedAt - 86400, 'bytes' => 240_000_000],
        ['ts' => $completedAt - 172800, 'bytes' => 230_000_000],
        ['ts' => $completedAt - 259200, 'bytes' => 235_000_000],
    ];

    $checks = backupChecks(['ts' => $completedAt, 'remote_ok' => true], $backups);

    expect($checks['B2']->status)->toBe(HealthStatus::OK)
        ->and($checks['B2']->value)->toContain('228.9MB');
});

it('still catches a genuinely truncated backup once it completes', function () {
    // Same shape, but the marker now covers the small one — it finished, and
    // finishing small is exactly the failure B2 exists to catch.
    $completedAt = now()->getTimestamp();

    $backups = [
        ['ts' => $completedAt, 'bytes' => 80_000_000],
        ['ts' => $completedAt - 86400, 'bytes' => 240_000_000],
        ['ts' => $completedAt - 172800, 'bytes' => 240_000_000],
        ['ts' => $completedAt - 259200, 'bytes' => 230_000_000],
        ['ts' => $completedAt - 345600, 'bytes' => 235_000_000],
    ];

    expect(backupChecks(['ts' => $completedAt, 'remote_ok' => true], $backups)['B2']->status)
        ->toBe(HealthStatus::CRIT);
});
