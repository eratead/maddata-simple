<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // Create an admin user to act as
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_view_roles_index()
    {
        Role::create(['name' => 'Manager', 'permissions' => ['is_admin' => true]]);
        
        $response = $this->actingAs($this->admin)->get(route('admin.roles.index'));
        
        $response->assertStatus(200);
        $response->assertSee('Manager');
    }

    public function test_admin_can_view_create_role_page()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.roles.create'));
        
        $response->assertStatus(200);
        $response->assertSee('Create Role');
        $response->assertSee('Role Name');
    }

    public function test_admin_can_store_new_role()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.roles.store'), [
            'name' => 'Editor',
            'permissions' => [
                'can_view_budget' => '1',
                'can_upload_reports' => '1',
            ]
        ]);

        $response->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseHas('roles', [
            'name' => 'Editor',
        ]);
        
        $role = Role::where('name', 'Editor')->first();
        $this->assertEquals(true, $role->permissions['can_view_budget']);
        $this->assertEquals(true, $role->permissions['can_upload_reports']);
        $this->assertArrayNotHasKey('is_admin', $role->permissions);
    }

    public function test_admin_cannot_store_duplicate_role_name()
    {
        Role::create(['name' => 'Duplicate Role', 'permissions' => []]);

        $response = $this->actingAs($this->admin)->post(route('admin.roles.store'), [
            'name' => 'Duplicate Role',
            'permissions' => []
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_admin_can_view_edit_role_page()
    {
        $role = Role::create(['name' => 'Viewer', 'permissions' => ['can_view_budget' => true]]);
        
        $response = $this->actingAs($this->admin)->get(route('admin.roles.edit', $role->id));
        
        $response->assertStatus(200);
        $response->assertSee('Save Changes');
        $response->assertSee('Viewer');
    }

    public function test_admin_can_update_role()
    {
        $role = Role::create(['name' => 'Updater', 'permissions' => ['is_admin' => false]]);

        $response = $this->actingAs($this->admin)->put(route('admin.roles.update', $role->id), [
            'name' => 'Super Updater',
            'permissions' => [
                'is_admin' => '1',
            ]
        ]);

        $response->assertRedirect(route('admin.roles.index'));
        
        $role->refresh();
        $this->assertEquals('Super Updater', $role->name);
        $this->assertEquals(true, $role->permissions['is_admin']);
    }

    public function test_admin_can_delete_role_with_no_users()
    {
        $role = Role::create(['name' => 'To Be Deleted', 'permissions' => []]);

        $response = $this->actingAs($this->admin)->delete(route('admin.roles.destroy', $role->id));

        $response->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }
}
