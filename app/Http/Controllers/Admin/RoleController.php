<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
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

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles',
            'permissions' => 'nullable|array',
        ]);

        $permissions = $request->input('permissions', []);
        // Convert 'on' or '1' to boolean true
        $formattedPermissions = [];
        foreach ($permissions as $key => $value) {
            $formattedPermissions[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        Role::create([
            'name' => $request->name,
            'permissions' => $formattedPermissions,
        ]);

        return redirect()->route('admin.roles.index')->with('success', 'Role created successfully.');
    }

    public function edit(Role $role)
    {
        $permissions = Role::availablePermissions();
        return view('admin.roles.edit', compact('role', 'permissions'));
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'permissions' => 'nullable|array',
        ]);

        $permissions = $request->input('permissions', []);
        $formattedPermissions = [];
        foreach ($permissions as $key => $value) {
            $formattedPermissions[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        $role->update([
            'name' => $request->name,
            'permissions' => $formattedPermissions,
        ]);

        return redirect()->route('admin.roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {
        if ($role->users()->count() > 0) {
            return redirect()->route('admin.roles.index')->with('error', 'Cannot delete role because it is assigned to users.');
        }

        $role->delete();
        return redirect()->route('admin.roles.index')->with('success', 'Role deleted successfully.');
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
