<?php

use App\Models\ActivityLog;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;
use App\Observers\CampaignObserver;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Call the observer's updated() method directly, since ShouldHandleEventsAfterCommit
 * prevents it from firing inside RefreshDatabase transactions.
 */
function triggerObserverUpdate(Campaign $campaign): void
{
    $observer = new CampaignObserver(app(ActivityLogger::class));
    $observer->updated($campaign);
}

function createCampaignForObserverTest(array $attrs = []): Campaign
{
    $admin = User::factory()->create(['is_admin' => true]);
    auth()->login($admin);

    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create(array_merge([
        'client_id' => $client->id,
        'status' => 'active',
        'budget' => 10000,
        'name' => 'Original Name',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'expected_impressions' => 100000,
    ], $attrs));

    // Clear auto-created logs from CampaignObserver::created()
    ActivityLog::where('campaign_id', $campaign->id)->delete();

    return $campaign;
}

// ─────────────────────────────────────────────────────────
// Name change
// ─────────────────────────────────────────────────────────

it('logs name change via observer', function () {
    $campaign = createCampaignForObserverTest();

    $campaign->name = 'New Campaign Name';
    $campaign->save();
    triggerObserverUpdate($campaign);

    $log = ActivityLog::where('campaign_id', $campaign->id)
        ->where('description', 'like', '%Name changed%')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->description)->toContain('Original Name');
    expect($log->description)->toContain('New Campaign Name');
    expect($log->changes['name']['old'])->toBe('Original Name');
    expect($log->changes['name']['new'])->toBe('New Campaign Name');
    expect($log->status)->toBe('pending');
});

// ─────────────────────────────────────────────────────────
// Start date change
// ─────────────────────────────────────────────────────────

it('logs start date change via observer', function () {
    $campaign = createCampaignForObserverTest();

    $campaign->start_date = '2026-06-01';
    $campaign->save();
    triggerObserverUpdate($campaign);

    $log = ActivityLog::where('campaign_id', $campaign->id)
        ->where('description', 'like', '%Start date changed%')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->description)->toContain('2026-01-01');
    expect($log->description)->toContain('2026-06-01');
    expect($log->changes['start_date']['old'])->toBe('2026-01-01');
    expect($log->changes['start_date']['new'])->toBe('2026-06-01');
});

// ─────────────────────────────────────────────────────────
// End date change
// ─────────────────────────────────────────────────────────

it('logs end date change via observer', function () {
    $campaign = createCampaignForObserverTest();

    $campaign->end_date = '2027-03-31';
    $campaign->save();
    triggerObserverUpdate($campaign);

    $log = ActivityLog::where('campaign_id', $campaign->id)
        ->where('description', 'like', '%End date changed%')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->description)->toContain('2026-12-31');
    expect($log->description)->toContain('2027-03-31');
    expect($log->changes['end_date']['old'])->toBe('2026-12-31');
    expect($log->changes['end_date']['new'])->toBe('2027-03-31');
});

// ─────────────────────────────────────────────────────────
// Status change
// ─────────────────────────────────────────────────────────

it('logs status change via observer', function () {
    $campaign = createCampaignForObserverTest();

    $campaign->status = 'paused';
    $campaign->save();
    triggerObserverUpdate($campaign);

    $log = ActivityLog::where('campaign_id', $campaign->id)
        ->where('description', 'like', '%Status changed%')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->description)->toContain('active');
    expect($log->description)->toContain('paused');
    expect($log->changes['status']['old'])->toBe('active');
    expect($log->changes['status']['new'])->toBe('paused');
});

// ─────────────────────────────────────────────────────────
// Expected impressions change
// ─────────────────────────────────────────────────────────

it('logs expected impressions change via observer', function () {
    $campaign = createCampaignForObserverTest();

    $campaign->expected_impressions = 500000;
    $campaign->save();
    triggerObserverUpdate($campaign);

    $log = ActivityLog::where('campaign_id', $campaign->id)
        ->where('description', 'like', '%Expected impressions changed%')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->description)->toContain('100,000');
    expect($log->description)->toContain('500,000');
});

// ─────────────────────────────────────────────────────────
// Multiple fields changed at once
// ─────────────────────────────────────────────────────────

it('creates separate log entries for each changed field', function () {
    $campaign = createCampaignForObserverTest();

    $campaign->name = 'Renamed Campaign';
    $campaign->start_date = '2026-02-01';
    $campaign->budget = 20000;
    $campaign->save();
    triggerObserverUpdate($campaign);

    $logs = ActivityLog::where('campaign_id', $campaign->id)->get();

    expect($logs->filter(fn ($l) => str_contains($l->description, 'Name changed'))->count())->toBe(1);
    expect($logs->filter(fn ($l) => str_contains($l->description, 'Start date changed'))->count())->toBe(1);
    expect($logs->filter(fn ($l) => str_contains($l->description, 'Budget changed'))->count())->toBe(1);
});

// ─────────────────────────────────────────────────────────
// No log when field is not dirty
// ─────────────────────────────────────────────────────────

it('does not log unchanged fields', function () {
    $campaign = createCampaignForObserverTest();

    // Only change status, leave name and dates alone
    $campaign->status = 'completed';
    $campaign->save();
    triggerObserverUpdate($campaign);

    $logs = ActivityLog::where('campaign_id', $campaign->id)->get();

    expect($logs->filter(fn ($l) => str_contains($l->description, 'Name changed'))->count())->toBe(0);
    expect($logs->filter(fn ($l) => str_contains($l->description, 'Start date changed'))->count())->toBe(0);
    expect($logs->filter(fn ($l) => str_contains($l->description, 'End date changed'))->count())->toBe(0);
    expect($logs->filter(fn ($l) => str_contains($l->description, 'Status changed'))->count())->toBe(1);
});
