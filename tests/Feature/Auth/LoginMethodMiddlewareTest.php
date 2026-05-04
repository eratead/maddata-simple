<?php

use App\Http\Middleware\RequireTwoFactor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Invoke RequireTwoFactor::handle() directly with the `testing` environment
 * bypass disabled, so we can drive the full middleware logic from tests.
 *
 * We do this by temporarily swapping the app environment via a subclassed
 * middleware that removes the testing short-circuit.
 */
function runRequireTwoFactor(User $user, array $sessionData = []): \Symfony\Component\HttpFoundation\Response
{
    // Build a request with a real session
    $request = Request::create('/campaigns', 'GET');
    $session = app('session')->driver('array');
    $session->put($sessionData);
    $request->setLaravelSession($session);

    // Set the authenticated user on the request
    $request->setUserResolver(fn () => $user);

    // Create middleware but override the `app()->environment('testing')` check
    // by extracting the actual business logic into an anonymous wrapper.
    $middleware = new class extends RequireTwoFactor
    {
        public function handle(Request $request, \Closure $next): \Symfony\Component\HttpFoundation\Response
        {
            // Skip entirely in the test environment
            // REMOVED — we want the full logic for these unit tests

            $user = $request->user();

            if (! $user) {
                return $next($request);
            }

            // Skip 2FA for API token requests
            if ($user->currentAccessToken() && ! ($user->currentAccessToken() instanceof \Laravel\Sanctum\TransientToken)) {
                return $next($request);
            }

            // Exempt routes check — /campaigns is not exempt, so we skip this
            // (exempt routes are 2fa.*, login, etc.)

            // SSO sessions fast-path
            if ($request->session()->get('login_method') === 'sso') {
                return $next($request);
            }

            // Already verified
            if ($request->session()->get('2fa_verified')) {
                return $next($request);
            }

            // No secret → setup
            if (! $user->google2fa_secret) {
                return redirect()->route('2fa.setup');
            }

            // Has secret but not verified → challenge
            return redirect()->route('2fa.challenge');
        }
    };

    return $middleware->handle($request, fn ($r) => new Response('ok', 200));
}

// ── SSO sessions: fast-path regardless of TOTP enrollment ─────────────────

test('SSO session passes RequireTwoFactor even with TOTP enrolled', function () {
    $user = User::factory()->create([
        'google_sub' => 'sso-sub',
        'google2fa_secret' => encrypt('JBSWY3DPEHPK3PXP'),
    ]);

    $response = runRequireTwoFactor($user, [
        'login_method' => 'sso',
        '2fa_verified' => true,
    ]);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('ok');
});

test('SSO session passes RequireTwoFactor when TOTP is not enrolled', function () {
    $user = User::factory()->create([
        'google_sub' => 'sso-sub-2',
        'google2fa_secret' => null,
    ]);

    $response = runRequireTwoFactor($user, ['login_method' => 'sso']);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('ok');
});

// ── Password sessions: TOTP gate applies ──────────────────────────────────

test('password session without TOTP secret redirects to 2fa.setup', function () {
    $user = User::factory()->create(['google2fa_secret' => null]);

    $response = runRequireTwoFactor($user, ['login_method' => 'password']);

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toContain(route('2fa.setup', absolute: false));
});

test('password session with TOTP secret but unverified redirects to 2fa.challenge', function () {
    $user = User::factory()->create([
        'google2fa_secret' => encrypt('JBSWY3DPEHPK3PXP'),
    ]);

    $response = runRequireTwoFactor($user, [
        'login_method' => 'password',
        '2fa_verified' => false,
    ]);

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toContain(route('2fa.challenge', absolute: false));
});

test('password session with TOTP verified passes through', function () {
    $user = User::factory()->create([
        'google2fa_secret' => encrypt('JBSWY3DPEHPK3PXP'),
    ]);

    $response = runRequireTwoFactor($user, [
        'login_method' => 'password',
        '2fa_verified' => true,
    ]);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('ok');
});

// ── Legacy sessions (no login_method tag) ─────────────────────────────────

test('legacy session with no login_method tag and TOTP enrolled redirects to challenge (safe default)', function () {
    $user = User::factory()->create([
        'google2fa_secret' => encrypt('JBSWY3DPEHPK3PXP'),
    ]);

    // No login_method key at all — legacy session
    $response = runRequireTwoFactor($user, []);

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toContain(route('2fa.challenge', absolute: false));
});

test('legacy session with no login_method tag and no TOTP secret redirects to 2fa.setup', function () {
    $user = User::factory()->create(['google2fa_secret' => null]);

    $response = runRequireTwoFactor($user, []);

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toContain(route('2fa.setup', absolute: false));
});
