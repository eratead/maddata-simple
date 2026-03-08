<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignData;
use App\Models\Client;
use App\Models\PlacementData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportApiExtendedTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function userWithCampaign(): array
    {
        $user     = User::factory()->create();
        $client   = Client::factory()->create();
        $user->clients()->attach($client);
        $campaign = Campaign::factory()->create(['client_id' => $client->id]);

        return [$user, $campaign];
    }

    // -------------------------------------------------------------------------
    // byDate
    // -------------------------------------------------------------------------

    public function test_by_date_returns_correct_structure(): void
    {
        [$user, $campaign] = $this->userWithCampaign();
        CampaignData::factory()->create([
            'campaign_id' => $campaign->id,
            'report_date' => '2024-01-15',
            'impressions'  => 1000,
            'clicks'       => 20,
        ]);

        $response = $this->actingAs($user)->getJson(route('reports.by-date', $campaign));

        $response->assertOk()
            ->assertJsonStructure([
                'campaign_id',
                'campaign_name',
                'by_date' => [['date', 'impressions', 'clicks', 'ctr']],
            ]);
    }

    public function test_by_date_returns_one_row_per_date(): void
    {
        [$user, $campaign] = $this->userWithCampaign();
        CampaignData::factory()->create([
            'campaign_id' => $campaign->id,
            'report_date' => '2024-01-10',
            'impressions'  => 600,
            'clicks'       => 10,
        ]);
        CampaignData::factory()->create([
            'campaign_id' => $campaign->id,
            'report_date' => '2024-01-11',
            'impressions'  => 400,
            'clicks'       => 5,
        ]);

        $response = $this->actingAs($user)->getJson(route('reports.by-date', $campaign));

        $byDate = $response->json('by_date');
        $this->assertCount(2, $byDate);
        $dates = array_column($byDate, 'date');
        $this->assertContains('2024-01-10', $dates);
        $this->assertContains('2024-01-11', $dates);
    }

    public function test_by_date_calculates_ctr_correctly(): void
    {
        [$user, $campaign] = $this->userWithCampaign();
        CampaignData::factory()->create([
            'campaign_id' => $campaign->id,
            'report_date' => '2024-03-01',
            'impressions'  => 1000,
            'clicks'       => 25,
        ]);

        $response = $this->actingAs($user)->getJson(route('reports.by-date', $campaign));

        $ctr = $response->json('by_date.0.ctr');
        $this->assertEquals(2.5, $ctr);
    }

    public function test_by_date_filters_by_date_range(): void
    {
        [$user, $campaign] = $this->userWithCampaign();
        CampaignData::factory()->create([
            'campaign_id' => $campaign->id,
            'report_date' => '2024-01-05',
            'impressions'  => 100,
        ]);
        CampaignData::factory()->create([
            'campaign_id' => $campaign->id,
            'report_date' => '2024-01-20',
            'impressions'  => 200,
        ]);

        $response = $this->actingAs($user)->getJson(
            route('reports.by-date', $campaign) . '?start=2024-01-01&end=2024-01-10'
        );

        $byDate = $response->json('by_date');
        $this->assertCount(1, $byDate);
        $this->assertEquals('2024-01-05', $byDate[0]['date']);
    }

    public function test_by_date_returns_video_metrics_for_video_campaigns(): void
    {
        [$user, $campaign] = $this->userWithCampaign();
        $campaign->update(['is_video' => true]);
        CampaignData::factory()->create([
            'campaign_id' => $campaign->id,
            'report_date' => '2024-01-01',
        ]);

        $response = $this->actingAs($user)->getJson(route('reports.by-date', $campaign));

        $response->assertJsonStructure([
            'by_date' => [['video_25', 'video_50', 'video_75', 'video_100']],
        ]);
    }

    public function test_by_date_does_not_include_video_metrics_for_non_video_campaigns(): void
    {
        [$user, $campaign] = $this->userWithCampaign();
        $campaign->update(['is_video' => false]);
        CampaignData::factory()->create(['campaign_id' => $campaign->id, 'report_date' => '2024-01-01']);

        $response = $this->actingAs($user)->getJson(route('reports.by-date', $campaign));

        $this->assertArrayNotHasKey('video_25', $response->json('by_date.0') ?? []);
    }

    public function test_by_date_rejects_unauthorized_user(): void
    {
        $campaign    = Campaign::factory()->create();
        $outsider    = User::factory()->create();

        $response = $this->actingAs($outsider)->getJson(route('reports.by-date', $campaign));

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // byPlacement
    // -------------------------------------------------------------------------

    public function test_by_placement_returns_correct_structure(): void
    {
        [$user, $campaign] = $this->userWithCampaign();
        PlacementData::create([
            'campaign_id'         => $campaign->id,
            'name'                => 'Homepage Banner',
            'report_date'         => '2024-01-01',
            'impressions'         => 500,
            'clicks'              => 10,
            'visible_impressions' => 400,
        ]);

        $response = $this->actingAs($user)->getJson(route('reports.by-placement', $campaign));

        $response->assertOk()
            ->assertJsonStructure([
                'campaign_id',
                'campaign_name',
                'by_placement' => [['placement', 'impressions', 'clicks', 'ctr', 'visible_impressions']],
            ]);
    }

    public function test_by_placement_aggregates_rows_by_placement_name(): void
    {
        [$user, $campaign] = $this->userWithCampaign();
        PlacementData::create([
            'campaign_id' => $campaign->id,
            'name'        => 'Banner A',
            'report_date' => '2024-01-01',
            'impressions' => 300,
            'clicks'      => 6,
        ]);
        PlacementData::create([
            'campaign_id' => $campaign->id,
            'name'        => 'Banner A',
            'report_date' => '2024-01-02',
            'impressions' => 200,
            'clicks'      => 4,
        ]);

        $response = $this->actingAs($user)->getJson(route('reports.by-placement', $campaign));

        $byPlacement = $response->json('by_placement');
        $this->assertCount(1, $byPlacement);
        $this->assertEquals(500, $byPlacement[0]['impressions']);
        $this->assertEquals(10, $byPlacement[0]['clicks']);
    }

    public function test_by_placement_filters_by_date_range(): void
    {
        [$user, $campaign] = $this->userWithCampaign();
        PlacementData::create([
            'campaign_id' => $campaign->id,
            'name'        => 'In Range',
            'report_date' => '2024-02-05',
            'impressions' => 100,
            'clicks'      => 2,
        ]);
        PlacementData::create([
            'campaign_id' => $campaign->id,
            'name'        => 'Out of Range',
            'report_date' => '2024-03-01',
            'impressions' => 50,
            'clicks'      => 1,
        ]);

        $response = $this->actingAs($user)->getJson(
            route('reports.by-placement', $campaign) . '?start=2024-02-01&end=2024-02-28'
        );

        $placements = collect($response->json('by_placement'))->pluck('placement');
        $this->assertContains('In Range', $placements->all());
        $this->assertNotContains('Out of Range', $placements->all());
    }

    public function test_by_placement_rejects_unauthorized_user(): void
    {
        $campaign = Campaign::factory()->create();
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider)->getJson(route('reports.by-placement', $campaign));

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // campaigns
    // -------------------------------------------------------------------------

    public function test_campaigns_returns_only_accessible_campaigns_for_regular_user(): void
    {
        $user   = User::factory()->create();
        $client = Client::factory()->create();
        $user->clients()->attach($client);

        $ownCampaign   = Campaign::factory()->create(['client_id' => $client->id,                'name' => 'My Campaign']);
        $otherCampaign = Campaign::factory()->create(['name' => 'Other Campaign']);

        $response = $this->actingAs($user)->getJson(route('reports.campaigns'));

        $names = collect($response->json())->pluck('name');
        $this->assertContains('My Campaign', $names->all());
        $this->assertNotContains('Other Campaign', $names->all());
    }

    public function test_campaigns_admin_sees_all_campaigns(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Campaign::factory()->create(['name' => 'Campaign Alpha']);
        Campaign::factory()->create(['name' => 'Campaign Beta']);

        $response = $this->actingAs($admin)->getJson(route('reports.campaigns'));

        $names = collect($response->json())->pluck('name');
        $this->assertContains('Campaign Alpha', $names->all());
        $this->assertContains('Campaign Beta', $names->all());
    }

    public function test_campaigns_returns_expected_fields(): void
    {
        $admin  = User::factory()->create(['is_admin' => true]);
        Campaign::factory()->create();

        $response = $this->actingAs($admin)->getJson(route('reports.campaigns'));

        $response->assertJsonStructure([['id', 'name', 'client_name', 'client_id', 'created_at']]);
    }

    public function test_campaigns_filters_by_date_range(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Campaign::factory()->create(['name' => 'Old Campaign',     'created_at' => '2023-01-01']);
        Campaign::factory()->create(['name' => 'Current Campaign', 'created_at' => '2024-06-15']);

        $response = $this->actingAs($admin)->getJson(
            route('reports.campaigns') . '?start=2024-01-01&end=2024-12-31'
        );

        $names = collect($response->json())->pluck('name');
        $this->assertContains('Current Campaign', $names->all());
        $this->assertNotContains('Old Campaign', $names->all());
    }
}
