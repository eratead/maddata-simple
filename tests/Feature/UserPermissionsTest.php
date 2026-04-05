<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ===================================================================
// Test 1: hasPermission() respects is_active
// ===================================================================

it('returns false for legacy is_admin user when is_active is false', function () {
    $user = User::factory()->create(['is_admin' => true, 'is_active' => false]);

    expect($user->hasPermission('is_admin'))->toBeFalse();
});

it('returns true for legacy is_admin user when is_active is true', function () {
    $user = User::factory()->create(['is_admin' => true, 'is_active' => true]);

    expect($user->hasPermission('is_admin'))->toBeTrue();
});

it('returns false for disabled user with role granting can_view_budget', function () {
    $role = Role::create([
        'name' => 'Budget Viewer',
        'permissions' => ['can_view_budget' => true],
    ]);

    $user = User::factory()->create(['is_admin' => false, 'is_active' => false]);
    $user->role_id = $role->id;
    $user->save();

    expect($user->hasPermission('can_view_budget'))->toBeFalse();
});

it('returns true for active user with role granting can_view_budget', function () {
    $role = Role::create([
        'name' => 'Budget Viewer',
        'permissions' => ['can_view_budget' => true],
    ]);

    $user = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $user->role_id = $role->id;
    $user->save();

    expect($user->hasPermission('can_view_budget'))->toBeTrue();
});

it('returns false for all permissions when is_active is false regardless of role', function () {
    $role = Role::create([
        'name' => 'Full Permissions',
        'permissions' => [
            'is_admin' => true,
            'can_view_campaigns' => true,
            'can_edit_campaigns' => true,
            'can_view_budget' => true,
            'can_manage_users' => true,
        ],
    ]);

    $user = User::factory()->create(['is_admin' => false, 'is_active' => false]);
    $user->role_id = $role->id;
    $user->save();

    expect($user->hasPermission('is_admin'))->toBeFalse();
    expect($user->hasPermission('can_view_campaigns'))->toBeFalse();
    expect($user->hasPermission('can_view_budget'))->toBeFalse();
    expect($user->hasPermission('can_manage_users'))->toBeFalse();
});

it('disabled legacy admin cannot access admin routes', function () {
    $user = User::factory()->create(['is_admin' => true, 'is_active' => false]);

    $this->actingAs($user)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});
