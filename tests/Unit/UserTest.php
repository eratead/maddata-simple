<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_admin()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->assertTrue($admin->is_admin);
    }

    public function test_user_can_be_reporter()
    {
        $reporter = User::factory()->create(['is_report' => true]);
        $this->assertTrue($reporter->is_report);
    }

    public function test_user_can_have_clients()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        $user->clients()->attach($client);
        $this->assertTrue($user->clients->contains($client));
    }

    public function test_user_has_client_method_works()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $user->clients()->attach($client);

        $this->assertTrue($user->clients->contains($client));
    }
}
