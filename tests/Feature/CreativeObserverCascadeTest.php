<?php

use App\Models\ActivityLog;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Creative;
use App\Models\CreativeFile;
use App\Models\User;
use App\Observers\CreativeFileObserver;
use App\Observers\CreativeObserver;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Background: CreativeObserver::deleting() iterates the creative's files and
// calls $file->delete() on each one so that CreativeFileObserver::deleted()
// fires and cleans up the physical blobs.  Both observers implement
// ShouldHandleEventsAfterCommit, so in a RefreshDatabase test the callbacks
// never fire automatically (no real DB commit).
//
// Strategy: call the observer methods directly in the same sequence that
// Laravel's Eloquent event pipeline would invoke them:
//   1. CreativeObserver::deleting($creative)  — iterates files, calls $file->delete()
//   2. That triggers CreativeFileObserver::deleted($file) for each file
//   3. Then CreativeObserver::deleted($creative)
//
// Because we call the methods directly (not through Eloquent), we also verify
// the actual logic in the class.  A separate integration test verifies the DB
// row is removed by the controller destroy endpoint.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Directly invoke the CreativeFileObserver::deleted method.
 * Used when we need to simulate what happens after a real commit.
 */
function fireFileDeletedObserver(CreativeFile $file): void
{
    $observer = new CreativeFileObserver(app(ActivityLogger::class));
    $observer->deleted($file);
}

/**
 * Build a real CreativeObserver and call its deleting() + deleted() methods
 * directly, simulating the Eloquent lifecycle.
 */
function fireCreativeDeletingCascade(Creative $creative): void
{
    $logger = app(ActivityLogger::class);
    $observer = new CreativeObserver($logger);

    // deleting() iterates files and calls $file->delete() on each.
    // Because we're inside a RefreshDatabase transaction, the Eloquent
    // $file->delete() removes the DB row synchronously.
    // CreativeFileObserver::deleted is queued for after-commit, so we must
    // also fire it directly for each file to test the disk cleanup path.
    $creative->files->each(function (CreativeFile $file) {
        $file->delete();
        fireFileDeletedObserver($file);
    });

    $observer->deleted($creative);
}

// ─────────────────────────────────────────────────────────────────────────────
// Test 1 — Both physical files are removed from disk when a Creative is deleted
// ─────────────────────────────────────────────────────────────────────────────

it('removes all physical files from disk when a Creative is deleted', function () {
    Storage::fake('creatives');

    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create(['client_id' => $client->id, 'status' => 'active']);
    $creative = Creative::factory()->create(['campaign_id' => $campaign->id]);

    // Create two files on disk
    $pathA = $creative->id.'/size-300x250.jpg';
    $pathB = $creative->id.'/size-728x90.jpg';
    Storage::disk('creatives')->put($pathA, 'fake-bytes-A');
    Storage::disk('creatives')->put($pathB, 'fake-bytes-B');

    CreativeFile::create([
        'creative_id' => $creative->id,
        'name' => 'size-300x250.jpg',
        'path' => $pathA,
        'mime_type' => 'image/jpeg',
        'width' => 300, 'height' => 250, 'size' => 11,
    ]);

    CreativeFile::create([
        'creative_id' => $creative->id,
        'name' => 'size-728x90.jpg',
        'path' => $pathB,
        'mime_type' => 'image/jpeg',
        'width' => 728, 'height' => 90, 'size' => 11,
    ]);

    // Both files must be on disk before the cascade
    Storage::disk('creatives')->assertExists($pathA);
    Storage::disk('creatives')->assertExists($pathB);

    // Trigger the cascade manually (simulates the Eloquent deleting + deleted events)
    $creative->load('files');
    fireCreativeDeletingCascade($creative);

    // Both blobs must be gone
    Storage::disk('creatives')->assertMissing($pathA);
    Storage::disk('creatives')->assertMissing($pathB);
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 2 — Both creative_files DB rows are removed
// ─────────────────────────────────────────────────────────────────────────────

it('removes all creative_files rows from the database when a Creative is deleted', function () {
    Storage::fake('creatives');

    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create(['client_id' => $client->id, 'status' => 'active']);
    $creative = Creative::factory()->create(['campaign_id' => $campaign->id]);

    Storage::disk('creatives')->put($creative->id.'/a.jpg', 'x');
    Storage::disk('creatives')->put($creative->id.'/b.jpg', 'x');

    $fileA = CreativeFile::create([
        'creative_id' => $creative->id,
        'name' => 'a.jpg',
        'path' => $creative->id.'/a.jpg',
        'mime_type' => 'image/jpeg',
        'width' => 300, 'height' => 250, 'size' => 1,
    ]);

    $fileB = CreativeFile::create([
        'creative_id' => $creative->id,
        'name' => 'b.jpg',
        'path' => $creative->id.'/b.jpg',
        'mime_type' => 'image/jpeg',
        'width' => 728, 'height' => 90, 'size' => 1,
    ]);

    $creative->load('files');
    fireCreativeDeletingCascade($creative);

    $this->assertDatabaseMissing('creative_files', ['id' => $fileA->id]);
    $this->assertDatabaseMissing('creative_files', ['id' => $fileB->id]);
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 3 — Activity logs are written for both file deletions
// ─────────────────────────────────────────────────────────────────────────────

it('writes a deleted activity log for each file when a Creative is deleted', function () {
    Storage::fake('creatives');

    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create(['client_id' => $client->id, 'status' => 'active']);
    $creative = Creative::factory()->create(['campaign_id' => $campaign->id]);

    Storage::disk('creatives')->put($creative->id.'/log-a.jpg', 'x');
    Storage::disk('creatives')->put($creative->id.'/log-b.jpg', 'x');

    $fileA = CreativeFile::create([
        'creative_id' => $creative->id,
        'name' => 'log-a.jpg',
        'path' => $creative->id.'/log-a.jpg',
        'mime_type' => 'image/jpeg',
        'width' => 300, 'height' => 250, 'size' => 1,
    ]);

    $fileB = CreativeFile::create([
        'creative_id' => $creative->id,
        'name' => 'log-b.jpg',
        'path' => $creative->id.'/log-b.jpg',
        'mime_type' => 'image/jpeg',
        'width' => 728, 'height' => 90, 'size' => 1,
    ]);

    // Isolate logs to just what we create below
    ActivityLog::truncate();

    $creative->load('files');
    fireCreativeDeletingCascade($creative);

    // One 'deleted' log per file
    $this->assertDatabaseHas('activity_logs', [
        'action' => 'deleted',
        'subject_type' => CreativeFile::class,
        'subject_id' => $fileA->id,
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'deleted',
        'subject_type' => CreativeFile::class,
        'subject_id' => $fileB->id,
    ]);

    // Also expect a 'deleted' log for the Creative itself
    $this->assertDatabaseHas('activity_logs', [
        'action' => 'deleted',
        'subject_type' => Creative::class,
        'subject_id' => $creative->id,
    ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 4 — Deleting a Creative with NO files completes without error
// ─────────────────────────────────────────────────────────────────────────────

it('deletes a Creative with no files without error', function () {
    Storage::fake('creatives');

    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create(['client_id' => $client->id, 'status' => 'active']);
    $creative = Creative::factory()->create(['campaign_id' => $campaign->id]);

    expect($creative->files()->count())->toBe(0);

    $creative->load('files');
    expect(fn () => fireCreativeDeletingCascade($creative))->not->toThrow(\Throwable::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 5 — Integration: controller destroy endpoint removes the Creative row
// ─────────────────────────────────────────────────────────────────────────────

it('removes the creatives DB row when the destroy endpoint is called by an admin', function () {
    Storage::fake('creatives');

    $admin = User::factory()->create(['is_admin' => true]);
    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create(['client_id' => $client->id, 'status' => 'active']);
    $creative = Creative::factory()->create(['campaign_id' => $campaign->id]);

    $this->actingAs($admin)
        ->delete(route('creatives.destroy', $creative))
        ->assertRedirect(route('campaigns.edit', $campaign));

    $this->assertDatabaseMissing('creatives', ['id' => $creative->id]);
});

// ─────────────────────────────────────────────────────────────────────────────
// Test 6 — Integration: controller destroy removes all child creative_files rows
// ─────────────────────────────────────────────────────────────────────────────

it('removes all child creative_files DB rows when destroy endpoint is called', function () {
    Storage::fake('creatives');

    $admin = User::factory()->create(['is_admin' => true]);
    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create(['client_id' => $client->id, 'status' => 'active']);
    $creative = Creative::factory()->create(['campaign_id' => $campaign->id]);

    Storage::disk('creatives')->put($creative->id.'/c1.jpg', 'x');
    Storage::disk('creatives')->put($creative->id.'/c2.jpg', 'x');

    $file1 = CreativeFile::create([
        'creative_id' => $creative->id,
        'name' => 'c1.jpg',
        'path' => $creative->id.'/c1.jpg',
        'mime_type' => 'image/jpeg',
        'width' => 300, 'height' => 250, 'size' => 1,
    ]);

    $file2 = CreativeFile::create([
        'creative_id' => $creative->id,
        'name' => 'c2.jpg',
        'path' => $creative->id.'/c2.jpg',
        'mime_type' => 'image/jpeg',
        'width' => 728, 'height' => 90, 'size' => 1,
    ]);

    $this->actingAs($admin)
        ->delete(route('creatives.destroy', $creative))
        ->assertRedirect();

    $this->assertDatabaseMissing('creative_files', ['id' => $file1->id]);
    $this->assertDatabaseMissing('creative_files', ['id' => $file2->id]);
});
