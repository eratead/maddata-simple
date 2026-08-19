<?php

use App\Dtos\HealthCheckResult;
use App\Enums\HealthStatus;
use App\Mail\DependencyDigestMail;
use App\Services\Health\Checks\HealthCheck;
use App\Services\Health\HealthMarkers;
use Illuminate\Support\Facades\Mail;

/** Two checks on the digest-owned node, driven by the test. */
class DigestCheck extends HealthCheck
{
    public static HealthStatus $advisories = HealthStatus::OK;

    public static HealthStatus $eol = HealthStatus::OK;

    public function run(): array
    {
        return [
            new HealthCheckResult('d1', 'Composer advisories', self::$advisories, 'platform', 'value', 't'),
            new HealthCheckResult('d2-php', 'PHP version', self::$eol, 'platform', '8.4.7', 't'),
        ];
    }
}

function runDigest(): Illuminate\Testing\PendingCommand
{
    HealthMarkers::store()->forget(HealthMarkers::SNAPSHOT);
    HealthMarkers::store()->forget(HealthMarkers::SNAPSHOT_LAST);

    return test()->artisan('deps:digest');
}

beforeEach(function () {
    DigestCheck::$advisories = HealthStatus::OK;
    DigestCheck::$eol = HealthStatus::OK;

    config([
        'health.checks' => [DigestCheck::class],
        'health.alert_excluded_nodes' => ['platform'],
        'health.deps_digest_recipients' => ['ops@maddata.test'],
        'health.alert_recipients' => ['fallback@maddata.test'],
    ]);

    Mail::fake();
});

it('sends even when there is nothing wrong', function () {
    runDigest()->assertSuccessful();

    // A report that only appears on bad news is indistinguishable from one that
    // has stopped working — the same argument that makes recovery notices
    // mandatory in Phase 2.
    Mail::assertSent(DependencyDigestMail::class, function (DependencyDigestMail $mail) {
        expect($mail->envelope()->subject)->toContain('all clear');

        return true;
    });
});

it('reports the findings when there are some', function () {
    DigestCheck::$advisories = HealthStatus::CRIT;
    DigestCheck::$eol = HealthStatus::WARN;

    runDigest()->assertSuccessful();

    Mail::assertSent(DependencyDigestMail::class, function (DependencyDigestMail $mail) {
        expect($mail->envelope()->subject)->toContain('2 items');

        $rendered = $mail->render();

        expect($rendered)->toContain('Composer advisories')
            ->and($rendered)->toContain('deps:mark-patch-run');

        return true;
    });
});

it('lists what is fine alongside what is not', function () {
    DigestCheck::$advisories = HealthStatus::CRIT;

    runDigest()->assertSuccessful();

    Mail::assertSent(DependencyDigestMail::class, function (DependencyDigestMail $mail) {
        expect($mail->render())->toContain('Currently fine')->toContain('PHP version');

        return true;
    });
});

it('falls back to the alert recipients when no digest recipients are set', function () {
    config(['health.deps_digest_recipients' => []]);

    runDigest()->assertSuccessful();

    Mail::assertSent(DependencyDigestMail::class, fn ($mail) => $mail->hasTo('fallback@maddata.test'));
});

it('stays quiet and successful when nobody is configured to receive it', function () {
    config(['health.deps_digest_recipients' => [], 'health.alert_recipients' => []]);

    runDigest()->assertSuccessful();

    Mail::assertNothingSent();
});

it('sends nothing when no checks are routed to the digest', function () {
    config(['health.alert_excluded_nodes' => []]);

    runDigest()->assertSuccessful();

    Mail::assertNothingSent();
});

it('does not fail the scheduler when the mailer throws', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp is down'));

    // A broken mailer must be logged, never thrown: it would otherwise take the
    // whole scheduled run down with it.
    runDigest()->assertSuccessful();
});
