<?php

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Creative;
use App\Models\CreativeFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// Unit-level: basename() strips path traversal components
it('basename strips path traversal from filenames', function () {
    expect(basename('../../etc/passwd'))->toBe('passwd');
    expect(basename('../../../windows/system32/cmd.exe'))->toBe('cmd.exe');
    expect(basename('/etc/shadow'))->toBe('shadow');
    expect(basename('normal-filename.jpg'))->toBe('normal-filename.jpg');
});

// Integration-level: the download endpoint uses basename() on $file->name
it('download endpoint uses basename of CreativeFile name as Content-Disposition filename', function () {
    Storage::fake('creatives');

    $admin = User::factory()->create(['is_admin' => true]);
    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
    ]);
    $creative = Creative::factory()->create(['campaign_id' => $campaign->id]);

    // Store a fake file on the creatives disk
    $fakePath = $creative->id.'/testfile.jpg';
    Storage::disk('creatives')->put($fakePath, 'fake-image-content');

    // Create a CreativeFile with a path-traversal name
    $file = CreativeFile::create([
        'creative_id' => $creative->id,
        'name' => '../../etc/passwd',
        'width' => 300,
        'height' => 250,
        'path' => $fakePath,
        'mime_type' => 'image/jpeg',
        'size' => 100,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('creatives.files.download', $file));

    // The response should succeed (not 404/403)
    $response->assertOk();

    // The Content-Disposition header must contain only the basename, not the full path
    $disposition = $response->headers->get('Content-Disposition');
    expect($disposition)->toContain('passwd');
    expect($disposition)->not->toContain('../');
    expect($disposition)->not->toContain('etc');
});

it('download endpoint denies unauthenticated access', function () {
    $file = CreativeFile::factory()->create();

    $this->get(route('creatives.files.download', $file))
        ->assertRedirect(route('login'));
})->skip(fn () => ! class_exists(\Database\Factories\CreativeFileFactory::class), 'No CreativeFile factory available');

it('download endpoint returns 404 when file does not exist on disk', function () {
    Storage::fake('creatives');

    $admin = User::factory()->create(['is_admin' => true]);
    $client = Client::factory()->create();
    $campaign = Campaign::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
    ]);
    $creative = Creative::factory()->create(['campaign_id' => $campaign->id]);

    $file = CreativeFile::create([
        'creative_id' => $creative->id,
        'name' => 'missing.jpg',
        'width' => 300,
        'height' => 250,
        'path' => $creative->id.'/nonexistent.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 100,
    ]);

    $this->actingAs($admin)
        ->get(route('creatives.files.download', $file))
        ->assertNotFound();
});

it('cross-tenant user cannot download creative file belonging to another client', function () {
    Storage::fake('creatives');

    // Tenant A's campaign and creative
    $clientA = Client::factory()->create();
    $campaignA = Campaign::factory()->create([
        'client_id' => $clientA->id,
        'status' => 'active',
    ]);
    $creativeA = Creative::factory()->create(['campaign_id' => $campaignA->id]);

    $fakePath = $creativeA->id.'/image.jpg';
    Storage::disk('creatives')->put($fakePath, 'content');

    $fileA = CreativeFile::create([
        'creative_id' => $creativeA->id,
        'name' => 'image.jpg',
        'width' => 300,
        'height' => 250,
        'path' => $fakePath,
        'mime_type' => 'image/jpeg',
        'size' => 100,
    ]);

    // Tenant B's user — has no access to clientA
    $clientB = Client::factory()->create();
    $userB = User::factory()->create();
    $userB->clients()->attach($clientB);

    $this->actingAs($userB)
        ->get(route('creatives.files.download', $fileA))
        ->assertForbidden();
});
