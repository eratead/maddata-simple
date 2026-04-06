<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreventsPrivilegeEscalation;
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
    use AuthorizesRequests, PreventsPrivilegeEscalation;

    public function index(\Illuminate\Http\Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::with(['userRole', 'agencies:id,name', 'clients:id,name'])
            ->select('id', 'name', 'email', 'role_id', 'is_active', 'is_admin', 'can_view_budget', 'is_report');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('role')) {
            $roleVal = $request->role;
            if ($roleVal === 'null') {
                $query->whereNull('role_id');
            } else {
                $query->where('role_id', $roleVal);
            }
        }

        if ($request->filled('agency')) {
            $query->whereHas('agencies', fn ($q) => $q->where('agencies.id', $request->agency));
        }

        if ($request->filled('client')) {
            $query->whereHas('clients', fn ($q) => $q->where('clients.id', $request->client));
        }

        $users = $query->paginate(25)->withQueryString();
        $roles = Role::orderBy('sort_order')->get();
        $clients = Client::orderBy('name')->select('id', 'name')->get();
        $agencies = Agency::orderBy('name')->select('id', 'name')->get();

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

        $roleId = $validated['role_id'] ?? null;
        $targetRole = $roleId ? Role::find($roleId) : null;

        // Anti-escalation: acting user cannot grant permissions they do not hold
        if ($targetRole) {
            $this->preventPrivilegeEscalation(auth()->user(), $targetRole);
        }

        // Single-agency manager constraint
        $agencyData = $validated['agencies'] ?? [];
        if ($targetRole?->hasPermission('can_manage_users') && count($agencyData) > 1) {
            return back()->withErrors(['agencies' => 'Users with management permissions can only belong to one agency.'])->withInput();
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
        $user->agencies()->detach();
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

        $roleId = $validated['role_id'] ?? null;
        $targetRole = $roleId ? Role::find($roleId) : null;

        // Gate role changes: a user cannot change their own role, and acting
        // user cannot grant permissions they do not hold.
        $roleIsChanging = $roleId !== null && (string) $roleId !== (string) $user->role_id;
        if ($roleIsChanging) {
            $this->authorize('changeRole', $user);
            if ($targetRole) {
                $this->preventPrivilegeEscalation(auth()->user(), $targetRole);
            }
        }

        // Single-agency manager constraint
        $agencyData = $validated['agencies'] ?? [];
        if ($targetRole?->hasPermission('can_manage_users') && count($agencyData) > 1) {
            return back()->withErrors(['agencies' => 'Users with management permissions can only belong to one agency.'])->withInput();
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
