<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Creative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CampaignChangeFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_only_latest_change_for_same_dimensions()
    {
        // Setup
        $admin = User::factory()->create(['is_admin' => true, 'is_report' => false]);
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create(['client_id' => $client->id]);
        $creative = Creative::factory()->create(['campaign_id' => $campaign->id]);

        // Clear any logs created by factories (e.g. Creative creation)
        ActivityLog::truncate();

        // Manually create ActivityLogs to simulate "Upload -> Delete -> Upload" sequence
        // We need to bypass the observer/controller stack to precisely control strict timing and content

        // 1. First Upload (Older)
        $log1 = new ActivityLog([
            'user_id' => $admin->id,
            'campaign_id' => $campaign->id,
            'subject_type' => 'App\Models\CreativeFile',
            'subject_id' => 1, // Dummy ID
            'action' => 'created',
            'description' => 'Uploaded file [300x250] "file A"',
            'changes' => ['width' => 300, 'height' => 250, 'creative_id' => $creative->id],
            'status' => 'pending',
        ]);
        $log1->created_at = now()->subMinutes(10);
        $log1->save();

        // 2. Delete (Middle)
        $log2 = new ActivityLog([
            'user_id' => $admin->id,
            'campaign_id' => $campaign->id,
            'subject_type' => 'App\Models\CreativeFile',
            'subject_id' => 1,
            'action' => 'deleted',
            'description' => 'Deleted file [300x250] "file A"',
            'changes' => ['width' => 300, 'height' => 250, 'creative_id' => $creative->id],
            'status' => 'pending',
        ]);
        $log2->created_at = now()->subMinutes(5);
        $log2->save();

        // 3. Second Upload (Latest) - This is the one we want to see
        $latestLog = new ActivityLog([
            'user_id' => $admin->id,
            'campaign_id' => $campaign->id,
            'subject_type' => 'App\Models\CreativeFile',
            'subject_id' => 2, // New Dummy ID
            'action' => 'created',
            'description' => 'Uploaded file [300x250] "file B"',
            'changes' => ['width' => 300, 'height' => 250, 'creative_id' => $creative->id],
            'status' => 'pending',
        ]);
        $latestLog->created_at = now();
        $latestLog->save();

        // Act
        $response = $this->actingAs($admin)->get(route('admin.campaign_changes.show', $campaign));

        // Assert
        $response->assertOk();
        
        // Should only see the LATEST log for 300x250
        $response->assertViewHas('logs', function ($logs) use ($latestLog) {
            return $logs->count() === 1 && $logs->first()->id === $latestLog->id;
        });
    }

    public function test_shows_logs_for_different_dimensions()
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_report' => false]);
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create(['client_id' => $client->id]);
        $creative = Creative::factory()->create(['campaign_id' => $campaign->id]);

        ActivityLog::truncate();

        // Log 1: 300x250
        ActivityLog::create([
            'user_id' => $admin->id,
            'campaign_id' => $campaign->id,
            'subject_type' => 'App\Models\CreativeFile',
            'subject_id' => 1,
            'action' => 'created',
            'description' => 'Uploaded 300x250',
            'changes' => ['width' => 300, 'height' => 250, 'creative_id' => $creative->id],
            'status' => 'pending',
        ]);

        // Log 2: 728x90
        ActivityLog::create([
            'user_id' => $admin->id,
            'campaign_id' => $campaign->id,
            'subject_type' => 'App\Models\CreativeFile',
            'subject_id' => 2,
            'action' => 'created',
            'description' => 'Uploaded 728x90',
            'changes' => ['width' => 728, 'height' => 90, 'creative_id' => $creative->id],
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.campaign_changes.show', $campaign));

        $response->assertOk();
        
        // Should see BOTH logs
        $response->assertViewHas('logs', function ($logs) {
            return $logs->count() === 2;
        });
    }

    public function test_shows_only_latest_creative_optimization_change()
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_report' => false]);
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create(['client_id' => $client->id]);

        ActivityLog::truncate();

        // 1. First toggle (Older)
        $log1 = new ActivityLog([
            'user_id' => $admin->id,
            'campaign_id' => $campaign->id,
            'subject_type' => Campaign::class,
            'subject_id' => $campaign->id,
            'action' => 'updated',
            'description' => 'Creative optimisation changed to CTR',
            'changes' => ['creative_optimization' => true],
            'status' => 'pending',
        ]);
        $log1->created_at = now()->subMinutes(10);
        $log1->save();

        // 2. Second toggle (Middle - same setting, just for test)
        $log2 = new ActivityLog([
            'user_id' => $admin->id,
            'campaign_id' => $campaign->id,
            'subject_type' => Campaign::class,
            'subject_id' => $campaign->id,
            'action' => 'updated',
            'description' => 'Creative optimisation changed to equal weights',
            'changes' => ['creative_optimization' => false],
            'status' => 'pending',
        ]);
        $log2->created_at = now()->subMinutes(5);
        $log2->save();

        // 3. Third toggle (Latest) - This is the one we want to see
        $latestLog = new ActivityLog([
            'user_id' => $admin->id,
            'campaign_id' => $campaign->id,
            'subject_type' => Campaign::class,
            'subject_id' => $campaign->id,
            'action' => 'updated',
            'description' => 'Creative optimisation changed to CTR',
            'changes' => ['creative_optimization' => true],
            'status' => 'pending',
        ]);
        $latestLog->created_at = now();
        $latestLog->save();

        // Act
        $response = $this->actingAs($admin)->get(route('admin.campaign_changes.show', $campaign));

        // Assert
        $response->assertOk();
        
        // Should only see the LATEST optimization log
        $response->assertViewHas('logs', function ($logs) use ($latestLog) {
            return $logs->count() === 1 && $logs->first()->id === $latestLog->id;
        });
    }
}
