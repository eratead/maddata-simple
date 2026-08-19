<?php

use App\Enums\HealthStatus;
use App\Models\Campaign;
use App\Models\CampaignData;
use App\Services\Health\Checks\DataStoreCheck;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    // The suite runs on SQLite; point the MySQL probe at whatever the test
    // connection actually is so D1 exercises real query timing.
    config(['health.mysql_connection' => config('database.default')]);
});

function dataChecks(): array
{
    return checksByKey(app(DataStoreCheck::class));
}

it('passes when the database answers quickly', function () {
    $check = dataChecks()['D1'];

    expect($check->status)->toBe(HealthStatus::OK)
        ->and($check->value)->toEndWith('ms');
});

it('reports the database as critical when the connection is unusable', function () {
    config(['health.mysql_connection' => 'no-such-connection']);

    $check = dataChecks()['D1'];

    // The guard turns any throwable into a CRIT tagged to the right node,
    // rather than letting it escape and take the whole snapshot down.
    expect($check->status)->toBe(HealthStatus::CRIT)
        ->and($check->value)->toBe('unreachable')
        ->and($check->node)->toBe('data');
});

it('escalates redis memory against its own ceiling', function (int $used, int $max, HealthStatus $expected) {
    Redis::shouldReceive('connection')->andReturnSelf();
    Redis::shouldReceive('info')->with('memory')->andReturn([
        'used_memory' => $used,
        'maxmemory' => $max,
    ]);

    expect(dataChecks()['D2']->status)->toBe($expected);
})->with([
    'comfortable' => [100, 1000, HealthStatus::OK],
    'at warn' => [900, 1000, HealthStatus::WARN],
    'at ceiling' => [1000, 1000, HealthStatus::WARN],
]);

it('treats redis with no ceiling as informational', function () {
    Redis::shouldReceive('connection')->andReturnSelf();
    Redis::shouldReceive('info')->with('memory')->andReturn(['used_memory' => 5_000_000, 'maxmemory' => 0]);

    $check = dataChecks()['D2'];

    expect($check->status)->toBe(HealthStatus::OK)
        ->and($check->value)->toContain('no ceiling set');
});

it('reports redis as critical when it cannot be reached', function () {
    Redis::shouldReceive('connection')->andThrow(new RuntimeException('Connection refused'));

    expect(dataChecks()['D2']->status)->toBe(HealthStatus::CRIT)
        ->and(dataChecks()['D2']->value)->toBe('unreachable');
});

it('passes campaign data freshness when there is no data at all', function () {
    $check = dataChecks()['D3'];

    expect($check->status)->toBe(HealthStatus::OK)
        ->and($check->value)->toBe('no campaign data yet');
});

it('warns on stale campaign data', function () {
    $campaign = Campaign::factory()->create();
    CampaignData::factory()->create([
        'campaign_id' => $campaign->id,
        'report_date' => now()->subDays(10)->toDateString(),
    ]);

    expect(dataChecks()['D3']->status)->toBe(HealthStatus::WARN);
});

it('never lets campaign data freshness reach critical', function () {
    // Manual-upload driven: "stale" often just means nobody uploaded this
    // week. A check that cries wolf gets ignored, and an ignored check is
    // worse than no check — so D3 warns and stops there, however old it gets.
    $campaign = Campaign::factory()->create();
    CampaignData::factory()->create([
        'campaign_id' => $campaign->id,
        'report_date' => now()->subYears(5)->toDateString(),
    ]);

    $check = dataChecks()['D3'];

    expect($check->status)->toBe(HealthStatus::WARN)
        ->and($check->status)->not->toBe(HealthStatus::CRIT);
});

it('passes campaign data freshness for a recent upload', function () {
    $campaign = Campaign::factory()->create();
    CampaignData::factory()->create([
        'campaign_id' => $campaign->id,
        'report_date' => now()->toDateString(),
    ]);

    expect(dataChecks()['D3']->status)->toBe(HealthStatus::OK);
});
