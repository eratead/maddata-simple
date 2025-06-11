<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_belongs_to_client()
    {
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create(['client_id' => $client->id]);

        $this->assertEquals($client->id, $campaign->client->id);
    }

    public function test_campaign_pacing_calculation()
    {
        $campaign = Campaign::factory()->make([
            'impressions' => 500,
            'expected_impressions' => 1000,
        ]);

        $pacing = $campaign->impressions / $campaign->expected_impressions;

        $this->assertEquals(0.5, $pacing);
    }
}
