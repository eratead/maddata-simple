<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ===================================================================
// Test 6: Privilege escalation guard in UserController
// ===================================================================

it('user with only can_manage_users cannot create a user with can_view_budget role', function () {
    $managerRole = Role::create([
        'name' => 'Manager No Budget',
        'permissions' => [
            'can_manage_users' => true,
            'can_view_campaigns' => true,
        ],
    ]);

    $budgetRole = Role::create([
        'name' => 'Budget Role',
        'permissions' => ['can_view_budget' => true],
    ]);

    $manager = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $manager->role_id = $managerRole->id;
    $manager->save();

    $this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsAdmin::class)
        ->actingAs($manager)
        ->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'newescalated@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'role_id' => $budgetRole->id,
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'newescalated@example.com']);
});

it('user with only can_manage_users cannot update another user to a can_view_budget role', function () {
    $managerRole = Role::create([
        'name' => 'Manager No Budget',
        'permissions' => [
            'can_manage_users' => true,
            'can_view_campaigns' => true,
        ],
    ]);

    $budgetRole = Role::create([
        'name' => 'Budget Role',
        'permissions' => ['can_view_budget' => true],
    ]);

    $manager = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $manager->role_id = $managerRole->id;
    $manager->save();

    $target = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $originalRoleId = $target->role_id;

    $this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsAdmin::class)
        ->actingAs($manager)
        ->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => $budgetRole->id,
        ])
        ->assertForbidden();

    $target->refresh();
    expect($target->role_id)->toBe($originalRoleId);
});

it('admin acting user can assign any role including can_view_budget', function () {
    $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

    $budgetRole = Role::create([
        'name' => 'Budget Role',
        'permissions' => ['can_view_budget' => true],
    ]);

    $target = User::factory()->create(['is_admin' => false, 'is_active' => true]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => $budgetRole->id,
        ])
        ->assertRedirect(route('admin.users.index'));

    $target->refresh();
    expect($target->role_id)->toBe($budgetRole->id);
});

it('agency manager with can_view_budget can assign a can_view_budget role but not is_admin', function () {
    // The privilege-escalation guard for can_manage_users actors is exercised via the
    // agency route (AgencyUserController), where UserPolicy::update is not the gate.
    $managerRole = Role::create([
        'name' => 'Manager With Budget',
        'permissions' => [
            'can_manage_users' => true,
            'can_manage_clients' => true,
            'can_view_campaigns' => true,
            'can_view_budget' => true,
        ],
    ]);

    $budgetRole = Role::create([
        'name' => 'Budget Viewer',
        'permissions' => ['can_view_budget' => true, 'can_view_campaigns' => true],
    ]);

    $adminRole = Role::create([
        'name' => 'Full Admin',
        'permissions' => ['is_admin' => true],
    ]);

    $agency = \App\Models\Agency::factory()->create();

    $manager = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $manager->role_id = $managerRole->id;
    $manager->save();
    $agency->users()->attach($manager->id, ['access_all_clients' => true]);

    $target = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $viewerRole = Role::create([
        'name' => 'Viewer',
        'permissions' => ['can_view_campaigns' => true],
    ]);
    $target->role_id = $viewerRole->id;
    $target->save();
    $agency->users()->attach($target->id, ['access_all_clients' => true]);

    // CAN assign budget role — manager holds can_view_budget, so no escalation
    $this->actingAs($manager)
        ->put(route('agency.users.update', [$agency, $target]), [
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => $budgetRole->id,
            'access_all_clients' => true,
        ])
        ->assertRedirect();

    $target->refresh();
    expect($target->role_id)->toBe($budgetRole->id);

    // CANNOT assign admin role — manager does not hold is_admin
    $this->actingAs($manager)
        ->put(route('agency.users.update', [$agency, $target]), [
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => $adminRole->id,
            'access_all_clients' => true,
        ])
        ->assertForbidden();

    $target->refresh();
    // Role remains as budgetRole — admin escalation was blocked
    expect($target->role_id)->toBe($budgetRole->id);
});
