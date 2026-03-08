<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAdmin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function makeCampaignManager(): User
    {
        $role = Role::create(['name' => 'Campaign Manager', 'permissions' => []]);
        return User::factory()->create(['role_id' => $role->id]);
    }

    private function makeRegularUser(): User
    {
        return User::factory()->create();
    }

    // -------------------------------------------------------------------------
    // Access control
    // -------------------------------------------------------------------------

    public function test_admin_can_view_token_index(): void
    {
        $response = $this->actingAs($this->makeAdmin())->get(route('tokens.index'));

        $response->assertOk();
    }

    public function test_campaign_manager_can_view_token_index(): void
    {
        $response = $this->actingAs($this->makeCampaignManager())->get(route('tokens.index'));

        $response->assertOk();
    }

    public function test_regular_user_cannot_access_token_index(): void
    {
        $response = $this->actingAs($this->makeRegularUser())->get(route('tokens.index'));

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_from_token_index(): void
    {
        $response = $this->get(route('tokens.index'));

        $response->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_user_can_create_a_named_token(): void
    {
        $user = $this->makeAdmin();

        $response = $this->actingAs($user)->post(route('tokens.create'), [
            'token_name' => 'My API Token',
        ]);

        $response->assertRedirect();
        $this->assertCount(1, $user->fresh()->tokens);
        $this->assertEquals('My API Token', $user->fresh()->tokens->first()->name);
    }

    public function test_created_token_expires_in_30_days(): void
    {
        $user = $this->makeAdmin();

        $this->actingAs($user)->post(route('tokens.create'), ['token_name' => 'Test Token']);

        $token = $user->fresh()->tokens->first();
        $this->assertNotNull($token->expires_at);
        $this->assertEqualsWithDelta(
            now()->addDays(30)->timestamp,
            $token->expires_at->timestamp,
            60 // within 60 seconds
        );
    }

    public function test_token_creation_requires_a_name(): void
    {
        $response = $this->actingAs($this->makeAdmin())->post(route('tokens.create'), [
            'token_name' => '',
        ]);

        $response->assertSessionHasErrors('token_name');
    }

    public function test_plain_text_token_is_flashed_to_session_after_creation(): void
    {
        $user = $this->makeAdmin();

        $response = $this->actingAs($user)->post(route('tokens.create'), [
            'token_name' => 'Flash Token',
        ]);

        $response->assertSessionHas('token');
        $this->assertNotEmpty(session('token'));
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_user_can_delete_own_token(): void
    {
        $user = $this->makeAdmin();
        $this->actingAs($user)->post(route('tokens.create'), ['token_name' => 'Delete Me']);
        $token = $user->fresh()->tokens->first();

        $response = $this->actingAs($user)->delete(route('tokens.destroy', $token->id));

        $response->assertRedirect();
        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_user_cannot_delete_another_users_token(): void
    {
        $owner = $this->makeAdmin();
        $other = $this->makeAdmin();

        $this->actingAs($owner)->post(route('tokens.create'), ['token_name' => 'Owners Token']);
        $token = $owner->fresh()->tokens->first();

        $response = $this->actingAs($other)->delete(route('tokens.destroy', $token->id));

        $response->assertStatus(404);
        $this->assertCount(1, $owner->fresh()->tokens);
    }

    // -------------------------------------------------------------------------
    // extend
    // -------------------------------------------------------------------------

    public function test_user_can_extend_token_by_30_days(): void
    {
        $user = $this->makeAdmin();
        $this->actingAs($user)->post(route('tokens.create'), ['token_name' => 'Extend Me']);
        $token = $user->fresh()->tokens->first();

        // Manually expire the token
        $token->expires_at = now()->subDay();
        $token->save();

        $this->actingAs($user)->post(route('tokens.extend', $token->id));

        $updatedToken = $user->fresh()->tokens->first();
        $this->assertEqualsWithDelta(
            now()->addDays(30)->timestamp,
            $updatedToken->expires_at->timestamp,
            60
        );
    }

    public function test_user_cannot_extend_another_users_token(): void
    {
        $owner = $this->makeAdmin();
        $other = $this->makeAdmin();

        $this->actingAs($owner)->post(route('tokens.create'), ['token_name' => 'Owners Token']);
        $originalToken = $owner->fresh()->tokens->first();
        $originalExpiry = $originalToken->expires_at;

        $response = $this->actingAs($other)->post(route('tokens.extend', $originalToken->id));

        $response->assertStatus(404);
        $this->assertEquals($originalExpiry->timestamp, $originalToken->fresh()->expires_at->timestamp);
    }
}
