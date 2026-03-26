<?php

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeAdmin(): User
{
    $role = Role::create([
        'name' => 'Admin',
        'permissions' => [
            'is_admin' => true,
            'can_view_campaigns' => true,
            'can_edit_campaigns' => true,
            'can_view_budget' => true,
        ],
    ]);
    $admin = User::factory()->create(['is_admin' => true]);
    $admin->role_id = $role->id;
    $admin->save();

    return $admin;
}

function makeEditor(): array
{
    $role = Role::create([
        'name' => 'Editor',
        'permissions' => [
            'can_view_campaigns' => true,
            'can_edit_campaigns' => true,
        ],
    ]);
    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $role->id;
    $user->save();
    $client = Client::factory()->create();
    $user->clients()->attach($client);

    return [$user, $client];
}

// ───────────────────────────────────────────────
// STORE (CREATE)
// ───────────────────────────────────────────────

it('allows admin to create campaign with all fields', function () {
    $admin = makeAdmin();
    $client = Client::factory()->create();

    $response = $this->actingAs($admin)->post(route('campaigns.store'), [
        'name' => 'Full Campaign',
        'client_id' => $client->id,
        'status' => 'active',
        'budget' => 50000,
        'expected_impressions' => 200000,
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'required_sizes' => '300x250,728x90',
    ]);

    $response->assertRedirect(route('campaigns.index'));
    $this->assertDatabaseHas('campaigns', [
        'name' => 'Full Campaign',
        'client_id' => $client->id,
        'status' => 'active',
    ]);
});

it('persists all fields correctly in DB after create', function () {
    $admin = makeAdmin();
    $client = Client::factory()->create();

    $startDate = now()->addDay()->toDateString();
    $endDate = now()->addMonth()->toDateString();

    $this->actingAs($admin)->post(route('campaigns.store'), [
        'name' => 'Persisted Campaign',
        'client_id' => $client->id,
        'status' => 'active',
        'budget' => 75000,
        'expected_impressions' => 500000,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'required_sizes' => '1920x1080,300x250',
    ]);

    $campaign = Campaign::where('name', 'Persisted Campaign')->first();
    expect($campaign)->not->toBeNull();
    expect($campaign->client_id)->toBe($client->id);
    expect($campaign->status)->toBe('active');
    expect((int) $campaign->budget)->toBe(75000);
    expect($campaign->expected_impressions)->toBe(500000);
    expect($campaign->start_date->toDateString())->toBe($startDate);
    expect($campaign->end_date->toDateString())->toBe($endDate);
    expect($campaign->required_sizes)->toBe('1920x1080,300x250');
});

it('allows non-admin editor to create campaign for their client', function () {
    [$editor, $client] = makeEditor();

    $response = $this->actingAs($editor)->post(route('campaigns.store'), [
        'name' => 'Editor Campaign',
        'client_id' => $client->id,
        'status' => 'active',
    ]);

    $response->assertRedirect(route('campaigns.index'));
    $this->assertDatabaseHas('campaigns', ['name' => 'Editor Campaign']);
});

it('prevents non-admin from creating campaign for a client they lack access to', function () {
    [$editor, $ownClient] = makeEditor();
    $otherClient = Client::factory()->create();

    $response = $this->actingAs($editor)->post(route('campaigns.store'), [
        'name' => 'Forbidden Campaign',
        'client_id' => $otherClient->id,
        'status' => 'active',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('campaigns', ['name' => 'Forbidden Campaign']);
});

it('validates that name, client_id, and status are required on store', function () {
    $admin = makeAdmin();

    $response = $this->actingAs($admin)->post(route('campaigns.store'), []);

    $response->assertSessionHasErrors(['name', 'client_id', 'status']);
});

// ───────────────────────────────────────────────
// UPDATE
// ───────────────────────────────────────────────

it('allows admin to update all fields including required_sizes, budget, expected_impressions', function () {
    $admin = makeAdmin();
    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
    ]);

    $response = $this->actingAs($admin)->put(route('campaigns.update', $campaign), [
        'name' => 'Updated All Fields',
        'client_id' => $client->id,
        'status' => 'paused',
        'budget' => 99000,
        'expected_impressions' => 750000,
        'required_sizes' => '160x600,320x50',
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
    ]);

    $response->assertRedirect(route('campaigns.index'));
    $campaign->refresh();
    expect($campaign->name)->toBe('Updated All Fields');
    expect($campaign->status)->toBe('paused');
    expect((int) $campaign->budget)->toBe(99000);
    expect($campaign->expected_impressions)->toBe(750000);
    expect($campaign->required_sizes)->toBe('160x600,320x50');
});

it('persists required_sizes correctly after update', function () {
    $admin = makeAdmin();
    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create([
        'client_id' => $client->id,
        'required_sizes' => '100x100',
        'status' => 'active',
    ]);

    $this->actingAs($admin)->put(route('campaigns.update', $campaign), [
        'name' => $campaign->name,
        'client_id' => $client->id,
        'status' => 'active',
        'required_sizes' => '1920x1080,300x250,728x90',
    ]);

    $campaign->refresh();
    expect($campaign->required_sizes)->toBe('1920x1080,300x250,728x90');
});

it('allows required_sizes to be cleared to empty string', function () {
    $admin = makeAdmin();
    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create([
        'client_id' => $client->id,
        'required_sizes' => '300x250',
        'status' => 'active',
    ]);

    $this->actingAs($admin)->put(route('campaigns.update', $campaign), [
        'name' => $campaign->name,
        'client_id' => $client->id,
        'status' => 'active',
        'required_sizes' => '',
    ]);

    $campaign->refresh();
    expect($campaign->required_sizes)->toBeIn([null, '']);
});

it('strips budget from non-admin update', function () {
    [$editor, $client] = makeEditor();
    $campaign = Campaign::factory()->create([
        'client_id' => $client->id,
        'budget' => 10000,
        'status' => 'active',
    ]);

    $this->actingAs($editor)->put(route('campaigns.update', $campaign), [
        'name' => $campaign->name,
        'client_id' => $client->id,
        'status' => 'active',
        'budget' => 99999,
    ]);

    $campaign->refresh();
    // Budget should remain unchanged — non-admin cannot edit budget
    expect((int) $campaign->budget)->toBe(10000);
});

it('strips expected_impressions from non-admin update', function () {
    [$editor, $client] = makeEditor();
    $campaign = Campaign::factory()->create([
        'client_id' => $client->id,
        'expected_impressions' => 5000,
        'status' => 'active',
    ]);

    $this->actingAs($editor)->put(route('campaigns.update', $campaign), [
        'name' => $campaign->name,
        'client_id' => $client->id,
        'status' => 'active',
        'expected_impressions' => 999999,
    ]);

    $campaign->refresh();
    // expected_impressions should remain unchanged — non-admin cannot edit
    expect($campaign->expected_impressions)->toBe(5000);
});

it('persists targeting_rules JSON correctly', function () {
    $admin = makeAdmin();
    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
    ]);

    $targetingRules = [
        'genders' => ['Male', 'Female'],
        'ages' => ['18-24', '25-34'],
        'device_types' => ['Mobile', 'Desktop'],
        'os' => ['iOS', 'Android'],
        'days' => ['Mon', 'Tue', 'Wed'],
        'time_start' => '08:00',
        'time_end' => '20:00',
    ];

    $this->actingAs($admin)->put(route('campaigns.update', $campaign), [
        'name' => $campaign->name,
        'client_id' => $client->id,
        'status' => 'active',
        'targeting_rules' => $targetingRules,
    ]);

    $campaign->refresh();
    $saved = $campaign->targeting_rules;
    expect($saved['genders'])->toBe(['Male', 'Female']);
    expect($saved['ages'])->toBe(['18-24', '25-34']);
    expect($saved['device_types'])->toBe(['Mobile', 'Desktop']);
    expect($saved['days'])->toBe(['Mon', 'Tue', 'Wed']);
});

it('persists creative_optimization boolean correctly', function () {
    $admin = makeAdmin();
    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create([
        'client_id' => $client->id,
        'creative_optimization' => false,
        'status' => 'active',
    ]);

    $this->actingAs($admin)->put(route('campaigns.update', $campaign), [
        'name' => $campaign->name,
        'client_id' => $client->id,
        'status' => 'active',
        'creative_optimization' => true,
    ]);

    $campaign->refresh();
    expect($campaign->creative_optimization)->toBeTrue();
});

it('persists start_date and end_date correctly', function () {
    $admin = makeAdmin();
    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
    ]);

    $this->actingAs($admin)->put(route('campaigns.update', $campaign), [
        'name' => $campaign->name,
        'client_id' => $client->id,
        'status' => 'active',
        'start_date' => '2025-06-01',
        'end_date' => '2025-12-31',
    ]);

    $campaign->refresh();
    expect($campaign->start_date->toDateString())->toBe('2025-06-01');
    expect($campaign->end_date->toDateString())->toBe('2025-12-31');
});

it('syncs locations on update — old deleted, new created', function () {
    $admin = makeAdmin();
    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
    ]);

    // Create initial location
    $campaign->locations()->create([
        'name' => 'Old Location',
        'lat' => 40.7128,
        'lng' => -74.0060,
        'radius_meters' => 5000,
    ]);

    expect($campaign->locations()->count())->toBe(1);

    // Update with new locations
    $this->actingAs($admin)->put(route('campaigns.update', $campaign), [
        'name' => $campaign->name,
        'client_id' => $client->id,
        'status' => 'active',
        'locations' => [
            ['name' => 'New Location A', 'lat' => 34.0522, 'lng' => -118.2437, 'radius_meters' => 2000],
            ['name' => 'New Location B', 'lat' => 41.8781, 'lng' => -87.6298, 'radius_meters' => 3000],
        ],
    ]);

    $campaign->refresh();
    expect($campaign->locations()->count())->toBe(2);
    expect($campaign->locations->pluck('name')->toArray())->toBe(['New Location A', 'New Location B']);
    // Old location should be gone
    $this->assertDatabaseMissing('campaign_locations', ['name' => 'Old Location']);
});

// ───────────────────────────────────────────────
// AUTHORIZATION
// ───────────────────────────────────────────────

it('prevents non-admin from accessing campaigns outside their scope', function () {
    [$editor, $ownClient] = makeEditor();
    $otherClient = Client::factory()->create();
    $campaign = Campaign::factory()->create([
        'client_id' => $otherClient->id,
        'status' => 'active',
    ]);

    // Cannot edit
    $response = $this->actingAs($editor)->get(route('campaigns.edit', $campaign));
    $response->assertForbidden();

    // Cannot update
    $response = $this->actingAs($editor)->put(route('campaigns.update', $campaign), [
        'name' => 'Hijacked',
        'client_id' => $otherClient->id,
        'status' => 'active',
    ]);
    $response->assertForbidden();

    // Cannot delete
    $response = $this->actingAs($editor)->delete(route('campaigns.destroy', $campaign));
    $response->assertForbidden();
});

it('allows admin to access any campaign regardless of client', function () {
    $admin = makeAdmin();
    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
    ]);

    $response = $this->actingAs($admin)->get(route('campaigns.edit', $campaign));
    $response->assertOk();

    $response = $this->actingAs($admin)->put(route('campaigns.update', $campaign), [
        'name' => 'Admin Updated',
        'client_id' => $client->id,
        'status' => 'active',
    ]);
    $response->assertRedirect(route('campaigns.index'));

    $response = $this->actingAs($admin)->delete(route('campaigns.destroy', $campaign));
    $response->assertRedirect(route('campaigns.index'));
});

// ───────────────────────────────────────────────
// IS_VIDEO FIELD
// ───────────────────────────────────────────────

it('can set is_video field programmatically on campaign', function () {
    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create([
        'client_id' => $client->id,
        'is_video' => false,
        'status' => 'active',
    ]);

    expect($campaign->is_video)->toBeFalse();

    $campaign->update(['is_video' => true]);
    $campaign->refresh();

    expect($campaign->is_video)->toBeTrue();
});

// ───────────────────────────────────────────────
// INDEX
// ───────────────────────────────────────────────

it('shows all campaigns to admin on index', function () {
    $admin = makeAdmin();
    $clientA = Client::factory()->create();
    $clientB = Client::factory()->create();
    $campaignA = Campaign::factory()->create(['client_id' => $clientA->id, 'status' => 'active']);
    $campaignB = Campaign::factory()->create(['client_id' => $clientB->id, 'status' => 'active']);

    $response = $this->actingAs($admin)->get(route('campaigns.index'));
    $response->assertOk();
    $response->assertSee($campaignA->name);
    $response->assertSee($campaignB->name);
});

it('shows only accessible client campaigns to non-admin on index', function () {
    [$editor, $ownClient] = makeEditor();
    $otherClient = Client::factory()->create();

    $ownCampaign = Campaign::factory()->create(['client_id' => $ownClient->id, 'status' => 'active']);
    $otherCampaign = Campaign::factory()->create(['client_id' => $otherClient->id, 'status' => 'active']);

    $response = $this->actingAs($editor)->get(route('campaigns.index'));
    $response->assertOk();
    $response->assertSee($ownCampaign->name);
    $response->assertDontSee($otherCampaign->name);
});

it('returns all campaigns to the view for client-side pagination', function () {
    $admin = makeAdmin();
    $client = Client::factory()->create();

    // Create 30 campaigns
    Campaign::factory()->count(30)->create([
        'client_id' => $client->id,
        'status' => 'active',
    ]);

    $response = $this->actingAs($admin)->get(route('campaigns.index'));
    $response->assertOk();

    // All campaigns returned (DataTable handles client-side pagination)
    $campaigns = $response->viewData('campaigns');
    expect($campaigns->count())->toBe(30);
});
