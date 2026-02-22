<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\ActivityLog;
use App\Models\Campaign;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_logs_index_renders_without_error()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $client = Client::factory()->create();
        $campaign = Campaign::create(['name' => 'Test Campaign', 'client_id' => $client->id]);
        
        ActivityLog::create([
            'user_id' => $admin->id,
            'campaign_id' => $campaign->id,
            'subject_type' => get_class($campaign),
            'subject_id' => $campaign->id,
            'action' => 'created',
            'description' => 'Test',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.activity-logs.index'));

        $response->assertStatus(200);
    }
}
