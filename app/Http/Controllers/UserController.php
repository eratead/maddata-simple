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

        return view('users.create', compact('clients'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:6'],
            'clients' => ['array'],
            'clients.*' => ['exists:clients,id'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_admin' => $request->has('is_admin'),
            'is_report' => $request->has('is_report'),
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

        return view('users.edit', compact('user', 'clients'));
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
        ]);

        // Update user info
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->is_admin = $request->has('is_admin');
        $user->is_report = $request->has('is_report');

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        // Sync client relationships
        $user->clients()->sync($validated['clients'] ?? []);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }
}
