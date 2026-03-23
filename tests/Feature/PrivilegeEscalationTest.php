<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- UserController: Non-admin cannot create users ---

it('prevents non-admin user from creating users', function () {
    $nonAdmin = User::factory()->create(['is_admin' => false]);

    $this->actingAs($nonAdmin)
        ->post(route('admin.users.store'), [
            'name' => 'Hacker',
            'email' => 'hacker@example.com',
            'password' => 'Password1',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'hacker@example.com']);
});

// --- UserController: Admin without is_admin cannot assign admin role ---

it('prevents user without is_admin from assigning admin role', function () {
    // Limited role without is_admin permission
    $limitedRole = Role::create([
        'name' => 'Limited',
        'permissions' => ['can_edit_campaigns' => true, 'can_view_campaigns' => true],
    ]);

    // User does NOT have is_admin (legacy false), bypass admin middleware to test escalation check
    $limitedUser = User::factory()->create(['is_admin' => false]);
    $limitedUser->role_id = $limitedRole->id;
    $limitedUser->save();

    $adminRole = Role::create([
        'name' => 'Full Admin',
        'permissions' => ['is_admin' => true],
    ]);

    $this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsAdmin::class)
        ->actingAs($limitedUser)
        ->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'Password1A',
            'role_id' => $adminRole->id,
        ])
        ->assertForbidden();
});

// --- UserController: Admin WITH is_admin CAN assign admin roles ---

it('allows admin with is_admin permission to assign admin role', function () {
    $adminRole = Role::create([
        'name' => 'Full Admin',
        'permissions' => ['is_admin' => true, 'can_edit_campaigns' => true],
    ]);

    $admin = User::factory()->create(['is_admin' => true]);
    $admin->role_id = $adminRole->id;
    $admin->save();

    $targetRole = Role::create([
        'name' => 'Another Admin',
        'permissions' => ['is_admin' => true],
    ]);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Admin User',
            'email' => 'adminuser@example.com',
            'password' => 'Password1',
            'role_id' => $targetRole->id,
        ])
        ->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseHas('users', ['email' => 'adminuser@example.com']);
});

// --- UserController update: escalation prevention on update ---

it('prevents escalation when updating user role to admin', function () {
    $limitedRole = Role::create([
        'name' => 'Limited',
        'permissions' => ['can_view_campaigns' => true],
    ]);

    $limitedUser = User::factory()->create(['is_admin' => false]);
    $limitedUser->role_id = $limitedRole->id;
    $limitedUser->save();

    $targetUser = User::factory()->create(['is_admin' => false]);

    $adminRole = Role::create([
        'name' => 'Admin Role',
        'permissions' => ['is_admin' => true],
    ]);

    $this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsAdmin::class)
        ->actingAs($limitedUser)
        ->put(route('admin.users.update', $targetUser), [
            'name' => $targetUser->name,
            'email' => $targetUser->email,
            'role_id' => $adminRole->id,
        ])
        ->assertForbidden();
});

// --- RoleController: user cannot grant permissions they don't hold ---

it('prevents user from granting permissions they do not hold via role store', function () {
    // Manager role has edit + view but NOT can_view_budget
    // Note: we bypass admin middleware to test with a non-is_admin user,
    // because hasPermission() returns true for ALL perms when is_admin is true.
    $managerRole = Role::create([
        'name' => 'Manager',
        'permissions' => ['can_edit_campaigns' => true, 'can_view_campaigns' => true],
    ]);

    $manager = User::factory()->create(['is_admin' => false]);
    $manager->role_id = $managerRole->id;
    $manager->save();

    // Bypass admin middleware to reach preventPrivilegeEscalation()
    $this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsAdmin::class)
        ->actingAs($manager)
        ->post(route('admin.roles.store'), [
            'name' => 'Escalated Role',
            'permissions' => [
                'can_view_budget' => '1',
                'can_edit_campaigns' => '1',
            ],
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('roles', ['name' => 'Escalated Role']);
});

it('prevents user from granting permissions they do not hold via role update', function () {
    // Manager role has edit + view but NOT can_view_budget
    $managerRole = Role::create([
        'name' => 'Manager',
        'permissions' => ['can_edit_campaigns' => true, 'can_view_campaigns' => true],
    ]);

    $manager = User::factory()->create(['is_admin' => false]);
    $manager->role_id = $managerRole->id;
    $manager->save();

    $existingRole = Role::create([
        'name' => 'Basic Role',
        'permissions' => ['can_view_campaigns' => true],
    ]);

    // Bypass admin middleware to reach preventPrivilegeEscalation()
    $this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsAdmin::class)
        ->actingAs($manager)
        ->put(route('admin.roles.update', $existingRole), [
            'name' => 'Basic Role',
            'permissions' => [
                'can_view_budget' => '1',
                'can_view_campaigns' => '1',
            ],
        ])
        ->assertForbidden();

    $existingRole->refresh();
    expect($existingRole->hasPermission('can_view_budget'))->toBeFalse();
});

// --- RoleController: user CAN grant permissions they hold ---

it('allows user to grant permissions they hold via role store', function () {
    $adminRole = Role::create([
        'name' => 'Full Admin',
        'permissions' => ['is_admin' => true, 'can_edit_campaigns' => true, 'can_view_budget' => true],
    ]);

    $admin = User::factory()->create(['is_admin' => true]);
    $admin->role_id = $adminRole->id;
    $admin->save();

    $this->actingAs($admin)
        ->post(route('admin.roles.store'), [
            'name' => 'New Editor',
            'permissions' => [
                'can_edit_campaigns' => '1',
                'can_view_budget' => '1',
            ],
        ])
        ->assertRedirect(route('admin.roles.index'));

    $this->assertDatabaseHas('roles', ['name' => 'New Editor']);

    $newRole = Role::where('name', 'New Editor')->first();
    expect($newRole->hasPermission('can_edit_campaigns'))->toBeTrue();
    expect($newRole->hasPermission('can_view_budget'))->toBeTrue();
});

it('allows admin to grant is_admin permission via role store', function () {
    $adminRole = Role::create([
        'name' => 'Super Admin',
        'permissions' => ['is_admin' => true],
    ]);

    $admin = User::factory()->create(['is_admin' => true]);
    $admin->role_id = $adminRole->id;
    $admin->save();

    $this->actingAs($admin)
        ->post(route('admin.roles.store'), [
            'name' => 'Another Admin Role',
            'permissions' => [
                'is_admin' => '1',
            ],
        ])
        ->assertRedirect(route('admin.roles.index'));

    $this->assertDatabaseHas('roles', ['name' => 'Another Admin Role']);
});

// --- Non-admin cannot access role management at all ---

it('prevents non-admin from accessing role management', function () {
    $nonAdmin = User::factory()->create(['is_admin' => false]);

    $this->actingAs($nonAdmin)
        ->get(route('admin.roles.index'))
        ->assertForbidden();

    $this->actingAs($nonAdmin)
        ->post(route('admin.roles.store'), [
            'name' => 'Hacked Role',
            'permissions' => ['is_admin' => '1'],
        ])
        ->assertForbidden();
});

// --- Non-admin cannot access user management ---

it('prevents non-admin from accessing user management', function () {
    $nonAdmin = User::factory()->create(['is_admin' => false]);

    $this->actingAs($nonAdmin)
        ->get(route('admin.users.index'))
        ->assertForbidden();

    $this->actingAs($nonAdmin)
        ->get(route('admin.users.create'))
        ->assertForbidden();
});
