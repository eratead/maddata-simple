<?php

use App\Models\Campaign;
use App\Models\CampaignData;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not render a Visible column header in the by-date table', function () {
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client);
    $campaign = Campaign::factory()->create(['client_id' => $client->id]);

    CampaignData::factory()->create([
        'campaign_id' => $campaign->id,
        'report_date' => now()->toDateString(),
        'impressions' => 500,
        'clicks' => 25,
        'visible_impressions' => 300,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.campaign', $campaign));

    $response->assertOk();

    // The Visible <th> bound to sortDateCol==='visible' must be gone from the by-date thead.
    $response->assertDontSee("toggleDateSort('visible')", false);

    // The row cell binding must also be absent.
    $response->assertDontSee('nf(row.visible)', false);
});
