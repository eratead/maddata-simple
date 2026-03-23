<?php

use App\Models\Agency;
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
});

it('allows admin to create agency without manager', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.agencies.store'), [
            'name' => 'Agency Without Manager',
        ])
        ->assertRedirect(route('admin.agencies.index'));

    $this->assertDatabaseHas('agencies', ['name' => 'Agency Without Manager']);

    // No additional user should be created (only the admin exists)
    expect(User::where('email', '!=', $this->admin->email)->count())->toBe(0);
});

it('allows admin to create agency with manager fields', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.agencies.store'), [
            'name' => 'Agency With Manager',
            'manager_name' => 'John Manager',
            'manager_email' => 'john@agency.com',
            'manager_password' => 'Password1',
        ])
        ->assertRedirect(route('admin.agencies.index'));

    $this->assertDatabaseHas('agencies', ['name' => 'Agency With Manager']);
    $this->assertDatabaseHas('users', [
        'name' => 'John Manager',
        'email' => 'john@agency.com',
    ]);
});

it('creates manager user with correct role and agency pivot', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.agencies.store'), [
            'name' => 'Pivot Test Agency',
            'manager_name' => 'Jane Manager',
            'manager_email' => 'jane@agency.com',
            'manager_password' => 'Password1',
        ]);

    $agency = Agency::where('name', 'Pivot Test Agency')->first();
    $manager = User::where('email', 'jane@agency.com')->first();

    expect($manager)->not->toBeNull();
    expect($agency)->not->toBeNull();

    // Check role has can_manage_users
    $role = $manager->userRole;
    expect($role)->not->toBeNull();
    expect($role->hasPermission('can_manage_users'))->toBeTrue();
    expect($role->hasPermission('can_manage_clients'))->toBeTrue();

    // Check pivot: user is attached to agency with access_all_clients=true
    $this->assertDatabaseHas('agency_user', [
        'agency_id' => $agency->id,
        'user_id' => $manager->id,
        'access_all_clients' => true,
    ]);
});

it('validates manager_email and manager_password are required when manager_name is provided', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.agencies.store'), [
            'name' => 'Validation Test Agency',
            'manager_name' => 'Incomplete Manager',
        ])
        ->assertSessionHasErrors(['manager_email', 'manager_password']);

    $this->assertDatabaseMissing('agencies', ['name' => 'Validation Test Agency']);
});
