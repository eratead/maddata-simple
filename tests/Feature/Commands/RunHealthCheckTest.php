<?php

use App\Dtos\HealthCheckResult;
use App\Enums\HealthStatus;
use App\Services\Health\Checks\HealthCheck;
use Illuminate\Support\Facades\Artisan;

/** Emits whatever status the test asks for, so exit codes can be pinned down. */
class ConfigurableCheck extends HealthCheck
{
    public static HealthStatus $status = HealthStatus::OK;

    public function run(): array
    {
        return [new HealthCheckResult(
            key: 'X1',
            label: 'Configurable check',
            status: self::$status,
            node: 'data',
            value: 'value',
            threshold: 'threshold',
        )];
    }
}

beforeEach(function () {
    ConfigurableCheck::$status = HealthStatus::OK;
    config(['health.checks' => [ConfigurableCheck::class]]);
});

it('reports a healthy system and exits zero', function () {
    $this->artisan('health:check')
        ->expectsOutputToContain('ALL SYSTEMS GO')
        ->assertExitCode(0);
});

it('exits non-zero only at critical by default', function (HealthStatus $status, int $expected) {
    ConfigurableCheck::$status = $status;

    $this->artisan('health:check')->assertExitCode($expected);
})->with([
    'ok' => [HealthStatus::OK, 0],
    'warn does not fail the default gate' => [HealthStatus::WARN, 0],
    'stale does not fail the default gate' => [HealthStatus::STALE, 0],
    'crit fails' => [HealthStatus::CRIT, 2],
]);

it('exits non-zero on anything but green with --fail-on=warn', function (HealthStatus $status, int $expected) {
    ConfigurableCheck::$status = $status;

    // Not being able to see is a reason to stop a deploy too, so STALE counts.
    $this->artisan('health:check', ['--fail-on' => 'warn'])->assertExitCode($expected);
})->with([
    'ok' => [HealthStatus::OK, 0],
    'warn' => [HealthStatus::WARN, 1],
    'stale' => [HealthStatus::STALE, 1],
    'crit' => [HealthStatus::CRIT, 2],
]);

it('emits the json contract', function () {
    ConfigurableCheck::$status = HealthStatus::WARN;

    expect(Artisan::call('health:check', ['--json' => true]))->toBe(0);

    $decoded = json_decode(Artisan::output(), true);

    expect($decoded)->toBeArray()
        ->and($decoded['overall'])->toBe('warn')
        ->and($decoded['checks'][0]['key'])->toBe('X1')
        ->and($decoded['nodes'][0]['key'])->toBe('data')
        ->and($decoded)->toHaveKey('generated_at');
});

it('names the failing checks in its summary line', function () {
    ConfigurableCheck::$status = HealthStatus::CRIT;

    expect(Artisan::call('health:check'))->toBe(2);

    expect(Artisan::output())
        ->toContain('OUTAGE')
        ->toContain('1 failing')
        ->toContain('X1');
});
