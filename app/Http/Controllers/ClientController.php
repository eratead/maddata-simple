<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;


class ClientController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $user = Auth::user();
        // dump($user);
        $this->authorize('viewAny', Client::class);


        // If admin, show all clients; otherwise, only their own
        $clients = $user->is_admin
            ? Client::all()
            : $user->clients;
        return view('clients.index', compact('clients'));
    }

    public function edit(Client $client)
    {
        $this->authorize('update', $client);
        $agencies = Client::whereNotNull('agency')->select('agency')->distinct()->pluck('agency');
        return view('clients.edit', compact('client', 'agencies'));
    }

    public function update(Request $request, \App\Models\Client $client)
    {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'agency' => ['nullable', 'string', 'max:255'],
        ]);

        $client->update($validated);

        return redirect()->route('clients.index')
            ->with('success', 'Client updated successfully.');
    }

    public function create()
    {
        $this->authorize('create', Client::class);
        $agencies = Client::whereNotNull('agency')->select('agency')->distinct()->pluck('agency');
        return view('clients.create', compact('agencies'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Client::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'agency' => ['nullable', 'string', 'max:255'],
        ]);

        Client::create($validated);

        return redirect()->route('clients.index')
            ->with('success', 'Client created successfully.');
    }

    public function destroy(Client $client)
    {
        $this->authorize('delete', $client);

        $client->delete();

        return redirect()->route('clients.index')
            ->with('success', 'Client deleted successfully.');
    }
}
