<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('strips unknown permission keys on role creation', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.roles.store'), [
            'name' => 'Filtered Role',
            'permissions' => [
                'is_admin' => true,
                'unknown_key' => true,
                'super_hack' => true,
                'inject_field' => true,
            ],
        ])
        ->assertRedirect(route('admin.roles.index'));

    $role = Role::where('name', 'Filtered Role')->firstOrFail();

    // Known key should be present
    expect($role->permissions)->toHaveKey('is_admin');
    expect($role->permissions['is_admin'])->toBeTrue();

    // Unknown keys must be stripped
    expect($role->permissions)->not->toHaveKey('unknown_key');
    expect($role->permissions)->not->toHaveKey('super_hack');
    expect($role->permissions)->not->toHaveKey('inject_field');
});

it('only stores permissions that are in availablePermissions on creation', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.roles.store'), [
            'name' => 'Known Only Role',
            'permissions' => [
                'can_view_campaigns' => true,
                'can_view_budget' => true,
                'evil_permission' => true,
                '__proto__' => true,
                'constructor' => true,
            ],
        ])
        ->assertRedirect(route('admin.roles.index'));

    $role = Role::where('name', 'Known Only Role')->firstOrFail();
    $allowedKeys = array_keys(Role::availablePermissions());

    foreach (array_keys($role->permissions ?? []) as $key) {
        expect($allowedKeys)->toContain($key);
    }
});

it('strips unknown permission keys on role update', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $role = Role::create([
        'name' => 'Updatable Role',
        'permissions' => ['can_view_campaigns' => true],
        'is_protected' => false,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.roles.update', $role), [
            'name' => 'Updatable Role',
            'permissions' => [
                'can_view_campaigns' => true,
                'malicious_key' => true,
                'root_access' => true,
            ],
        ])
        ->assertRedirect(route('admin.roles.index'));

    $role->refresh();

    expect($role->permissions)->toHaveKey('can_view_campaigns');
    expect($role->permissions)->not->toHaveKey('malicious_key');
    expect($role->permissions)->not->toHaveKey('root_access');
});

it('accepts all known permission keys without stripping', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $knownPermissions = collect(Role::availablePermissions())
        ->keys()
        ->mapWithKeys(fn ($key) => [$key => true])
        ->toArray();

    $this->actingAs($admin)
        ->post(route('admin.roles.store'), [
            'name' => 'Full Permissions Role',
            'permissions' => $knownPermissions,
        ])
        ->assertRedirect(route('admin.roles.index'));

    $role = Role::where('name', 'Full Permissions Role')->firstOrFail();

    foreach (array_keys($knownPermissions) as $key) {
        expect($role->permissions)->toHaveKey($key);
    }
});

it('validates that role name is required on creation', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.roles.store'), [
            'permissions' => ['can_view_campaigns' => true],
        ])
        ->assertSessionHasErrors('name');
});

it('validates that role name is unique on creation', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Role::create(['name' => 'Existing Role', 'permissions' => []]);

    $this->actingAs($admin)
        ->post(route('admin.roles.store'), [
            'name' => 'Existing Role',
            'permissions' => [],
        ])
        ->assertSessionHasErrors('name');
});
