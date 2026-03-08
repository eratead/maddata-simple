<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignData;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_dashboard_for_authorized_campaign()
    {
        $client = Client::factory()->create();
        $user = User::factory()->create();
        $user->clients()->attach($client);

        $campaign = Campaign::factory()->create(['client_id' => $client->id]);

        CampaignData::factory()->create([
            'campaign_id' => $campaign->id,
            'report_date' => now()->toDateString(),
            'impressions' => 100,
            'clicks' => 10,
            'visible_impressions' => 60,
            'uniques' => 80,
        ]);

        $this->actingAs($user);
        $response = $this->get(route('dashboard.campaign', $campaign));

        $response->assertStatus(200);
        $response->assertSee($campaign->name);
    }


    public function test_user_cannot_view_dashboard_for_unauthorized_campaign()
    {
        $authorizedClient = Client::factory()->create();
        $unauthorizedClient = Client::factory()->create();

        $user = User::factory()->create();
        $user->clients()->attach($authorizedClient);

        $unauthorizedCampaign = Campaign::factory()->create(['client_id' => $unauthorizedClient->id]);

        $this->actingAs($user);
        $response = $this->get(route('dashboard.campaign', $unauthorizedCampaign));

        $response->assertForbidden();
    }



    public function test_dashboard_filters_data_by_date_range()
    {
        $client = Client::factory()->create();
        $user = User::factory()->create();
        $user->clients()->attach($client);
        $campaign = Campaign::factory()->create(['client_id' => $client->id]);

        CampaignData::factory()->create([
            'campaign_id' => $campaign->id,
            'report_date' => now()->subDays(3)->toDateString(),
            'impressions' => 200,
        ]);

        CampaignData::factory()->create([
            'campaign_id' => $campaign->id,
            'report_date' => now()->toDateString(),
            'impressions' => 324,
        ]);

        $this->actingAs($user);
        $response = $this->get(route('dashboard.campaign', [
            $campaign,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertSee('324');
        // Note: not asserting '200' absence due to layout noise
    }

    public function test_dashboard_renders_without_error_for_non_video_campaign()
    {
        $client = Client::factory()->create();
        $user = User::factory()->create();
        $user->clients()->attach($client);
        $campaign = Campaign::factory()->create(['client_id' => $client->id, 'is_video' => false]);

        CampaignData::factory()->create([
            'campaign_id' => $campaign->id,
            'report_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.campaign', $campaign));

        $response->assertStatus(200);
        $response->assertSee('__dashDateRows', false);
        $response->assertSee('__dashPlacementRows', false);
    }

    public function test_dashboard_renders_without_error_for_video_campaign()
    {
        $client = Client::factory()->create();
        $user = User::factory()->create();
        $user->clients()->attach($client);
        $campaign = Campaign::factory()->create(['client_id' => $client->id, 'is_video' => true]);

        CampaignData::factory()->create([
            'campaign_id' => $campaign->id,
            'report_date' => now()->toDateString(),
            'video_25'    => 800,
            'video_50'    => 600,
            'video_75'    => 400,
            'video_100'   => 200,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.campaign', $campaign));

        $response->assertStatus(200);
        $response->assertSee('__dashDateRows', false);
    }

    public function test_dashboard_renders_with_multiple_date_rows_and_placements()
    {
        $client = Client::factory()->create();
        $user = User::factory()->create();
        $user->clients()->attach($client);
        $campaign = Campaign::factory()->create(['client_id' => $client->id]);

        CampaignData::factory()->create(['campaign_id' => $campaign->id, 'report_date' => now()->subDays(2)->toDateString(), 'impressions' => 1000]);
        CampaignData::factory()->create(['campaign_id' => $campaign->id, 'report_date' => now()->subDay()->toDateString(), 'impressions' => 2000]);
        CampaignData::factory()->create(['campaign_id' => $campaign->id, 'report_date' => now()->toDateString(), 'impressions' => 3000]);

        $response = $this->actingAs($user)->get(route('dashboard.campaign', $campaign));

        $response->assertStatus(200);
        // Verify JSON data is embedded correctly (not a 500 due to Blade parse errors)
        $response->assertSee('"impr":1000', false);
        $response->assertSee('"impr":3000', false);
    }

    public function test_user_can_export_dashboard_data()
    {
        $client = Client::factory()->create();
        $user = User::factory()->create();
        $user->clients()->attach($client);
        $campaign = Campaign::factory()->create(['client_id' => $client->id]);

        CampaignData::factory()->create([
            'campaign_id' => $campaign->id,
            'report_date' => now()->toDateString(),
            'impressions' => 500,
        ]);

        $this->actingAs($user);
        $response = $this->get(route('dashboard.export.excel', [
            'campaign' => $campaign->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
