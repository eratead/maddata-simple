<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// The terminateAll endpoint uses DB::table('sessions'), which requires the database
// session driver. In the test environment the session driver is "array", so the
// sessions table does not exist. We create a temporary sessions table for these
// tests to exercise the query logic without changing the session driver.

beforeEach(function () {
    // Create a temporary sessions table so DB::table('sessions') works
    DB::statement('CREATE TABLE IF NOT EXISTS sessions (
        id VARCHAR(255) NOT NULL PRIMARY KEY,
        user_id BIGINT UNSIGNED NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        payload LONGTEXT NOT NULL,
        last_activity INT NOT NULL
    )');
});

afterEach(function () {
    DB::statement('DROP TABLE IF EXISTS sessions');
});

/**
 * Seed a fake session row for the given user.
 */
function seedSession(User $user, ?string $sessionId = null): string
{
    $id = $sessionId ?? \Illuminate\Support\Str::random(40);

    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'TestAgent/1.0',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->timestamp,
    ]);

    return $id;
}

it('terminateAll endpoint returns a redirect for an admin user', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.system-status.terminate-all'))
        ->assertRedirect(route('admin.system-status.index'));
});

it('terminateAll deletes sessions for regular users', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $regularUser = User::factory()->create(['is_admin' => false]);

    $regularSessionId = seedSession($regularUser);

    $this->actingAs($admin)
        ->post(route('admin.system-status.terminate-all'));

    expect(DB::table('sessions')->where('id', $regularSessionId)->exists())->toBeFalse();
});

it('terminateAll preserves sessions for legacy is_admin=true users', function () {
    $callerAdmin = User::factory()->create(['is_admin' => true]);
    $legacyAdmin = User::factory()->create(['is_admin' => true]);

    $adminSessionId = seedSession($legacyAdmin);

    $this->actingAs($callerAdmin)
        ->post(route('admin.system-status.terminate-all'));

    // Legacy admin session must be preserved
    expect(DB::table('sessions')->where('id', $adminSessionId)->exists())->toBeTrue();
});

it('terminateAll preserves sessions for role-based admin users', function () {
    $callerAdmin = User::factory()->create(['is_admin' => true]);

    $adminRole = Role::create([
        'name' => 'Role Admin',
        'permissions' => ['is_admin' => true],
    ]);
    $roleBasedAdmin = User::factory()->create(['role_id' => $adminRole->id]);

    $roleAdminSessionId = seedSession($roleBasedAdmin);

    $this->actingAs($callerAdmin)
        ->post(route('admin.system-status.terminate-all'));

    // Role-based admin session must be preserved
    expect(DB::table('sessions')->where('id', $roleAdminSessionId)->exists())->toBeTrue();
});

it('terminateAll deletes sessions for regular users but not admins in the same pass', function () {
    $callerAdmin = User::factory()->create(['is_admin' => true]);

    $adminRole = Role::create([
        'name' => 'Admin Role',
        'permissions' => ['is_admin' => true],
    ]);
    $roleAdmin = User::factory()->create(['role_id' => $adminRole->id]);
    $regularUser = User::factory()->create(['is_admin' => false]);

    $adminSessionId = seedSession($roleAdmin);
    $regularSessionId = seedSession($regularUser);

    $this->actingAs($callerAdmin)
        ->post(route('admin.system-status.terminate-all'));

    expect(DB::table('sessions')->where('id', $adminSessionId)->exists())->toBeTrue();
    expect(DB::table('sessions')->where('id', $regularSessionId)->exists())->toBeFalse();
});

it('terminateAll ignores sessions with no user_id (anonymous)', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    // Anonymous session (no user_id)
    $anonId = \Illuminate\Support\Str::random(40);
    DB::table('sessions')->insert([
        'id' => $anonId,
        'user_id' => null,
        'ip_address' => '10.0.0.1',
        'user_agent' => 'Bot/1.0',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.system-status.terminate-all'));

    // Anonymous sessions are not touched (query has whereNotNull('user_id'))
    expect(DB::table('sessions')->where('id', $anonId)->exists())->toBeTrue();
});

it('non-admin cannot call terminateAll', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->post(route('admin.system-status.terminate-all'))
        ->assertForbidden();
});
