<?php

use App\Models\Agency;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- Direct client access ---

it('allows user with direct client access to view that clients campaign', function () {
    $viewerRole = Role::create([
        'name' => 'Viewer',
        'permissions' => ['can_view_campaigns' => true],
    ]);

    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $viewerRole->id;
    $user->save();

    $client = Client::factory()->create();
    $user->clients()->attach($client);

    $campaign = Campaign::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.campaign', $campaign))
        ->assertOk();
});

// --- Agency-based access ---

it('allows user with agency access to view campaigns of clients in that agency', function () {
    $viewerRole = Role::create([
        'name' => 'Viewer',
        'permissions' => ['can_view_campaigns' => true],
    ]);

    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $viewerRole->id;
    $user->save();

    $agency = Agency::factory()->create();
    $user->agencies()->attach($agency->id, ['access_all_clients' => true]);

    $client = Client::factory()->create(['agency_id' => $agency->id]);
    $campaign = Campaign::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.campaign', $campaign))
        ->assertOk();
});

// --- No access ---

it('denies user access to campaigns of clients they have no access to', function () {
    $viewerRole = Role::create([
        'name' => 'Viewer',
        'permissions' => ['can_view_campaigns' => true],
    ]);

    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $viewerRole->id;
    $user->save();

    // User has access to one client, but NOT to the other
    $myClient = Client::factory()->create();
    $user->clients()->attach($myClient);

    $otherClient = Client::factory()->create();
    $forbiddenCampaign = Campaign::factory()->create([
        'client_id' => $otherClient->id,
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.campaign', $forbiddenCampaign))
        ->assertForbidden();
});

// --- Admin sees everything ---

it('allows admin to view all campaigns regardless of pivot assignments', function () {
    $adminRole = Role::create([
        'name' => 'Admin',
        'permissions' => ['is_admin' => true],
    ]);

    $admin = User::factory()->create(['is_admin' => true]);
    $admin->role_id = $adminRole->id;
    $admin->save();

    // Admin has no client/agency pivot assignments
    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard.campaign', $campaign))
        ->assertOk();
});

// --- accessibleClientIds() with agency access ---

it('returns correct client IDs from accessibleClientIds for agency user', function () {
    $agency = Agency::factory()->create();
    $client1 = Client::factory()->create(['agency_id' => $agency->id]);
    $client2 = Client::factory()->create(['agency_id' => $agency->id]);
    $otherClient = Client::factory()->create(); // different agency

    $user = User::factory()->create(['is_admin' => false]);
    $user->agencies()->attach($agency->id, ['access_all_clients' => true]);

    $ids = $user->accessibleClientIds();

    expect($ids)->toContain($client1->id);
    expect($ids)->toContain($client2->id);
    expect($ids)->not->toContain($otherClient->id);
});

// --- Merged + deduplicated IDs ---

it('merges and deduplicates IDs from direct and agency access', function () {
    $agency = Agency::factory()->create();
    $agencyClient = Client::factory()->create(['agency_id' => $agency->id]);
    $directClient = Client::factory()->create();
    // Also make agencyClient a direct client (overlap)
    $sharedClient = Client::factory()->create(['agency_id' => $agency->id]);

    $user = User::factory()->create(['is_admin' => false]);
    $user->agencies()->attach($agency->id, ['access_all_clients' => true]);
    $user->clients()->attach([$directClient->id, $sharedClient->id]);

    $ids = $user->accessibleClientIds();

    // Should contain all three, deduplicated
    expect($ids)->toContain($agencyClient->id);
    expect($ids)->toContain($directClient->id);
    expect($ids)->toContain($sharedClient->id);

    // Should be unique (sharedClient appears in both agency and direct, but only once)
    expect($ids->count())->toBe($ids->unique()->count());
});

// --- User with no assignments sees nothing ---

it('returns empty accessible client IDs for user with no assignments', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $ids = $user->accessibleClientIds();

    expect($ids)->toBeEmpty();
});

// --- Agency access does not leak to other agencies ---

it('does not grant access to clients of a different agency', function () {
    $agency1 = Agency::factory()->create();
    $agency2 = Agency::factory()->create();

    $clientInAgency1 = Client::factory()->create(['agency_id' => $agency1->id]);
    $clientInAgency2 = Client::factory()->create(['agency_id' => $agency2->id]);

    $user = User::factory()->create(['is_admin' => false]);
    $user->agencies()->attach($agency1->id, ['access_all_clients' => true]);

    $ids = $user->accessibleClientIds();

    expect($ids)->toContain($clientInAgency1->id);
    expect($ids)->not->toContain($clientInAgency2->id);
});

// --- CampaignPolicy view with no role_id ---

it('allows user without role but with direct access to view campaign', function () {
    $user = User::factory()->create(['is_admin' => false]);
    // No role_id set

    $client = Client::factory()->create();
    $user->clients()->attach($client);

    $campaign = Campaign::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.campaign', $campaign))
        ->assertOk();
});

it('denies user without role and without access from viewing campaign', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.campaign', $campaign))
        ->assertForbidden();
});
