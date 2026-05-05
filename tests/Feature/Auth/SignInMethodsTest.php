<?php

use App\Models\ActivityLog;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

// ── Index page ─────────────────────────────────────────────────────────────

test('sign-in methods page is accessible to authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.sign-in-methods.index'))
        ->assertOk()
        ->assertViewIs('settings.sign-in-methods');
});

test('sign-in methods page is inaccessible to guests', function () {
    $this->get(route('settings.sign-in-methods.index'))
        ->assertRedirect(route('login'));
});

// ── Connect Google ─────────────────────────────────────────────────────────

test('start connect google requires correct password', function () {
    config(['auth.google_sso_enabled' => true]);

    $user = User::factory()->create();

    // Wrong password
    $this->actingAs($user)
        ->post(route('settings.sign-in-methods.start-connect-google'), [
            'password' => 'wrong-password',
        ])
        ->assertSessionHasErrors('password');

    $this->assertNull($user->fresh()->google_sub);
});

test('start connect google redirects to Google and stores session intent', function () {
    config(['auth.google_sso_enabled' => true]);

    $user = User::factory()->create();

    $driver = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $driver->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com/oauth'));

    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    $response = $this->actingAs($user)
        ->post(route('settings.sign-in-methods.start-connect-google'), [
            'password' => 'password',
        ]);

    $response->assertRedirect();
    expect(session('google_oauth_intent'))->toBe('link');
    expect(session('google_oauth_user'))->toBe($user->id);
});

// ── Callback completes the link ────────────────────────────────────────────

test('google callback links the account when session intent is valid', function () {
    $user = User::factory()->create();

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('new-sub-789');
    $socialiteUser->shouldReceive('getEmail')->andReturn('linked@gmail.com');

    $driver = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $driver->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    $this->actingAs($user)
        ->withSession([
            'google_oauth_intent' => 'link',
            'google_oauth_user'   => $user->id,
        ])
        ->get('/auth/google/callback')
        ->assertRedirect(route('settings.sign-in-methods.index'));

    $fresh = $user->fresh();
    expect($fresh->google_sub)->toBe('new-sub-789');
    expect($fresh->google_email)->toBe('linked@gmail.com');
    expect($fresh->google_linked_at)->not->toBeNull();
});

test('google link writes an activity log row', function () {
    $user = User::factory()->create();

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('audit-sub');
    $socialiteUser->shouldReceive('getEmail')->andReturn('audit@gmail.com');

    $driver = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $driver->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    $this->actingAs($user)
        ->withSession([
            'google_oauth_intent' => 'link',
            'google_oauth_user'   => $user->id,
        ])
        ->get('/auth/google/callback');

    $log = ActivityLog::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('action', 'sso.linked')
        ->first();

    expect($log)->not->toBeNull();
});

// ── Disconnect Google ──────────────────────────────────────────────────────

test('disconnect google requires correct password', function () {
    $user = User::factory()->create([
        'google_sub' => 'some-sub',
        'google_email' => 'g@gmail.com',
        'google2fa_secret' => encrypt('secret'), // TOTP enrolled so invariant is satisfied
    ]);

    $this->actingAs($user)
        ->post(route('settings.sign-in-methods.disconnect-google'), [
            'password' => 'wrong-password',
        ])
        ->assertSessionHasErrors('password');

    expect($user->fresh()->google_sub)->toBe('some-sub');
});

test('disconnect google clears the google_sub when password is correct and TOTP is enrolled', function () {
    $user = User::factory()->create([
        'google_sub' => 'sub-to-remove',
        'google_email' => 'remove@gmail.com',
        'google2fa_secret' => encrypt('secret'),
    ]);

    $this->actingAs($user)
        ->post(route('settings.sign-in-methods.disconnect-google'), [
            'password' => 'password',
        ])
        ->assertRedirect(route('settings.sign-in-methods.index'));

    $fresh = $user->fresh();
    expect($fresh->google_sub)->toBeNull();
    expect($fresh->google_email)->toBeNull();
});

test('disconnect google returns 422 when TOTP is not enrolled (lockout invariant)', function () {
    $user = User::factory()->create([
        'google_sub' => 'only-method',
        'google_email' => 'only@gmail.com',
        'google2fa_secret' => null, // No TOTP — invariant would be violated
    ]);

    $this->actingAs($user)
        ->post(route('settings.sign-in-methods.disconnect-google'), [
            'password' => 'password',
        ])
        ->assertStatus(422)
        ->assertSessionHasErrors('google');

    expect($user->fresh()->google_sub)->toBe('only-method');
});

test('disconnect google writes an activity log row', function () {
    $user = User::factory()->create([
        'google_sub' => 'log-sub',
        'google_email' => 'log@gmail.com',
        'google2fa_secret' => encrypt('secret'),
    ]);

    $this->actingAs($user)
        ->post(route('settings.sign-in-methods.disconnect-google'), [
            'password' => 'password',
        ]);

    $log = ActivityLog::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('action', 'sso.unlinked')
        ->first();

    expect($log)->not->toBeNull();
});

// ── Disable TOTP ───────────────────────────────────────────────────────────

test('disable TOTP requires correct password', function () {
    $user = User::factory()->create([
        'google_sub' => 'google-sub',
        'google2fa_secret' => encrypt('totp-secret'),
    ]);

    $this->actingAs($user)
        ->post(route('settings.sign-in-methods.disable-totp'), [
            'password' => 'wrong-password',
        ])
        ->assertSessionHasErrors('password');

    expect($user->fresh()->google2fa_secret)->not->toBeNull();
});

test('disable TOTP clears the secret when Google is linked', function () {
    $user = User::factory()->create([
        'google_sub' => 'google-sub',
        'google2fa_secret' => encrypt('totp-secret'),
    ]);

    $this->actingAs($user)
        ->post(route('settings.sign-in-methods.disable-totp'), [
            'password' => 'password',
        ])
        ->assertRedirect(route('settings.sign-in-methods.index'));

    expect($user->fresh()->google2fa_secret)->toBeNull();
});

test('disable TOTP returns 422 when Google is not linked (lockout invariant)', function () {
    $user = User::factory()->create([
        'google_sub' => null, // No Google — invariant would be violated
        'google2fa_secret' => encrypt('totp-secret'),
    ]);

    $this->actingAs($user)
        ->post(route('settings.sign-in-methods.disable-totp'), [
            'password' => 'password',
        ])
        ->assertStatus(422)
        ->assertSessionHasErrors('totp');

    expect($user->fresh()->google2fa_secret)->not->toBeNull();
});

test('disable TOTP writes an activity log row', function () {
    $user = User::factory()->create([
        'google_sub' => 'google-sub',
        'google2fa_secret' => encrypt('totp-secret'),
    ]);

    $this->actingAs($user)
        ->post(route('settings.sign-in-methods.disable-totp'), [
            'password' => 'password',
        ]);

    $log = ActivityLog::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('action', 'totp.disabled')
        ->first();

    expect($log)->not->toBeNull();
});
