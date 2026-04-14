<?php

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Create a user with a valid Sanctum token that has the reports:read ability.
 */
function apiTestUser(string $ability = 'reports:read'): array
{
    $role = Role::create(['name' => 'API Tester', 'permissions' => ['can_view_campaigns' => true]]);
    $user = User::factory()->create(['is_admin' => false, 'role_id' => $role->id]);
    $client = Client::factory()->create();
    $user->clients()->attach($client);

    $token = $user->createToken('test-token', [$ability]);
    $accessToken = $token->accessToken;
    $accessToken->expires_at = now()->addDays(30);
    $accessToken->save();

    return [$user, $client, $token->plainTextToken];
}

/**
 * Shared assertions for every error response on api/* paths.
 *
 * @param  \Illuminate\Testing\TestResponse  $response
 */
function assertJsonErrorResponse($response, int $status): void
{
    $response
        ->assertStatus($status)
        ->assertHeader('Content-Type', 'application/json')
        ->assertDontSee('<!DOCTYPE html', false)
        ->assertDontSee('Welcome back', false);
}

// ---------------------------------------------------------------------------
// Case (a) — No Authorization header → 401 JSON
// ---------------------------------------------------------------------------

it('returns JSON 401 when no Authorization header is sent', function () {
    $response = $this->getJson('/api/reports/campaigns');

    assertJsonErrorResponse($response, 401);
    $response->assertJson(['message' => 'Unauthenticated.']);
});

// ---------------------------------------------------------------------------
// Case (b) — Malformed Authorization header → 401 JSON
// ---------------------------------------------------------------------------

it('returns JSON 401 for malformed Bearer token', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer garbage'])->getJson('/api/reports/campaigns');

    assertJsonErrorResponse($response, 401);
    $response->assertJson(['message' => 'Unauthenticated.']);
});

// ---------------------------------------------------------------------------
// Case (c) — Valid header format, non-existent token hash → 401 JSON
// ---------------------------------------------------------------------------

it('returns JSON 401 for a well-formed but non-existent token', function () {
    // Sanctum plain-text token format is "<id>|<hash>" — use a hash that does not exist in DB
    $response = $this->withHeaders(['Authorization' => 'Bearer 9999|nonexistentHashThatIsNotInDatabase'])->getJson('/api/reports/campaigns');

    assertJsonErrorResponse($response, 401);
    $response->assertJson(['message' => 'Unauthenticated.']);
});

// ---------------------------------------------------------------------------
// Case (d) — Valid token with expires_at in the past → 401 JSON
// Sanctum's guard checks expires_at itself and rejects the token before
// CheckTokenExpiry middleware runs, so the response is "Unauthenticated."
// (CheckTokenExpiry only fires when the user is already authenticated via
// a different auth path; with Sanctum token auth, expiry is enforced by
// the guard itself.)
// ---------------------------------------------------------------------------

it('returns JSON 401 for an expired token', function () {
    [, , $plainToken] = apiTestUser();

    // Expire the token in the DB
    $tokenModel = PersonalAccessToken::findToken(explode('|', $plainToken)[1]);
    $tokenModel->expires_at = now()->subDay();
    $tokenModel->save();

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$plainToken])->getJson('/api/reports/campaigns');

    assertJsonErrorResponse($response, 401);
    $response->assertJson(['message' => 'Unauthenticated.']);
});

// ---------------------------------------------------------------------------
// Case (e) — Valid token missing reports:read ability → 403 JSON
// ---------------------------------------------------------------------------

it('returns JSON 403 when token lacks reports:read ability', function () {
    [, , $plainToken] = apiTestUser('other:ability');

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$plainToken])->getJson('/api/reports/campaigns');

    assertJsonErrorResponse($response, 403);
    $response->assertJson(['message' => 'This action is unauthorized.']);
});

// ---------------------------------------------------------------------------
// Case (f) — Valid token, valid ability, invalid start param → 422 JSON with errors
// ---------------------------------------------------------------------------

it('returns JSON 422 with errors key for invalid start date parameter', function () {
    [, , $plainToken] = apiTestUser();

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$plainToken])
        ->getJson('/api/reports/campaigns?start=not-a-date');

    assertJsonErrorResponse($response, 422);
    $response->assertJson(['message' => 'The given data was invalid.']);
    $response->assertJsonStructure(['message', 'errors']);
});

// ---------------------------------------------------------------------------
// Case (g) — Valid token, valid ability, valid request → 200 JSON (regression guard)
// ---------------------------------------------------------------------------

it('returns 200 JSON for a valid authenticated request', function () {
    [$user, $client, $plainToken] = apiTestUser();
    Campaign::factory()->count(2)->create(['client_id' => $client->id, 'status' => 'active']);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$plainToken])
        ->getJson('/api/reports/campaigns');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/json');
    $response->assertJsonStructure(['data', 'current_page', 'total']);
    $response->assertDontSee('<!DOCTYPE html', false);
});

// ---------------------------------------------------------------------------
// Case (h) — Accept header variations: all must return JSON 401, not HTML
// ---------------------------------------------------------------------------

it('returns JSON 401 with default Accept: */* header (Postman default)', function () {
    $response = $this->withHeaders(['Accept' => '*/*'])->get('/api/reports/campaigns');

    assertJsonErrorResponse($response, 401);
    $response->assertJson(['message' => 'Unauthenticated.']);
});

it('returns JSON 401 with Accept: text/html header (the critical regression)', function () {
    // This is the root-cause scenario: client sends Accept: text/html (or no JSON Accept),
    // and without our fix Laravel would redirect to /login returning 200 HTML.
    $response = $this->withHeaders(['Accept' => 'text/html'])->get('/api/reports/campaigns');

    assertJsonErrorResponse($response, 401);
    $response->assertJson(['message' => 'Unauthenticated.']);
});

it('returns JSON 401 with no Accept header', function () {
    // PHP test client sends no Accept header at all.
    $response = $this->get('/api/reports/campaigns');

    assertJsonErrorResponse($response, 401);
    $response->assertJson(['message' => 'Unauthenticated.']);
});
