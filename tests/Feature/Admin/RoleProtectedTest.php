<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('admin cannot update a protected role', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $protectedRole = Role::create([
        'name' => 'Super Admin',
        'permissions' => ['is_admin' => true],
        'is_protected' => true,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.roles.update', $protectedRole), [
            'name' => 'Hacked Admin',
            'permissions' => ['is_admin' => true],
        ])
        ->assertForbidden();

    // Role name unchanged in database
    $this->assertDatabaseHas('roles', ['id' => $protectedRole->id, 'name' => 'Super Admin']);
});

it('admin cannot delete a protected role', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $protectedRole = Role::create([
        'name' => 'Protected Role',
        'permissions' => [],
        'is_protected' => true,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.roles.destroy', $protectedRole))
        ->assertForbidden();

    $this->assertDatabaseHas('roles', ['id' => $protectedRole->id]);
});

it('admin can update a non-protected role', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $role = Role::create([
        'name' => 'Viewer',
        'permissions' => ['can_view_campaigns' => true],
        'is_protected' => false,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.roles.update', $role), [
            'name' => 'Senior Viewer',
            'permissions' => ['can_view_campaigns' => true],
        ])
        ->assertRedirect(route('admin.roles.index'));

    $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'Senior Viewer']);
});

it('admin can delete a non-protected role with no assigned users', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $role = Role::create([
        'name' => 'Temp Role',
        'permissions' => [],
        'is_protected' => false,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.roles.destroy', $role))
        ->assertRedirect(route('admin.roles.index'));

    $this->assertDatabaseMissing('roles', ['id' => $role->id]);
});

it('non-admin cannot access admin role routes', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $role = Role::create([
        'name' => 'Some Role',
        'permissions' => [],
        'is_protected' => false,
    ]);

    $this->actingAs($user)
        ->put(route('admin.roles.update', $role), [
            'name' => 'Hijacked',
            'permissions' => [],
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('admin.roles.destroy', $role))
        ->assertForbidden();
});
