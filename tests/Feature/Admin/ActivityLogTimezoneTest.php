<?php

use App\Models\ActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders activity log timestamps in the configured display timezone', function () {
    config(['app.display_timezone' => 'Asia/Jerusalem']);

    $admin = User::factory()->create(['is_admin' => true]);

    // Match the controller's default filter: action=created + subject_type=Campaign
    $log = ActivityLog::factory()->create([
        'action' => 'created',
        'subject_type' => 'App\Models\Campaign',
        'subject_id' => 1,
        'created_at' => Carbon::parse('2026-01-15 10:00:00', 'UTC'),
    ]);

    // Delete any auto-created logs from observers so we have a clean set
    ActivityLog::where('id', '!=', $log->id)->delete();

    $response = $this->actingAs($admin)->get(route('admin.activity-logs.index'));

    $response->assertOk();
    // 10:00 UTC == 12:00 Jerusalem (IST, UTC+2) in January
    $response->assertSeeText('Jan 15, 2026 12:00 PM');
    $response->assertDontSeeText('Jan 15, 2026 10:00 AM');
});
