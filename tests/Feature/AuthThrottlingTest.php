<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Laravel's ThrottleRequests middleware redirects back with a session error for
 * web (HTML) requests when the limit is exceeded. Sending Accept: application/json
 * causes the exception handler to return a proper 429 JSON response instead,
 * which is the correct way to assert throttling in feature tests.
 */
$jsonHeaders = ['Accept' => 'application/json'];

// ── 2FA confirm throttle (POST /2fa/setup, throttle:5,1) ─────────────────────

it('allows the first 5 requests to POST /2fa/setup before throttling', function () use ($jsonHeaders) {
    Cache::flush();

    $user = User::factory()->create();
    $session = ['2fa_setup_secret' => 'JBSWY3DPEHPK3PXP'];

    foreach (range(1, 5) as $attempt) {
        $response = $this->actingAs($user)
            ->withSession($session)
            ->withHeaders($jsonHeaders)
            ->post(route('2fa.confirm'), ['code' => '000000']);

        // The TOTP code is wrong so the controller redirects back with an error,
        // but it must NOT be throttled (429) yet.
        expect($response->status())
            ->not->toBe(429, "Request {$attempt} should not be throttled yet.");
    }
});

it('returns 429 on the 6th POST to /2fa/setup within one minute', function () use ($jsonHeaders) {
    Cache::flush();

    $user = User::factory()->create();
    $session = ['2fa_setup_secret' => 'JBSWY3DPEHPK3PXP'];

    // Burn through the 5-request allowance.
    foreach (range(1, 5) as $attempt) {
        $this->actingAs($user)
            ->withSession($session)
            ->withHeaders($jsonHeaders)
            ->post(route('2fa.confirm'), ['code' => '000000']);
    }

    // 6th request must be throttled.
    $response = $this->actingAs($user)
        ->withSession($session)
        ->withHeaders($jsonHeaders)
        ->post(route('2fa.confirm'), ['code' => '000000']);

    $response->assertStatus(429);
});

// ── Forgot-password throttle (POST /forgot-password, throttle:3,1) ────────────

it('allows the first 3 requests to POST /forgot-password before throttling', function () use ($jsonHeaders) {
    Cache::flush();

    foreach (range(1, 3) as $attempt) {
        $response = $this->withHeaders($jsonHeaders)
            ->post(route('password.email'), ['email' => 'noone@example.com']);

        expect($response->status())
            ->not->toBe(429, "Request {$attempt} should not be throttled yet.");
    }
});

it('returns 429 on the 4th POST to /forgot-password within one minute', function () use ($jsonHeaders) {
    Cache::flush();

    // Burn through the 3-request allowance.
    foreach (range(1, 3) as $attempt) {
        $this->withHeaders($jsonHeaders)
            ->post(route('password.email'), ['email' => 'noone@example.com']);
    }

    // 4th request must be throttled.
    $response = $this->withHeaders($jsonHeaders)
        ->post(route('password.email'), ['email' => 'noone@example.com']);

    $response->assertStatus(429);
});
