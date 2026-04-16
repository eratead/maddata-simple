<?php

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function adminUser(): User
{
    return User::factory()->create(['is_admin' => true, 'is_active' => true]);
}

function nonAdminUser(): User
{
    $role = Role::create([
        'name' => 'Editor '.uniqid(),
        'permissions' => ['can_edit_campaigns' => true],
        'sort_order' => 99,
    ]);

    return User::factory()->create([
        'is_admin' => false,
        'role_id' => $role->id,
        'is_active' => true,
    ]);
}

// ---------------------------------------------------------------------------
// Store (create campaign)
// ---------------------------------------------------------------------------

it('admin can store a campaign with expected_impressions', function () {
    $admin = adminUser();
    $client = Client::factory()->create();

    $response = $this->actingAs($admin)
        ->post(route('campaigns.store'), [
            'name' => 'Admin Campaign',
            'client_id' => $client->id,
            'expected_impressions' => 50000,
            'status' => 'active',
        ]);

    $campaign = Campaign::where('name', 'Admin Campaign')->firstOrFail();
    $response->assertRedirect(route('campaigns.edit', $campaign));
    expect($campaign->expected_impressions)->toBe(50000);
});

it('non-admin user gets a validation error for expected_impressions on store', function () {
    $user = nonAdminUser();
    $client = Client::factory()->create();
    $user->clients()->attach($client);

    // StoreCampaignRequest runs before the controller's authorize() call.
    // The 'prohibited' rule fires, so the response is a redirect with session errors.
    $this->actingAs($user)
        ->post(route('campaigns.store'), [
            'name' => 'Non-Admin Campaign',
            'client_id' => $client->id,
            'expected_impressions' => 50000,
            'status' => 'active',
        ])
        ->assertSessionHasErrors(['expected_impressions']);
});

// ---------------------------------------------------------------------------
// Update (edit campaign)
// ---------------------------------------------------------------------------

it('admin can update a campaign with expected_impressions', function () {
    $admin = adminUser();
    $campaign = Campaign::factory()->create(['expected_impressions' => 1000]);

    $response = $this->actingAs($admin)
        ->put(route('campaigns.update', $campaign), [
            'name' => $campaign->name,
            'client_id' => $campaign->client_id,
            'expected_impressions' => 99999,
            'status' => 'active',
        ]);

    $response->assertRedirect(route('campaigns.edit', $campaign));
    expect($campaign->fresh()->expected_impressions)->toBe(99999);
});

it('non-admin user gets a validation error for expected_impressions on update', function () {
    $user = nonAdminUser();
    $client = Client::factory()->create();
    $user->clients()->attach($client);
    $campaign = Campaign::factory()->create(['client_id' => $client->id, 'expected_impressions' => 1000]);

    // UpdateCampaignRequest runs before the controller's authorize() call.
    // The 'prohibited' rule fires, so the response is a redirect with session errors.
    $this->actingAs($user)
        ->put(route('campaigns.update', $campaign), [
            'name' => $campaign->name,
            'client_id' => $campaign->client_id,
            'expected_impressions' => 99999,
            'status' => 'active',
        ])
        ->assertSessionHasErrors(['expected_impressions']);

    // Value must not have changed
    expect($campaign->fresh()->expected_impressions)->toBe(1000);
});

it('non-admin user can update a campaign without sending expected_impressions', function () {
    $user = nonAdminUser();
    $client = Client::factory()->create();
    $user->clients()->attach($client);
    $campaign = Campaign::factory()->create([
        'name' => 'Before',
        'client_id' => $client->id,
        'expected_impressions' => 1000,
    ]);

    $response = $this->actingAs($user)
        ->put(route('campaigns.update', $campaign), [
            'name' => 'After',
            'client_id' => $campaign->client_id,
            'status' => 'active',
        ]);

    $response->assertRedirect(route('campaigns.edit', $campaign));
    $updated = $campaign->fresh();
    expect($updated->name)->toBe('After');
    // expected_impressions must remain untouched
    expect($updated->expected_impressions)->toBe(1000);
});
