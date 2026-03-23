<?php

use App\Models\Agency;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->managerRole = Role::create([
        'name' => 'Agency Manager',
        'permissions' => [
            'can_manage_users' => true,
            'can_manage_clients' => true,
            'can_view_campaigns' => true,
            'can_edit_campaigns' => true,
        ],
    ]);

    $this->viewerRole = Role::create([
        'name' => 'Viewer',
        'permissions' => ['can_view_campaigns' => true],
    ]);

    $this->agency = Agency::factory()->create();

    $this->manager = User::factory()->create(['is_admin' => false]);
    $this->manager->role_id = $this->managerRole->id;
    $this->manager->save();
    $this->agency->users()->attach($this->manager->id, ['access_all_clients' => true]);
});

// --- Index ---

it('allows manager to view client index for their agency', function () {
    $client = Client::factory()->create(['agency_id' => $this->agency->id]);

    $this->actingAs($this->manager)
        ->get(route('agency.clients.index', $this->agency))
        ->assertOk()
        ->assertSee($client->name);
});

// --- Store ---

it('allows manager to create a client with auto-assigned agency_id', function () {
    $this->actingAs($this->manager)
        ->post(route('agency.clients.store', $this->agency), [
            'name' => 'New Client',
        ])
        ->assertRedirect(route('agency.clients.index', $this->agency));

    $this->assertDatabaseHas('clients', [
        'name' => 'New Client',
        'agency_id' => $this->agency->id,
    ]);
});

// --- Edit ---

it('allows manager to view edit form for a client in their agency', function () {
    $client = Client::factory()->create(['agency_id' => $this->agency->id]);

    $this->actingAs($this->manager)
        ->get(route('agency.clients.edit', [$this->agency, $client]))
        ->assertOk()
        ->assertSee($client->name);
});

// --- Update ---

it('allows manager to update a client', function () {
    $client = Client::factory()->create(['agency_id' => $this->agency->id]);

    $this->actingAs($this->manager)
        ->put(route('agency.clients.update', [$this->agency, $client]), [
            'name' => 'Updated Client Name',
        ])
        ->assertRedirect(route('agency.clients.index', $this->agency));

    $client->refresh();
    expect($client->name)->toBe('Updated Client Name');
});

// --- Destroy ---

it('prevents deleting a client with campaigns', function () {
    $client = Client::factory()->create(['agency_id' => $this->agency->id]);
    Campaign::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
    ]);

    $this->actingAs($this->manager)
        ->delete(route('agency.clients.destroy', [$this->agency, $client]))
        ->assertRedirect(route('agency.clients.index', $this->agency))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('clients', ['id' => $client->id]);
});

it('allows deleting a client without campaigns', function () {
    $client = Client::factory()->create(['agency_id' => $this->agency->id]);

    $this->actingAs($this->manager)
        ->delete(route('agency.clients.destroy', [$this->agency, $client]))
        ->assertRedirect(route('agency.clients.index', $this->agency));

    $this->assertDatabaseMissing('clients', ['id' => $client->id]);
});

// --- Authorization: wrong agency ---

it('denies manager access to another agency client routes', function () {
    $otherAgency = Agency::factory()->create();
    $otherClient = Client::factory()->create(['agency_id' => $otherAgency->id]);

    $this->actingAs($this->manager)
        ->get(route('agency.clients.index', $otherAgency))
        ->assertForbidden();

    $this->actingAs($this->manager)
        ->get(route('agency.clients.edit', [$otherAgency, $otherClient]))
        ->assertForbidden();
});

// --- Authorization: non-manager ---

it('denies non-manager access to agency client routes', function () {
    $regularUser = User::factory()->create(['is_admin' => false]);
    $regularUser->role_id = $this->viewerRole->id;
    $regularUser->save();
    $this->agency->users()->attach($regularUser->id, ['access_all_clients' => true]);

    $this->actingAs($regularUser)
        ->get(route('agency.clients.index', $this->agency))
        ->assertForbidden();
});
