<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignChangeControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $nonAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->nonAdmin = User::factory()->create();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function pendingLog(Campaign $campaign, array $overrides = []): ActivityLog
    {
        return ActivityLog::create(array_merge([
            'user_id' => $this->admin->id,
            'campaign_id' => $campaign->id,
            'subject_type' => Campaign::class,
            'subject_id' => $campaign->id,
            'action' => 'updated',
            'description' => 'Test change',
            'status' => 'pending',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_admin_can_view_campaign_changes_index(): void
    {
        $campaign = Campaign::factory()->create();
        $this->pendingLog($campaign);

        $response = $this->actingAs($this->admin)->get(route('admin.campaign_changes.index'));

        $response->assertOk();
    }

    public function test_index_only_shows_campaigns_with_pending_logs(): void
    {
        $withPending = Campaign::factory()->create(['name' => 'Has Pending']);
        $withoutPending = Campaign::factory()->create(['name' => 'No Pending']);

        // CampaignObserver auto-creates a pending log on creation; remove the one for $withoutPending
        ActivityLog::where('campaign_id', $withoutPending->id)->delete();

        $this->pendingLog($withPending);

        $response = $this->actingAs($this->admin)->get(route('admin.campaign_changes.index'));

        $response->assertSee('Has Pending');
        $response->assertDontSee('No Pending');
    }

    public function test_index_does_not_show_campaigns_with_only_handled_logs(): void
    {
        $campaign = Campaign::factory()->create(['name' => 'Already Handled']);
        // Clear observer-created pending log(s), then add only a handled one
        ActivityLog::where('campaign_id', $campaign->id)->delete();
        ActivityLog::create([
            'user_id' => $this->admin->id,
            'campaign_id' => $campaign->id,
            'subject_type' => Campaign::class,
            'subject_id' => $campaign->id,
            'action' => 'updated',
            'description' => 'Already done',
            'status' => 'handled',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.campaign_changes.index'));

        $response->assertDontSee('Already Handled');
    }

    public function test_non_admin_cannot_view_campaign_changes_index(): void
    {
        $response = $this->actingAs($this->nonAdmin)->get(route('admin.campaign_changes.index'));

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_admin_can_view_campaign_change_details(): void
    {
        $campaign = Campaign::factory()->create();
        $this->pendingLog($campaign, ['description' => 'Updated targeting rules']);

        $response = $this->actingAs($this->admin)->get(route('admin.campaign_changes.show', $campaign));

        $response->assertOk();
        $response->assertSee('Updated targeting rules');
    }

    public function test_show_does_not_include_handled_logs(): void
    {
        $campaign = Campaign::factory()->create();
        $this->pendingLog($campaign, ['description' => 'Pending Change']);
        ActivityLog::create([
            'user_id' => $this->admin->id,
            'campaign_id' => $campaign->id,
            'subject_type' => Campaign::class,
            'subject_id' => $campaign->id,
            'action' => 'updated',
            'description' => 'Handled Change',
            'status' => 'handled',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.campaign_changes.show', $campaign));

        $response->assertSee('Pending Change');
        $response->assertDontSee('Handled Change');
    }

    // -------------------------------------------------------------------------
    // markAsHandled
    // -------------------------------------------------------------------------

    public function test_admin_can_mark_all_campaign_logs_as_handled(): void
    {
        $campaign = Campaign::factory()->create();
        $log1 = $this->pendingLog($campaign);
        $log2 = $this->pendingLog($campaign);

        $this->actingAs($this->admin)->post(route('admin.campaign_changes.handle', $campaign));

        $this->assertEquals('handled', $log1->fresh()->status);
        $this->assertEquals('handled', $log2->fresh()->status);
    }

    public function test_admin_can_mark_selected_logs_as_handled(): void
    {
        $campaign = Campaign::factory()->create();
        $log1 = $this->pendingLog($campaign);
        $log2 = $this->pendingLog($campaign);

        $this->actingAs($this->admin)->post(
            route('admin.campaign_changes.handle', $campaign),
            ['log_ids' => [$log1->id]]
        );

        $this->assertEquals('handled', $log1->fresh()->status);
        $this->assertEquals('pending', $log2->fresh()->status); // untouched
    }

    public function test_mark_as_handled_redirects_to_index(): void
    {
        $campaign = Campaign::factory()->create();
        $this->pendingLog($campaign);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.campaign_changes.handle', $campaign));

        $response->assertRedirect(route('admin.campaign_changes.index'));
    }

    public function test_non_admin_cannot_mark_logs_as_handled(): void
    {
        $campaign = Campaign::factory()->create();
        $log = $this->pendingLog($campaign);

        $this->actingAs($this->nonAdmin)
            ->post(route('admin.campaign_changes.handle', $campaign));

        $this->assertEquals('pending', $log->fresh()->status);
    }
}
