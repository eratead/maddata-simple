<?php

use App\Dtos\HealthCheckResult;
use App\Enums\HealthStatus;
use App\Models\Role;
use App\Models\User;
use App\Services\Health\Checks\HealthCheck;
use App\Services\Health\HealthMarkers;

/** A cheap, deterministic stand-in for the real 26 — no files, no MySQL, no Redis. */
class MonitorPageCheck extends HealthCheck
{
    public static HealthStatus $status = HealthStatus::OK;

    public function run(): array
    {
        return [
            new HealthCheckResult(
                key: 'H2', label: 'CPU', status: self::$status,
                node: 'host', value: '24%', threshold: 'warn ≥70%',
            ),
            new HealthCheckResult(
                key: 'D1', label: 'MySQL reachable', status: HealthStatus::OK,
                node: 'data', value: '12ms', threshold: 'warn ≥100ms',
            ),
        ];
    }
}

/** Everything the monitor depends on, on fire at once. */
class MonitorBoomCheck extends HealthCheck
{
    public function run(): array
    {
        throw new RuntimeException('the thing I monitor is on fire');
    }
}

/** Not an admin, but the most privileged non-admin the app has. */
function monitorAgencyManager(): User
{
    $role = Role::create([
        'name' => 'Agency Manager '.uniqid(),
        'permissions' => ['can_manage_users' => true, 'can_manage_clients' => true],
    ]);

    $user = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $user->role_id = $role->id;
    $user->save();

    return $user;
}

beforeEach(function () {
    MonitorPageCheck::$status = HealthStatus::OK;
    config(['health.checks' => [MonitorPageCheck::class]]);
    HealthMarkers::store()->forget(HealthMarkers::SNAPSHOT);
    HealthMarkers::store()->forget(HealthMarkers::SNAPSHOT_LAST);
});

it('renders the monitor page for an admin', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get(route('admin.monitor.index'))
        ->assertOk()
        ->assertSee('System Health')
        ->assertSee('H2');
});

it('serves the snapshot JSON in the documented contract shape', function () {
    app(App\Services\Health\SystemHealthService::class)->refresh();

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->getJson(route('admin.monitor.data'))
        ->assertOk()
        ->assertJsonStructure([
            'overall',
            'generated_at',
            'nodes' => [['key', 'label', 'status', 'check_keys']],
            'checks' => [['key', 'label', 'status', 'node', 'value', 'threshold', 'link']],
        ]);
});

/*
| Three separate assertions, not one. Health is system-level: no Agency, Client
| or User relationship scopes it, so the admin middleware is the ONLY thing
| between an agency manager and the droplet's internals. The JSON endpoint is
| the one that actually leaks, and it does not inherit the page's test.
*/
it('forbids an agency manager from the monitor page', function () {
    $this->actingAs(monitorAgencyManager())
        ->get(route('admin.monitor.index'))
        ->assertForbidden();
});

it('forbids an agency manager from the JSON endpoint', function () {
    $this->actingAs(monitorAgencyManager())
        ->getJson(route('admin.monitor.data'))
        ->assertForbidden();
});

it('forbids an agency manager from forcing a refresh', function () {
    $this->actingAs(monitorAgencyManager())
        ->postJson(route('admin.monitor.refresh'))
        ->assertForbidden();
});

it('redirects guests away from all three endpoints', function () {
    $this->get(route('admin.monitor.index'))->assertRedirect();
    $this->get(route('admin.monitor.data'))->assertRedirect();
    $this->post(route('admin.monitor.refresh'))->assertRedirect();
});

it('never rebuilds from the polled endpoint, however cold the cache', function () {
    /*
     * Audit finding. When the marker store is unreadable, a rebuilding read
     * path had every poll from every open tab running the full check suite —
     * outbound HTTPS included — inside an FPM worker. On one core with a small
     * pool that is the monitor taking the site down.
     *
     * Rebuilding belongs to the scheduler and the CLI. The page reads.
     */
    MonitorPageCheck::$status = HealthStatus::CRIT;

    $response = $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->getJson(route('admin.monitor.data'))
        ->assertOk();

    // Nothing cached and nothing built: an empty snapshot, which the page's own
    // stale banner already knows how to render.
    expect($response->json('checks'))->toBe([])
        ->and($response->json('overall'))->toBe('ok');
});

it('rebuilds on demand and returns a newer snapshot', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $before = app(App\Services\Health\SystemHealthService::class)->refresh()->toArray()['generated_at'];

    $this->travel(5)->seconds();

    $after = $this->actingAs($admin)->postJson(route('admin.monitor.refresh'))->json('generated_at');

    expect($after)->not->toBe($before);
});

it('serves the in-flight snapshot instead of stampeding when a rebuild already holds the lock', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $cached = app(App\Services\Health\SystemHealthService::class)->refresh()->toArray()['generated_at'];

    // Someone else — the scheduler, or another admin's click — is mid-rebuild.
    $lock = HealthMarkers::store()->lock(HealthMarkers::SNAPSHOT_LOCK, 20);
    expect($lock->get())->toBeTrue();

    $this->travel(5)->seconds();

    $response = $this->actingAs($admin)->postJson(route('admin.monitor.refresh'))->assertOk();

    // Unchanged: we took their result rather than queueing a second rebuild
    // behind it. A sick box is exactly when the probes are slowest.
    expect($response->json('generated_at'))->toBe($cached);

    $lock->release();
});

it('still answers 200 with a CRIT payload when every check explodes', function () {
    config(['health.checks' => [MonitorBoomCheck::class]]);
    HealthMarkers::store()->forget(HealthMarkers::SNAPSHOT);
    HealthMarkers::store()->forget(HealthMarkers::SNAPSHOT_LAST);

    app(App\Services\Health\SystemHealthService::class)->refresh();

    // Resilience is THE property of this vertical, so it is asserted at the
    // HTTP boundary too — not only in the service unit test.
    $response = $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->getJson(route('admin.monitor.data'))
        ->assertOk();

    expect($response->json('overall'))->toBe('crit');
});

it('throttles the polled JSON endpoint', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    app(App\Services\Health\SystemHealthService::class)->refresh();

    for ($i = 0; $i < 60; $i++) {
        $this->actingAs($admin)->getJson(route('admin.monitor.data'))->assertOk();
    }

    $this->actingAs($admin)->getJson(route('admin.monitor.data'))->assertStatus(429);
});

it('throttles the refresh button harder than the poller', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    for ($i = 0; $i < 6; $i++) {
        $this->actingAs($admin)->postJson(route('admin.monitor.refresh'))->assertOk();
    }

    $this->actingAs($admin)->postJson(route('admin.monitor.refresh'))->assertStatus(429);
});
