<?php

use App\Mail\ActivityDigestMail;
use App\Models\ActivityLog;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders activity digest mail with Israel-time timestamps', function () {
    config(['app.display_timezone' => 'Asia/Jerusalem']);

    $client = Client::factory()->create(['name' => 'Test Client']);
    $campaign = Campaign::factory()->create([
        'client_id' => $client->id,
        'name' => 'Test Campaign',
        'status' => 'active',
    ]);

    $user = User::factory()->create();

    $log = ActivityLog::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
        'created_at' => Carbon::parse('2026-07-15 09:00:00', 'UTC'),
    ]);

    $mail = new ActivityDigestMail(collect([$log]));
    $rendered = $mail->render();

    // 09:00 UTC == 12:00 Jerusalem (IDT, UTC+3) in July
    expect($rendered)->toContain('12:00');
    expect($rendered)->not->toContain('09:00');
});
