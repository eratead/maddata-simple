<?php

use App\Dtos\HealthCheckResult;
use App\Enums\HealthStatus;
use App\Mail\HealthAlertMail;
use App\Services\Health\Checks\HealthCheck;
use App\Services\Health\HealthMarkers;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/** Emits whatever the test dictates, so every alert path can be driven. */
class AlertableCheck extends HealthCheck
{
    public static HealthStatus $status = HealthStatus::OK;

    /** @var array<string, HealthStatus> extra checks, keyed by check key */
    public static array $extra = [];

    /** @var array<string, HealthStatus> checks on the digest-only `platform` node */
    public static array $platform = [];

    public function run(): array
    {
        $results = [new HealthCheckResult(
            key: 'A1',
            label: 'Primary check',
            status: self::$status,
            node: 'data',
            value: 'value',
            threshold: 'threshold',
        )];

        foreach (self::$extra as $key => $status) {
            $results[] = new HealthCheckResult($key, $key.' check', $status, 'data', 'v', 't');
        }

        foreach (self::$platform as $key => $status) {
            $results[] = new HealthCheckResult($key, $key.' check', $status, 'platform', 'v', 't');
        }

        return $results;
    }
}

beforeEach(function () {
    AlertableCheck::$status = HealthStatus::OK;
    AlertableCheck::$extra = [];
    AlertableCheck::$platform = [];

    config([
        'health.checks' => [AlertableCheck::class],
        'health.alert_recipients' => ['ops@maddata.test'],
        'health.realert_hours' => 6,
        'health.alert_excluded_nodes' => ['platform'],
    ]);

    HealthMarkers::store()->forget(HealthMarkers::ALERT_STATE);

    // Pin uptime well past the boot-grace window. Without this these tests read
    // the real /proc/uptime, so a CI container that has been up for under three
    // minutes would suppress every alert and fail the whole file.
    fakeUptime(86400);

    Mail::fake();
});

/** Each run must observe current state, not the 30s-cached snapshot. */
function runAlert(array $options = []): void
{
    HealthMarkers::store()->forget(HealthMarkers::SNAPSHOT);
    HealthMarkers::store()->forget(HealthMarkers::SNAPSHOT_LAST);

    test()->artisan('health:alert', $options)->run();
}

it('stays quiet while everything is healthy', function () {
    runAlert();
    runAlert();

    Mail::assertNothingSent();
});

it('does not alert on a single non-ok observation', function () {
    AlertableCheck::$status = HealthStatus::CRIT;

    runAlert();

    // A deploy restarting PHP-FPM resolves well inside one interval. One
    // observation is never enough to wake anyone up.
    Mail::assertNothingSent();
});

it('alerts on the second consecutive non-ok observation', function () {
    AlertableCheck::$status = HealthStatus::CRIT;

    runAlert();
    runAlert();

    Mail::assertSent(HealthAlertMail::class, function (HealthAlertMail $mail) {
        return $mail->hasTo('ops@maddata.test')
            && $mail->isRecovery === false
            && $mail->reason === 'New problem detected.';
    });
});

it('does not repeat the same alert inside the re-alert window', function () {
    AlertableCheck::$status = HealthStatus::CRIT;

    runAlert();
    runAlert();
    Mail::assertSentCount(1);

    test()->travel(3)->hours();
    runAlert();

    Mail::assertSentCount(1);
});

it('nags again once the re-alert window has passed', function () {
    AlertableCheck::$status = HealthStatus::CRIT;

    runAlert();
    runAlert();
    Mail::assertSentCount(1);

    test()->travel(7)->hours();
    runAlert();

    Mail::assertSentCount(2);
    Mail::assertSent(HealthAlertMail::class, fn ($mail) => str_starts_with($mail->reason, 'Still failing after'));
});

it('holds a same-severity new failing check until the notify floor passes', function () {
    AlertableCheck::$status = HealthStatus::WARN;
    runAlert();
    runAlert();
    Mail::assertSentCount(1);

    // Two checks straddling their thresholds and alternating would otherwise
    // produce an email every five minutes forever.
    AlertableCheck::$extra = ['B9' => HealthStatus::WARN];
    runAlert();
    Mail::assertSentCount(1);

    test()->travel(16)->minutes();
    runAlert();

    Mail::assertSentCount(2);
    Mail::assertSent(HealthAlertMail::class, fn ($mail) => $mail->reason === 'New failing check: B9.');
});

it('never delays an escalation behind the notify floor', function () {
    AlertableCheck::$status = HealthStatus::WARN;
    runAlert();
    runAlert();
    Mail::assertSentCount(1);

    // The system got worse. That is the one thing you must hear immediately,
    // floor or no floor.
    AlertableCheck::$status = HealthStatus::CRIT;
    runAlert();

    Mail::assertSentCount(2);
    Mail::assertSent(HealthAlertMail::class, fn ($mail) => $mail->reason === 'Escalated from warn to crit.');
});

it('stays quiet on a partial recovery that leaves the status unchanged', function () {
    AlertableCheck::$status = HealthStatus::WARN;
    AlertableCheck::$extra = ['B9' => HealthStatus::WARN];
    runAlert();
    runAlert();
    Mail::assertSentCount(1);

    // One of the two recovered. Fewer problems is not news — the re-alert
    // timer covers the ones still outstanding.
    AlertableCheck::$extra = [];
    runAlert();

    Mail::assertSentCount(1);
});

it('sends a recovery notice once things go green again', function () {
    AlertableCheck::$status = HealthStatus::CRIT;
    runAlert();
    runAlert();
    Mail::assertSentCount(1);

    AlertableCheck::$status = HealthStatus::OK;
    runAlert();

    Mail::assertSentCount(2);
    Mail::assertSent(HealthAlertMail::class, fn ($mail) => $mail->isRecovery === true);
});

it('does not send a recovery notice for a blip nobody was told about', function () {
    AlertableCheck::$status = HealthStatus::CRIT;
    runAlert();

    AlertableCheck::$status = HealthStatus::OK;
    runAlert();

    // Nothing was ever reported, so there is nothing to un-report.
    Mail::assertNothingSent();
});

it('clears its state after recovering so the next problem is treated as new', function () {
    AlertableCheck::$status = HealthStatus::CRIT;
    runAlert();
    runAlert();
    AlertableCheck::$status = HealthStatus::OK;
    runAlert();

    expect(HealthMarkers::store()->get(HealthMarkers::ALERT_STATE))->toBeNull();
});

it('skips suppression and alerts immediately when its own state is unreadable', function () {
    $real = Cache::store('array');

    $repo = Mockery::mock(Repository::class);
    $repo->shouldReceive('get')->with(HealthMarkers::ALERT_STATE)
        ->andThrow(new RuntimeException('cache is down'));
    $repo->shouldReceive('get')->andReturnUsing(fn ($key) => $real->get($key));
    $repo->shouldReceive('put')->andReturnUsing(fn ($key, $value, $ttl = null) => $real->put($key, $value, $ttl ?? 60));
    $repo->shouldReceive('forever')->andReturnUsing(fn ($key, $value) => $real->forever($key, $value));
    $repo->shouldReceive('forget')->andReturnUsing(fn ($key) => $real->forget($key));
    $repo->shouldReceive('lock')->andReturnUsing(fn ($name, $seconds = 0) => $real->lock($name, $seconds));

    Cache::shouldReceive('store')->andReturn($repo);

    AlertableCheck::$status = HealthStatus::CRIT;
    runAlert();

    // A cache outage is exactly the correlated failure this command exists to
    // report. Its own broken state must never be why it stays quiet.
    Mail::assertSent(HealthAlertMail::class);
});

it('does not fail when the mailer throws', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp is down'));

    AlertableCheck::$status = HealthStatus::CRIT;

    HealthMarkers::store()->forget(HealthMarkers::SNAPSHOT);
    $this->artisan('health:alert')->assertExitCode(0);
    HealthMarkers::store()->forget(HealthMarkers::SNAPSHOT);
    $this->artisan('health:alert')->assertExitCode(0);
});

it('does not record a notification when the send fails', function () {
    AlertableCheck::$status = HealthStatus::CRIT;
    runAlert();

    // Second observation: suppression satisfied, but the send blows up.
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp is down'));
    runAlert();

    // An unsent alert is not a sent alert. Leaving notified_at null is what
    // makes the next tick try again instead of swallowing it.
    expect(HealthMarkers::store()->get(HealthMarkers::ALERT_STATE)['notified_at'])->toBeNull();
});

it('alerts on the next run after a failed send', function () {
    // The state a failed send leaves behind: episode under way, nobody told.
    HealthMarkers::store()->forever(HealthMarkers::ALERT_STATE, [
        'signature' => 'A1:crit',
        'status' => 'crit',
        'consecutive_non_ok' => 2,
        'episode_started_at' => now()->subMinutes(10)->getTimestamp(),
        'notified_at' => null,
        'notified_signature' => null,
        'notified_status' => null,
    ]);

    AlertableCheck::$status = HealthStatus::CRIT;
    runAlert();

    Mail::assertSent(HealthAlertMail::class, fn ($mail) => $mail->reason === 'New problem detected.');
});

it('sends nothing when no recipients are configured', function () {
    config(['health.alert_recipients' => []]);

    AlertableCheck::$status = HealthStatus::CRIT;
    runAlert();
    runAlert();

    Mail::assertNothingSent();
});

it('sends on demand with --force regardless of state', function () {
    runAlert(['--force' => true]);

    Mail::assertSent(HealthAlertMail::class, fn ($mail) => $mail->reason === 'Manual send.');
});

it('subjects the mail by severity', function () {
    AlertableCheck::$status = HealthStatus::CRIT;
    runAlert();
    runAlert();

    Mail::assertSent(HealthAlertMail::class, function (HealthAlertMail $mail) {
        return $mail->envelope()->subject === '[MadData] OUTAGE - 1 check failing';
    });
});

it('renders the alert email', function () {
    // Mail::fake() never renders the view, so without this a broken Blade
    // template would ship and only fail at 3am when it actually matters.
    $snapshot = App\Dtos\HealthSnapshot::fromResults([
        new HealthCheckResult('A1', 'Primary check', HealthStatus::CRIT, 'data', '3 failures', 'warn ≥1'),
        new HealthCheckResult('B1', 'Local backup age', HealthStatus::WARN, 'backups', '2d 4h', 'age ≥1d 2h'),
        new HealthCheckResult('C1', 'Healthy check', HealthStatus::OK, 'edge', 'fine', ''),
    ]);

    $html = (new HealthAlertMail($snapshot, 'New problem detected.'))->render();

    expect($html)->toContain('OUTAGE')
        ->toContain('New problem detected.')
        ->toContain('Primary check')
        ->toContain('Local backup age')
        ->toContain('3 checks run')
        ->and($html)->not->toContain('Healthy check');
});

it('renders the recovery email', function () {
    $snapshot = App\Dtos\HealthSnapshot::fromResults([
        new HealthCheckResult('A1', 'Primary check', HealthStatus::OK, 'data', 'fine', ''),
    ]);

    $html = (new HealthAlertMail(
        snapshot: $snapshot,
        reason: 'Everything recovered after 2h.',
        isRecovery: true,
        episodeStartedAt: now()->subHours(2)->getTimestamp(),
    ))->render();

    expect($html)->toContain('All systems go')
        ->toContain('Everything recovered after 2h.')
        ->toContain('The problem lasted 2h');
});

it('holds alerts while the host is still booting', function () {
    fakeUptime(30);
    AlertableCheck::$status = HealthStatus::CRIT;

    runAlert();
    runAlert();

    // Consecutive-observation suppression does not cover this: at reboot an
    // existing episode escalates, and escalations alert immediately. That is
    // exactly how production sent a false outage alert after a routine reboot.
    Mail::assertNothingSent();
});

it('resumes alerting once the boot grace window closes', function () {
    fakeUptime(3600);
    AlertableCheck::$status = HealthStatus::CRIT;

    runAlert();
    runAlert();

    Mail::assertSent(HealthAlertMail::class);
});

it('still sends with --force during boot grace', function () {
    fakeUptime(10);

    runAlert(['--force' => true]);

    Mail::assertSent(HealthAlertMail::class);
});

/*
|--------------------------------------------------------------------------
| The alerting split (Phase 4)
|--------------------------------------------------------------------------
|
| Checks on an excluded node are real findings reported everywhere else, and
| deps:digest owns them. The property that matters is that routing them away
| CANNOT silence anything that was alerting before.
|
*/

it('never alerts on a problem confined to a digest-only node', function () {
    AlertableCheck::$platform = ['d1' => HealthStatus::CRIT];

    runAlert();
    runAlert();
    runAlert();

    // A critical composer advisory is real, and it is not a 2am page.
    Mail::assertNothingSent();
});

it('still alerts on a real outage while a digest-only check is also failing', function () {
    AlertableCheck::$status = HealthStatus::CRIT;
    AlertableCheck::$platform = ['d1' => HealthStatus::CRIT];

    runAlert();
    runAlert();

    Mail::assertSent(HealthAlertMail::class);
});

it('names the digest-only findings in the alert without counting them as failures', function () {
    AlertableCheck::$status = HealthStatus::WARN;
    AlertableCheck::$platform = ['d1' => HealthStatus::CRIT, 'd4' => HealthStatus::WARN];

    runAlert();
    runAlert();

    Mail::assertSent(HealthAlertMail::class, function (HealthAlertMail $mail) {
        // The subject describes the alertable half only — one WARN, not an
        // OUTAGE — while the body still cross-references the other channel.
        expect($mail->envelope()->subject)->toContain('Degraded')->toContain('1 check');

        $rendered = $mail->render();

        expect($rendered)->toContain('weekly dependency digest')
            ->and($rendered)->toContain('d1');

        return true;
    });
});

it('treats a recovery as a recovery even while digest-only checks are still failing', function () {
    AlertableCheck::$status = HealthStatus::CRIT;
    runAlert();
    runAlert();
    Mail::assertSent(HealthAlertMail::class);

    // The outage clears; a dependency finding remains, as it will for weeks.
    AlertableCheck::$status = HealthStatus::OK;
    AlertableCheck::$platform = ['d1' => HealthStatus::CRIT];
    runAlert();

    // Without the split this recovery notice would never be sent, and the
    // operator would be left believing the outage was still open.
    Mail::assertSent(HealthAlertMail::class, fn (HealthAlertMail $mail) => $mail->isRecovery);
});

it('does not treat a new digest-only finding as a new failing check', function () {
    AlertableCheck::$status = HealthStatus::CRIT;
    runAlert();
    runAlert();

    $sentAfterOutage = count(Mail::sent(HealthAlertMail::class));

    // A fresh advisory lands. The signature must not move — otherwise this
    // reads as "something new broke" and re-alerts about a dependency.
    AlertableCheck::$platform = ['d1' => HealthStatus::CRIT];
    runAlert();

    expect(Mail::sent(HealthAlertMail::class))->toHaveCount($sentAfterOutage);
});
