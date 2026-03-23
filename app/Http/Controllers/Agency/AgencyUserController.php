<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agency\StoreAgencyUserRequest;
use App\Http\Requests\Agency\UpdateAgencyUserRequest;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Hash;

class AgencyUserController extends Controller
{
    use AuthorizesRequests;

    /**
     * List users belonging to this agency.
     */
    public function index(Agency $agency)
    {
        $this->authorize('manage', $agency);

        $users = $agency->users()->with('userRole')->get();

        return view('agency.users.index', compact('agency', 'users'));
    }

    /**
     * Show form to create a new user in this agency.
     */
    public function create(Agency $agency)
    {
        $this->authorize('manage', $agency);

        $roles = $this->assignableRoles();
        $clients = $agency->clients()->orderBy('name')->get();

        return view('agency.users.create', compact('agency', 'roles', 'clients'));
    }

    /**
     * Create a new user and attach to this agency.
     */
    public function store(StoreAgencyUserRequest $request, Agency $agency)
    {
        $this->authorize('manage', $agency);

        $role = Role::findOrFail($request->role_id);
        $this->validateRoleAssignment($role);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_active' => true,
        ]);

        $user->role_id = $role->id;
        $user->save();

        $accessAllClients = (bool) $request->input('access_all_clients', true);

        $agency->users()->attach($user->id, [
            'access_all_clients' => $accessAllClients,
        ]);

        // Sync specific client access if not access_all_clients
        if (! $accessAllClients && $request->has('clients')) {
            $agencyClientIds = $agency->clients()->pluck('id')->toArray();
            $validClients = array_intersect($request->clients, $agencyClientIds);
            $user->clients()->sync($validClients);
        }

        app(ActivityLogger::class)->log('created', $user, "Created user \"{$user->name}\" in agency \"{$agency->name}\"");

        return redirect()->route('agency.users.index', $agency)
            ->with('success', 'User created successfully.');
    }

    /**
     * Show form to edit an existing user in this agency.
     */
    public function edit(Agency $agency, User $user)
    {
        $this->authorize('manage', $agency);
        $this->ensureUserBelongsToAgency($agency, $user);

        $roles = $this->assignableRoles();
        $clients = $agency->clients()->orderBy('name')->get();

        // Get current pivot data
        $pivot = $agency->users()->where('user_id', $user->id)->first()?->pivot;
        $accessAllClients = $pivot?->access_all_clients ?? true;

        // Get user's currently assigned client IDs (only those belonging to this agency)
        $agencyClientIds = $agency->clients()->pluck('id')->toArray();
        $assignedClientIds = $user->clients()->pluck('clients.id')->intersect($agencyClientIds)->values()->toArray();

        return view('agency.users.edit', compact('agency', 'user', 'roles', 'clients', 'accessAllClients', 'assignedClientIds'));
    }

    /**
     * Update an existing user in this agency.
     */
    public function update(UpdateAgencyUserRequest $request, Agency $agency, User $user)
    {
        $this->authorize('manage', $agency);
        $this->ensureUserBelongsToAgency($agency, $user);

        $role = Role::findOrFail($request->role_id);
        $this->validateRoleAssignment($role);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->role_id = $role->id;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        // Handle is_active toggle (CTRL-6: re-enable)
        if ($request->has('is_active')) {
            $user->is_active = (bool) $request->is_active;
        }

        $user->save();

        $accessAllClients = (bool) $request->input('access_all_clients', true);

        $agency->users()->updateExistingPivot($user->id, [
            'access_all_clients' => $accessAllClients,
        ]);

        // Sync specific client access
        if (! $accessAllClients && $request->has('clients')) {
            $agencyClientIds = $agency->clients()->pluck('id')->toArray();
            $validClients = array_intersect($request->clients, $agencyClientIds);
            $user->clients()->sync($validClients);
        } elseif ($accessAllClients) {
            // Remove specific client assignments for this agency's clients when switching to "all"
            $agencyClientIds = $agency->clients()->pluck('id')->toArray();
            $user->clients()->detach($agencyClientIds);
        }

        app(ActivityLogger::class)->log('updated', $user, "Updated user \"{$user->name}\" in agency \"{$agency->name}\"");

        return redirect()->route('agency.users.index', $agency)
            ->with('success', 'User updated successfully.');
    }

    /**
     * Disable a user (set is_active = false). Does NOT delete or detach pivots.
     */
    public function destroy(Agency $agency, User $user)
    {
        $this->authorize('manage', $agency);
        $this->ensureUserBelongsToAgency($agency, $user);

        // Prevent disabling yourself
        if ($user->id === auth()->id()) {
            return redirect()->route('agency.users.index', $agency)
                ->with('error', 'You cannot disable your own account.');
        }

        $user->is_active = false;
        $user->save();

        app(ActivityLogger::class)->log('updated', $user, "Disabled user \"{$user->name}\" in agency \"{$agency->name}\"");

        return redirect()->route('agency.users.index', $agency)
            ->with('success', $user->name.' has been disabled.');
    }

    /**
     * Anti-escalation: validate the target role cannot exceed current user's permissions.
     */
    private function validateRoleAssignment(Role $targetRole): void
    {
        $currentUser = auth()->user();

        // Agency Manager cannot grant can_manage_users
        if ($targetRole->hasPermission('can_manage_users')) {
            abort(403, 'You cannot grant user management permission.');
        }

        // Cannot assign role with more permissions than own
        $permissions = $targetRole->permissions ?? [];
        foreach ($permissions as $key => $granted) {
            if ($granted && ! $currentUser->hasPermission($key)) {
                abort(403, "You cannot grant the '{$key}' permission.");
            }
        }
    }

    /**
     * Get roles that are assignable by an agency manager (excludes roles with can_manage_users).
     */
    private function assignableRoles(): \Illuminate\Database\Eloquent\Collection
    {
        $currentUser = auth()->user();

        return Role::orderBy('sort_order')->get()->filter(function (Role $role) use ($currentUser) {
            // Exclude admin and manager roles
            if ($role->hasPermission('can_manage_users') || $role->hasPermission('is_admin')) {
                return false;
            }

            // Exclude roles with any permission the current user doesn't hold
            foreach ($role->permissions ?? [] as $key => $granted) {
                if ($granted && ! $currentUser->hasPermission($key)) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * Ensure the user belongs to this agency.
     */
    private function ensureUserBelongsToAgency(Agency $agency, User $user): void
    {
        if (! $agency->users()->where('user_id', $user->id)->exists()) {
            abort(404, 'User does not belong to this agency.');
        }
    }
}
