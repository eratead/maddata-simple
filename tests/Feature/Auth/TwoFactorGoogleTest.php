<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Build a mock Socialite user for 2FA flows.
 * Uses a distinct function name to avoid collision with GoogleSsoLoginTest.
 */
function make2FaSocialiteUser(string $sub, string $email): SocialiteUser
{
    $mock = Mockery::mock(SocialiteUser::class);
    $mock->shouldReceive('getId')->andReturn($sub);
    $mock->shouldReceive('getEmail')->andReturn($email);
    $mock->shouldReceive('getName')->andReturn('Test User');

    return $mock;
}

/**
 * Stub Socialite so ->user() returns $socialiteUser without hitting Google.
 */
function stub2FaSocialiteUser(SocialiteUser $socialiteUser): void
{
    $driver = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $driver->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
}

/**
 * Stub Socialite redirect flow for start-setup / start-verify POSTs.
 */
function stubSocialiteRedirect(): void
{
    $driver = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $driver->shouldReceive('with')->once()->andReturnSelf();
    $driver->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com/oauth'));

    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
}

// ── 2fa.setup view: both options shown ────────────────────────────────────

test('2fa setup page shows both authenticator and Google options when SSO enabled', function () {
    config(['auth.google_sso_enabled' => true]);

    $user = User::factory()->create([
        'google2fa_secret' => null,
        'google_sub' => null,
    ]);

    $this->actingAs($user)
        ->get(route('2fa.setup'))
        ->assertOk()
        ->assertSee('Use Google account')
        ->assertSee(route('2fa.google.start-setup'));
});

test('2fa setup page does not show Google option when SSO disabled', function () {
    config(['auth.google_sso_enabled' => false]);

    $user = User::factory()->create([
        'google2fa_secret' => null,
        'google_sub' => null,
    ]);

    $this->actingAs($user)
        ->get(route('2fa.setup'))
        ->assertOk()
        ->assertDontSee('Use Google account');
});

// ── startGoogleSetup: redirect to Google ──────────────────────────────────

test('POST 2fa.google.start-setup redirects to Google with signed 2fa_setup state', function () {
    $user = User::factory()->create([
        'google2fa_secret' => null,
        'google_sub' => null,
    ]);

    stubSocialiteRedirect();

    $response = $this->actingAs($user)
        ->post(route('2fa.google.start-setup'));

    $response->assertRedirect();
});

test('POST 2fa.google.start-setup requires authentication', function () {
    $this->post(route('2fa.google.start-setup'))
        ->assertRedirect(route('login'));
});

// ── 2fa_setup callback: links Google and sets 2fa_verified ────────────────

test('2fa_setup callback links Google account and sets 2fa_verified', function () {
    config(['auth.google_sso_enabled' => true]);

    $user = User::factory()->create([
        'google2fa_secret' => null,
        'google_sub' => null,
    ]);

    $hmac = hash_hmac('sha256', '2fa_setup:'.$user->id, config('app.key'));
    $state = '2fa_setup:'.$user->id.':'.$hmac;

    $socialiteUser = make2FaSocialiteUser('new-sub-setup', 'setup@gmail.com');
    stub2FaSocialiteUser($socialiteUser);

    $response = $this->actingAs($user)
        ->get('/auth/google/callback?state='.urlencode($state));

    $response->assertRedirect();
    $this->assertTrue(session('2fa_verified'));

    $fresh = $user->fresh();
    expect($fresh->google_sub)->toBe('new-sub-setup');
    expect($fresh->google_email)->toBe('setup@gmail.com');
});

test('2fa_setup callback is blocked by HMAC mismatch', function () {
    $user = User::factory()->create([
        'google2fa_secret' => null,
        'google_sub' => null,
    ]);

    $state = '2fa_setup:'.$user->id.':bad-hmac';

    $response = $this->actingAs($user)
        ->get('/auth/google/callback?state='.urlencode($state));

    $response->assertRedirect(route('2fa.setup'));
    $response->assertSessionHas('error');
    $this->assertNull($user->fresh()->google_sub);
});

test('2fa_setup callback blocks when Google sub is already linked to another user', function () {
    $existingUser = User::factory()->create([
        'google_sub' => 'taken-sub',
    ]);

    $user = User::factory()->create([
        'google_sub' => null,
    ]);

    $hmac = hash_hmac('sha256', '2fa_setup:'.$user->id, config('app.key'));
    $state = '2fa_setup:'.$user->id.':'.$hmac;

    $socialiteUser = make2FaSocialiteUser('taken-sub', 'taken@gmail.com');
    stub2FaSocialiteUser($socialiteUser);

    $response = $this->actingAs($user)
        ->get('/auth/google/callback?state='.urlencode($state));

    $response->assertRedirect(route('2fa.setup'));
    $response->assertSessionHas('error');
    $this->assertNull($user->fresh()->google_sub);
});

// ── 2fa.challenge view: shows correct options ─────────────────────────────

test('2fa challenge shows TOTP input and Google button when both enrolled', function () {
    config(['auth.google_sso_enabled' => true]);

    $user = User::factory()->create([
        'google2fa_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'google_sub' => 'linked-sub',
        'google_email' => 'linked@gmail.com',
    ]);

    $this->actingAs($user)
        ->get(route('2fa.challenge'))
        ->assertOk()
        ->assertSee('name="code"', false)
        ->assertSee('Verify with Google');
});

test('2fa challenge shows only Google button when no TOTP enrolled', function () {
    config(['auth.google_sso_enabled' => true]);

    $user = User::factory()->create([
        'google2fa_secret' => null,
        'google_sub' => 'linked-only-sub',
        'google_email' => 'only@gmail.com',
    ]);

    $this->actingAs($user)
        ->get(route('2fa.challenge'))
        ->assertOk()
        ->assertSee('Verify with Google')
        ->assertDontSee('name="code"', false);
});

// ── startGoogleVerify: redirect to Google ────────────────────────────────

test('POST 2fa.google.start-verify redirects to Google with signed 2fa_verify state', function () {
    $user = User::factory()->create([
        'google_sub' => 'verify-sub',
    ]);

    stubSocialiteRedirect();

    $response = $this->actingAs($user)
        ->post(route('2fa.google.start-verify'));

    $response->assertRedirect();
});

// ── 2fa_verify callback ────────────────────────────────────────────────────

test('2fa_verify callback sets 2fa_verified when sub matches', function () {
    $sub = 'correct-sub';
    $user = User::factory()->create([
        'google_sub' => $sub,
        'google_email' => 'correct@gmail.com',
    ]);

    $hmac = hash_hmac('sha256', '2fa_verify:'.$user->id, config('app.key'));
    $state = '2fa_verify:'.$user->id.':'.$hmac;

    $socialiteUser = make2FaSocialiteUser($sub, 'correct@gmail.com');
    stub2FaSocialiteUser($socialiteUser);

    $response = $this->actingAs($user)
        ->get('/auth/google/callback?state='.urlencode($state));

    $response->assertRedirect();
    $this->assertTrue(session('2fa_verified'));
});

test('2fa_verify callback is rejected when Google sub does not match stored sub', function () {
    $user = User::factory()->create([
        'google_sub' => 'stored-sub',
        'google_email' => 'stored@gmail.com',
    ]);

    $hmac = hash_hmac('sha256', '2fa_verify:'.$user->id, config('app.key'));
    $state = '2fa_verify:'.$user->id.':'.$hmac;

    // Google returns a different sub (user signed into wrong Google account)
    $socialiteUser = make2FaSocialiteUser('different-sub', 'different@gmail.com');
    stub2FaSocialiteUser($socialiteUser);

    $response = $this->actingAs($user)
        ->get('/auth/google/callback?state='.urlencode($state));

    $response->assertRedirect(route('2fa.challenge'));
    $response->assertSessionHas('error');
    $this->assertFalse((bool) session('2fa_verified'));
});

test('2fa_verify callback is blocked by HMAC mismatch', function () {
    $user = User::factory()->create([
        'google_sub' => 'some-sub',
    ]);

    $state = '2fa_verify:'.$user->id.':tampered-hmac';

    $response = $this->actingAs($user)
        ->get('/auth/google/callback?state='.urlencode($state));

    $response->assertRedirect(route('2fa.challenge'));
    $response->assertSessionHas('error');
    $this->assertFalse((bool) session('2fa_verified'));
});

test('2fa_verify callback is blocked when session user does not match state userId', function () {
    $userA = User::factory()->create(['google_sub' => 'sub-a']);
    $userB = User::factory()->create(['google_sub' => 'sub-b']);

    // HMAC is signed for userA, but we are authenticated as userB
    $hmac = hash_hmac('sha256', '2fa_verify:'.$userA->id, config('app.key'));
    $state = '2fa_verify:'.$userA->id.':'.$hmac;

    $response = $this->actingAs($userB)
        ->get('/auth/google/callback?state='.urlencode($state));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error');
    $this->assertFalse((bool) session('2fa_verified'));
});
