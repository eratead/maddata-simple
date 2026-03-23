<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_clients_index()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);

        $response = $this->get(route('admin.clients.index'));

        $response->assertStatus(200);
        $response->assertSee('Clients'); // adjust text if needed
    }

    public function test_admin_can_view_create_form()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);
        $response = $this->get(route('admin.clients.create'));

        $response->assertStatus(200);
        $response->assertSee('Create'); // Adjust based on actual form text
    }

    public function test_non_admin_user_cannot_view_clients_index()
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user);
        $response = $this->get(route('admin.clients.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_store_client()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);
        $response = $this->post(route('admin.clients.store'), [
            'name' => 'Test Client',
            'agency' => 'Test Agency',
        ]);

        $response->assertRedirect(route('admin.clients.index'));
        $this->assertDatabaseHas('clients', ['name' => 'Test Client']);
    }

    public function test_admin_can_update_client()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = \App\Models\Client::factory()->create(['name' => 'Old Name']);

        $this->actingAs($admin);
        $response = $this->put(route('admin.clients.update', $client), [
            'name' => 'Updated Name',
        ]);

        $response->assertRedirect(route('admin.clients.index'));
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'Updated Name']);
    }

    public function test_admin_can_delete_client()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = \App\Models\Client::factory()->create();

        $this->actingAs($admin);
        $response = $this->delete(route('admin.clients.destroy', $client));

        $response->assertRedirect(route('admin.clients.index'));
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }
}
