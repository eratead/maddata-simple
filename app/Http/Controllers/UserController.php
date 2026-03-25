<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', User::class);

        $users = User::with(['clients', 'userRole', 'agencies'])->get();
        $roles = Role::orderBy('sort_order')->get();
        $clients = Client::orderBy('name')->get();
        $agencies = Agency::orderBy('name')->get();

        return view('users.index', compact('users', 'roles', 'clients', 'agencies'));
    }

    public function create()
    {
        $this->authorize('create', User::class);

        $roles = Role::orderBy('sort_order')->get();
        $agencies = Agency::orderBy('name')->get();
        $clientsByAgency = Agency::with('clients:id,name,agency_id')->get()
            ->mapWithKeys(fn ($a) => [$a->id => $a->clients->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])]);

        return view('users.create', compact('roles', 'agencies', 'clientsByAgency'));
    }

    public function store(StoreUserRequest $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validated();

        // Anti-escalation: prevent assigning a role with is_admin unless current user has is_admin
        $roleId = $validated['role_id'] ?? null;
        if ($roleId) {
            $targetRole = Role::find($roleId);
            if ($targetRole && $targetRole->hasPermission('is_admin') && ! auth()->user()->hasPermission('is_admin')) {
                abort(403, 'You cannot assign an admin role.');
            }
        }

        // Single-agency manager constraint
        $agencyData = $validated['agencies'] ?? [];
        if ($roleId) {
            $targetRole = $targetRole ?? Role::find($roleId);
            if ($targetRole?->hasPermission('can_manage_users') && count($agencyData) > 1) {
                return back()->withErrors(['agencies' => 'Users with management permissions can only belong to one agency.'])->withInput();
            }
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'receive_activity_notifications' => $request->has('receive_activity_notifications'),
        ]);

        $user->role_id = $roleId;
        $user->save();

        // Sync agency assignments and collect specific client IDs
        $isManager = $targetRole?->hasPermission('can_manage_users') ?? false;
        $syncData = [];
        $allClientIds = [];
        foreach ($agencyData as $entry) {
            $agencyId = $entry['agency_id'];
            $accessAll = $isManager ? true : (bool) ($entry['access_all_clients'] ?? true);
            $syncData[$agencyId] = ['access_all_clients' => $accessAll];
            if (! $accessAll && isset($entry['clients'])) {
                $allClientIds = array_merge($allClientIds, $entry['clients']);
            }
        }
        $user->agencies()->sync($syncData);
        $user->clients()->sync($allClientIds);

        app(ActivityLogger::class)->log('created', $user, "Created user \"{$user->name}\" ({$user->email})");

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function destroy(User $user)
    {
        if (auth()->id() === $user->id) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        $this->authorize('delete', $user);

        app(ActivityLogger::class)->log('deleted', $user, "Deleted user \"{$user->name}\" ({$user->email})");

        $user->clients()->detach();
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }

    public function edit(User $user)
    {
        $this->authorize('update', $user);

        $roles = Role::orderBy('sort_order')->get();
        $agencies = Agency::orderBy('name')->get();
        $clientsByAgency = Agency::with('clients:id,name,agency_id')->get()
            ->mapWithKeys(fn ($a) => [$a->id => $a->clients->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])]);

        // Pre-populate existing agency assignments for this user
        $userAgencies = $user->agencies->map(fn ($a) => [
            'agency_id' => $a->id,
            'name' => $a->name,
            'access_all_clients' => (bool) $a->pivot->access_all_clients,
            'clients' => $user->clients()->whereIn('clients.id',
                Client::where('agency_id', $a->id)->pluck('id')
            )->pluck('clients.id')->toArray(),
        ]);

        return view('users.edit', compact('user', 'roles', 'agencies', 'clientsByAgency', 'userAgencies'));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validated();

        // Anti-escalation: prevent assigning a role with is_admin unless current user has is_admin
        $roleId = $validated['role_id'] ?? null;
        if ($roleId) {
            $targetRole = Role::find($roleId);
            if ($targetRole && $targetRole->hasPermission('is_admin') && ! auth()->user()->hasPermission('is_admin')) {
                abort(403, 'You cannot assign an admin role.');
            }
        }

        // Single-agency manager constraint
        $agencyData = $validated['agencies'] ?? [];
        if ($roleId) {
            $targetRole = $targetRole ?? Role::find($roleId);
            if ($targetRole?->hasPermission('can_manage_users') && count($agencyData) > 1) {
                return back()->withErrors(['agencies' => 'Users with management permissions can only belong to one agency.'])->withInput();
            }
        }

        // Update user info
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->receive_activity_notifications = $request->has('receive_activity_notifications');
        $user->role_id = $roleId;

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        // Sync agency assignments and collect specific client IDs
        $isManager = ($targetRole ?? Role::find($roleId))?->hasPermission('can_manage_users') ?? false;
        $syncData = [];
        $allClientIds = [];
        foreach ($agencyData as $entry) {
            $agencyId = $entry['agency_id'];
            $accessAll = $isManager ? true : (bool) ($entry['access_all_clients'] ?? true);
            $syncData[$agencyId] = ['access_all_clients' => $accessAll];
            if (! $accessAll && isset($entry['clients'])) {
                $allClientIds = array_merge($allClientIds, $entry['clients']);
            }
        }
        $user->agencies()->sync($syncData);
        $user->clients()->sync($allClientIds);

        app(ActivityLogger::class)->log('updated', $user, "Updated user \"{$user->name}\" ({$user->email})");

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    public function reset2fa(User $user): \Illuminate\Http\RedirectResponse
    {
        // Only admins may reset another user's 2FA secret
        abort_unless(auth()->user()->hasPermission('is_admin'), 403);

        $user->update(['google2fa_secret' => null]);

        return redirect()->route('admin.users.edit', $user)
            ->with('success', "2FA has been reset for {$user->name}. They will be required to set it up again on next login.");
    }

    public function attachClient(User $user)
    {
        $this->authorize('update', $user);

        return response()->json([
            'message' => "Attach client dialog for user {$user->name} (ID: {$user->id})",
        ]);
    }
}
