<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_campaigns_index()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);

        $response = $this->get(route('campaigns.index'));

        $response->assertStatus(200);
        $response->assertSee('Campaigns'); // Adjust text based on actual view
    }

    public function test_admin_can_view_create_form()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);
        $response = $this->get(route('campaigns.create'));

        $response->assertStatus(200);
        $response->assertSee('Create'); // Adjust if needed
    }

    public function test_admin_can_store_campaign()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = \App\Models\Client::factory()->create();

        $this->actingAs($admin);
        $response = $this->post(route('campaigns.store'), [
            'name' => 'Test Campaign',
            'client_id' => $client->id,
            'expected_impressions' => 10000,
        ]);

        $response->assertRedirect(route('campaigns.index'));
        $this->assertDatabaseHas('campaigns', ['name' => 'Test Campaign']);
    }

    public function test_admin_can_update_campaign()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $campaign = \App\Models\Campaign::factory()->create(['name' => 'Old Name']);

        $this->actingAs($admin);
        $response = $this->put(route('campaigns.update', $campaign), [
            'name' => 'Updated Campaign',
            'client_id' => $campaign->client_id,
            'expected_impressions' => $campaign->expected_impressions,
        ]);

        $response->assertRedirect(route('campaigns.index'));
        $this->assertDatabaseHas('campaigns', ['id' => $campaign->id, 'name' => 'Updated Campaign']);
    }

    public function test_admin_can_delete_campaign()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $campaign = \App\Models\Campaign::factory()->create();

        $this->actingAs($admin);
        $response = $this->delete(route('campaigns.destroy', $campaign));

        $response->assertRedirect(route('campaigns.index'));
        $this->assertDatabaseMissing('campaigns', ['id' => $campaign->id]);
    }

    public function test_non_admin_can_view_campaigns_for_their_clients()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $client = \App\Models\Client::factory()->create();
        $user->clients()->attach($client);

        $campaign = \App\Models\Campaign::factory()->create(['client_id' => $client->id]);

        $this->actingAs($user);
        $response = $this->get(route('campaigns.index'));

        $response->assertStatus(200);
        $response->assertSee($campaign->name);
    }

    public function test_non_admin_cannot_store_campaign()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $client = \App\Models\Client::factory()->create();

        $this->actingAs($user);
        $response = $this->post(route('campaigns.store'), [
            'name' => 'Test Campaign',
            'client_id' => $client->id,
            'expected_impressions' => 10000,
        ]);

        $response->assertForbidden();
    }

    public function test_non_admin_can_update_campaign_for_their_client()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $client = \App\Models\Client::factory()->create();
        $user->clients()->attach($client);
        
        $campaign = \App\Models\Campaign::factory()->create([
            'name' => 'Original',
            'client_id' => $client->id,
        ]);

        $this->actingAs($user);
        $response = $this->put(route('campaigns.update', $campaign), [
            'name' => 'Updated by User',
            'client_id' => $campaign->client_id,
            'expected_impressions' => $campaign->expected_impressions,
        ]);

        $response->assertRedirect(route('campaigns.index'));
        $this->assertDatabaseHas('campaigns', ['id' => $campaign->id, 'name' => 'Updated by User']);
    }

    public function test_non_admin_cannot_update_campaign_for_other_client()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $userClient = \App\Models\Client::factory()->create();
        $user->clients()->attach($userClient);
        
        $otherClient = \App\Models\Client::factory()->create();
        $campaign = \App\Models\Campaign::factory()->create([
            'name' => 'Original',
            'client_id' => $otherClient->id,
        ]);

        $this->actingAs($user);
        $response = $this->put(route('campaigns.update', $campaign), [
            'name' => 'Blocked Update',
            'client_id' => $campaign->client_id,
            'expected_impressions' => $campaign->expected_impressions,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('campaigns', ['id' => $campaign->id, 'name' => 'Original']);
    }

    public function test_non_admin_cannot_delete_campaign()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $campaign = \App\Models\Campaign::factory()->create();

        $this->actingAs($user);
        $response = $this->delete(route('campaigns.destroy', $campaign));

        $response->assertForbidden();
        $this->assertDatabaseHas('campaigns', ['id' => $campaign->id]);
    }
}
