<?php

use App\Enums\HealthStatus;
use App\Models\User;
use App\Services\Health\Checks\SecurityPostureCheck;
use App\Services\Health\HealthMarkers;
use Illuminate\Auth\Events\Failed;

function posture(string $key): App\Dtos\HealthCheckResult
{
    return checksByKey(app(SecurityPostureCheck::class))[$key];
}

function seedFailedLogins(int $count, int $minutesAgo = 0): void
{
    HealthMarkers::store()->forever(
        HealthMarkers::AUTH_FAIL_PREFIX.now()->subMinutes($minutesAgo)->format('YmdHi'),
        $count,
    );
}

beforeEach(function () {
    config(['health.mysql_connection' => config('database.default')]);
});

/* ── X1 ─────────────────────────────────────────────────────────────────── */

it('passes when no expired tokens are lying around', function () {
    expect(posture('X1')->status)->toBe(HealthStatus::OK)
        ->and(posture('X1')->value)->toBe('none');
});

it('warns — never worse — about expired tokens still in the table', function () {
    $user = User::factory()->create();
    $token = $user->createToken('stale')->accessToken;
    $token->expires_at = now()->subDay();
    $token->save();

    // CheckTokenExpiry already refuses these. Leftovers are untidy, not dangerous.
    expect(posture('X1')->status)->toBe(HealthStatus::WARN)
        ->and(posture('X1')->value)->toBe('1 past expiry');
});

it('ignores tokens that have not expired yet', function () {
    $user = User::factory()->create();
    $token = $user->createToken('live')->accessToken;
    $token->expires_at = now()->addDays(30);
    $token->save();

    expect(posture('X1')->status)->toBe(HealthStatus::OK);
});

/* ── X2 ─────────────────────────────────────────────────────────────────── */

it('passes a quiet window', function () {
    expect(posture('X2')->status)->toBe(HealthStatus::OK)
        ->and(posture('X2')->value)->toBe('none');
});

it('holds at ok just below the burst threshold', function () {
    seedFailedLogins(19);

    expect(posture('X2')->status)->toBe(HealthStatus::OK);
});

it('warns at the burst threshold', function () {
    seedFailedLogins(20);

    expect(posture('X2')->status)->toBe(HealthStatus::WARN);
});

it('goes critical on a large burst', function () {
    seedFailedLogins(100);

    expect(posture('X2')->status)->toBe(HealthStatus::CRIT);
});

it('sums the whole window, not just this minute', function () {
    seedFailedLogins(10, minutesAgo: 0);
    seedFailedLogins(10, minutesAgo: 7);

    expect(posture('X2')->status)->toBe(HealthStatus::WARN);
});

it('forgets failures older than the window', function () {
    seedFailedLogins(50, minutesAgo: 30);

    expect(posture('X2')->status)->toBe(HealthStatus::OK);
});

/* ── The listener that feeds X2 ──────────────────────────────────────────── */

it('counts a failed login into the current bucket', function () {
    event(new Failed('web', null, ['email' => 'nobody@example.test']));
    event(new Failed('web', null, ['email' => 'nobody@example.test']));

    expect(posture('X2')->value)->toContain('2');
});

it('lets people log in even when the marker store is broken', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-horse'), 'is_active' => true]);

    // Breaks ONLY the health markers, not the app cache: the listener must
    // swallow its own failure rather than take the login request down with it.
    config(['health.marker_store' => 'no-such-store']);

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertRedirect();
});

it('puts the failed-login burst on a node that can raise an alert', function () {
    /*
     * Audit finding M-2. X1 (expired-token housekeeping) belongs on the
     * digest-only `platform` node. X2 does not: it is the one check that sees
     * an attack happening now, and thresholds of 20 and 100 failures in 15
     * minutes mean nothing if the result waits for a weekly email.
     */
    $excluded = (array) config('health.alert_excluded_nodes', []);

    expect(posture('X2')->node)->not->toBeIn($excluded)
        ->and(posture('X1')->node)->toBeIn($excluded);
});
