<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Agency;
use App\Models\Client;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ClientController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $user = Auth::user();
        $this->authorize('viewAny', Client::class);

        // If admin, show all clients; otherwise, only their own
        $clients = $user->hasPermission('is_admin')
            ? Client::with('agency')->withCount(['campaigns' => fn ($q) => $q->where('status', 'active')])->get()
            : $user->clients()->with('agency')->withCount(['campaigns' => fn ($q) => $q->where('status', 'active')])->get();

        return view('clients.index', compact('clients'));
    }

    public function edit(Client $client)
    {
        $this->authorize('update', $client);
        $agencies = Agency::orderBy('name')->get();

        return view('clients.edit', compact('client', 'agencies'));
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $this->authorize('update', $client);

        $oldName = $client->name;
        $client->update($request->validated());

        app(ActivityLogger::class)->log('updated', $client, "Updated client \"{$oldName}\"");

        Cache::forget('clients_list');

        return redirect()->route('admin.clients.index')
            ->with('success', 'Client updated successfully.');
    }

    public function create()
    {
        $this->authorize('create', Client::class);
        $agencies = Agency::orderBy('name')->get();

        return view('clients.create', compact('agencies'));
    }

    public function store(StoreClientRequest $request)
    {
        $this->authorize('create', Client::class);

        $client = Client::create($request->validated());

        app(ActivityLogger::class)->log('created', $client, "Created client \"{$client->name}\"");

        Cache::forget('clients_list');

        return redirect()->route('admin.clients.index')
            ->with('success', 'Client created successfully.');
    }

    public function destroy(Client $client)
    {
        $this->authorize('delete', $client);

        app(ActivityLogger::class)->log('deleted', $client, "Deleted client \"{$client->name}\"");

        $client->delete();

        Cache::forget('clients_list');

        return redirect()->route('admin.clients.index')
            ->with('success', 'Client deleted successfully.');
    }
}
