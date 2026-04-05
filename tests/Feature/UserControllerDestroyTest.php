<?php

use App\Models\Agency;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ===================================================================
// Test 7: UserController::destroy detaches agencies and clients
// ===================================================================

it('admin delete detaches user from all agencies and clients and removes the user', function () {
    $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

    $agency1 = Agency::factory()->create();
    $agency2 = Agency::factory()->create();
    $client1 = Client::factory()->create(['agency_id' => $agency1->id]);
    $client2 = Client::factory()->create(['agency_id' => $agency2->id]);

    $target = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $target->agencies()->attach($agency1->id, ['access_all_clients' => true]);
    $target->agencies()->attach($agency2->id, ['access_all_clients' => false]);
    $target->clients()->attach([$client1->id, $client2->id]);

    // Verify pivots exist before deletion
    expect(DB::table('agency_user')->where('user_id', $target->id)->count())->toBe(2);
    expect(DB::table('client_user')->where('user_id', $target->id)->count())->toBe(2);

    // Admin deletes the user
    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $target))
        ->assertRedirect(route('admin.users.index'));

    // User is deleted
    expect(User::find($target->id))->toBeNull();

    // Both agency pivots are cleaned up
    expect(DB::table('agency_user')->where('user_id', $target->id)->count())->toBe(0);

    // Both client pivots are cleaned up
    expect(DB::table('client_user')->where('user_id', $target->id)->count())->toBe(0);
});

it('agencies and clients remain intact after user deletion — only pivots are removed', function () {
    $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

    $agency = Agency::factory()->create();
    $client = Client::factory()->create(['agency_id' => $agency->id]);

    $target = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $target->agencies()->attach($agency->id, ['access_all_clients' => true]);
    $target->clients()->attach($client->id);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $target))
        ->assertRedirect(route('admin.users.index'));

    // Agency and client models still exist — only the pivot rows were removed
    expect(Agency::find($agency->id))->not->toBeNull();
    expect(Client::find($client->id))->not->toBeNull();
});

it('user with no agency or client assignments is deleted cleanly', function () {
    $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
    // Create a second admin so deleting $target doesn't trigger last-admin guard
    User::factory()->create(['is_admin' => true, 'is_active' => true]);

    $target = User::factory()->create(['is_admin' => false, 'is_active' => true]);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $target))
        ->assertRedirect(route('admin.users.index'));

    expect(User::find($target->id))->toBeNull();
});

it('other users agency and client pivots are unaffected by deleting a different user', function () {
    $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

    $agency = Agency::factory()->create();
    $client = Client::factory()->create(['agency_id' => $agency->id]);

    $target = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $target->agencies()->attach($agency->id, ['access_all_clients' => true]);
    $target->clients()->attach($client->id);

    $otherUser = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $otherUser->agencies()->attach($agency->id, ['access_all_clients' => true]);
    $otherUser->clients()->attach($client->id);

    // Delete only the target
    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $target))
        ->assertRedirect(route('admin.users.index'));

    // Other user's pivots must remain untouched
    expect(DB::table('agency_user')->where('user_id', $otherUser->id)->count())->toBe(1);
    expect(DB::table('client_user')->where('user_id', $otherUser->id)->count())->toBe(1);
});
