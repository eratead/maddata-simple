<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreativeSizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_can_be_created_with_required_sizes()
    {
        $admin = User::factory()->create(['is_report' => false, 'is_admin' => true]);
        $client = Client::factory()->create();

        $response = $this->actingAs($admin)->post(route('campaigns.store'), [
            'name' => 'Test Campaign',
            'client_id' => $client->id,
            'required_sizes' => '1920x1080,300x250',
        ]);

        $response->assertRedirect(route('campaigns.index'));
        
        $this->assertDatabaseHas('campaigns', [
            'name' => 'Test Campaign',
            'required_sizes' => '1920x1080,300x250',
        ]);
    }

    public function test_campaign_can_be_updated_with_required_sizes()
    {
        $admin = User::factory()->create(['is_report' => false, 'is_admin' => true]);
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create([
            'client_id' => $client->id,
            'required_sizes' => '100x100',
        ]);

        $response = $this->actingAs($admin)->put(route('campaigns.update', $campaign), [
            'name' => 'Updated Campaign',
            'client_id' => $client->id,
            'required_sizes' => '1920x1080,300x250',
        ]);

        $response->assertRedirect(route('campaigns.index'));
        
        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'required_sizes' => '1920x1080,300x250',
        ]);
    }

    public function test_campaign_required_sizes_can_be_nullable()
    {
        $admin = User::factory()->create(['is_report' => false, 'is_admin' => true]);
        $client = Client::factory()->create();

        $response = $this->actingAs($admin)->post(route('campaigns.store'), [
            'name' => 'Test Campaign Null Sizes',
            'client_id' => $client->id,
            'required_sizes' => null,
        ]);

        $response->assertRedirect(route('campaigns.index'));
        
        $this->assertDatabaseHas('campaigns', [
            'name' => 'Test Campaign Null Sizes',
            'required_sizes' => null,
        ]);
    }
}
