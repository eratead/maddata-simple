<?php

use App\Services\Health\HealthMarkers;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['health.probe_url' => 'https://example.test/up']);
    HealthMarkers::store()->forget(HealthMarkers::PUBLIC_PROBE);
});

function probeMarker(): array
{
    return HealthMarkers::store()->get(HealthMarkers::PUBLIC_PROBE);
}

it('records a successful probe', function () {
    Http::fake(['*' => Http::response('OK', 200)]);

    $this->artisan('health:probe')
        ->expectsOutputToContain('Probe OK')
        ->assertExitCode(0);

    expect(probeMarker())
        ->toHaveKey('ok', true)
        ->toHaveKey('consec_fails', 0)
        ->and(probeMarker()['latency_ms'])->toBeInt();
});

it('records an http error as a failure', function () {
    Http::fake(['*' => Http::response('nope', 502)]);

    $this->artisan('health:probe')->assertExitCode(0);

    expect(probeMarker())
        ->toHaveKey('ok', false)
        ->toHaveKey('consec_fails', 1)
        ->toHaveKey('error', 'HTTP 502');
});

it('records a connection timeout as a failure', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    $this->artisan('health:probe')->assertExitCode(0);

    expect(probeMarker())->toHaveKey('ok', false)
        ->and(probeMarker()['error'])->toBe('ConnectionException');
});

it('accumulates consecutive failures', function () {
    Http::fake(['*' => Http::response('nope', 500)]);

    $this->artisan('health:probe')->run();
    $this->artisan('health:probe')->run();
    $this->artisan('health:probe')->run();

    // The count is what escalates P1 to CRIT; one blip during a deploy is not
    // an outage, three in a row is.
    expect(probeMarker()['consec_fails'])->toBe(3);
});

it('resets the failure count as soon as the probe recovers', function () {
    // fakeSequence, not two fake() calls: fake() APPENDS stubs, so a later
    // '*' stub never replaces an earlier one.
    Http::fakeSequence()
        ->push('nope', 500)
        ->push('nope', 500)
        ->push('OK', 200);

    $this->artisan('health:probe')->run();
    $this->artisan('health:probe')->run();
    expect(probeMarker()['consec_fails'])->toBe(2);

    $this->artisan('health:probe')->run();

    expect(probeMarker()['consec_fails'])->toBe(0);
});

it('never exits non-zero, so a failing probe does not spam cron mail', function () {
    Http::fake(fn () => throw new ConnectionException('down'));

    // A failing probe is DATA for the monitor, not a broken command.
    $this->artisan('health:probe')->assertExitCode(0);
});

it('does not follow redirects', function () {
    Http::fake(['*' => Http::response('', 302, ['Location' => 'https://elsewhere.test'])]);

    $this->artisan('health:probe')->assertExitCode(0);

    // A 302 away from /up means something is wrong with routing or TLS,
    // and quietly following it would report a healthy edge.
    expect(probeMarker())->toHaveKey('ok', false)
        ->toHaveKey('error', 'HTTP 302');
});
