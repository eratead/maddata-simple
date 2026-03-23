<?php

namespace Tests\Feature;

use App\Models\Audience;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignAudienceTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // audiencesJson
    // -------------------------------------------------------------------------

    public function test_admin_can_fetch_audiences_json_for_campaign()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $campaign = Campaign::factory()->create();
        Audience::factory()->count(3)->create();

        $this->actingAs($admin);
        $response = $this->getJson(route('campaigns.audiences.json', $campaign));

        $response->assertOk();
        $response->assertJsonCount(3);
        $response->assertJsonStructure([['id', 'main_category', 'sub_category', 'name', 'estimated_users', 'icon', 'provider']]);
    }

    public function test_audiences_json_excludes_inactive_audiences()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $campaign = Campaign::factory()->create();
        Audience::factory()->count(2)->create(['is_active' => true]);
        Audience::factory()->inactive()->count(1)->create();

        $this->actingAs($admin);
        $response = $this->getJson(route('campaigns.audiences.json', $campaign));

        $response->assertOk();
        $response->assertJsonCount(2);
    }

    public function test_audiences_json_is_ordered_by_category_then_sub_then_name()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $campaign = Campaign::factory()->create();
        Audience::factory()->create(['main_category' => 'Z Category', 'sub_category' => 'A Sub', 'name' => 'Audience A']);
        Audience::factory()->create(['main_category' => 'A Category', 'sub_category' => 'A Sub', 'name' => 'Audience B']);

        $this->actingAs($admin);
        $response = $this->getJson(route('campaigns.audiences.json', $campaign));

        $response->assertOk();
        $this->assertEquals('A Category', $response->json('0.main_category'));
        $this->assertEquals('Z Category', $response->json('1.main_category'));
    }

    public function test_non_admin_with_access_can_fetch_audiences_json()
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['is_admin' => false]);
        $user->clients()->attach($client);
        $campaign = Campaign::factory()->create(['client_id' => $client->id]);
        Audience::factory()->count(2)->create();

        $this->actingAs($user);
        $response = $this->getJson(route('campaigns.audiences.json', $campaign));

        $response->assertOk();
        $response->assertJsonCount(2);
    }

    public function test_non_admin_without_access_cannot_fetch_audiences_json()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $campaign = Campaign::factory()->create();

        $this->actingAs($user);
        $response = $this->getJson(route('campaigns.audiences.json', $campaign));

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_fetch_audiences_json()
    {
        $campaign = Campaign::factory()->create();

        $response = $this->getJson(route('campaigns.audiences.json', $campaign));

        $response->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // syncAudiences
    // -------------------------------------------------------------------------

    public function test_admin_can_sync_audiences_to_campaign()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $campaign = Campaign::factory()->create();
        $audiences = Audience::factory()->count(3)->create();

        $this->actingAs($admin);
        $response = $this->postJson(route('campaigns.audiences.sync', $campaign), [
            'audience_ids' => $audiences->pluck('id')->toArray(),
        ]);

        $response->assertOk();
        $response->assertJsonCount(3, 'connected');
        $this->assertDatabaseHas('campaign_audience', [
            'campaign_id' => $campaign->id,
            'audience_id' => $audiences->first()->id,
        ]);
    }

    public function test_sync_response_contains_correct_audience_fields()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $campaign = Campaign::factory()->create();
        $audience = Audience::factory()->create([
            'main_category' => 'Demographics',
            'sub_category' => 'Family',
            'name' => 'Parents with Teens',
            'estimated_users' => 500000,
        ]);

        $this->actingAs($admin);
        $response = $this->postJson(route('campaigns.audiences.sync', $campaign), [
            'audience_ids' => [$audience->id],
        ]);

        $response->assertOk();
        $connected = $response->json('connected.0');
        $this->assertEquals($audience->id, $connected['id']);
        $this->assertEquals('Demographics', $connected['main_category']);
        $this->assertEquals('Family', $connected['sub_category']);
        $this->assertEquals('Parents with Teens', $connected['name']);
        $this->assertEquals(500000, $connected['estimated_users']);
    }

    public function test_sync_removes_audiences_not_in_the_new_list()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $campaign = Campaign::factory()->create();
        [$a1, $a2, $a3] = Audience::factory()->count(3)->create();

        // Connect all three first
        $campaign->audiences()->sync([$a1->id, $a2->id, $a3->id]);

        // Sync to only a1
        $this->actingAs($admin);
        $response = $this->postJson(route('campaigns.audiences.sync', $campaign), [
            'audience_ids' => [$a1->id],
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'connected');
        $this->assertDatabaseMissing('campaign_audience', ['campaign_id' => $campaign->id, 'audience_id' => $a2->id]);
        $this->assertDatabaseMissing('campaign_audience', ['campaign_id' => $campaign->id, 'audience_id' => $a3->id]);
    }

    public function test_sync_with_empty_array_removes_all_audiences()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $campaign = Campaign::factory()->create();
        $audiences = Audience::factory()->count(2)->create();
        $campaign->audiences()->sync($audiences->pluck('id')->toArray());

        $this->actingAs($admin);
        $response = $this->postJson(route('campaigns.audiences.sync', $campaign), [
            'audience_ids' => [],
        ]);

        $response->assertOk();
        $response->assertJsonCount(0, 'connected');
        $this->assertDatabaseMissing('campaign_audience', ['campaign_id' => $campaign->id]);
    }

    public function test_sync_rejects_nonexistent_audience_id()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $campaign = Campaign::factory()->create();

        $this->actingAs($admin);
        $response = $this->postJson(route('campaigns.audiences.sync', $campaign), [
            'audience_ids' => [99999],
        ]);

        $response->assertUnprocessable();
    }

    public function test_non_admin_without_access_cannot_sync_audiences()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $campaign = Campaign::factory()->create();
        $audience = Audience::factory()->create();

        $this->actingAs($user);
        $response = $this->postJson(route('campaigns.audiences.sync', $campaign), [
            'audience_ids' => [$audience->id],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('campaign_audience', ['campaign_id' => $campaign->id]);
    }

    // -------------------------------------------------------------------------
    // edit view
    // -------------------------------------------------------------------------

    public function test_edit_view_passes_connected_audiences_to_template()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $campaign = Campaign::factory()->create();
        $audience = Audience::factory()->create(['name' => 'Tech Enthusiasts']);
        $campaign->audiences()->attach($audience->id);

        $this->actingAs($admin);
        $response = $this->get(route('campaigns.edit', $campaign));

        $response->assertOk();
        $response->assertViewHas('connectedAudiences', function ($audiences) use ($audience) {
            return $audiences->contains('id', $audience->id);
        });
    }

    // -------------------------------------------------------------------------
    // Audience model relationships
    // -------------------------------------------------------------------------

    public function test_audience_belongs_to_many_campaigns()
    {
        $audience = Audience::factory()->create();
        $campaigns = Campaign::factory()->count(2)->create();
        $audience->campaigns()->attach($campaigns->pluck('id')->toArray());

        $this->assertCount(2, $audience->campaigns);
        $this->assertDatabaseHas('campaign_audience', ['audience_id' => $audience->id, 'campaign_id' => $campaigns->first()->id]);
    }

    public function test_campaign_belongs_to_many_audiences()
    {
        $campaign = Campaign::factory()->create();
        $audiences = Audience::factory()->count(3)->create();
        $campaign->audiences()->attach($audiences->pluck('id')->toArray());

        $this->assertCount(3, $campaign->audiences);
    }

    public function test_deleting_campaign_removes_pivot_rows()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $campaign = Campaign::factory()->create();
        $audience = Audience::factory()->create();
        $campaign->audiences()->attach($audience->id);

        $this->assertDatabaseHas('campaign_audience', ['campaign_id' => $campaign->id]);

        $this->actingAs($admin);
        $this->delete(route('campaigns.destroy', $campaign));

        $this->assertDatabaseMissing('campaign_audience', ['campaign_id' => $campaign->id]);
    }

    public function test_deleting_audience_removes_pivot_rows()
    {
        $campaign = Campaign::factory()->create();
        $audience = Audience::factory()->create();
        $campaign->audiences()->attach($audience->id);

        $this->assertDatabaseHas('campaign_audience', ['audience_id' => $audience->id]);

        $audience->delete();

        $this->assertDatabaseMissing('campaign_audience', ['audience_id' => $audience->id]);
    }
}
