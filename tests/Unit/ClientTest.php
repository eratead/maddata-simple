<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_have_campaigns()
    {
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create(['client_id' => $client->id]);

        $this->assertTrue($client->campaigns->contains($campaign));
    }

    public function test_client_can_have_users()
    {
        $client = Client::factory()->create();
        $user = User::factory()->create();
        $user->clients()->attach($client);

        $this->assertTrue($client->users->contains($user));
    }
}
