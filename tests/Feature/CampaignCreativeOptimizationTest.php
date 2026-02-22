<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignCreativeOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creative_optimization_toggle_logs_ctr_change()
    {
        // Setup
        $admin = User::factory()->create(['is_admin' => true]);
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create([
            'client_id' => $client->id,
            'creative_optimization' => false,
        ]);

        // Act: Turn ON optimization
        $this->actingAs($admin)->put(route('campaigns.update', $campaign), [
            'name' => $campaign->name,
            'client_id' => $campaign->client_id,
            'creative_optimization' => true,
        ]);

        // Assert DB
        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'creative_optimization' => 1,
        ]);

        // Assert Log
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => Campaign::class,
            'subject_id' => $campaign->id,
            'description' => 'Creative optimisation changed to CTR',
        ]);
    }

    public function test_creative_optimization_toggle_logs_equal_weights_change()
    {
        // Setup
        $admin = User::factory()->create(['is_admin' => true]);
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create([
            'client_id' => $client->id,
            'creative_optimization' => true,
        ]);

        // Act: Turn OFF optimization (send 0 or false)
        $this->actingAs($admin)->put(route('campaigns.update', $campaign), [
            'name' => $campaign->name,
            'client_id' => $campaign->client_id,
            'creative_optimization' => false,
        ]);

        // Assert DB
        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'creative_optimization' => 0,
        ]);

        // Assert Log
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => Campaign::class,
            'subject_id' => $campaign->id,
            'description' => 'Creative optimisation changed to equal weights',
        ]);
    }

    public function test_no_log_if_optimization_not_changed()
    {
        // Setup
        $admin = User::factory()->create(['is_admin' => true]);
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create([
            'client_id' => $client->id,
            'creative_optimization' => true,
        ]);

        // Clear logs
        ActivityLog::truncate();

        // Act: Update something else, keep optimization same
        $this->actingAs($admin)->put(route('campaigns.update', $campaign), [
            'name' => 'New Name',
            'client_id' => $campaign->client_id,
            'creative_optimization' => true,
        ]);

        // Assert DB
        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'name' => 'New Name',
            'creative_optimization' => 1,
        ]);

        // Assert Log: Should NOT have optimization message
        $this->assertDatabaseMissing('activity_logs', [
            'subject_type' => Campaign::class,
            'subject_id' => $campaign->id,
            'description' => 'Creative optimisation changed to CTR',
        ]);
    }
}
