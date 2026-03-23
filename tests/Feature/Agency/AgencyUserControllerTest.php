<?php

use App\Models\Agency;
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
        'permissions' => [
            'can_view_campaigns' => true,
        ],
    ]);

    $this->agency = Agency::factory()->create();

    $this->manager = User::factory()->create(['is_admin' => false]);
    $this->manager->role_id = $this->managerRole->id;
    $this->manager->save();
    $this->agency->users()->attach($this->manager->id, ['access_all_clients' => true]);
});

// --- Index ---

it('allows manager to view user index for their agency', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $this->viewerRole->id;
    $user->save();
    $this->agency->users()->attach($user->id, ['access_all_clients' => true]);

    $this->actingAs($this->manager)
        ->get(route('agency.users.index', $this->agency))
        ->assertOk()
        ->assertSee($user->name);
});

// --- Create ---

it('allows manager to view create user form', function () {
    $this->actingAs($this->manager)
        ->get(route('agency.users.create', $this->agency))
        ->assertOk();
});

// --- Store ---

it('allows manager to create a user in their agency', function () {
    $this->actingAs($this->manager)
        ->post(route('agency.users.store', $this->agency), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'Password1',
            'role_id' => $this->viewerRole->id,
            'access_all_clients' => true,
        ])
        ->assertRedirect(route('agency.users.index', $this->agency));

    $this->assertDatabaseHas('users', [
        'email' => 'newuser@example.com',
        'name' => 'New User',
    ]);
});

it('attaches created user to the agency pivot', function () {
    $this->actingAs($this->manager)
        ->post(route('agency.users.store', $this->agency), [
            'name' => 'Pivot User',
            'email' => 'pivotuser@example.com',
            'password' => 'Password1',
            'role_id' => $this->viewerRole->id,
            'access_all_clients' => true,
        ]);

    $user = User::where('email', 'pivotuser@example.com')->first();
    expect($user)->not->toBeNull();

    $this->assertDatabaseHas('agency_user', [
        'agency_id' => $this->agency->id,
        'user_id' => $user->id,
        'access_all_clients' => true,
    ]);
});

// --- Edit ---

it('allows manager to view edit form for a user in their agency', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $this->viewerRole->id;
    $user->save();
    $this->agency->users()->attach($user->id, ['access_all_clients' => true]);

    $this->actingAs($this->manager)
        ->get(route('agency.users.edit', [$this->agency, $user]))
        ->assertOk()
        ->assertSee($user->name);
});

// --- Update ---

it('allows manager to update a user role and client access', function () {
    $editorRole = Role::create([
        'name' => 'Editor',
        'permissions' => [
            'can_view_campaigns' => true,
            'can_edit_campaigns' => true,
        ],
    ]);

    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $this->viewerRole->id;
    $user->save();
    $this->agency->users()->attach($user->id, ['access_all_clients' => true]);

    $client = Client::factory()->create(['agency_id' => $this->agency->id]);

    $this->actingAs($this->manager)
        ->put(route('agency.users.update', [$this->agency, $user]), [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $editorRole->id,
            'access_all_clients' => false,
            'clients' => [$client->id],
        ])
        ->assertRedirect(route('agency.users.index', $this->agency));

    $user->refresh();
    expect($user->role_id)->toBe($editorRole->id);

    $this->assertDatabaseHas('agency_user', [
        'agency_id' => $this->agency->id,
        'user_id' => $user->id,
        'access_all_clients' => false,
    ]);
});

// --- Destroy (disable) ---

it('allows manager to disable a user via destroy', function () {
    $user = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $user->role_id = $this->viewerRole->id;
    $user->save();
    $this->agency->users()->attach($user->id, ['access_all_clients' => true]);

    $this->actingAs($this->manager)
        ->delete(route('agency.users.destroy', [$this->agency, $user]))
        ->assertRedirect(route('agency.users.index', $this->agency));

    $user->refresh();
    expect($user->is_active)->toBeFalse();

    // Pivot should still exist (not detached)
    $this->assertDatabaseHas('agency_user', [
        'agency_id' => $this->agency->id,
        'user_id' => $user->id,
    ]);
});

// --- Re-enable ---

it('allows manager to re-enable a disabled user via update', function () {
    $user = User::factory()->create(['is_admin' => false, 'is_active' => false]);
    $user->role_id = $this->viewerRole->id;
    $user->save();
    $this->agency->users()->attach($user->id, ['access_all_clients' => true]);

    $this->actingAs($this->manager)
        ->put(route('agency.users.update', [$this->agency, $user]), [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $this->viewerRole->id,
            'access_all_clients' => true,
            'is_active' => true,
        ])
        ->assertRedirect(route('agency.users.index', $this->agency));

    $user->refresh();
    expect($user->is_active)->toBeTrue();
});

// --- Authorization: non-manager ---

it('denies non-manager access to agency user routes', function () {
    $regularUser = User::factory()->create(['is_admin' => false]);
    $regularUser->role_id = $this->viewerRole->id;
    $regularUser->save();
    $this->agency->users()->attach($regularUser->id, ['access_all_clients' => true]);

    $this->actingAs($regularUser)
        ->get(route('agency.users.index', $this->agency))
        ->assertForbidden();
});

// --- Authorization: wrong agency ---

it('denies manager access to another agency user routes', function () {
    $otherAgency = Agency::factory()->create();

    $this->actingAs($this->manager)
        ->get(route('agency.users.index', $otherAgency))
        ->assertForbidden();
});
