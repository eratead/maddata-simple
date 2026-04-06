<?php

use App\Models\Agency;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// StoreClientRequest — agency_id enforcement
// ---------------------------------------------------------------------------

it('rejects store without agency_id (422 validation error)', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.clients.store'), ['name' => 'ACME'])
        ->assertSessionHasErrors('agency_id');
});

it('rejects store with a non-existent agency_id (422 validation error)', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.clients.store'), [
            'name' => 'ACME',
            'agency_id' => 99999,
        ])
        ->assertSessionHasErrors('agency_id');
});

it('admin can store a client with any valid agency_id', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $agency = Agency::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.clients.store'), [
            'name' => 'ACME Corp',
            'agency_id' => $agency->id,
        ])
        ->assertRedirect(route('admin.clients.index'));

    $this->assertDatabaseHas('clients', ['name' => 'ACME Corp', 'agency_id' => $agency->id]);
});

it('non-admin with can_manage_clients can store a client for their own agency', function () {
    $role = Role::create([
        'name' => 'Client Manager',
        'permissions' => [
            'is_admin' => false,
            'can_manage_clients' => true,
        ],
    ]);
    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $role->id;
    $user->save();

    $agency = Agency::factory()->create();
    $user->agencies()->attach($agency->id, ['access_all_clients' => true]);
    $user->load('agencies'); // reload relationship

    // The ClientController authorizes via ClientPolicy (admin-only) and the
    // admin middleware. Bypass both so we can test the FormRequest's Rule::in
    // logic in isolation — this is the constraint that prevents non-admin
    // users from assigning agencies they don't belong to.
    \Illuminate\Support\Facades\Gate::before(fn () => true);

    $this->actingAs($user)
        ->withoutMiddleware(\App\Http\Middleware\EnsureUserIsAdmin::class)
        ->post(route('admin.clients.store'), [
            'name' => 'Owned Client',
            'agency_id' => $agency->id,
        ])
        ->assertRedirect(route('admin.clients.index'));

    $this->assertDatabaseHas('clients', ['name' => 'Owned Client', 'agency_id' => $agency->id]);
});

it('non-admin with can_manage_clients cannot store a client for an agency they do not belong to', function () {
    $role = Role::create([
        'name' => 'Client Manager',
        'permissions' => [
            'is_admin' => false,
            'can_manage_clients' => true,
        ],
    ]);
    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $role->id;
    $user->save();

    $ownAgency = Agency::factory()->create();
    $user->agencies()->attach($ownAgency->id, ['access_all_clients' => true]);

    $foreignAgency = Agency::factory()->create();

    $this->actingAs($user)
        ->withoutMiddleware(\App\Http\Middleware\EnsureUserIsAdmin::class)
        ->post(route('admin.clients.store'), [
            'name' => 'Stolen Client',
            'agency_id' => $foreignAgency->id,
        ])
        ->assertSessionHasErrors('agency_id');

    $this->assertDatabaseMissing('clients', ['name' => 'Stolen Client']);
});

// ---------------------------------------------------------------------------
// UpdateClientRequest — agency_id enforcement
// ---------------------------------------------------------------------------

it('rejects update without agency_id (422 validation error)', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $client = Client::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.clients.update', $client), ['name' => 'New Name'])
        ->assertSessionHasErrors('agency_id');
});

it('rejects update with a non-existent agency_id', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $client = Client::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.clients.update', $client), [
            'name' => 'New Name',
            'agency_id' => 99999,
        ])
        ->assertSessionHasErrors('agency_id');
});

it('admin can update a client and move it to any valid agency', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $newAgency = Agency::factory()->create();
    $client = Client::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.clients.update', $client), [
            'name' => 'Moved Client',
            'agency_id' => $newAgency->id,
        ])
        ->assertRedirect(route('admin.clients.index'));

    $this->assertDatabaseHas('clients', [
        'id' => $client->id,
        'name' => 'Moved Client',
        'agency_id' => $newAgency->id,
    ]);
});

it('non-admin with can_manage_clients can update a client within their own agency', function () {
    $role = Role::create([
        'name' => 'Client Manager',
        'permissions' => [
            'is_admin' => false,
            'can_manage_clients' => true,
        ],
    ]);
    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $role->id;
    $user->save();

    $agency = Agency::factory()->create();
    $user->agencies()->attach($agency->id, ['access_all_clients' => true]);

    $client = Client::factory()->create(['agency_id' => $agency->id]);

    // Bypass admin policy so we can test the FormRequest's Rule::in guard.
    \Illuminate\Support\Facades\Gate::before(fn () => true);

    $this->actingAs($user)
        ->withoutMiddleware(\App\Http\Middleware\EnsureUserIsAdmin::class)
        ->put(route('admin.clients.update', $client), [
            'name' => 'Updated Own Client',
            'agency_id' => $agency->id,
        ])
        ->assertRedirect(route('admin.clients.index'));

    $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'Updated Own Client']);
});

it('non-admin with can_manage_clients cannot move a client to a foreign agency', function () {
    $role = Role::create([
        'name' => 'Client Manager',
        'permissions' => [
            'is_admin' => false,
            'can_manage_clients' => true,
        ],
    ]);
    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $role->id;
    $user->save();

    $ownAgency = Agency::factory()->create();
    $user->agencies()->attach($ownAgency->id, ['access_all_clients' => true]);

    $foreignAgency = Agency::factory()->create();
    $client = Client::factory()->create(['agency_id' => $ownAgency->id]);

    $this->actingAs($user)
        ->withoutMiddleware(\App\Http\Middleware\EnsureUserIsAdmin::class)
        ->put(route('admin.clients.update', $client), [
            'name' => 'Hijack Client',
            'agency_id' => $foreignAgency->id,
        ])
        ->assertSessionHasErrors('agency_id');

    // client must remain in original agency
    $this->assertDatabaseHas('clients', [
        'id' => $client->id,
        'agency_id' => $ownAgency->id,
    ]);
});
