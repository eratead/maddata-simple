<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount('users')->orderBy('sort_order')->get();

        return view('admin.roles.index', compact('roles'));
    }

    public function create()
    {
        $permissions = Role::availablePermissions();

        return view('admin.roles.create', compact('permissions'));
    }

    public function store(StoreRoleRequest $request)
    {
        $permissions = $request->input('permissions', []);
        // Convert 'on' or '1' to boolean true
        $formattedPermissions = [];
        foreach ($permissions as $key => $value) {
            $formattedPermissions[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        // Privilege escalation prevention: cannot grant permissions you don't hold
        $this->preventPrivilegeEscalation($formattedPermissions);

        $role = Role::create([
            'name' => $request->name,
            'permissions' => $formattedPermissions,
            'is_protected' => (bool) $request->input('is_protected', false),
        ]);

        app(ActivityLogger::class)->log('created', $role, "Created role \"{$role->name}\"", [
            'permissions' => $formattedPermissions,
        ]);

        return redirect()->route('admin.roles.index')->with('success', 'Role created successfully.');
    }

    public function edit(Role $role)
    {
        $permissions = Role::availablePermissions();

        return view('admin.roles.edit', compact('role', 'permissions'));
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $permissions = $request->input('permissions', []);
        $formattedPermissions = [];
        foreach ($permissions as $key => $value) {
            $formattedPermissions[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        // Privilege escalation prevention: cannot grant permissions you don't hold
        $this->preventPrivilegeEscalation($formattedPermissions);

        $oldPermissions = $role->permissions;
        $oldName = $role->name;

        $role->update([
            'name' => $request->name,
            'permissions' => $formattedPermissions,
            'is_protected' => (bool) $request->input('is_protected', false),
        ]);

        app(ActivityLogger::class)->log('updated', $role, "Updated role \"{$oldName}\"", [
            'old_name' => $oldName,
            'new_name' => $request->name,
            'old_permissions' => $oldPermissions,
            'new_permissions' => $formattedPermissions,
        ]);

        return redirect()->route('admin.roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {
        if ($role->users()->count() > 0) {
            return redirect()->route('admin.roles.index')->with('error', 'Cannot delete role because it is assigned to users.');
        }

        app(ActivityLogger::class)->log('deleted', $role, "Deleted role \"{$role->name}\"");

        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Role deleted successfully.');
    }

    /**
     * Prevent privilege escalation: a user cannot grant permissions they don't hold.
     */
    private function preventPrivilegeEscalation(array $permissions): void
    {
        $currentUser = auth()->user();

        foreach ($permissions as $key => $granted) {
            if ($granted && ! $currentUser->hasPermission($key)) {
                abort(403, "You cannot grant the '{$key}' permission because you do not hold it yourself.");
            }
        }
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*' => 'exists:roles,id',
        ]);

        foreach ($request->order as $index => $id) {
            Role::where('id', $id)->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }
}
