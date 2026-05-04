<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Helper: build a mock Socialite user.
 */
function makeSocialiteUser(string $sub, string $email, string $name = 'Test User'): SocialiteUser
{
    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn($sub);
    $socialiteUser->shouldReceive('getEmail')->andReturn($email);
    $socialiteUser->shouldReceive('getName')->andReturn($name);

    return $socialiteUser;
}

/**
 * Stub the Socialite driver so the test does not touch Google's servers.
 */
function stubSocialiteLogin(SocialiteUser $socialiteUser): void
{
    $driver = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $driver->shouldReceive('redirect')->andReturn(redirect('/auth/google/callback'));
    $driver->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn($driver);
}

// ── Login: linked user ─────────────────────────────────────────────────────

test('linked user can log in via Google SSO without TOTP challenge', function () {
    $sub = 'google-sub-123';
    $user = User::factory()->create([
        'google_sub' => $sub,
        'google_email' => 'test@gmail.com',
        'is_active' => true,
    ]);

    $socialiteUser = makeSocialiteUser($sub, 'test@gmail.com');
    stubSocialiteLogin($socialiteUser);

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);

    // Session must be tagged as SSO so RequireTwoFactor fast-paths it
    $this->assertEquals('sso', session('login_method'));
    $this->assertTrue(session('2fa_verified'));
});

test('linked user with TOTP enrolled logs in via SSO without TOTP challenge', function () {
    $sub = 'google-sub-456';
    // User has BOTH methods configured — SSO still skips TOTP
    $user = User::factory()->create([
        'google_sub' => $sub,
        'google_email' => 'both@gmail.com',
        'google2fa_secret' => encrypt('JBSWY3DPEHPK3PXP'), // non-empty TOTP secret
        'is_active' => true,
    ]);

    $socialiteUser = makeSocialiteUser($sub, 'both@gmail.com');
    stubSocialiteLogin($socialiteUser);

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);
    $this->assertEquals('sso', session('login_method'));
    $this->assertTrue(session('2fa_verified'));
});

// ── Login: email match but not linked ──────────────────────────────────────

test('SSO callback blocks when Google email matches an unlinked account', function () {
    $user = User::factory()->create([
        'email' => 'existing@company.com',
        'google_sub' => null,
        'is_active' => true,
    ]);

    $socialiteUser = makeSocialiteUser('unknown-sub', 'existing@company.com');
    stubSocialiteLogin($socialiteUser);

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect(route('login'));
    $this->assertGuest();

    // Flash message must contain "connect in settings" guidance
    $response->assertSessionHas('error');
    expect(session('error'))->toContain('Sign in with email and password');
});

// ── Login: no matching account ─────────────────────────────────────────────

test('SSO callback blocks when no account found for Google identity', function () {
    $socialiteUser = makeSocialiteUser('brand-new-sub', 'nobody@gmail.com');
    stubSocialiteLogin($socialiteUser);

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect(route('login'));
    $this->assertGuest();
    $response->assertSessionHas('error');
    expect(session('error'))->toContain('No MadData account found');
});

// ── Login: inactive user ───────────────────────────────────────────────────

test('SSO callback blocks an inactive user', function () {
    $sub = 'inactive-sub';
    User::factory()->create([
        'google_sub' => $sub,
        'google_email' => 'inactive@gmail.com',
        'is_active' => false,
    ]);

    $socialiteUser = makeSocialiteUser($sub, 'inactive@gmail.com');
    stubSocialiteLogin($socialiteUser);

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect(route('login'));
    $this->assertGuest();
    $response->assertSessionHas('error');
});

// ── Login: Socialite throws (e.g. user denied OAuth) ──────────────────────

test('SSO callback handles Socialite exception gracefully', function () {
    $driver = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $driver->shouldReceive('user')->andThrow(new \Exception('OAuth error'));

    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn($driver);

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect(route('login'));
    $this->assertGuest();
    $response->assertSessionHas('error');
});

// ── Post-login: login_method=password set on password login ───────────────

test('password login sets login_method session tag to password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertEquals('password', session('login_method'));
});
