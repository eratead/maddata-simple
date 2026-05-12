<?php

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

// ── Flash banners ──────────────────────────────────────────────────────────

test('flash error banner renders on 2fa challenge when session error is set', function () {
    config(['auth.google_sso_enabled' => true]);

    $user = User::factory()->create([
        'google2fa_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'google_sub' => null,
    ]);

    $this->actingAs($user)
        ->withSession(['error' => 'The Google account does not match.'])
        ->get(route('2fa.challenge'))
        ->assertOk()
        ->assertSee('The Google account does not match.')
        ->assertSee('bg-red-50', false);
});

test('flash success banner renders on 2fa challenge when session success is set', function () {
    config(['auth.google_sso_enabled' => true]);

    $user = User::factory()->create([
        'google2fa_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'google_sub' => null,
    ]);

    $this->actingAs($user)
        ->withSession(['success' => 'Google account linked successfully.'])
        ->get(route('2fa.challenge'))
        ->assertOk()
        ->assertSee('Google account linked successfully.')
        ->assertSee('bg-green-50', false);
});

// ── startGoogleVerify: login_hint forwarding ───────────────────────────────

test('startGoogleVerify forwards login_hint when user has google_email', function () {
    config(['auth.google_sso_enabled' => true]);

    $user = User::factory()->create([
        'google_sub' => 'some-sub',
        'google_email' => 'foo@example.com',
    ]);

    $driver = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $driver->shouldReceive('with')
        ->once()
        ->with(['login_hint' => 'foo@example.com'])
        ->andReturnSelf();
    $driver->shouldReceive('redirect')
        ->once()
        ->andReturn(new RedirectResponse('https://accounts.google.com/oauth'));

    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn($driver);

    $response = $this->actingAs($user)
        ->post(route('2fa.google.start-verify'));

    $response->assertRedirect();
    expect(session('google_oauth_intent'))->toBe('2fa_verify');
    expect(session('google_oauth_user'))->toBe($user->id);
});

test('startGoogleVerify skips login_hint when google_email is null', function () {
    config(['auth.google_sso_enabled' => true]);

    $user = User::factory()->create([
        'google_sub' => 'some-sub',
        'google_email' => null,
    ]);

    $driver = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $driver->shouldNotReceive('with');
    $driver->shouldReceive('redirect')
        ->once()
        ->andReturn(new RedirectResponse('https://accounts.google.com/oauth'));

    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn($driver);

    $response = $this->actingAs($user)
        ->post(route('2fa.google.start-verify'));

    $response->assertRedirect();
});

// ── Button label: masked email ─────────────────────────────────────────────

test('masked google email appears in verify button label and unmasked email does not', function () {
    config(['auth.google_sso_enabled' => true]);

    $user = User::factory()->create([
        'google2fa_secret' => null,
        'google_sub' => 'sub-joe',
        'google_email' => 'joe.smith@gmail.com',
    ]);

    // SSO-only user with block_google_auto_verify prevents auto-redirect
    $this->actingAs($user)
        ->withSession(['block_google_auto_verify' => true])
        ->get(route('2fa.challenge'))
        ->assertOk()
        ->assertSee('j***@gmail.com')
        ->assertDontSee('joe.smith@gmail.com');
});
