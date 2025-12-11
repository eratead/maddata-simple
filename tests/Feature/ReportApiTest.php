<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Campaign;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_returns_dates_in_Ymd_format()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $user->clients()->attach($client);
        
        $campaign = Campaign::factory()->create([
            'client_id' => $client->id,
            'start_date' => '2023-01-01 10:00:00',
            'end_date' => '2023-01-31 15:30:00',
        ]);

        $this->actingAs($user);

        $response = $this->getJson(route('reports.summary', $campaign));

        $response->assertStatus(200)
            ->assertJson([
                'campaign_start' => '2023-01-01',
                'campaign_end' => '2023-01-31',
            ]);
    }
}
