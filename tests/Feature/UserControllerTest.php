<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_edit_form()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this->actingAs($admin);
        $response = $this->get(route('admin.users.edit', $user));
        $response->assertStatus(200);
        $response->assertSee('User Details');
    }

    public function test_admin_can_update_user()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($admin);
        $response = $this->put(route('admin.users.update', $user), [
            'name' => 'New Name',
            'email' => $user->email,
            'clients' => [],
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_admin_can_delete_user()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this->actingAs($admin);
        $response = $this->delete(route('admin.users.destroy', $user));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_regular_user_cannot_delete_user()
    {
        $regular = User::factory()->create(['is_admin' => false]);
        $target = User::factory()->create();

        $this->actingAs($regular);
        $response = $this->delete(route('admin.users.destroy', $target));

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_regular_user_cannot_view_edit_form()
    {
        $regular = User::factory()->create(['is_admin' => false]);
        $user = User::factory()->create();

        $this->actingAs($regular);
        $response = $this->get(route('admin.users.edit', $user));

        $response->assertForbidden();
    }

    public function test_regular_user_cannot_update_user()
    {
        $regular = User::factory()->create(['is_admin' => false]);
        $user = User::factory()->create(['name' => 'Unchanged Name']);

        $this->actingAs($regular);
        $response = $this->put(route('admin.users.update', $user), [
            'name' => 'Changed Name',
            'email' => $user->email,
            'clients' => [],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Unchanged Name']);
    }
}
