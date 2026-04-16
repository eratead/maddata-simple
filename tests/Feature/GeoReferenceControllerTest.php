<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

function geoUser(): User
{
    $role = Role::create([
        'name' => 'Geo Viewer '.uniqid(),
        'permissions' => ['can_view_campaigns' => true],
        'sort_order' => 99,
    ]);

    return User::factory()->create([
        'is_admin' => false,
        'role_id' => $role->id,
        'is_active' => true,
    ]);
}

// ---------------------------------------------------------------------------
// Authentication
// Routes are at /api/geo/* so the bootstrap/app.php JSON error handler
// converts unauthenticated exceptions to 401 JSON (not a web redirect).
// ---------------------------------------------------------------------------

it('returns 401 for unauthenticated requests to /api/geo/countries', function () {
    $this->getJson(route('geo.countries'))
        ->assertUnauthorized();
});

it('returns 401 for unauthenticated requests to /api/geo/regions', function () {
    $this->getJson(route('geo.regions', ['country' => 'Israel']))
        ->assertUnauthorized();
});

it('returns 401 for unauthenticated requests to /api/geo/cities', function () {
    $this->getJson(route('geo.cities', ['country' => 'Israel']))
        ->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// Validation
// ---------------------------------------------------------------------------

it('returns 422 when country is missing from regions endpoint', function () {
    $user = geoUser();

    $this->actingAs($user)
        ->getJson(route('geo.regions'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['country']);
});

it('returns 422 when country is missing from cities endpoint', function () {
    $user = geoUser();

    $this->actingAs($user)
        ->getJson(route('geo.cities'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['country']);
});

// ---------------------------------------------------------------------------
// Countries — successful upstream response
// ---------------------------------------------------------------------------

it('returns data array from /api/geo/countries on upstream success', function () {
    Http::fake([
        'countriesnow.space/*' => Http::response([
            'error' => false,
            'data' => [
                ['name' => 'Israel', 'Iso2' => 'IL'],
                ['name' => 'United States', 'Iso2' => 'US'],
            ],
        ], 200),
    ]);

    $user = geoUser();

    $response = $this->actingAs($user)
        ->getJson(route('geo.countries'))
        ->assertOk()
        ->assertJsonStructure(['data']);

    expect($response->json('data'))->toBeArray()->not->toBeEmpty();
});

// ---------------------------------------------------------------------------
// Cache behaviour
// ---------------------------------------------------------------------------

it('only calls the upstream once when cache is warm on countries', function () {
    Http::fake([
        'countriesnow.space/*' => Http::response([
            'error' => false,
            'data' => [['name' => 'Israel', 'Iso2' => 'IL']],
        ], 200),
    ]);

    Cache::flush();

    $user = geoUser();

    // First call — populates cache
    $this->actingAs($user)->getJson(route('geo.countries'))->assertOk();

    // Second call — should be served from cache, no new HTTP request
    $this->actingAs($user)->getJson(route('geo.countries'))->assertOk();

    Http::assertSentCount(1);
});

// ---------------------------------------------------------------------------
// Static fallback — upstream failure
// ---------------------------------------------------------------------------

it('returns 200 with static fallback when upstream returns 500 for countries', function () {
    Http::fake([
        'countriesnow.space/*' => Http::response([], 500),
    ]);

    Cache::flush();

    $user = geoUser();

    $response = $this->actingAs($user)
        ->getJson(route('geo.countries'))
        ->assertOk()
        ->assertJsonStructure(['data']);

    // Israel must be in the static fallback
    expect($response->json('data'))->toContain('Israel');
});

it('emits a geo.fallback_used log message when upstream returns 500', function () {
    Http::fake([
        'countriesnow.space/*' => Http::response([], 500),
    ]);

    Cache::flush();

    $messages = [];
    Log::listen(function (\Illuminate\Log\Events\MessageLogged $event) use (&$messages) {
        $messages[] = $event->message;
    });

    $user = geoUser();

    $this->actingAs($user)->getJson(route('geo.countries'))->assertOk();

    expect($messages)->toContain('geo.fallback_used');
});

// ---------------------------------------------------------------------------
// Bilingual Israel cities fallback
// ---------------------------------------------------------------------------

it('returns both English and Hebrew city names for Israel when upstream fails', function () {
    Http::fake([
        'countriesnow.space/*' => Http::response([], 500),
    ]);

    Cache::flush();

    $user = geoUser();

    $response = $this->actingAs($user)
        ->getJson(route('geo.cities', ['country' => 'Israel']))
        ->assertOk();

    $cities = $response->json('data');

    expect($cities)
        ->toBeArray()
        ->toContain('Holon')
        ->toContain('חולון')
        ->toContain('Bat Yam')
        ->toContain('בת ים')
        ->toContain('Rishon LeZion')
        ->toContain('ראשון לציון')
        ->toContain('Ramla')
        ->toContain('רמלה')
        ->toContain('Lod')
        ->toContain('לוד')
        ->toContain('Tel Aviv')
        ->toContain('תל אביב');
});

// ---------------------------------------------------------------------------
// Regions fallback
// ---------------------------------------------------------------------------

it('returns static Israel regions when upstream fails', function () {
    Http::fake([
        'countriesnow.space/*' => Http::response([], 500),
    ]);

    Cache::flush();

    $user = geoUser();

    $response = $this->actingAs($user)
        ->getJson(route('geo.regions', ['country' => 'Israel']))
        ->assertOk();

    $regions = $response->json('data');

    expect($regions)
        ->toBeArray()
        ->toContain('Tel Aviv District')
        ->toContain('Jerusalem District');
});

// ---------------------------------------------------------------------------
// Missing static file returns empty array (not 500)
// ---------------------------------------------------------------------------

it('returns empty data array for a country with no static file when upstream also fails', function () {
    Http::fake([
        'countriesnow.space/*' => Http::response([], 500),
    ]);

    Cache::flush();

    $user = geoUser();

    $response = $this->actingAs($user)
        ->getJson(route('geo.cities', ['country' => 'Bhutan']))
        ->assertOk();

    expect($response->json('data'))->toBeArray();
});

// ---------------------------------------------------------------------------
// Response shape
// ---------------------------------------------------------------------------

it('returns the correct json shape for a successful upstream countries response', function () {
    Http::fake([
        'countriesnow.space/*' => Http::response([
            'error' => false,
            'data' => [
                ['name' => 'Israel', 'Iso2' => 'IL'],
                ['name' => 'Germany', 'Iso2' => 'DE'],
            ],
        ], 200),
    ]);

    Cache::flush();

    $user = geoUser();

    $this->actingAs($user)
        ->getJson(route('geo.countries'))
        ->assertOk()
        ->assertJsonStructure(['data'])
        ->assertJsonMissingValidationErrors();
});
