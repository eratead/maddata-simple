<?php

use App\Models\ActivityLog;
use App\Models\Agency;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Helper: create a manager with agency and role.
 */
function createManager(?Agency $agency = null): array
{
    $role = Role::create([
        'name' => 'Agency Manager Test',
        'permissions' => [
            'can_manage_users' => true,
            'can_manage_clients' => true,
            'can_view_campaigns' => true,
            'can_edit_campaigns' => true,
            'can_view_budget' => true,
        ],
    ]);

    $manager = User::factory()->create(['is_admin' => false]);
    $manager->role_id = $role->id;
    $manager->save();

    $agency = $agency ?? Agency::factory()->create();
    $manager->agencies()->attach($agency->id, ['access_all_clients' => true]);

    return [$manager, $agency, $role];
}

/**
 * Helper: create a viewer role.
 */
function createViewerRole(): Role
{
    return Role::create([
        'name' => 'Viewer',
        'permissions' => [
            'can_view_campaigns' => true,
        ],
    ]);
}

/**
 * Helper: create an admin user.
 */
function createAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

// ===================================================================
// Client Deletion with User Assignments
// ===================================================================

it('cleans up client_user pivot entries when a client is deleted via cascade', function () {
    [$manager, $agency, $role] = createManager();
    $viewerRole = createViewerRole();

    $client = Client::factory()->create(['agency_id' => $agency->id]);
    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $viewerRole->id;
    $user->save();
    $agency->users()->attach($user->id, ['access_all_clients' => false]);
    $user->clients()->attach($client->id);

    // Verify pivot exists
    expect(DB::table('client_user')->where('client_id', $client->id)->where('user_id', $user->id)->exists())->toBeTrue();

    // Delete the client (cascadeOnDelete on client_user FK)
    $client->delete();

    // Pivot should be cleaned up by FK cascade
    expect(DB::table('client_user')->where('client_id', $client->id)->exists())->toBeFalse();
});

it('does not restore old user assignments when recreating a client with the same name', function () {
    [$manager, $agency, $role] = createManager();
    $viewerRole = createViewerRole();

    $client = Client::factory()->create(['agency_id' => $agency->id, 'name' => 'Acme Corp']);
    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $viewerRole->id;
    $user->save();
    $user->clients()->attach($client->id);
    $oldClientId = $client->id;

    // Delete the client
    $client->delete();

    // Recreate a client with the same name
    $newClient = Client::factory()->create(['agency_id' => $agency->id, 'name' => 'Acme Corp']);

    // New client has a different ID
    expect($newClient->id)->not->toBe($oldClientId);

    // User has no client_user entries for the new client
    expect($user->clients()->where('clients.id', $newClient->id)->exists())->toBeFalse();
});

it('removes client_user pivots for agency users when agency manager deletes a client', function () {
    [$manager, $agency, $role] = createManager();
    $viewerRole = createViewerRole();

    $client = Client::factory()->create(['agency_id' => $agency->id]);
    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $viewerRole->id;
    $user->save();
    $agency->users()->attach($user->id, ['access_all_clients' => false]);
    $user->clients()->attach($client->id);

    // Verify pivot exists
    expect(DB::table('client_user')->where('client_id', $client->id)->where('user_id', $user->id)->exists())->toBeTrue();

    // Agency manager deletes the client via controller
    $response = test()->actingAs($manager)
        ->delete(route('agency.clients.destroy', [$agency, $client]));

    $response->assertRedirect(route('agency.clients.index', $agency));

    // Client and its pivot entries should be gone
    expect(Client::find($client->id))->toBeNull();
    expect(DB::table('client_user')->where('client_id', $client->id)->exists())->toBeFalse();
});

// ===================================================================
// Agency Deletion Edge Cases
// ===================================================================

it('prevents admin from deleting agency with clients', function () {
    $admin = createAdmin();
    $agency = Agency::factory()->create();
    Client::factory()->create(['agency_id' => $agency->id]);

    $response = test()->actingAs($admin)
        ->delete(route('admin.agencies.destroy', $agency));

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Cannot delete agency with active clients.');

    // Agency still exists
    expect(Agency::find($agency->id))->not->toBeNull();
});

it('cleans up agency_user pivot when admin deletes agency with no clients via cascade', function () {
    $admin = createAdmin();
    $agency = Agency::factory()->create();
    $viewerRole = createViewerRole();

    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $viewerRole->id;
    $user->save();
    $agency->users()->attach($user->id, ['access_all_clients' => true]);

    // Verify pivot exists
    expect(DB::table('agency_user')->where('agency_id', $agency->id)->where('user_id', $user->id)->exists())->toBeTrue();

    // Delete agency (no clients, should succeed)
    $response = test()->actingAs($admin)
        ->delete(route('admin.agencies.destroy', $agency));

    $response->assertRedirect(route('admin.agencies.index'));
    $response->assertSessionHas('success');

    // Agency deleted, pivot cleaned up by cascadeOnDelete
    expect(Agency::find($agency->id))->toBeNull();
    expect(DB::table('agency_user')->where('agency_id', $agency->id)->exists())->toBeFalse();
});

it('leaves the agency manager user intact after agency deletion', function () {
    $admin = createAdmin();
    [$manager, $agency, $role] = createManager();

    // Delete agency (no clients)
    test()->actingAs($admin)
        ->delete(route('admin.agencies.destroy', $agency));

    // Manager user still exists but has no agencies
    $manager->refresh();
    expect($manager->exists)->toBeTrue();
    expect($manager->agencies()->count())->toBe(0);
});

// ===================================================================
// Manager Self-Actions
// ===================================================================

it('prevents agency manager from disabling themselves', function () {
    [$manager, $agency, $role] = createManager();

    $response = test()->actingAs($manager)
        ->delete(route('agency.users.destroy', [$agency, $manager]));

    $response->assertRedirect(route('agency.users.index', $agency));
    $response->assertSessionHas('error', 'You cannot disable your own account.');

    // Manager is still active
    $manager->refresh();
    expect($manager->is_active)->toBeTrue();
});

it('prevents agency manager from changing their own role to non-manager role', function () {
    [$manager, $agency, $role] = createManager();
    $viewerRole = createViewerRole();
    $originalRoleId = $manager->role_id;

    // Attempt to update self with a viewer role.
    // UserPolicy::changeRole now blocks self-role-changes — must return 403.
    $response = test()->actingAs($manager)
        ->put(route('agency.users.update', [$agency, $manager]), [
            'name' => $manager->name,
            'email' => $manager->email,
            'role_id' => $viewerRole->id,
            'access_all_clients' => true,
        ]);

    $response->assertForbidden();

    // Role must NOT have changed — self-demotion is blocked.
    $manager->refresh();
    expect($manager->role_id)->toBe($originalRoleId);
});

it('shows manager user listing includes themselves in the agency', function () {
    [$manager, $agency, $role] = createManager();

    $response = test()->actingAs($manager)
        ->get(route('agency.users.index', $agency));

    $response->assertOk();
    $response->assertSee($manager->name);
});

// ===================================================================
// Client Reassignment
// ===================================================================

it('removes client from agency A users access when admin reassigns client to agency B', function () {
    $admin = createAdmin();
    $agencyA = Agency::factory()->create();
    $agencyB = Agency::factory()->create();

    $client = Client::factory()->create(['agency_id' => $agencyA->id]);

    // User in agency A with access_all_clients = true
    $viewerRole = createViewerRole();
    $userA = User::factory()->create(['is_admin' => false]);
    $userA->role_id = $viewerRole->id;
    $userA->save();
    $agencyA->users()->attach($userA->id, ['access_all_clients' => true]);

    // User A can see the client via accessibleClientIds
    expect($userA->accessibleClientIds())->toContain($client->id);

    // Admin reassigns client to agency B
    $client->update(['agency_id' => $agencyB->id]);

    // User A (agency A, access_all_clients) should no longer see the client
    // Must create a fresh user instance because accessibleClientIds uses once()
    $userAFresh = User::find($userA->id);
    expect($userAFresh->accessibleClientIds())->not->toContain($client->id);
});

it('handles admin removing agency from client by setting agency_id to null', function () {
    $admin = createAdmin();
    $agency = Agency::factory()->create();

    $client = Client::factory()->create(['agency_id' => $agency->id]);

    // User with access_all_clients in agency
    $viewerRole = createViewerRole();
    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $viewerRole->id;
    $user->save();
    $agency->users()->attach($user->id, ['access_all_clients' => true]);

    expect($user->accessibleClientIds())->toContain($client->id);

    // Remove agency from client
    $client->update(['agency_id' => null]);

    // Fresh user instance — client no longer visible via agency access
    $userFresh = User::find($user->id);
    expect($userFresh->accessibleClientIds())->not->toContain($client->id);
});

// ===================================================================
// Activity Logging
// ===================================================================

it('logs activity when agency manager creates a user', function () {
    [$manager, $agency, $role] = createManager();
    $viewerRole = createViewerRole();

    test()->actingAs($manager)
        ->post(route('agency.users.store', $agency), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'role_id' => $viewerRole->id,
            'access_all_clients' => true,
        ]);

    $newUser = User::where('email', 'newuser@example.com')->first();
    expect($newUser)->not->toBeNull();

    $log = ActivityLog::where('subject_type', User::class)
        ->where('subject_id', $newUser->id)
        ->where('action', 'created')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($manager->id);
    expect($log->description)->toContain('Created user');
    expect($log->description)->toContain($agency->name);
});

it('logs activity when agency manager creates a client', function () {
    [$manager, $agency, $role] = createManager();

    test()->actingAs($manager)
        ->post(route('agency.clients.store', $agency), [
            'name' => 'New Client Corp',
        ]);

    $newClient = Client::where('name', 'New Client Corp')->first();
    expect($newClient)->not->toBeNull();

    $log = ActivityLog::where('subject_type', Client::class)
        ->where('subject_id', $newClient->id)
        ->where('action', 'created')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($manager->id);
    expect($log->description)->toContain('Created client');
});

it('logs activity when agency manager disables a user', function () {
    [$manager, $agency, $role] = createManager();
    $viewerRole = createViewerRole();

    $user = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $user->role_id = $viewerRole->id;
    $user->save();
    $agency->users()->attach($user->id, ['access_all_clients' => true]);

    test()->actingAs($manager)
        ->delete(route('agency.users.destroy', [$agency, $user]));

    $log = ActivityLog::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('action', 'updated')
        ->where('description', 'LIKE', '%Disabled user%')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($manager->id);
});

it('logs activity when admin creates an agency', function () {
    $admin = createAdmin();

    test()->actingAs($admin)
        ->post(route('admin.agencies.store'), [
            'name' => 'Test Agency Inc',
        ]);

    $agency = Agency::where('name', 'Test Agency Inc')->first();
    expect($agency)->not->toBeNull();

    $log = ActivityLog::where('subject_type', Agency::class)
        ->where('subject_id', $agency->id)
        ->where('action', 'created')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($admin->id);
    expect($log->description)->toContain('Created agency');
});

it('logs activity when admin creates, updates, and deletes a role', function () {
    $admin = createAdmin();

    // Create
    test()->actingAs($admin)
        ->post(route('admin.roles.store'), [
            'name' => 'Test Role',
            'permissions' => ['can_view_campaigns' => true],
        ]);

    $createdRole = Role::where('name', 'Test Role')->first();
    expect($createdRole)->not->toBeNull();

    $createLog = ActivityLog::where('subject_type', Role::class)
        ->where('subject_id', $createdRole->id)
        ->where('action', 'created')
        ->first();
    expect($createLog)->not->toBeNull();

    // Update
    test()->actingAs($admin)
        ->put(route('admin.roles.update', $createdRole), [
            'name' => 'Test Role Updated',
            'permissions' => ['can_view_campaigns' => true, 'can_view_budget' => true],
        ]);

    $updateLog = ActivityLog::where('subject_type', Role::class)
        ->where('subject_id', $createdRole->id)
        ->where('action', 'updated')
        ->first();
    expect($updateLog)->not->toBeNull();
    expect($updateLog->description)->toContain('Updated role');

    // Delete (role has no users, so it should succeed)
    test()->actingAs($admin)
        ->delete(route('admin.roles.destroy', $createdRole));

    $deleteLog = ActivityLog::where('subject_type', Role::class)
        ->where('subject_id', $createdRole->id)
        ->where('action', 'deleted')
        ->first();
    expect($deleteLog)->not->toBeNull();
});

// ===================================================================
// Data Integrity
// ===================================================================

it('keeps disabled user campaigns visible to other agency users', function () {
    [$manager, $agency, $role] = createManager();
    $viewerRole = createViewerRole();

    $client = Client::factory()->create(['agency_id' => $agency->id]);

    // Create two agency users
    $user1 = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $user1->role_id = $viewerRole->id;
    $user1->save();
    $agency->users()->attach($user1->id, ['access_all_clients' => true]);

    $user2 = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $user2->role_id = $viewerRole->id;
    $user2->save();
    $agency->users()->attach($user2->id, ['access_all_clients' => true]);

    // Create a campaign for the client
    $campaign = Campaign::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
    ]);
    // Clean up auto-created activity log from CampaignObserver
    ActivityLog::where('campaign_id', $campaign->id)->delete();

    // Both users can see the client
    expect($user1->accessibleClientIds())->toContain($client->id);
    expect($user2->accessibleClientIds())->toContain($client->id);

    // Disable user1
    $user1->update(['is_active' => false]);

    // User2 can still see the campaign's client
    $user2Fresh = User::find($user2->id);
    expect($user2Fresh->accessibleClientIds())->toContain($client->id);

    // Campaign still exists
    expect(Campaign::find($campaign->id))->not->toBeNull();
});

it('assigns only specific clients to user when access_all_clients is false', function () {
    [$manager, $agency, $role] = createManager();
    $viewerRole = createViewerRole();

    $client1 = Client::factory()->create(['agency_id' => $agency->id]);
    $client2 = Client::factory()->create(['agency_id' => $agency->id]);
    $client3 = Client::factory()->create(['agency_id' => $agency->id]);

    // Create user with specific client access
    test()->actingAs($manager)
        ->post(route('agency.users.store', $agency), [
            'name' => 'Specific Access User',
            'email' => 'specific@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'role_id' => $viewerRole->id,
            'access_all_clients' => false,
            'clients' => [$client1->id, $client3->id],
        ]);

    $user = User::where('email', 'specific@example.com')->first();
    expect($user)->not->toBeNull();

    // Check pivot: access_all_clients should be false
    $pivot = DB::table('agency_user')
        ->where('agency_id', $agency->id)
        ->where('user_id', $user->id)
        ->first();
    expect((bool) $pivot->access_all_clients)->toBeFalse();

    // Check client_user pivot: only client1 and client3
    $assignedClientIds = $user->clients()->pluck('clients.id')->sort()->values()->toArray();
    expect($assignedClientIds)->toBe(collect([$client1->id, $client3->id])->sort()->values()->toArray());
});

it('removes specific client_user entries when switching user from specific to all access', function () {
    [$manager, $agency, $role] = createManager();
    $viewerRole = createViewerRole();

    $client1 = Client::factory()->create(['agency_id' => $agency->id]);
    $client2 = Client::factory()->create(['agency_id' => $agency->id]);

    // Create user with specific client access
    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $viewerRole->id;
    $user->save();
    $agency->users()->attach($user->id, ['access_all_clients' => false]);
    $user->clients()->sync([$client1->id, $client2->id]);

    // Verify specific clients are assigned
    expect($user->clients()->count())->toBe(2);

    // Manager switches user to "all access"
    test()->actingAs($manager)
        ->put(route('agency.users.update', [$agency, $user]), [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $viewerRole->id,
            'access_all_clients' => true,
        ]);

    // Specific client_user entries for agency clients should be removed
    $user->refresh();
    $remainingAgencyClients = $user->clients()
        ->whereIn('clients.id', [$client1->id, $client2->id])
        ->count();
    expect($remainingAgencyClients)->toBe(0);

    // Pivot should show access_all_clients = true
    $pivot = DB::table('agency_user')
        ->where('agency_id', $agency->id)
        ->where('user_id', $user->id)
        ->first();
    expect((bool) $pivot->access_all_clients)->toBeTrue();
});
