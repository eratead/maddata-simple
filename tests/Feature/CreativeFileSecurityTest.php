<?php

use App\Models\ActivityLog;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Creative;
use App\Models\CreativeFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Create a user, client, campaign, creative, and a CreativeFile record on the
 * faked 'creatives' disk.  Returns all five objects as a tuple.
 */
function makeCreativeSetup(string $filename = 'banner.jpg'): array
{
    $client   = Client::factory()->create();
    $campaign = Campaign::factory()->create(['client_id' => $client->id, 'status' => 'active']);
    $creative = Creative::factory()->create(['campaign_id' => $campaign->id]);

    $path = $creative->id . '/' . $filename;
    Storage::disk('creatives')->put($path, 'fake-image-bytes');

    $file = CreativeFile::create([
        'creative_id' => $creative->id,
        'name'        => $filename,
        'path'        => $path,
        'mime_type'   => 'image/jpeg',
        'width'       => 300,
        'height'      => 250,
        'size'        => 16,
    ]);

    return [$client, $campaign, $creative, $file];
}

// ─────────────────────────────────────────────────────────────────────────────
// Preview route — ownership chain
// ─────────────────────────────────────────────────────────────────────────────

it('allows a user to preview a file belonging to their accessible campaign', function () {
    Storage::fake('creatives');

    $user = User::factory()->create(['is_admin' => false]);

    [$clientA, $campaignA, $creativeA, $fileA] = makeCreativeSetup('bannerA.jpg');
    $user->clients()->attach($clientA);

    $this->actingAs($user)
        ->get(route('creatives.files.preview', $fileA))
        ->assertOk();
});

it('returns 403 when user tries to preview a file from a campaign they cannot access', function () {
    Storage::fake('creatives');

    $user = User::factory()->create(['is_admin' => false]);

    // Campaign A — user has access
    [$clientA, $campaignA, $creativeA, $fileA] = makeCreativeSetup('bannerA.jpg');
    $user->clients()->attach($clientA);

    // Campaign B — user has NO access
    [$clientB, $campaignB, $creativeB, $fileB] = makeCreativeSetup('bannerB.jpg');

    // User attempts to preview a file from campaign B
    $this->actingAs($user)
        ->get(route('creatives.files.preview', $fileB))
        ->assertForbidden();
});

it('returns 401 on preview when unauthenticated', function () {
    Storage::fake('creatives');

    [, , , $file] = makeCreativeSetup();

    $this->get(route('creatives.files.preview', $file))
        ->assertRedirect(route('login'));
});

// ─────────────────────────────────────────────────────────────────────────────
// Download route — ownership chain
// ─────────────────────────────────────────────────────────────────────────────

it('allows a user to download a file belonging to their accessible campaign', function () {
    Storage::fake('creatives');

    $user = User::factory()->create(['is_admin' => false]);

    [$clientA, $campaignA, $creativeA, $fileA] = makeCreativeSetup('bannerA_dl.jpg');
    $user->clients()->attach($clientA);

    $this->actingAs($user)
        ->get(route('creatives.files.download', $fileA))
        ->assertOk();
});

it('returns 403 when user tries to download a file from a campaign they cannot access', function () {
    Storage::fake('creatives');

    $user = User::factory()->create(['is_admin' => false]);

    // Campaign A — user has access
    [$clientA, $campaignA, $creativeA, $fileA] = makeCreativeSetup('bannerA_dl.jpg');
    $user->clients()->attach($clientA);

    // Campaign B — user has NO access
    [$clientB, $campaignB, $creativeB, $fileB] = makeCreativeSetup('bannerB_dl.jpg');

    $this->actingAs($user)
        ->get(route('creatives.files.download', $fileB))
        ->assertForbidden();
});

it('returns 401 on download when unauthenticated', function () {
    Storage::fake('creatives');

    [, , , $file] = makeCreativeSetup();

    $this->get(route('creatives.files.download', $file))
        ->assertRedirect(route('login'));
});

// ─────────────────────────────────────────────────────────────────────────────
// Delete route — ownership chain
// ─────────────────────────────────────────────────────────────────────────────

it('allows a user to delete a file belonging to their accessible campaign', function () {
    Storage::fake('creatives');

    $user = User::factory()->create(['is_admin' => false]);

    [$clientA, $campaignA, $creativeA, $fileA] = makeCreativeSetup('banner_del.jpg');
    $user->clients()->attach($clientA);

    // Clean up the auto-created activity log from campaign creation so we
    // don't conflict with observer-based log assertions elsewhere.
    ActivityLog::where('campaign_id', $campaignA->id)->delete();

    $this->actingAs($user)
        ->delete(route('creatives.files.delete', $fileA))
        ->assertRedirect();

    $this->assertDatabaseMissing('creative_files', ['id' => $fileA->id]);
});

it('returns 403 when user tries to delete a file from a campaign they cannot access', function () {
    Storage::fake('creatives');

    $user = User::factory()->create(['is_admin' => false]);

    // Campaign A — user has access
    [$clientA, $campaignA, $creativeA, $fileA] = makeCreativeSetup('bannerA_del.jpg');
    $user->clients()->attach($clientA);

    // Campaign B — user has NO access
    [$clientB, $campaignB, $creativeB, $fileB] = makeCreativeSetup('bannerB_del.jpg');

    $this->actingAs($user)
        ->delete(route('creatives.files.delete', $fileB))
        ->assertForbidden();

    // File B must still exist in the DB — nothing was deleted
    $this->assertDatabaseHas('creative_files', ['id' => $fileB->id]);
});

it('returns 401 on delete when unauthenticated', function () {
    Storage::fake('creatives');

    [, , , $file] = makeCreativeSetup();

    $this->delete(route('creatives.files.delete', $file))
        ->assertRedirect(route('login'));
});

// ─────────────────────────────────────────────────────────────────────────────
// Cross-campaign IDOR: user has access to BOTH campaigns but files belong to
// different creatives — verify a file cannot be confused with another's file.
// ─────────────────────────────────────────────────────────────────────────────

it('admin can preview files from any campaign', function () {
    Storage::fake('creatives');

    $admin = User::factory()->create(['is_admin' => true]);

    [, , , $fileA] = makeCreativeSetup('adminA.jpg');
    [, , , $fileB] = makeCreativeSetup('adminB.jpg');

    $this->actingAs($admin)
        ->get(route('creatives.files.preview', $fileA))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('creatives.files.preview', $fileB))
        ->assertOk();
});

it('returns 404 for a preview request when the creative_file record does not exist', function () {
    Storage::fake('creatives');

    $user = User::factory()->create(['is_admin' => true]);

    $this->actingAs($user)
        ->get(route('creatives.files.preview', 99999))
        ->assertNotFound();
});
