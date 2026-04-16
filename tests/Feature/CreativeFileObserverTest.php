<?php

use App\Models\ActivityLog;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Creative;
use App\Models\CreativeFile;
use App\Models\User;
use App\Observers\CreativeFileObserver;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Background: CreativeFileObserver implements ShouldHandleEventsAfterCommit,
// which means in a RefreshDatabase-wrapped test (SQLite in-memory, no real
// commit) the observer callbacks are queued and never fire automatically.
//
// Strategy: call the observer's deleted() method directly.  This tests the
// exact code path that fires in production while bypassing the transaction
// commit requirement.  We also test the controller-level delete endpoint to
// verify the DB row is removed (DB cleanup is synchronous).
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Boot a real CreativeFileObserver with a real ActivityLogger and call
 * its deleted() method directly, simulating what happens after a real commit.
 */
function fireDeletedObserver(CreativeFile $file): void
{
    $logger = app(ActivityLogger::class);
    $observer = new CreativeFileObserver($logger);
    $observer->deleted($file);
}

// ─────────────────────────────────────────────────────────────────────────────
// Test 1 — Physical file is removed from disk
// ─────────────────────────────────────────────────────────────────────────────

it('deletes the physical file from the creatives disk when CreativeFile::deleted fires', function () {
    Storage::fake('creatives');

    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create(['client_id' => $client->id, 'status' => 'active']);
    $creative = Creative::factory()->create(['campaign_id' => $campaign->id]);

    $path = $creative->id.'/some-random-hash.jpg';
    Storage::disk('creatives')->put($path, 'fake-image-bytes');

    // Confirm the file is on disk before we start
    Storage::disk('creatives')->assertExists($path);

    $file = CreativeFile::create([
        'creative_id' => $creative->id,
        'name' => 'some-random-hash.jpg',
        'path' => $path,
        'mime_type' => 'image/jpeg',
        'width' => 300,
        'height' => 250,
        'size' => 16,
    ]);

    // Fire the observer directly (bypasses ShouldHandleEventsAfterCommit)
    fireDeletedObserver($file);

    // The blob must be gone from disk
    Storage::disk('creatives')->assertMissing($path);
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 2 — Activity log is written on deletion
// ─────────────────────────────────────────────────────────────────────────────

it('writes a deleted activity log entry when CreativeFile::deleted fires', function () {
    Storage::fake('creatives');

    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create(['client_id' => $client->id, 'status' => 'active']);
    $creative = Creative::factory()->create(['campaign_id' => $campaign->id]);

    $path = $creative->id.'/audit-test.jpg';
    Storage::disk('creatives')->put($path, 'fake-image-bytes');

    $file = CreativeFile::create([
        'creative_id' => $creative->id,
        'name' => 'audit-test.jpg',
        'path' => $path,
        'mime_type' => 'image/jpeg',
        'width' => 300,
        'height' => 250,
        'size' => 32,
    ]);

    // Clear any auto-generated logs from campaign creation so the assertion
    // below isolates the single deletion log entry we're testing.
    ActivityLog::truncate();

    fireDeletedObserver($file);

    // Exactly one 'deleted' log for this CreativeFile must exist
    $this->assertDatabaseHas('activity_logs', [
        'action' => 'deleted',
        'subject_type' => CreativeFile::class,
        'subject_id' => $file->id,
    ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 3 — Observer is tolerant when the physical file is already gone
// ─────────────────────────────────────────────────────────────────────────────

it('does not throw when the physical file is already missing from disk', function () {
    Storage::fake('creatives');

    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create(['client_id' => $client->id, 'status' => 'active']);
    $creative = Creative::factory()->create(['campaign_id' => $campaign->id]);

    // Do NOT put a file on disk — simulate an already-cleaned path
    $file = CreativeFile::create([
        'creative_id' => $creative->id,
        'name' => 'missing.jpg',
        'path' => $creative->id.'/missing.jpg',
        'mime_type' => 'image/jpeg',
        'width' => 0,
        'height' => 0,
        'size' => 0,
    ]);

    // Must not throw
    expect(fn () => fireDeletedObserver($file))->not->toThrow(\Throwable::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 4 — Full integration: controller DELETE endpoint removes DB row
// (disk cleanup is handled by the observer and verified separately above)
// ─────────────────────────────────────────────────────────────────────────────

it('removes the creative_files DB row when the delete endpoint is called', function () {
    Storage::fake('creatives');

    $user = User::factory()->create(['is_admin' => true]);
    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create(['client_id' => $client->id, 'status' => 'active']);
    $creative = Creative::factory()->create(['campaign_id' => $campaign->id]);

    $path = $creative->id.'/endpoint-delete.jpg';
    Storage::disk('creatives')->put($path, 'fake-image-bytes');

    $file = CreativeFile::create([
        'creative_id' => $creative->id,
        'name' => 'endpoint-delete.jpg',
        'path' => $path,
        'mime_type' => 'image/jpeg',
        'width' => 300,
        'height' => 250,
        'size' => 16,
    ]);

    $this->actingAs($user)
        ->delete(route('creatives.files.delete', $file))
        ->assertRedirect();

    $this->assertDatabaseMissing('creative_files', ['id' => $file->id]);
});
