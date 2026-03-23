<?php

use App\Models\Agency;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows viewer in two agencies to see clients from both agencies', function () {
    $viewerRole = Role::create([
        'name' => 'Viewer',
        'permissions' => ['can_view_campaigns' => true],
    ]);

    $agencyA = Agency::factory()->create();
    $agencyB = Agency::factory()->create();

    $clientA = Client::factory()->create(['agency_id' => $agencyA->id]);
    $clientB = Client::factory()->create(['agency_id' => $agencyB->id]);

    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $viewerRole->id;
    $user->save();

    $user->agencies()->attach($agencyA->id, ['access_all_clients' => true]);
    $user->agencies()->attach($agencyB->id, ['access_all_clients' => true]);

    $ids = $user->accessibleClientIds();

    expect($ids)->toContain($clientA->id);
    expect($ids)->toContain($clientB->id);
});

it('sees all agency A clients and only direct agency B clients based on access_all_clients flag', function () {
    $viewerRole = Role::create([
        'name' => 'Viewer',
        'permissions' => ['can_view_campaigns' => true],
    ]);

    $agencyA = Agency::factory()->create();
    $agencyB = Agency::factory()->create();

    $clientA1 = Client::factory()->create(['agency_id' => $agencyA->id]);
    $clientA2 = Client::factory()->create(['agency_id' => $agencyA->id]);
    $clientB1 = Client::factory()->create(['agency_id' => $agencyB->id]);
    $clientB2 = Client::factory()->create(['agency_id' => $agencyB->id]);

    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $viewerRole->id;
    $user->save();

    // access_all_clients = true for A
    $user->agencies()->attach($agencyA->id, ['access_all_clients' => true]);
    // access_all_clients = false for B
    $user->agencies()->attach($agencyB->id, ['access_all_clients' => false]);

    // Only direct access to clientB1
    $user->clients()->attach($clientB1->id);

    $ids = $user->accessibleClientIds();

    expect($ids)->toContain($clientA1->id);
    expect($ids)->toContain($clientA2->id);
    expect($ids)->toContain($clientB1->id);
    expect($ids)->not->toContain($clientB2->id);
});

it('allows regular user to be attached to multiple agencies without constraint error', function () {
    $viewerRole = Role::create([
        'name' => 'Viewer',
        'permissions' => ['can_view_campaigns' => true],
    ]);

    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $viewerRole->id;
    $user->save();

    $agency1 = Agency::factory()->create();
    $agency2 = Agency::factory()->create();
    $agency3 = Agency::factory()->create();

    $agency1->users()->attach($user->id, ['access_all_clients' => true]);
    $agency2->users()->attach($user->id, ['access_all_clients' => false]);
    $agency3->users()->attach($user->id, ['access_all_clients' => true]);

    // No constraint violation — regular users can be in multiple agencies
    User::validateSingleAgencyConstraint($user, $viewerRole);

    expect($user->agencies()->count())->toBe(3);
});
