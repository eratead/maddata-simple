<?php

use App\Mail\ActivityDigestMail;
use App\Models\ActivityLog;
use App\Models\Campaign;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Cache::flush();
});

it('sends a digest of new activity to opted-in active users', function () {
    $recipient = User::factory()->create([
        'is_active' => true,
        'receive_activity_notifications' => true,
        'email' => 'inbox@example.test',
    ]);

    $campaign = Campaign::factory()->create();
    // CampaignObserver::created() auto-creates a pending ActivityLog — drop it
    // so the assertion counts only the log this test explicitly creates.
    ActivityLog::where('campaign_id', $campaign->id)->delete();

    ActivityLog::factory()->create([
        'campaign_id' => $campaign->id,
        'created_at' => now()->subMinutes(30),
    ]);

    $this->artisan('digest:send-activity')
        ->expectsOutputToContain('Sent digest covering 1 log(s) to 1 recipient(s).')
        ->assertSuccessful();

    Mail::assertQueued(ActivityDigestMail::class, fn ($mail) => $mail->hasTo($recipient->email));
});

it('does not send when there is no activity since the last digest', function () {
    User::factory()->create([
        'is_active' => true,
        'receive_activity_notifications' => true,
    ]);

    Cache::put('last_activity_digest_sent_at', now()->subMinutes(30));

    $this->artisan('digest:send-activity')
        ->expectsOutputToContain('nothing to send')
        ->assertSuccessful();

    Mail::assertNotQueued(ActivityDigestMail::class);
});

it('skips users who have not opted in or are inactive', function () {
    User::factory()->create([
        'is_active' => true,
        'receive_activity_notifications' => false,
    ]);
    User::factory()->create([
        'is_active' => false,
        'receive_activity_notifications' => true,
    ]);

    $campaign = Campaign::factory()->create();
    ActivityLog::where('campaign_id', $campaign->id)->delete();

    ActivityLog::factory()->create([
        'campaign_id' => $campaign->id,
        'created_at' => now()->subMinutes(15),
    ]);

    $this->artisan('digest:send-activity')
        ->expectsOutputToContain('no opted-in active users')
        ->assertSuccessful();

    Mail::assertNotQueued(ActivityDigestMail::class);
});

it('only includes activity logs since the previous digest run', function () {
    User::factory()->create([
        'is_active' => true,
        'receive_activity_notifications' => true,
    ]);

    Cache::put('last_activity_digest_sent_at', now()->subHour());

    $campaign = Campaign::factory()->create();
    ActivityLog::where('campaign_id', $campaign->id)->delete();

    // Old log — already covered by the previous digest, must be excluded.
    ActivityLog::factory()->create([
        'campaign_id' => $campaign->id,
        'created_at' => now()->subHours(3),
    ]);

    // Recent logs — within the new window, must be included.
    ActivityLog::factory()->count(2)->create([
        'campaign_id' => $campaign->id,
        'created_at' => now()->subMinutes(30),
    ]);

    $this->artisan('digest:send-activity')->assertSuccessful();

    Mail::assertQueued(ActivityDigestMail::class, function ($mail) {
        return $mail->logs->count() === 2;
    });
});

it('updates the last-sent cache timestamp on every run', function () {
    Cache::put('last_activity_digest_sent_at', Carbon::parse('2026-01-01 00:00:00'));

    $this->artisan('digest:send-activity')->assertSuccessful();

    $stored = Carbon::parse(Cache::get('last_activity_digest_sent_at'));
    expect($stored->isToday())->toBeTrue();
});

it('does not run digest as a side-effect of ActivityLogger::log()', function () {
    User::factory()->create([
        'is_active' => true,
        'receive_activity_notifications' => true,
    ]);

    $logger = app(\App\Services\ActivityLogger::class);
    $campaign = Campaign::factory()->create();
    $logger->log('updated', $campaign, 'test change');

    Mail::assertNothingQueued();
});
