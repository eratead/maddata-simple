<?php

use App\Dtos\HealthCheckResult;
use App\Enums\HealthStatus;
use App\Models\User;
use App\Services\Health\Checks\HealthCheck;
use App\Services\Health\SystemHealthService;

/** A permanently-failing platform check, plus an app check the test drives. */
class PillPlatformCheck extends HealthCheck
{
    public static HealthStatus $appStatus = HealthStatus::OK;

    public function run(): array
    {
        return [
            new HealthCheckResult('d2-mysql', 'MySQL version', HealthStatus::WARN, 'platform', 'past upstream EOL'),
            new HealthCheckResult('Q2', 'Failed jobs', self::$appStatus, 'app', 'n'),
        ];
    }
}

/*
| The pill renders into layouts/app.blade.php, which every authenticated page
| uses — including client-facing ones. So it is tested on an UNRELATED page,
| which is where it actually lives, and the non-admin case asserts the markup is
| ABSENT rather than merely hidden.
*/

beforeEach(function () {
    PillPlatformCheck::$appStatus = HealthStatus::OK;
    // Keep the real check registry — and its live Packagist call — out of these
    // tests. Audit finding: this file was POSTing composer.lock's package list
    // to packagist.org on every run.
    config(['health.checks' => [PillPlatformCheck::class]]);
});

it('shows the health pill to an admin on an unrelated page', function () {
    app(SystemHealthService::class)->refresh();

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get(route('campaigns.index'))
        ->assertOk()
        ->assertSee('data-testid="health-pill"', false);
});

it('renders no pill markup at all for a non-admin', function () {
    app(SystemHealthService::class)->refresh();

    // Not x-show, not hidden — absent. A hidden element would ship system
    // status into the DOM of every non-admin page.
    $this->actingAs(User::factory()->create(['is_admin' => false]))
        ->get(route('campaigns.index'))
        ->assertOk()
        ->assertDontSee('data-testid="health-pill"', false);
});

it('keeps an unrelated page alive when the health service blows up', function () {
    $this->mock(SystemHealthService::class, function ($mock) {
        $mock->shouldReceive('pillStatus')->andThrow(new RuntimeException('everything is down'));
    });

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get(route('campaigns.index'))
        ->assertOk()
        ->assertSee(HealthStatus::STALE->label());
});

it('keeps the pill green while only digest-owned checks are failing', function () {
    /*
     * Audit finding. Production carries two runtimes past their upstream
     * windows that nothing short of an OS migration will clear, so on `overall`
     * the pill would read amber forever — and a genuine new warning elsewhere
     * would produce no visible change. The page stays fully honest; the pill
     * spends its one bit on things that need someone now.
     */
    config([
        'health.checks' => [PillPlatformCheck::class],
        'health.alert_excluded_nodes' => ['platform'],
    ]);
    app(SystemHealthService::class)->refresh();

    expect(app(SystemHealthService::class)->pillStatus())->toBe(HealthStatus::OK);
});

it('turns the pill amber for a warning that does need attention', function () {
    config([
        'health.checks' => [PillPlatformCheck::class],
        'health.alert_excluded_nodes' => ['platform'],
    ]);
    PillPlatformCheck::$appStatus = HealthStatus::WARN;
    app(SystemHealthService::class)->refresh();

    expect(app(SystemHealthService::class)->pillStatus())->toBe(HealthStatus::WARN);
});
