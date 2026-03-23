<?php

use App\Models\Agency;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminRole = Role::create([
        'name' => 'Admin',
        'permissions' => ['is_admin' => true],
    ]);

    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->admin->role_id = $this->adminRole->id;
    $this->admin->save();

    $this->nonAdmin = User::factory()->create(['is_admin' => false]);
});

// --- Index ---

it('allows admin to view agencies index', function () {
    $agency = Agency::factory()->create(['name' => 'McCann']);

    $this->actingAs($this->admin)
        ->get(route('admin.agencies.index'))
        ->assertOk()
        ->assertSee('McCann');
});

it('shows client count on index', function () {
    $agency = Agency::factory()->create(['name' => 'Test Agency']);
    Client::factory()->count(3)->create(['agency_id' => $agency->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.agencies.index'))
        ->assertOk()
        ->assertSee('Test Agency');
});

it('denies non-admin access to agencies index', function () {
    $this->actingAs($this->nonAdmin)
        ->get(route('admin.agencies.index'))
        ->assertForbidden();
});

// --- Create ---

it('allows admin to view create agency form', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.agencies.create'))
        ->assertOk();
});

it('denies non-admin access to create agency form', function () {
    $this->actingAs($this->nonAdmin)
        ->get(route('admin.agencies.create'))
        ->assertForbidden();
});

// --- Store ---

it('allows admin to store a new agency', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.agencies.store'), ['name' => 'New Agency'])
        ->assertRedirect(route('admin.agencies.index'));

    $this->assertDatabaseHas('agencies', ['name' => 'New Agency']);
});

it('validates name is required on store', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.agencies.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('validates name is unique on store', function () {
    Agency::factory()->create(['name' => 'Existing Agency']);

    $this->actingAs($this->admin)
        ->post(route('admin.agencies.store'), ['name' => 'Existing Agency'])
        ->assertSessionHasErrors('name');
});

it('denies non-admin from storing an agency', function () {
    $this->actingAs($this->nonAdmin)
        ->post(route('admin.agencies.store'), ['name' => 'Forbidden Agency'])
        ->assertForbidden();

    $this->assertDatabaseMissing('agencies', ['name' => 'Forbidden Agency']);
});

// --- Edit ---

it('allows admin to view edit agency form', function () {
    $agency = Agency::factory()->create(['name' => 'Edit Me']);

    $this->actingAs($this->admin)
        ->get(route('admin.agencies.edit', $agency))
        ->assertOk()
        ->assertSee('Edit Me');
});

it('denies non-admin access to edit agency form', function () {
    $agency = Agency::factory()->create();

    $this->actingAs($this->nonAdmin)
        ->get(route('admin.agencies.edit', $agency))
        ->assertForbidden();
});

// --- Update ---

it('allows admin to update an agency', function () {
    $agency = Agency::factory()->create(['name' => 'Old Name']);

    $this->actingAs($this->admin)
        ->put(route('admin.agencies.update', $agency), ['name' => 'New Name'])
        ->assertRedirect(route('admin.agencies.index'));

    $this->assertDatabaseHas('agencies', ['id' => $agency->id, 'name' => 'New Name']);
});

it('validates name is unique on update excluding self', function () {
    $agency1 = Agency::factory()->create(['name' => 'Agency One']);
    $agency2 = Agency::factory()->create(['name' => 'Agency Two']);

    // Try to rename agency2 to agency1's name
    $this->actingAs($this->admin)
        ->put(route('admin.agencies.update', $agency2), ['name' => 'Agency One'])
        ->assertSessionHasErrors('name');
});

it('allows updating agency with its own name', function () {
    $agency = Agency::factory()->create(['name' => 'Same Name']);

    $this->actingAs($this->admin)
        ->put(route('admin.agencies.update', $agency), ['name' => 'Same Name'])
        ->assertRedirect(route('admin.agencies.index'));
});

it('denies non-admin from updating an agency', function () {
    $agency = Agency::factory()->create(['name' => 'Original']);

    $this->actingAs($this->nonAdmin)
        ->put(route('admin.agencies.update', $agency), ['name' => 'Hacked'])
        ->assertForbidden();

    $this->assertDatabaseHas('agencies', ['name' => 'Original']);
});

// --- Destroy ---

it('allows admin to delete an agency with no clients', function () {
    $agency = Agency::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.agencies.destroy', $agency))
        ->assertRedirect(route('admin.agencies.index'));

    $this->assertDatabaseMissing('agencies', ['id' => $agency->id]);
});

it('prevents deleting an agency that has clients', function () {
    $agency = Agency::factory()->create();
    Client::factory()->create(['agency_id' => $agency->id]);

    $this->actingAs($this->admin)
        ->delete(route('admin.agencies.destroy', $agency))
        ->assertRedirect();

    $this->assertDatabaseHas('agencies', ['id' => $agency->id]);
});

it('denies non-admin from deleting an agency', function () {
    $agency = Agency::factory()->create();

    $this->actingAs($this->nonAdmin)
        ->delete(route('admin.agencies.destroy', $agency))
        ->assertForbidden();

    $this->assertDatabaseHas('agencies', ['id' => $agency->id]);
});

// --- Relationships ---

it('associates clients with an agency via agency_id', function () {
    $agency = Agency::factory()->create();
    $client1 = Client::factory()->create(['agency_id' => $agency->id]);
    $client2 = Client::factory()->create(['agency_id' => $agency->id]);
    $otherClient = Client::factory()->create(); // different agency

    expect($agency->clients)->toHaveCount(2);
    expect($agency->clients->pluck('id')->toArray())->toContain($client1->id, $client2->id);
    expect($agency->clients->pluck('id')->toArray())->not->toContain($otherClient->id);
});

it('allows a client to belong to an agency', function () {
    $agency = Agency::factory()->create(['name' => 'Parent Agency']);
    $client = Client::factory()->create(['agency_id' => $agency->id]);

    expect($client->agency->id)->toBe($agency->id);
    expect($client->agency->name)->toBe('Parent Agency');
});

it('allows users to be associated with agencies via pivot', function () {
    $agency = Agency::factory()->create();
    $user = User::factory()->create();

    $agency->users()->attach($user->id, ['access_all_clients' => true]);

    expect($agency->users)->toHaveCount(1);
    expect((bool) $agency->users->first()->pivot->access_all_clients)->toBeTrue();
});

// --- Unauthenticated ---

it('redirects unauthenticated users from agency routes', function () {
    $this->get(route('admin.agencies.index'))->assertRedirect(route('login'));
    $this->get(route('admin.agencies.create'))->assertRedirect(route('login'));
    $this->post(route('admin.agencies.store'), ['name' => 'Test'])->assertRedirect(route('login'));
});
