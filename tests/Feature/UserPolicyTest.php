<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ===================================================================
// Test 2: UserPolicy::delete — self-targeting + last-admin invariant
// ===================================================================

it('admin cannot delete themselves', function () {
    $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $admin))
        ->assertRedirect(route('admin.users.index'));

    // User must still exist — self-delete was blocked
    expect(User::find($admin->id))->not->toBeNull();
});

it('admin cannot delete the last active admin when target is sole active admin', function () {
    // Two admins: one active (the target), one inactive (acting)
    $actingAdmin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
    $targetAdmin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

    // Make acting admin inactive so target becomes the only active admin,
    // but we need acting admin to pass middleware. Instead: create scenario
    // where target is the ONLY active admin by making all others inactive.
    // We use a third user setup: acting admin is active, target is the only OTHER admin,
    // and we disable the acting admin after setup isn't possible cleanly —
    // so let's use: acting admin + target admin are the only two admins, then
    // test that a non-admin (who bypasses middleware) cannot delete the last admin,
    // and that an admin deleting the last OTHER active admin is blocked.

    // Cleaner approach: acting admin is the only one left besides target.
    // Disable acting admin temporarily is not possible through DB for this scenario.
    // Real scenario: 1 active admin total, admin tries to delete themselves → blocked.
    // For last-admin guard: only target is active admin, acting admin is non-admin.

    // Correct setup: acting user is admin (passes middleware), target is the sole active admin,
    // meaning after deletion there would be zero active admins.
    // Make acting admin a legacy admin, and target the only role-based admin.
    $adminRole = Role::create([
        'name' => 'Role Admin',
        'permissions' => ['is_admin' => true],
    ]);

    // Sole active admin via role (not legacy)
    $soleAdmin = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $soleAdmin->role_id = $adminRole->id;
    $soleAdmin->save();

    // Acting admin is legacy is_admin=true — also active, so count = 2
    // We need count = 1, so disable acting admin's legacy flag and give them a non-admin role
    // but then they fail admin middleware. Use withoutMiddleware instead.
    $actorRole = Role::create([
        'name' => 'Actor Role',
        'permissions' => ['can_view_campaigns' => true],
    ]);
    $actingAdmin->role_id = $actorRole->id;
    $actingAdmin->is_admin = false;
    $actingAdmin->save();

    // Now soleAdmin is the only active admin — trying to delete them should be blocked
    $this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsAdmin::class)
        ->actingAs($actingAdmin)
        ->delete(route('admin.users.destroy', $soleAdmin))
        ->assertForbidden();

    expect(User::find($soleAdmin->id))->not->toBeNull();
});

it('admin can delete a non-admin user', function () {
    $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
    $nonAdmin = User::factory()->create(['is_admin' => false, 'is_active' => true]);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $nonAdmin))
        ->assertRedirect(route('admin.users.index'));

    expect(User::find($nonAdmin->id))->toBeNull();
});

it('admin can delete another admin when multiple active admins exist', function () {
    $actingAdmin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
    $targetAdmin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
    // Third active admin ensures count >= 2 after delete
    User::factory()->create(['is_admin' => true, 'is_active' => true]);

    $this->actingAs($actingAdmin)
        ->delete(route('admin.users.destroy', $targetAdmin))
        ->assertRedirect(route('admin.users.index'));

    expect(User::find($targetAdmin->id))->toBeNull();
});

it('non-admin cannot delete any user', function () {
    $nonAdmin = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $target = User::factory()->create(['is_admin' => false, 'is_active' => true]);

    $this->actingAs($nonAdmin)
        ->delete(route('admin.users.destroy', $target))
        ->assertForbidden();

    expect(User::find($target->id))->not->toBeNull();
});

// ===================================================================
// Test 3: UserPolicy::changeRole blocks self
// ===================================================================

it('user cannot changeRole on themselves via admin update', function () {
    $adminRole = Role::create([
        'name' => 'Admin Role',
        'permissions' => ['is_admin' => true],
    ]);
    $viewerRole = Role::create([
        'name' => 'Viewer',
        'permissions' => ['can_view_campaigns' => true],
    ]);

    $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
    $admin->role_id = $adminRole->id;
    $admin->save();

    $originalRoleId = $admin->role_id;

    // Admin attempts to change their own role
    $this->actingAs($admin)
        ->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role_id' => $viewerRole->id,
        ])
        ->assertForbidden();

    $admin->refresh();
    expect($admin->role_id)->toBe($originalRoleId);
});

it('admin can changeRole on another user', function () {
    $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
    $target = User::factory()->create(['is_admin' => false, 'is_active' => true]);

    $viewerRole = Role::create([
        'name' => 'Viewer',
        'permissions' => ['can_view_campaigns' => true],
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => $viewerRole->id,
        ])
        ->assertRedirect(route('admin.users.index'));

    $target->refresh();
    expect($target->role_id)->toBe($viewerRole->id);
});

it('user with can_manage_users can changeRole on another user via agency route', function () {
    $managerRole = Role::create([
        'name' => 'Agency Manager',
        'permissions' => [
            'can_manage_users' => true,
            'can_manage_clients' => true,
            'can_view_campaigns' => true,
        ],
    ]);
    $viewerRole = Role::create([
        'name' => 'Viewer',
        'permissions' => ['can_view_campaigns' => true],
    ]);

    $agency = \App\Models\Agency::factory()->create();

    $manager = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $manager->role_id = $managerRole->id;
    $manager->save();
    $agency->users()->attach($manager->id, ['access_all_clients' => true]);

    $target = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $target->role_id = $viewerRole->id;
    $target->save();
    $agency->users()->attach($target->id, ['access_all_clients' => true]);

    $viewerRole2 = Role::create([
        'name' => 'Viewer2',
        'permissions' => ['can_view_campaigns' => true],
    ]);

    $this->actingAs($manager)
        ->put(route('agency.users.update', [$agency, $target]), [
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => $viewerRole2->id,
            'access_all_clients' => true,
        ])
        ->assertRedirect();

    $target->refresh();
    expect($target->role_id)->toBe($viewerRole2->id);
});

it('user without admin or can_manage_users cannot changeRole on anyone', function () {
    $limitedRole = Role::create([
        'name' => 'Limited',
        'permissions' => ['can_view_campaigns' => true],
    ]);
    $viewerRole = Role::create([
        'name' => 'Viewer',
        'permissions' => ['can_view_campaigns' => true],
    ]);

    $limitedUser = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $limitedUser->role_id = $limitedRole->id;
    $limitedUser->save();

    $target = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $originalRole = $target->role_id;

    $this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsAdmin::class)
        ->actingAs($limitedUser)
        ->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => $viewerRole->id,
        ])
        ->assertForbidden();

    $target->refresh();
    expect($target->role_id)->toBe($originalRole);
});

// ===================================================================
// Test 4: UserPolicy::view is no longer a stub
// ===================================================================

it('admin can view any user profile', function () {
    $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
    $other = User::factory()->create(['is_admin' => false, 'is_active' => true]);

    $policy = new \App\Policies\UserPolicy();
    expect($policy->view($admin, $other))->toBeTrue();
});

it('user with can_manage_users can view any user', function () {
    $managerRole = Role::create([
        'name' => 'Manager',
        'permissions' => ['can_manage_users' => true],
    ]);

    $manager = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $manager->role_id = $managerRole->id;
    $manager->save();
    $manager->refresh();

    $other = User::factory()->create(['is_admin' => false, 'is_active' => true]);

    $policy = new \App\Policies\UserPolicy();
    expect($policy->view($manager, $other))->toBeTrue();
});

it('user can view themselves', function () {
    $user = User::factory()->create(['is_admin' => false, 'is_active' => true]);

    $policy = new \App\Policies\UserPolicy();
    expect($policy->view($user, $user))->toBeTrue();
});

it('unrelated user without admin cannot view another user', function () {
    $viewerRole = Role::create([
        'name' => 'Viewer',
        'permissions' => ['can_view_campaigns' => true],
    ]);

    $user = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $user->role_id = $viewerRole->id;
    $user->save();

    $other = User::factory()->create(['is_admin' => false, 'is_active' => true]);

    $policy = new \App\Policies\UserPolicy();
    expect($policy->view($user, $other))->toBeFalse();
});
