<?php

use App\Models\Agency;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('blocks manager user from being attached to a second agency', function () {
    $managerRole = Role::create([
        'name' => 'Agency Manager',
        'permissions' => [
            'can_manage_users' => true,
            'can_manage_clients' => true,
            'can_view_campaigns' => true,
        ],
    ]);

    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $managerRole->id;
    $user->save();

    $agency1 = Agency::factory()->create();
    $agency2 = Agency::factory()->create();

    // First attach is fine
    $agency1->users()->attach($user->id, ['access_all_clients' => true]);

    // Second attach should be blocked by validateSingleAgencyConstraint
    $response = null;
    try {
        User::validateSingleAgencyConstraint($user, $managerRole);
        $this->fail('Expected abort(422) to be thrown.');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        $response = $e;
    }

    expect($response->getStatusCode())->toBe(422);
    expect($response->getMessage())->toContain('one agency');
});

it('allows regular user without can_manage_users to be in multiple agencies', function () {
    $viewerRole = Role::create([
        'name' => 'Viewer',
        'permissions' => [
            'can_view_campaigns' => true,
        ],
    ]);

    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $viewerRole->id;
    $user->save();

    $agency1 = Agency::factory()->create();
    $agency2 = Agency::factory()->create();

    $agency1->users()->attach($user->id, ['access_all_clients' => true]);
    $agency2->users()->attach($user->id, ['access_all_clients' => false]);

    // No exception — constraint does not apply
    User::validateSingleAgencyConstraint($user, $viewerRole);

    expect($user->agencies()->count())->toBe(2);
});

it('blocks assigning can_manage_users role to multi-agency user', function () {
    $viewerRole = Role::create([
        'name' => 'Viewer',
        'permissions' => ['can_view_campaigns' => true],
    ]);

    $managerRole = Role::create([
        'name' => 'Agency Manager',
        'permissions' => [
            'can_manage_users' => true,
            'can_manage_clients' => true,
        ],
    ]);

    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $viewerRole->id;
    $user->save();

    $agency1 = Agency::factory()->create();
    $agency2 = Agency::factory()->create();
    $agency1->users()->attach($user->id, ['access_all_clients' => true]);
    $agency2->users()->attach($user->id, ['access_all_clients' => true]);

    // Trying to assign manager role to a multi-agency user should be blocked
    try {
        User::validateRoleAgencyConstraint($user, $managerRole);
        $this->fail('Expected abort(422) to be thrown.');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        expect($e->getStatusCode())->toBe(422);
        expect($e->getMessage())->toContain('multiple agencies');
    }
});
