<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agency\StoreAgencyClientRequest;
use App\Http\Requests\Agency\UpdateAgencyClientRequest;
use App\Models\Agency;
use App\Models\Client;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;

class AgencyClientController extends Controller
{
    use AuthorizesRequests;

    public function index(Agency $agency)
    {
        $this->authorize('manage', $agency);

        $clients = Client::where('agency_id', $agency->id)
            ->withCount('campaigns')
            ->orderBy('name')
            ->get();

        return view('agency.clients.index', compact('agency', 'clients'));
    }

    public function create(Agency $agency)
    {
        $this->authorize('manage', $agency);

        return view('agency.clients.create', compact('agency'));
    }

    public function store(StoreAgencyClientRequest $request, Agency $agency)
    {
        $this->authorize('manage', $agency);

        $client = Client::create([
            'name' => $request->validated('name'),
            'agency_id' => $agency->id,
        ]);

        app(ActivityLogger::class)->log('created', $client, "Created client \"{$client->name}\" in agency \"{$agency->name}\"");

        Cache::forget('clients_list');

        return redirect()->route('agency.clients.index', $agency)
            ->with('success', 'Client created successfully.');
    }

    public function edit(Agency $agency, Client $client)
    {
        $this->authorize('manage', $agency);

        if ($client->agency_id !== $agency->id) {
            abort(404);
        }

        return view('agency.clients.edit', compact('agency', 'client'));
    }

    public function update(UpdateAgencyClientRequest $request, Agency $agency, Client $client)
    {
        $this->authorize('manage', $agency);

        if ($client->agency_id !== $agency->id) {
            abort(404);
        }

        $oldName = $client->name;
        $client->update([
            'name' => $request->validated('name'),
        ]);

        app(ActivityLogger::class)->log('updated', $client, "Updated client \"{$oldName}\" in agency \"{$agency->name}\"");

        Cache::forget('clients_list');

        return redirect()->route('agency.clients.index', $agency)
            ->with('success', 'Client updated successfully.');
    }

    public function destroy(Agency $agency, Client $client)
    {
        $this->authorize('manage', $agency);

        if ($client->agency_id !== $agency->id) {
            abort(404);
        }

        if ($client->campaigns()->exists()) {
            return redirect()->route('agency.clients.index', $agency)
                ->with('error', 'Cannot delete client with existing campaigns.');
        }

        app(ActivityLogger::class)->log('deleted', $client, "Deleted client \"{$client->name}\" from agency \"{$agency->name}\"");

        $client->delete();

        Cache::forget('clients_list');

        return redirect()->route('agency.clients.index', $agency)
            ->with('success', 'Client deleted successfully.');
    }
}
