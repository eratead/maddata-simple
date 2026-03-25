<?php

use App\Models\Agency;
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

    $this->protectedRole = Role::create([
        'name' => 'Super Viewer',
        'permissions' => [
            'can_view_campaigns' => true,
        ],
        'is_protected' => true,
    ]);

    $this->agency = Agency::factory()->create();

    // Set up the agency manager
    $this->manager = User::factory()->create(['is_admin' => false]);
    $this->manager->role_id = $this->managerRole->id;
    $this->manager->save();
    $this->agency->users()->attach($this->manager->id, ['access_all_clients' => true]);

    // Set up a user with a protected role, attached to the same agency
    $this->protectedUser = User::factory()->create(['is_admin' => false]);
    $this->protectedUser->role_id = $this->protectedRole->id;
    $this->protectedUser->save();
    $this->agency->users()->attach($this->protectedUser->id, ['access_all_clients' => true]);
});

// --- Index: protected users hidden ---

it('hides protected-role users from agency user index', function () {
    $this->actingAs($this->manager)
        ->get(route('agency.users.index', $this->agency))
        ->assertOk()
        ->assertDontSee($this->protectedUser->name);
});

it('shows non-protected-role users on agency user index', function () {
    $normalUser = User::factory()->create(['is_admin' => false]);
    $normalUser->role_id = $this->viewerRole->id;
    $normalUser->save();
    $this->agency->users()->attach($normalUser->id, ['access_all_clients' => true]);

    $this->actingAs($this->manager)
        ->get(route('agency.users.index', $this->agency))
        ->assertOk()
        ->assertSee($normalUser->name);
});

// --- Edit: protected user returns 404 ---

it('returns 404 when agency manager tries to edit a protected-role user', function () {
    $this->actingAs($this->manager)
        ->get(route('agency.users.edit', [$this->agency, $this->protectedUser]))
        ->assertNotFound();
});

// --- Update: protected user returns 404 ---

it('returns 404 when agency manager tries to update a protected-role user', function () {
    $this->actingAs($this->manager)
        ->put(route('agency.users.update', [$this->agency, $this->protectedUser]), [
            'name' => 'Hacked Name',
            'email' => $this->protectedUser->email,
            'role_id' => $this->viewerRole->id,
            'access_all_clients' => true,
        ])
        ->assertNotFound();

    // Verify the user was not modified
    $this->protectedUser->refresh();
    expect($this->protectedUser->name)->not->toBe('Hacked Name');
    expect($this->protectedUser->role_id)->toBe($this->protectedRole->id);
});

// --- Destroy: protected user returns 404 ---

it('returns 404 when agency manager tries to destroy a protected-role user', function () {
    $this->actingAs($this->manager)
        ->delete(route('agency.users.destroy', [$this->agency, $this->protectedUser]))
        ->assertNotFound();

    // Verify the user was not disabled
    $this->protectedUser->refresh();
    expect($this->protectedUser->is_active)->toBeTrue();
});

// --- Create form: protected role excluded from assignable roles ---

it('excludes protected roles from the assignable roles dropdown', function () {
    $response = $this->actingAs($this->manager)
        ->get(route('agency.users.create', $this->agency))
        ->assertOk();

    $roles = $response->viewData('roles');

    // The protected role should not be in the list
    expect($roles->pluck('id')->toArray())->not->toContain($this->protectedRole->id);

    // The normal viewer role should be present
    expect($roles->pluck('id')->toArray())->toContain($this->viewerRole->id);
});

// --- Admin can still see protected-role users ---

it('allows admin to see protected-role users on admin user index', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee($this->protectedUser->name);
});

it('allows admin to edit a protected-role user', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('admin.users.edit', $this->protectedUser))
        ->assertOk()
        ->assertSee($this->protectedUser->name);
});
