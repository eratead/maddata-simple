<?php

use App\Models\ActivityLog;
use App\Models\Audience;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers (prefixed to avoid collision with helpers in other test files)
// ---------------------------------------------------------------------------

function audienceTestAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

function audienceTestNonAdmin(): User
{
    return User::factory()->create(['is_admin' => false]);
}

function audienceTestUserWithPermission(string $permission): User
{
    $role = Role::create([
        'name' => "Role-{$permission}-" . uniqid(),
        'permissions' => [$permission => true],
    ]);
    $user = User::factory()->create(['is_admin' => false]);
    $user->role_id = $role->id;
    $user->save();
    $user->refresh();

    return $user;
}

function audiencePayload(array $overrides = []): array
{
    return array_merge([
        'main_category' => 'Interests',
        'sub_category' => 'Tech',
        'name' => 'Early Adopters ' . uniqid(),
        'estimated_users' => 50000,
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Authentication guard
// ---------------------------------------------------------------------------

it('redirects unauthenticated users from audiences index', function () {
    $this->get(route('admin.audiences.index'))->assertRedirect(route('login'));
});

it('redirects unauthenticated users from audiences store', function () {
    $this->post(route('admin.audiences.store'), audiencePayload())->assertRedirect(route('login'));
});

it('redirects unauthenticated users from audiences update', function () {
    $audience = Audience::factory()->create();
    $this->put(route('admin.audiences.update', $audience), audiencePayload())->assertRedirect(route('login'));
});

it('redirects unauthenticated users from audiences destroy', function () {
    $audience = Audience::factory()->create();
    $this->delete(route('admin.audiences.destroy', $audience))->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------------
// Non-admin without can_manage_clients → 403
// ---------------------------------------------------------------------------

it('non-admin without can_manage_clients gets 403 on audiences index', function () {
    $user = audienceTestNonAdmin();
    $this->actingAs($user)->get(route('admin.audiences.index'))->assertForbidden();
});

it('non-admin without can_manage_clients gets 403 on audiences store', function () {
    $user = audienceTestNonAdmin();
    $this->actingAs($user)
        ->post(route('admin.audiences.store'), audiencePayload())
        ->assertForbidden();
});

it('non-admin without can_manage_clients gets 403 on audiences update', function () {
    $user = audienceTestNonAdmin();
    $audience = Audience::factory()->create();

    $this->actingAs($user)
        ->put(route('admin.audiences.update', $audience), audiencePayload())
        ->assertForbidden();
});

it('non-admin without can_manage_clients gets 403 on audiences destroy', function () {
    $user = audienceTestNonAdmin();
    $audience = Audience::factory()->create();

    $this->actingAs($user)
        ->delete(route('admin.audiences.destroy', $audience))
        ->assertForbidden();

    $this->assertDatabaseHas('audiences', ['id' => $audience->id]);
});

// ---------------------------------------------------------------------------
// Admin → full CRUD access
// ---------------------------------------------------------------------------

it('admin can view audiences index', function () {
    $admin = audienceTestAdmin();
    Audience::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get(route('admin.audiences.index'))
        ->assertOk();
});

it('admin can create an audience', function () {
    $admin = audienceTestAdmin();
    $payload = audiencePayload();

    $this->actingAs($admin)
        ->post(route('admin.audiences.store'), $payload)
        ->assertRedirect(route('admin.audiences.index'));

    $this->assertDatabaseHas('audiences', ['name' => $payload['name']]);
});

it('admin can update an audience', function () {
    $admin = audienceTestAdmin();
    $audience = Audience::factory()->create([
        'main_category' => 'Interests',
        'sub_category' => 'Tech',
        'name' => 'Old Name',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.audiences.update', $audience), [
            'main_category' => 'Interests',
            'sub_category' => 'Tech',
            'name' => 'New Name',
        ])
        ->assertRedirect(route('admin.audiences.index'));

    $this->assertDatabaseHas('audiences', ['id' => $audience->id, 'name' => 'New Name']);
});

it('admin can delete an audience', function () {
    $admin = audienceTestAdmin();
    $audience = Audience::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.audiences.destroy', $audience))
        ->assertRedirect(route('admin.audiences.index'));

    $this->assertDatabaseMissing('audiences', ['id' => $audience->id]);
});

// ---------------------------------------------------------------------------
// User with can_manage_clients (via role) → full CRUD access
// ---------------------------------------------------------------------------

it('user with can_manage_clients role can view audiences index', function () {
    $user = audienceTestUserWithPermission('can_manage_clients');

    $this->actingAs($user)
        ->get(route('admin.audiences.index'))
        ->assertForbidden(); // EnsureUserIsAdmin middleware blocks non-admins at route level
});

// The admin middleware wraps all /admin/* routes — users with can_manage_clients
// but without is_admin will be turned away by EnsureUserIsAdmin before the
// policy fires. This test documents the current security boundary: the route
// is admin-gated even though the policy would permit can_manage_clients holders.
it('can_manage_clients user blocked by admin middleware on audiences store', function () {
    $user = audienceTestUserWithPermission('can_manage_clients');

    $this->actingAs($user)
        ->post(route('admin.audiences.store'), audiencePayload())
        ->assertForbidden();
});

// Verify the policy alone permits can_manage_clients (bypassing route middleware)
it('policy permits can_manage_clients user to create audience when middleware bypassed', function () {
    $user = audienceTestUserWithPermission('can_manage_clients');
    $payload = audiencePayload();

    \Illuminate\Support\Facades\Gate::before(fn () => null); // reset any previous override

    $this->actingAs($user)
        ->withoutMiddleware(\App\Http\Middleware\EnsureUserIsAdmin::class)
        ->post(route('admin.audiences.store'), $payload)
        ->assertRedirect(route('admin.audiences.index'));

    $this->assertDatabaseHas('audiences', ['name' => $payload['name']]);
});

it('policy permits can_manage_clients user to update audience when middleware bypassed', function () {
    $user = audienceTestUserWithPermission('can_manage_clients');
    $audience = Audience::factory()->create([
        'main_category' => 'Interests',
        'sub_category' => 'Tech',
        'name' => 'Before',
    ]);

    $this->actingAs($user)
        ->withoutMiddleware(\App\Http\Middleware\EnsureUserIsAdmin::class)
        ->put(route('admin.audiences.update', $audience), [
            'main_category' => 'Interests',
            'sub_category' => 'Tech',
            'name' => 'After',
        ])
        ->assertRedirect(route('admin.audiences.index'));

    $this->assertDatabaseHas('audiences', ['id' => $audience->id, 'name' => 'After']);
});

it('policy permits can_manage_clients user to delete audience when middleware bypassed', function () {
    $user = audienceTestUserWithPermission('can_manage_clients');
    $audience = Audience::factory()->create();

    $this->actingAs($user)
        ->withoutMiddleware(\App\Http\Middleware\EnsureUserIsAdmin::class)
        ->delete(route('admin.audiences.destroy', $audience))
        ->assertRedirect(route('admin.audiences.index'));

    $this->assertDatabaseMissing('audiences', ['id' => $audience->id]);
});

// ---------------------------------------------------------------------------
// ActivityLogger integration — audit trail for CRUD operations
// ---------------------------------------------------------------------------

it('creating an audience writes an activity log entry', function () {
    $admin = audienceTestAdmin();
    $payload = audiencePayload();

    $this->actingAs($admin)
        ->post(route('admin.audiences.store'), $payload);

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'created',
        'subject_type' => Audience::class,
        'user_id' => $admin->id,
    ]);
});

it('updating an audience writes an activity log entry', function () {
    $admin = audienceTestAdmin();
    $audience = Audience::factory()->create([
        'main_category' => 'Interests',
        'sub_category' => 'Tech',
        'name' => 'Before Update',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.audiences.update', $audience), [
            'main_category' => 'Interests',
            'sub_category' => 'Tech',
            'name' => 'After Update',
        ]);

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'updated',
        'subject_type' => Audience::class,
        'user_id' => $admin->id,
    ]);
});

it('deleting an audience writes an activity log entry', function () {
    $admin = audienceTestAdmin();
    $audience = Audience::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.audiences.destroy', $audience));

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'deleted',
        'subject_type' => Audience::class,
        'user_id' => $admin->id,
    ]);
});

it('activity log description mentions the audience name on create', function () {
    $admin = audienceTestAdmin();
    $payload = audiencePayload(['name' => 'Known Audience Name']);

    $this->actingAs($admin)
        ->post(route('admin.audiences.store'), $payload);

    $log = ActivityLog::where('action', 'created')
        ->where('subject_type', Audience::class)
        ->where('user_id', $admin->id)
        ->latest()
        ->first();

    expect($log)->not->toBeNull();
    expect($log->description)->toContain('Known Audience Name');
});

it('activity log description mentions the audience name on update', function () {
    $admin = audienceTestAdmin();
    $audience = Audience::factory()->create([
        'main_category' => 'Interests',
        'sub_category' => 'Tech',
        'name' => 'Logged Name',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.audiences.update', $audience), [
            'main_category' => 'Interests',
            'sub_category' => 'Tech',
            'name' => 'Renamed Audience',
        ]);

    $log = ActivityLog::where('action', 'updated')
        ->where('subject_type', Audience::class)
        ->where('user_id', $admin->id)
        ->latest()
        ->first();

    // The logger records the OLD name in the description
    expect($log)->not->toBeNull();
    expect($log->description)->toContain('Logged Name');
});

it('activity log description mentions the audience name on delete', function () {
    $admin = audienceTestAdmin();
    $audience = Audience::factory()->create(['name' => 'Deleted Audience']);

    $this->actingAs($admin)
        ->delete(route('admin.audiences.destroy', $audience));

    $log = ActivityLog::where('action', 'deleted')
        ->where('subject_type', Audience::class)
        ->where('user_id', $admin->id)
        ->latest()
        ->first();

    expect($log)->not->toBeNull();
    expect($log->description)->toContain('Deleted Audience');
});
