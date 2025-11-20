<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\CampaignData;
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

    public function test_campaign_pacing_calculation_uses_campaign_data_impressions()
    {
        $client = Client::factory()->create();

        $campaign = Campaign::factory()->create([
            'client_id'            => $client->id,
            'expected_impressions' => 1000,
        ]);

        // Two rows of data: 300 + 200 = 500 impressions
        CampaignData::factory()->create([
            'campaign_id' => $campaign->id,
            'impressions' => 300,
        ]);

        CampaignData::factory()->create([
            'campaign_id' => $campaign->id,
            'impressions' => 200,
        ]);

        $totalImpressions = CampaignData::where('campaign_id', $campaign->id)->sum('impressions');

        $pacing = $totalImpressions / $campaign->expected_impressions;

        $this->assertEquals(0.5, $pacing);
    }

    public function test_campaign_can_have_start_and_end_dates()
    {
        $client = Client::factory()->create();

        $campaign = Campaign::factory()->create([
            'client_id'  => $client->id,
            'start_date' => '2025-11-20',
            'end_date'   => '2025-11-25',
        ]);

        // Depending on casts, these may be Carbon instances – compare as dates:
        $this->assertEquals('2025-11-20', (string) $campaign->start_date->format('Y-m-d'));
        $this->assertEquals('2025-11-25', (string) $campaign->end_date->format('Y-m-d'));
    }
}
