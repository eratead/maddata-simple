<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CampaignUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_report()
    {
        Storage::fake('local');

        $admin = User::factory()->create(['is_admin' => true]);
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create(['client_id' => $client->id]);

        $file = UploadedFile::fake()->create('report.csv', 10, 'text/csv');

        $response = $this->actingAs($admin)->post("/campaigns/{$campaign->id}/upload", [
            'date' => now()->toDateString(),
            'file' => $file,
        ]);

        $response->assertRedirect();
    }

    public function test_report_user_can_upload_for_assigned_client()
    {
        Storage::fake('local');

        $user = User::factory()->create(['is_report' => true]);
        $client = Client::factory()->create();
        $user->clients()->attach($client->id);

        $campaign = Campaign::factory()->create(['client_id' => $client->id]);
        $file = UploadedFile::fake()->create('report.csv', 10, 'text/csv');

        $response = $this->actingAs($user)->post("/campaigns/{$campaign->id}/upload", [
            'date' => now()->toDateString(),
            'file' => $file,
        ]);

        $response->assertRedirect();
    }

    public function test_unauthorized_user_cannot_upload()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create(['client_id' => $client->id]);

        $file = UploadedFile::fake()->create('report.csv', 10, 'text/csv');

        $response = $this->actingAs($user)->post("/campaigns/{$campaign->id}/upload", [
            'date' => now()->toDateString(),
            'file' => $file,
        ]);

        $response->assertForbidden();
    }
}
