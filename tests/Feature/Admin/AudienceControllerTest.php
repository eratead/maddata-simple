<?php

namespace Tests\Feature\Admin;

use App\Models\Audience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AudienceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $nonAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin    = User::factory()->create(['is_admin' => true]);
        $this->nonAdmin = User::factory()->create();
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_admin_can_view_audiences_index(): void
    {
        Audience::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.audiences.index'));

        $response->assertOk();
    }

    public function test_index_lists_all_audiences(): void
    {
        Audience::factory()->create(['name' => 'News Readers']);
        Audience::factory()->create(['name' => 'Sports Fans']);

        $response = $this->actingAs($this->admin)->get(route('admin.audiences.index'));

        $response->assertSee('News Readers');
        $response->assertSee('Sports Fans');
    }

    public function test_non_admin_cannot_view_audiences_index(): void
    {
        $response = $this->actingAs($this->nonAdmin)->get(route('admin.audiences.index'));

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_admin_can_create_audience(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.audiences.store'), [
            'main_category'   => 'Interests',
            'sub_category'    => 'Content',
            'name'            => 'Tech Readers',
            'estimated_users' => 50000,
            'provider'        => 'DV360',
        ]);

        $response->assertRedirect(route('admin.audiences.index'));
        $this->assertDatabaseHas('audiences', ['name' => 'Tech Readers', 'main_category' => 'Interests']);
    }

    public function test_audience_full_path_includes_subcategory(): void
    {
        $this->actingAs($this->admin)->post(route('admin.audiences.store'), [
            'main_category' => 'Interests',
            'sub_category'  => 'Behavioral',
            'name'          => 'Online Shoppers',
        ]);

        $this->assertDatabaseHas('audiences', [
            'full_path' => 'Audience > Interests > Behavioral > Online Shoppers',
        ]);
    }

    public function test_audience_full_path_without_subcategory_uses_main_category(): void
    {
        $this->actingAs($this->admin)->post(route('admin.audiences.store'), [
            'main_category' => 'Demographic',
            'sub_category'  => '',
            'name'          => 'Parents',
        ]);

        $audience = Audience::where('name', 'Parents')->first();
        $this->assertEquals('Demographic', $audience->sub_category);
        $this->assertEquals('Audience > Demographic > Parents', $audience->full_path);
    }

    public function test_audience_is_active_by_default(): void
    {
        $this->actingAs($this->admin)->post(route('admin.audiences.store'), [
            'main_category' => 'Interests',
            'sub_category'  => '',
            'name'          => 'Default Active',
        ]);

        $this->assertDatabaseHas('audiences', ['name' => 'Default Active', 'is_active' => true]);
    }

    public function test_store_requires_name_and_main_category(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.audiences.store'), [
            'main_category' => '',
            'name'          => '',
        ]);

        $response->assertSessionHasErrors(['main_category', 'name']);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_admin_can_update_audience(): void
    {
        $audience = Audience::factory()->create([
            'main_category' => 'Interests',
            'sub_category'  => 'Content',
            'name'          => 'Old Name',
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.audiences.update', $audience), [
            'main_category' => 'Interests',
            'sub_category'  => 'Content',
            'name'          => 'New Name',
        ]);

        $response->assertRedirect(route('admin.audiences.index'));
        $this->assertDatabaseHas('audiences', ['id' => $audience->id, 'name' => 'New Name']);
    }

    public function test_update_rebuilds_full_path(): void
    {
        $audience = Audience::factory()->create([
            'main_category' => 'Interests',
            'sub_category'  => 'Content',
            'name'          => 'Old Name',
            'full_path'     => 'Audience > Interests > Content > Old Name',
        ]);

        $this->actingAs($this->admin)->put(route('admin.audiences.update', $audience), [
            'main_category' => 'Demographic',
            'sub_category'  => 'Family',
            'name'          => 'New Parents',
        ]);

        $this->assertDatabaseHas('audiences', [
            'id'        => $audience->id,
            'full_path' => 'Audience > Demographic > Family > New Parents',
        ]);
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_admin_can_delete_audience(): void
    {
        $audience = Audience::factory()->create();

        $response = $this->actingAs($this->admin)->delete(route('admin.audiences.destroy', $audience));

        $response->assertRedirect(route('admin.audiences.index'));
        $this->assertDatabaseMissing('audiences', ['id' => $audience->id]);
    }

    public function test_non_admin_cannot_delete_audience(): void
    {
        $audience = Audience::factory()->create();

        $response = $this->actingAs($this->nonAdmin)->delete(route('admin.audiences.destroy', $audience));

        $response->assertForbidden();
        $this->assertDatabaseHas('audiences', ['id' => $audience->id]);
    }
}
