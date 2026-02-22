<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Creative;
use App\Models\CreativeFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreativeUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploading_file_detects_dimensions()
    {
        Storage::fake('creatives');
        $user = User::factory()->create(['is_admin' => true]);
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create(['client_id' => $client->id]);
        $creative = Creative::factory()->create(['campaign_id' => $campaign->id]);

        $file = UploadedFile::fake()->image('test_300x250.jpg', 300, 250);

        $response = $this->actingAs($user)->post(route('creatives.upload', $creative), [
            'files' => [$file],
        ]);

        $response->assertSessionHasNoErrors();
        
        $this->assertDatabaseHas('creative_files', [
            'creative_id' => $creative->id,
            'name' => 'test_300x250.jpg',
            'width' => 300,
            'height' => 250,
        ]);
    }

    public function test_uploading_same_dimensions_replaces_existing_file()
    {
        Storage::fake('creatives');
        $user = User::factory()->create(['is_admin' => true]);
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create(['client_id' => $client->id]);
        $creative = Creative::factory()->create(['campaign_id' => $campaign->id]);

        // Upload first file (300x250)
        $file1 = UploadedFile::fake()->image('first_300x250.jpg', 300, 250);
        $this->actingAs($user)->post(route('creatives.upload', $creative), [
            'files' => [$file1],
        ]);

        $firstFile = CreativeFile::where('name', 'first_300x250.jpg')->first();
        $this->assertNotNull($firstFile);
        $this->assertEquals(300, $firstFile->width);
        $this->assertEquals(250, $firstFile->height);

        // Upload second file with same dimensions (matches 300x250)
        $file2 = UploadedFile::fake()->image('second_300x250.jpg', 300, 250);
        $response = $this->actingAs($user)->post(route('creatives.upload', $creative), [
            'files' => [$file2],
        ]);

        $response->assertSessionHasNoErrors();

        // Assert first file is GONE
        $this->assertDatabaseMissing('creative_files', [
            'id' => $firstFile->id,
        ]);

        // Assert second file EXISTS
        $this->assertDatabaseHas('creative_files', [
            'creative_id' => $creative->id,
            'name' => 'second_300x250.jpg',
            'width' => 300,
            'height' => 250,
        ]);
        
        // Assert only 1 file total
        $this->assertEquals(1, $creative->files()->count());
    }

    public function test_uploading_different_dimensions_keeps_both_files()
    {
        Storage::fake('creatives');
        $user = User::factory()->create(['is_admin' => true]);
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create(['client_id' => $client->id]);
        $creative = Creative::factory()->create(['campaign_id' => $campaign->id]);

        // Upload first file (300x250)
        $file1 = UploadedFile::fake()->image('file_300x250.jpg', 300, 250);
        $this->actingAs($user)->post(route('creatives.upload', $creative), [
            'files' => [$file1],
        ]);

        // Upload second file (728x90)
        $file2 = UploadedFile::fake()->image('file_728x90.jpg', 728, 90);
        $this->actingAs($user)->post(route('creatives.upload', $creative), [
            'files' => [$file2],
        ]);

        // Assert both files exist
        $this->assertDatabaseHas('creative_files', ['name' => 'file_300x250.jpg']);
        $this->assertDatabaseHas('creative_files', ['name' => 'file_728x90.jpg']);
        $this->assertEquals(2, $creative->files()->count());
    }
}
