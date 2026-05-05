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

test('POST 2fa.google.start-setup redirects to Google and stores session intent', function () {
    $user = User::factory()->create([
        'google2fa_secret' => null,
        'google_sub' => null,
    ]);

    stubSocialiteRedirect();

    $response = $this->actingAs($user)
        ->post(route('2fa.google.start-setup'));

    $response->assertRedirect();
    expect(session('google_oauth_intent'))->toBe('2fa_setup');
    expect(session('google_oauth_user'))->toBe($user->id);
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

    $socialiteUser = make2FaSocialiteUser('new-sub-setup', 'setup@gmail.com');
    stub2FaSocialiteUser($socialiteUser);

    $response = $this->actingAs($user)
        ->withSession([
            'google_oauth_intent' => '2fa_setup',
            'google_oauth_user'   => $user->id,
        ])
        ->get('/auth/google/callback');

    $response->assertRedirect();
    $this->assertTrue(session('2fa_verified'));

    $fresh = $user->fresh();
    expect($fresh->google_sub)->toBe('new-sub-setup');
    expect($fresh->google_email)->toBe('setup@gmail.com');
});

test('2fa_setup callback is blocked when session intent is missing', function () {
    $user = User::factory()->create([
        'google2fa_secret' => null,
        'google_sub' => null,
    ]);

    // No session intent set — simulates a forged or replayed request
    $response = $this->actingAs($user)
        ->get('/auth/google/callback');

    $response->assertRedirect(route('login'));
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

    $socialiteUser = make2FaSocialiteUser('taken-sub', 'taken@gmail.com');
    stub2FaSocialiteUser($socialiteUser);

    $response = $this->actingAs($user)
        ->withSession([
            'google_oauth_intent' => '2fa_setup',
            'google_oauth_user'   => $user->id,
        ])
        ->get('/auth/google/callback');

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

test('POST 2fa.google.start-verify redirects to Google and stores session intent', function () {
    $user = User::factory()->create([
        'google_sub' => 'verify-sub',
    ]);

    stubSocialiteRedirect();

    $response = $this->actingAs($user)
        ->post(route('2fa.google.start-verify'));

    $response->assertRedirect();
    expect(session('google_oauth_intent'))->toBe('2fa_verify');
    expect(session('google_oauth_user'))->toBe($user->id);
});

// ── 2fa_verify callback ────────────────────────────────────────────────────

test('2fa_verify callback sets 2fa_verified when sub matches', function () {
    $sub = 'correct-sub';
    $user = User::factory()->create([
        'google_sub' => $sub,
        'google_email' => 'correct@gmail.com',
    ]);

    $socialiteUser = make2FaSocialiteUser($sub, 'correct@gmail.com');
    stub2FaSocialiteUser($socialiteUser);

    $response = $this->actingAs($user)
        ->withSession([
            'google_oauth_intent' => '2fa_verify',
            'google_oauth_user'   => $user->id,
        ])
        ->get('/auth/google/callback');

    $response->assertRedirect();
    $this->assertTrue(session('2fa_verified'));
});

test('2fa_verify callback is rejected when Google sub does not match stored sub', function () {
    $user = User::factory()->create([
        'google_sub' => 'stored-sub',
        'google_email' => 'stored@gmail.com',
    ]);

    // Google returns a different sub (user signed into wrong Google account)
    $socialiteUser = make2FaSocialiteUser('different-sub', 'different@gmail.com');
    stub2FaSocialiteUser($socialiteUser);

    $response = $this->actingAs($user)
        ->withSession([
            'google_oauth_intent' => '2fa_verify',
            'google_oauth_user'   => $user->id,
        ])
        ->get('/auth/google/callback');

    $response->assertRedirect(route('2fa.challenge'));
    $response->assertSessionHas('error');
    $this->assertFalse((bool) session('2fa_verified'));
});

test('2fa_verify callback is blocked when session intent is missing', function () {
    $user = User::factory()->create([
        'google_sub' => 'some-sub',
    ]);

    // No session intent — simulates a forged or replayed request
    $response = $this->actingAs($user)
        ->get('/auth/google/callback');

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error');
    $this->assertFalse((bool) session('2fa_verified'));
});

test('2fa_verify callback is blocked when session user does not match authenticated user', function () {
    $userA = User::factory()->create(['google_sub' => 'sub-a']);
    $userB = User::factory()->create(['google_sub' => 'sub-b']);

    // Session says userA, but we are authenticated as userB
    $socialiteUser = make2FaSocialiteUser('sub-a', 'a@gmail.com');
    stub2FaSocialiteUser($socialiteUser);

    $response = $this->actingAs($userB)
        ->withSession([
            'google_oauth_intent' => '2fa_verify',
            'google_oauth_user'   => $userA->id,
        ])
        ->get('/auth/google/callback');

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error');
    $this->assertFalse((bool) session('2fa_verified'));
});
