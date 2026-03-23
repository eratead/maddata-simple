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

    $this->agency = Agency::factory()->create();

    $this->manager = User::factory()->create(['is_admin' => false]);
    $this->manager->role_id = $this->managerRole->id;
    $this->manager->save();
    $this->agency->users()->attach($this->manager->id, ['access_all_clients' => true]);
});

it('prevents manager from assigning a role with can_manage_users permission', function () {
    $subManagerRole = Role::create([
        'name' => 'Sub Manager',
        'permissions' => [
            'can_manage_users' => true,
            'can_view_campaigns' => true,
        ],
    ]);

    $this->actingAs($this->manager)
        ->post(route('agency.users.store', $this->agency), [
            'name' => 'Escalation User',
            'email' => 'escalation@example.com',
            'password' => 'Password1',
            'role_id' => $subManagerRole->id,
            'access_all_clients' => true,
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'escalation@example.com']);
});

it('prevents manager from assigning a role with is_admin permission', function () {
    $adminRole = Role::create([
        'name' => 'Admin Role',
        'permissions' => [
            'is_admin' => true,
        ],
    ]);

    $this->actingAs($this->manager)
        ->post(route('agency.users.store', $this->agency), [
            'name' => 'Admin Escalation',
            'email' => 'adminescalation@example.com',
            'password' => 'Password1',
            'role_id' => $adminRole->id,
            'access_all_clients' => true,
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'adminescalation@example.com']);
});

it('prevents manager from assigning a role with permissions they do not hold', function () {
    // Manager does NOT have can_upload_reports
    $uploaderRole = Role::create([
        'name' => 'Uploader',
        'permissions' => [
            'can_view_campaigns' => true,
            'can_upload_reports' => true,
        ],
    ]);

    $this->actingAs($this->manager)
        ->post(route('agency.users.store', $this->agency), [
            'name' => 'Uploader User',
            'email' => 'uploader@example.com',
            'password' => 'Password1',
            'role_id' => $uploaderRole->id,
            'access_all_clients' => true,
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'uploader@example.com']);
});

it('allows manager to assign a role with equal or fewer permissions', function () {
    $viewerRole = Role::create([
        'name' => 'Viewer',
        'permissions' => [
            'can_view_campaigns' => true,
        ],
    ]);

    $this->actingAs($this->manager)
        ->post(route('agency.users.store', $this->agency), [
            'name' => 'Valid User',
            'email' => 'validuser@example.com',
            'password' => 'Password1',
            'role_id' => $viewerRole->id,
            'access_all_clients' => true,
        ])
        ->assertRedirect(route('agency.users.index', $this->agency));

    $this->assertDatabaseHas('users', ['email' => 'validuser@example.com']);
});
