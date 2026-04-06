<?php

use App\Models\Agency;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AgencyController::store — privilege escalation guard
//
// When `manager_name` is provided, AgencyController builds an in-memory Role
// with hard-coded permissions (can_manage_users, can_edit_campaigns,
// can_view_budget, etc.) and passes it through `preventPrivilegeEscalation()`.
// If the acting user does not hold every permission in that set, the controller
// aborts with 403.
// ---------------------------------------------------------------------------

it('admin with full permissions can create an agency with a manager', function () {
    // Give the admin a role that holds all permissions the Agency Manager role
    // will need, so preventPrivilegeEscalation() passes.
    $adminRole = Role::create([
        'name' => 'Full Admin',
        'permissions' => [
            'is_admin' => true,
            'can_manage_users' => true,
            'can_manage_clients' => true,
            'can_view_campaigns' => true,
            'can_edit_campaigns' => true,
            'can_view_budget' => true,
        ],
        'is_protected' => true,
    ]);

    $admin = User::factory()->create(['is_admin' => true]);
    $admin->role_id = $adminRole->id;
    $admin->save();
    $admin->refresh();

    $response = $this->actingAs($admin)
        ->post(route('admin.agencies.store'), [
            'name' => 'McCann Agency',
            'manager_name' => 'Jane Manager',
            'manager_email' => 'jane@mccann.test',
            'manager_password' => 'SecurePass1',
        ]);

    $response->assertRedirect(route('admin.agencies.index'));

    $this->assertDatabaseHas('agencies', ['name' => 'McCann Agency']);
    $this->assertDatabaseHas('users', ['email' => 'jane@mccann.test']);
});

it('admin lacking can_manage_users cannot create an agency with manager fields (403)', function () {
    // This admin passes the EnsureUserIsAdmin middleware (is_admin=true legacy),
    // but their role does NOT hold can_manage_users, can_view_budget, etc.
    // preventPrivilegeEscalation() should abort 403 because the auto-created
    // Agency Manager role grants permissions this actor doesn't hold.
    $limitedRole = Role::create([
        'name' => 'Limited Admin',
        'permissions' => [
            'is_admin' => false,
            'can_manage_users' => false,
            'can_manage_clients' => false,
            'can_view_campaigns' => true,
            'can_edit_campaigns' => false,
            'can_view_budget' => false,
        ],
    ]);

    // is_admin=false on the user so hasPermission() falls through to the Role.
    // We still need the admin middleware to pass — bypass it so the controller
    // action runs and we can test the escalation guard inside it.
    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $limitedRole->id;
    $user->save();
    $user->refresh();

    $response = $this->actingAs($user)
        ->withoutMiddleware(\App\Http\Middleware\EnsureUserIsAdmin::class)
        ->post(route('admin.agencies.store'), [
            'name' => 'Evil Agency',
            'manager_name' => 'Hacker',
            'manager_email' => 'hacker@evil.test',
            'manager_password' => 'SecurePass1',
        ]);

    $response->assertForbidden();

    $this->assertDatabaseMissing('agencies', ['name' => 'Evil Agency']);
    $this->assertDatabaseMissing('users', ['email' => 'hacker@evil.test']);
});

it('creating an agency without manager fields succeeds regardless of permissions', function () {
    // No manager fields = no Role building = no preventPrivilegeEscalation() call.
    // Any admin should be able to create a bare agency.
    $viewerRole = Role::create([
        'name' => 'Campaign Viewer',
        'permissions' => [
            'can_view_campaigns' => true,
        ],
    ]);

    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $viewerRole->id;
    $user->save();

    // Bypass the admin middleware — we only want to test the controller logic
    // for the escalation guard (which is NOT exercised when no manager fields).
    $response = $this->actingAs($user)
        ->withoutMiddleware(\App\Http\Middleware\EnsureUserIsAdmin::class)
        ->post(route('admin.agencies.store'), [
            'name' => 'Plain Agency',
            // no manager_name / manager_email / manager_password
        ]);

    $response->assertRedirect(route('admin.agencies.index'));
    $this->assertDatabaseHas('agencies', ['name' => 'Plain Agency']);
});

it('agency store is inaccessible to unauthenticated users', function () {
    $this->post(route('admin.agencies.store'), ['name' => 'Ghost Agency'])
        ->assertRedirect(route('login'));

    $this->assertDatabaseMissing('agencies', ['name' => 'Ghost Agency']);
});
