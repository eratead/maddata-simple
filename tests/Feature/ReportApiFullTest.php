<?php

use App\Models\Campaign;
use App\Models\CampaignData;
use App\Models\Client;
use App\Models\PlacementData;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function createTokenUser(array $permissions = ['can_view_campaigns' => true, 'can_view_budget' => true], string $ability = 'reports:read'): array
{
    $role = Role::create(['name' => 'API User', 'permissions' => $permissions]);
    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $role->id;
    $user->save();
    $client = Client::factory()->create();
    $user->clients()->attach($client);
    $token = $user->createToken('test-token', [$ability]);
    $accessToken = $token->accessToken;
    $accessToken->expires_at = now()->addDays(30);
    $accessToken->save();

    return [$user, $client, $token->plainTextToken];
}

function createCampaignWithData(Client $client): Campaign
{
    $campaign = Campaign::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
        'expected_impressions' => 100000,
        'budget' => 5000,
    ]);
    CampaignData::create([
        'campaign_id' => $campaign->id,
        'report_date' => '2025-03-01',
        'impressions' => 50000,
        'clicks' => 1500,
        'visible_impressions' => 45000,
        'uniques' => 40000,
    ]);
    CampaignData::create([
        'campaign_id' => $campaign->id,
        'report_date' => '2025-03-02',
        'impressions' => 30000,
        'clicks' => 900,
        'visible_impressions' => 27000,
        'uniques' => 25000,
    ]);

    return $campaign;
}

// ---------------------------------------------------------------------------
// Authentication & Authorization
// ---------------------------------------------------------------------------

it('returns 401 for request without token', function () {
    $campaign = Campaign::factory()->create();

    $this->getJson('/api/reports/summary/'.$campaign->id)
        ->assertUnauthorized();
});

it('returns 200 for valid token with reports:read ability', function () {
    [$user, $client, $plainToken] = createTokenUser();
    $campaign = createCampaignWithData($client);

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/summary/'.$campaign->id)
        ->assertOk();
});

it('returns 200 for valid token with wildcard ability (backward compat)', function () {
    [$user, $client, $plainToken] = createTokenUser(['can_view_campaigns' => true], '*');

    $campaign = createCampaignWithData($client);

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/summary/'.$campaign->id)
        ->assertOk();
});

it('returns 401 for expired token', function () {
    [$user, $client, $plainToken] = createTokenUser();
    $campaign = createCampaignWithData($client);

    // Expire the token
    $tokenModel = PersonalAccessToken::findToken(explode('|', $plainToken)[1]);
    $tokenModel->expires_at = now()->subDay();
    $tokenModel->save();

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/summary/'.$campaign->id)
        ->assertUnauthorized();
});

it('returns 403 for token lacking reports:read ability', function () {
    [$user, $client, $plainToken] = createTokenUser(['can_view_campaigns' => true], 'other:ability');
    $campaign = createCampaignWithData($client);

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/summary/'.$campaign->id)
        ->assertForbidden();
});

it('2FA middleware does not block API token requests', function () {
    [$user, $client, $plainToken] = createTokenUser();
    $campaign = createCampaignWithData($client);

    // Even if user has no 2FA secret, API token should pass through
    $user->google2fa_secret = null;
    $user->save();

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/summary/'.$campaign->id)
        ->assertOk();
});

// ---------------------------------------------------------------------------
// Token Expiry
// ---------------------------------------------------------------------------

it('allows token with future expires_at', function () {
    [$user, $client, $plainToken] = createTokenUser();
    $campaign = createCampaignWithData($client);

    $tokenModel = PersonalAccessToken::findToken(explode('|', $plainToken)[1]);
    $tokenModel->expires_at = now()->addDays(90);
    $tokenModel->save();

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/summary/'.$campaign->id)
        ->assertOk();
});

it('rejects token with past expires_at', function () {
    [$user, $client, $plainToken] = createTokenUser();
    $campaign = createCampaignWithData($client);

    $tokenModel = PersonalAccessToken::findToken(explode('|', $plainToken)[1]);
    $tokenModel->expires_at = now()->subHour();
    $tokenModel->save();

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/summary/'.$campaign->id)
        ->assertUnauthorized();
});

it('allows token with null expires_at (no expiry)', function () {
    [$user, $client, $plainToken] = createTokenUser();
    $campaign = createCampaignWithData($client);

    $tokenModel = PersonalAccessToken::findToken(explode('|', $plainToken)[1]);
    $tokenModel->expires_at = null;
    $tokenModel->save();

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/summary/'.$campaign->id)
        ->assertOk();
});

// ---------------------------------------------------------------------------
// Summary endpoint
// ---------------------------------------------------------------------------

it('summary returns correct structure', function () {
    [$user, $client, $plainToken] = createTokenUser();
    $campaign = createCampaignWithData($client);

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/summary/'.$campaign->id)
        ->assertOk()
        ->assertJsonStructure([
            'campaign_id',
            'campaign_name',
            'impressions',
            'clicks',
            'ctr',
            'uniques',
            'expected_impressions',
        ]);
});

it('summary returns budget fields when user has can_view_budget permission', function () {
    [$user, $client, $plainToken] = createTokenUser(['can_view_campaigns' => true, 'can_view_budget' => true]);
    $campaign = createCampaignWithData($client);

    $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/summary/'.$campaign->id)
        ->assertOk();

    expect($response->json())->toHaveKeys(['budget', 'cpm', 'spent', 'cpc']);
});

it('summary does not return budget fields when user lacks can_view_budget permission', function () {
    [$user, $client, $plainToken] = createTokenUser(['can_view_campaigns' => true, 'can_view_budget' => false]);
    $campaign = createCampaignWithData($client);

    $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/summary/'.$campaign->id)
        ->assertOk();

    expect($response->json())->not->toHaveKeys(['budget', 'cpm', 'spent', 'cpc']);
});

it('summary returns video fields when campaign is_video is true', function () {
    [$user, $client, $plainToken] = createTokenUser();
    $campaign = createCampaignWithData($client);
    $campaign->update(['is_video' => true]);

    $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/summary/'.$campaign->id)
        ->assertOk();

    expect($response->json())->toHaveKeys(['video_complete', 'vcr']);
});

it('summary does not return video fields when campaign is_video is false', function () {
    [$user, $client, $plainToken] = createTokenUser();
    $campaign = createCampaignWithData($client);
    $campaign->update(['is_video' => false]);

    $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/summary/'.$campaign->id)
        ->assertOk();

    expect($response->json())->not->toHaveKey('video_complete');
    expect($response->json())->not->toHaveKey('vcr');
});

it('summary date filtering with start and end works', function () {
    [$user, $client, $plainToken] = createTokenUser();
    $campaign = createCampaignWithData($client);

    // Only 2025-03-01 data should be included
    $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/summary/'.$campaign->id.'?start=2025-03-01&end=2025-03-01')
        ->assertOk();

    expect($response->json('impressions'))->toBe(50000);
    expect($response->json('clicks'))->toBe(1500);
});

it('summary returns 403 when user does not have access to the campaign client', function () {
    [$user, $client, $plainToken] = createTokenUser();

    // Create a campaign for a different client
    $otherClient = Client::factory()->create();
    $campaign = Campaign::factory()->create([
        'client_id' => $otherClient->id,
        'status' => 'active',
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/summary/'.$campaign->id)
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// By-date endpoint
// ---------------------------------------------------------------------------

it('by-date returns correct structure with by_date array', function () {
    [$user, $client, $plainToken] = createTokenUser();
    $campaign = createCampaignWithData($client);

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/by-date/'.$campaign->id)
        ->assertOk()
        ->assertJsonStructure([
            'campaign_id',
            'campaign_name',
            'by_date' => [['date', 'impressions', 'clicks', 'ctr']],
        ]);
});

it('by-date each entry has date, impressions, clicks, ctr', function () {
    [$user, $client, $plainToken] = createTokenUser();
    $campaign = createCampaignWithData($client);

    $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/by-date/'.$campaign->id)
        ->assertOk();

    $byDate = $response->json('by_date');
    expect($byDate)->toHaveCount(2);

    foreach ($byDate as $entry) {
        expect($entry)->toHaveKeys(['date', 'impressions', 'clicks', 'ctr']);
    }
});

// ---------------------------------------------------------------------------
// By-placement endpoint
// ---------------------------------------------------------------------------

it('by-placement returns correct structure with by_placement array', function () {
    [$user, $client, $plainToken] = createTokenUser();
    $campaign = createCampaignWithData($client);

    PlacementData::create([
        'campaign_id' => $campaign->id,
        'name' => 'Homepage Banner',
        'report_date' => '2025-03-01',
        'impressions' => 25000,
        'clicks' => 750,
        'visible_impressions' => 22000,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/by-placement/'.$campaign->id)
        ->assertOk()
        ->assertJsonStructure([
            'campaign_id',
            'campaign_name',
            'by_placement' => [['placement', 'impressions', 'clicks', 'ctr', 'visible_impressions']],
        ]);
});

it('by-placement each entry has placement, impressions, clicks, ctr, visible_impressions', function () {
    [$user, $client, $plainToken] = createTokenUser();
    $campaign = createCampaignWithData($client);

    PlacementData::create([
        'campaign_id' => $campaign->id,
        'name' => 'Sidebar Ad',
        'report_date' => '2025-03-01',
        'impressions' => 10000,
        'clicks' => 300,
        'visible_impressions' => 9000,
    ]);
    PlacementData::create([
        'campaign_id' => $campaign->id,
        'name' => 'Footer Banner',
        'report_date' => '2025-03-01',
        'impressions' => 5000,
        'clicks' => 100,
        'visible_impressions' => 4500,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/by-placement/'.$campaign->id)
        ->assertOk();

    $byPlacement = $response->json('by_placement');
    expect($byPlacement)->toHaveCount(2);

    foreach ($byPlacement as $entry) {
        expect($entry)->toHaveKeys(['placement', 'impressions', 'clicks', 'ctr', 'visible_impressions']);
    }
});

// ---------------------------------------------------------------------------
// Campaigns list endpoint
// ---------------------------------------------------------------------------

it('campaigns returns paginated list of accessible campaigns', function () {
    [$user, $client, $plainToken] = createTokenUser();
    Campaign::factory()->count(3)->create(['client_id' => $client->id, 'status' => 'active']);

    $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/campaigns')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3);
});

it('campaigns non-admin only sees their clients campaigns', function () {
    [$user, $client, $plainToken] = createTokenUser();
    Campaign::factory()->create(['client_id' => $client->id, 'status' => 'active', 'name' => 'My Campaign']);

    // Create a campaign for a different client
    $otherClient = Client::factory()->create();
    Campaign::factory()->create(['client_id' => $otherClient->id, 'status' => 'active', 'name' => 'Other Campaign']);

    $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/campaigns')
        ->assertOk();

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('My Campaign');
    expect($names)->not->toContain('Other Campaign');
});

it('campaigns response has pagination structure', function () {
    [$user, $client, $plainToken] = createTokenUser();
    Campaign::factory()->create(['client_id' => $client->id, 'status' => 'active']);

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/campaigns')
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'links',
            'current_page',
            'last_page',
            'per_page',
            'total',
        ]);
});

// ---------------------------------------------------------------------------
// Cross-tenant security
// ---------------------------------------------------------------------------

it('user cannot access summary for a campaign belonging to another client', function () {
    [$user, $client, $plainToken] = createTokenUser();

    $otherClient = Client::factory()->create();
    $otherCampaign = Campaign::factory()->create([
        'client_id' => $otherClient->id,
        'status' => 'active',
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/summary/'.$otherCampaign->id)
        ->assertForbidden();
});

it('user can access summary for their own clients campaign', function () {
    [$user, $client, $plainToken] = createTokenUser();
    $campaign = createCampaignWithData($client);

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/summary/'.$campaign->id)
        ->assertOk()
        ->assertJson(['campaign_id' => $campaign->id]);
});

it('user cannot access by-date for a campaign belonging to another client', function () {
    [$user, $client, $plainToken] = createTokenUser();

    $otherClient = Client::factory()->create();
    $otherCampaign = Campaign::factory()->create([
        'client_id' => $otherClient->id,
        'status' => 'active',
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/by-date/'.$otherCampaign->id)
        ->assertForbidden();
});

it('user cannot access by-placement for a campaign belonging to another client', function () {
    [$user, $client, $plainToken] = createTokenUser();

    $otherClient = Client::factory()->create();
    $otherCampaign = Campaign::factory()->create([
        'client_id' => $otherClient->id,
        'status' => 'active',
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/reports/by-placement/'.$otherCampaign->id)
        ->assertForbidden();
});
