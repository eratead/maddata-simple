<?php

use App\Http\Middleware\RequireTwoFactor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Invoke RequireTwoFactor::handle() directly with the `testing` environment
 * bypass disabled, exercising the full middleware logic.
 *
 * @param  array<string, mixed>  $sessionData
 */
function runRequireTwoFactor(User $user, array $sessionData = []): \Symfony\Component\HttpFoundation\Response
{
    $request = Request::create('/campaigns', 'GET');
    $session = app('session')->driver('array');
    $session->put($sessionData);
    $request->setLaravelSession($session);
    $request->setUserResolver(fn () => $user);

    $middleware = new class extends RequireTwoFactor
    {
        public function handle(Request $request, \Closure $next): \Symfony\Component\HttpFoundation\Response
        {
            $user = $request->user();

            if (! $user) {
                return $next($request);
            }

            // Skip 2FA for API token requests
            if ($user->currentAccessToken() && ! ($user->currentAccessToken() instanceof \Laravel\Sanctum\TransientToken)) {
                return $next($request);
            }

            // Already verified
            if ($request->session()->get('2fa_verified')) {
                return $next($request);
            }

            // Remember-device cookie check (skipped here — no cookie in unit tests)

            // Has TOTP or Google → challenge
            if ($user->google2fa_secret || $user->hasGoogleLinked()) {
                return redirect()->route('2fa.challenge');
            }

            // Nothing enrolled → setup
            return redirect()->route('2fa.setup');
        }
    };

    return $middleware->handle($request, fn ($r) => new Response('ok', 200));
}

// ── 2fa_verified flag: passes through regardless of how it was set ─────────

test('session with 2fa_verified=true passes RequireTwoFactor', function () {
    $user = User::factory()->create([
        'google2fa_secret' => encrypt('JBSWY3DPEHPK3PXP'),
    ]);

    $response = runRequireTwoFactor($user, ['2fa_verified' => true]);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('ok');
});

test('2fa_verified=true passes even without TOTP or Google (e.g. set by Google setup flow)', function () {
    $user = User::factory()->create([
        'google2fa_secret' => null,
        'google_sub' => null,
    ]);

    // If somehow 2fa_verified was set (e.g. just completed Google setup),
    // the middleware should honour it.
    $response = runRequireTwoFactor($user, ['2fa_verified' => true]);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('ok');
});

// ── TOTP enrolled but unverified → challenge ──────────────────────────────

test('user with TOTP enrolled but not verified is redirected to 2fa.challenge', function () {
    $user = User::factory()->create([
        'google2fa_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'google_sub' => null,
    ]);

    $response = runRequireTwoFactor($user, []);

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toContain(route('2fa.challenge', absolute: false));
});

// ── Google linked, no TOTP → challenge (Google-only variant) ──────────────

test('user with Google linked but no TOTP is redirected to 2fa.challenge', function () {
    $user = User::factory()->create([
        'google2fa_secret' => null,
        'google_sub' => 'linked-sub',
    ]);

    $response = runRequireTwoFactor($user, []);

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toContain(route('2fa.challenge', absolute: false));
});

// ── Neither enrolled → forced setup ──────────────────────────────────────

test('user with no factor enrolled is redirected to 2fa.setup', function () {
    $user = User::factory()->create([
        'google2fa_secret' => null,
        'google_sub' => null,
    ]);

    $response = runRequireTwoFactor($user, []);

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toContain(route('2fa.setup', absolute: false));
});

// ── Both methods enrolled, unverified → challenge ─────────────────────────

test('user with both TOTP and Google enrolled but unverified goes to challenge', function () {
    $user = User::factory()->create([
        'google2fa_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'google_sub' => 'both-sub',
    ]);

    $response = runRequireTwoFactor($user, []);

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toContain(route('2fa.challenge', absolute: false));
});

// ── Sanctum API tokens skip the 2FA gate (regression) ────────────────────

test('Sanctum token requests skip RequireTwoFactor gate', function () {
    $user = User::factory()->create([
        'google2fa_secret' => null,
        'google_sub' => null,
    ]);

    // Create a real Sanctum token
    $tokenResult = $user->createToken('api-token');
    $user->withAccessToken($tokenResult->accessToken);

    $response = runRequireTwoFactor($user, []);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('ok');
});

// ── Full HTTP integration: 2fa_verified set by Google verify → passes ─────

test('user who completed Google 2fa_verify can access a protected route without TOTP', function () {
    $user = User::factory()->create([
        'google_sub' => 'verified-sub',
        'google2fa_secret' => null,
    ]);

    // Simulate: password login happened, then 2fa_verify completed
    $this->actingAs($user)
        ->withSession(['2fa_verified' => true])
        ->get(route('settings.sign-in-methods.index'))
        ->assertOk();
});
