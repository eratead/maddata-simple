<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use AuthorizesRequests;
    public function index()
    {

        $this->authorize('viewAny', User::class); // optional if you want admin-only

        $users = User::with('clients')->get();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $this->authorize('create', User::class); // Optional if policy is used

        $clients = \App\Models\Client::all();
        $roles = \App\Models\Role::orderBy('sort_order')->get();

        return view('users.create', compact('clients', 'roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:6'],
            'clients' => ['array'],
            'clients.*' => ['exists:clients,id'],
            'role_id' => ['nullable', 'exists:roles,id'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'receive_activity_notifications' => $request->has('receive_activity_notifications'),
            'role_id' => $validated['role_id'] ?? null,
        ]);

        // Attach selected clients
        $user->clients()->sync($validated['clients'] ?? []);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function destroy(User $user)
    {
        if (auth()->id() === $user->id) {
            return redirect()->route('users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        $this->authorize('delete', $user); // Optional if you use policies

        $user->clients()->detach(); // Remove client relationships
        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }



    public function edit(User $user)
    {
        $this->authorize('update', $user); // Optional, for admin control

        $clients = Client::all();
        $roles = \App\Models\Role::orderBy('sort_order')->get();

        return view('users.edit', compact('user', 'clients', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'min:6'],
            'clients' => ['array'],
            'clients.*' => ['exists:clients,id'],
            'role_id' => ['nullable', 'exists:roles,id'],
        ]);

        // Update user info
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->receive_activity_notifications = $request->has('receive_activity_notifications');
        $user->role_id = $validated['role_id'] ?? null;

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        // Sync client relationships
        $user->clients()->sync($validated['clients'] ?? []);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function attachClient(User $user)
    {
        $this->authorize('update', $user);

        return response()->json([
            'message' => "Attach client dialog for user {$user->name} (ID: {$user->id})"
        ]);
    }
}
