<?php

use App\Models\Agency;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns all agency clients when access_all_clients is true', function () {
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

it('returns only direct client_user entries when access_all_clients is false', function () {
    $agency = Agency::factory()->create();
    $client1 = Client::factory()->create(['agency_id' => $agency->id]);
    $client2 = Client::factory()->create(['agency_id' => $agency->id]);

    $user = User::factory()->create(['is_admin' => false]);
    $user->agencies()->attach($agency->id, ['access_all_clients' => false]);

    // Only attach client1 via client_user pivot
    $user->clients()->attach($client1->id);

    $ids = $user->accessibleClientIds();

    expect($ids)->toContain($client1->id);
    expect($ids)->not->toContain($client2->id);
});

it('returns exactly the direct client_user entries when access_all_clients is false', function () {
    $agency = Agency::factory()->create();
    $client1 = Client::factory()->create(['agency_id' => $agency->id]);
    $client2 = Client::factory()->create(['agency_id' => $agency->id]);
    $client3 = Client::factory()->create(['agency_id' => $agency->id]);

    $user = User::factory()->create(['is_admin' => false]);
    $user->agencies()->attach($agency->id, ['access_all_clients' => false]);

    // Attach only client1 and client3
    $user->clients()->attach([$client1->id, $client3->id]);

    $ids = $user->accessibleClientIds();

    expect($ids)->toContain($client1->id);
    expect($ids)->not->toContain($client2->id);
    expect($ids)->toContain($client3->id);
    expect($ids->count())->toBe(2);
});

it('merges correctly for user in multiple agencies with different access_all_clients values', function () {
    $agencyA = Agency::factory()->create();
    $agencyB = Agency::factory()->create();

    $clientA1 = Client::factory()->create(['agency_id' => $agencyA->id]);
    $clientA2 = Client::factory()->create(['agency_id' => $agencyA->id]);
    $clientB1 = Client::factory()->create(['agency_id' => $agencyB->id]);
    $clientB2 = Client::factory()->create(['agency_id' => $agencyB->id]);

    $user = User::factory()->create(['is_admin' => false]);

    // access_all_clients = true for agency A — sees all A clients
    $user->agencies()->attach($agencyA->id, ['access_all_clients' => true]);
    // access_all_clients = false for agency B — sees only direct client_user entries
    $user->agencies()->attach($agencyB->id, ['access_all_clients' => false]);

    // Only give direct access to clientB1
    $user->clients()->attach($clientB1->id);

    $ids = $user->accessibleClientIds();

    // Should see all agency A clients
    expect($ids)->toContain($clientA1->id);
    expect($ids)->toContain($clientA2->id);
    // Should see only clientB1 from agency B (direct access)
    expect($ids)->toContain($clientB1->id);
    // Should NOT see clientB2 (no direct access, access_all_clients=false)
    expect($ids)->not->toContain($clientB2->id);
});
