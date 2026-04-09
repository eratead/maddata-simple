<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Models\Audience;
use App\Models\Campaign;
use App\Models\CampaignData;
use App\Models\Client;
use App\Services\ActivityLogger;
use App\Services\ReportImportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class CampaignController extends Controller
{
    use AuthorizesRequests;

    public function index($client_id = 0)
    {
        $this->authorize('viewAny', Campaign::class);

        $user = Auth::user();

        $query = Campaign::with('client.agency');

        if ($client_id != 0) {
            if (! $user->hasPermission('is_admin') && ! $user->accessibleClientIds()->contains($client_id)) {
                abort(403, 'Unauthorized access to this client\'s campaigns.');
            }
            $query->where('client_id', $client_id);
        } elseif (! $user->hasPermission('is_admin')) {
            // Non-admin, show only campaigns for accessible clients
            $clientIds = $user->accessibleClientIds();
            $query->whereIn('client_id', $clientIds);
        }

        $query->orderByDesc('start_date')->orderByDesc('created_at');

        // Use ->get() instead of ->paginate() — the index view uses MadDataTable
        // for client-side search/sort/pagination, which requires all rows in DOM.
        $campaigns = $query->get();

        // Calculate pacing data for all campaigns
        $pacingData = [];
        $campaignIds = $campaigns->pluck('id');

        if ($campaignIds->isNotEmpty()) {
            // Sum impressions per campaign from CampaignData
            $impressionsByCampaign = CampaignData::selectRaw('campaign_id, SUM(impressions) as total_impressions')
                ->whereIn('campaign_id', $campaignIds)
                ->groupBy('campaign_id')
                ->pluck('total_impressions', 'campaign_id');

            foreach ($campaigns as $campaign) {
                $expected = $campaign->expected_impressions ?? 0;
                $impressions = $impressionsByCampaign[$campaign->id] ?? 0;

                if ($expected > 0) {
                    $rawPercent = ($impressions / $expected) * 100;
                    $pacingData[$campaign->id] = [
                        'impressions' => $impressions,
                        'expected_impressions' => $expected,
                        'percent' => min(100, $rawPercent),
                        'percent_raw' => $rawPercent,
                    ];
                } else {
                    $pacingData[$campaign->id] = [
                        'impressions' => $impressions,
                        'expected_impressions' => $expected,
                        'percent' => null,
                        'percent_raw' => null,
                    ];
                }
            }
        }

        $clientName = null;
        if ($client_id != 0) {
            $client = Client::find($client_id);
            $clientName = $client?->name;
        }
        $clients = $user->hasPermission('is_admin') ? Cache::remember('clients_list', 300, fn () => Client::all()) : $user->accessibleClients()->get();

        // Calculate Overview Summary Metrics for the top boxes — use total counts, not paginated slice
        $statsQuery = Campaign::query();
        if ($client_id != 0) {
            $statsQuery->where('client_id', $client_id);
        } elseif (! $user->hasPermission('is_admin')) {
            $statsQuery->whereIn('client_id', $user->accessibleClientIds());
        }
        $totalClients = (clone $statsQuery)->distinct()->count('client_id');
        $activeCampaignsCount = (clone $statsQuery)->where('status', 'active')->count();

        $yesterday = Carbon::yesterday()->toDateString();
        // Get yesterday's data across all accessible campaigns (not just the current page)
        $allCampaignIds = (clone $statsQuery)->pluck('id');
        $yesterdayData = CampaignData::whereIn('campaign_id', $allCampaignIds)
            ->where('report_date', $yesterday)
            ->selectRaw('SUM(impressions) as total_impressions, SUM(clicks) as total_clicks')
            ->first();

        $lastDayImpressions = $yesterdayData->total_impressions ?? 0;
        $lastDayClicks = $yesterdayData->total_clicks ?? 0;
        $lastDayCtr = $lastDayImpressions > 0 ? ($lastDayClicks / $lastDayImpressions) * 100 : 0;

        return view('campaigns.index', compact(
            'campaigns',
            'clientName',
            'clients',
            'pacingData',
            'totalClients',
            'activeCampaignsCount',
            'lastDayImpressions',
            'lastDayCtr'
        ));
    }

    public function create()
    {
        $this->authorize('create', Campaign::class);
        $clients = Auth::user()->hasPermission('is_admin') ? Cache::remember('clients_list', 300, fn () => Client::all()) : Auth::user()->accessibleClients()->get();

        return view('campaigns.create', compact('clients'));
    }

    public function store(StoreCampaignRequest $request)
    {
        $this->authorize('create', Campaign::class);

        $validated = $request->validated();

        if (! Auth::user()->hasPermission('is_admin') && ! Auth::user()->accessibleClientIds()->contains($validated['client_id'])) {
            abort(403, 'You are not authorized to create campaigns for this client.');
        }

        if (! Auth::user()->hasPermission('can_view_budget')) {
            unset($validated['budget']);
        }

        $campaign = Campaign::create($validated);

        if (empty($campaign->start_date)) {
            $campaign->start_date = $campaign->created_at->toDateString();
            $campaign->save();
        }

        return redirect()->route('campaigns.edit', $campaign)->with('success', 'Campaign created successfully.');
    }

    public function upload(Request $request, Campaign $campaign)
    {
        $user = Auth::user();
        if (! $user->hasPermission('is_admin') && ! ($user->hasPermission('can_upload_reports') && $user->accessibleClientIds()->contains($campaign->client_id))) {
            abort(403, 'You do not have permission to upload a report for this campaign.');
        }

        $request->validate([
            'report' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $importService = app(ReportImportService::class);
        $result = $importService->import($campaign, $request->file('report'));

        $date = $result['date'];
        $summary = $result['summary'];

        return redirect()->route('campaigns.index')->with('success', 'Uploaded for campaign "'.$campaign->name.'" on '.$date.': '.
            $summary['impressions'].' impressions, '.
            $summary['clicks'].' clicks, '.
            $summary['visible'].' visible, '.
            $summary['uniques'].' uniques.');
    }

    public function edit(Campaign $campaign)
    {
        $this->authorize('update', $campaign);
        $campaign->load(['creatives', 'audiences', 'locations']);

        $clients = Auth::user()->hasPermission('is_admin') ? Cache::remember('clients_list', 300, fn () => Client::all()) : Auth::user()->accessibleClients()->get();
        $connectedAudiences = $campaign->audiences;

        return view('campaigns.edit', compact('campaign', 'clients', 'connectedAudiences'));
    }

    public function audiencesJson(Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        $audiences = Audience::where('is_active', true)
            ->orderBy('main_category')
            ->orderBy('sub_category')
            ->orderBy('name')
            ->get(['id', 'main_category', 'sub_category', 'name', 'estimated_users', 'icon', 'provider']);

        return response()->json($audiences);
    }

    public function syncAudiences(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        $request->validate([
            'audience_ids' => 'array',
            'audience_ids.*' => 'integer|exists:audiences,id',
        ]);

        $before = $campaign->audiences()->pluck('audiences.id')->all();
        $after = $request->input('audience_ids', []);

        $campaign->audiences()->sync($after);

        $added = array_diff($after, $before);
        $removed = array_diff($before, $after);

        if (! empty($added) || ! empty($removed)) {
            $logger = app(ActivityLogger::class);

            if (! empty($added)) {
                $names = Audience::whereIn('id', $added)->pluck('name')->join(', ');
                $logger->log('updated', $campaign, "Connected audiences to \"{$campaign->name}\": {$names}");
            }

            if (! empty($removed)) {
                $names = Audience::whereIn('id', $removed)->pluck('name')->join(', ');
                $logger->log('updated', $campaign, "Disconnected audiences from \"{$campaign->name}\": {$names}");
            }
        }

        $connected = $campaign->audiences()
            ->get(['audiences.id', 'main_category', 'sub_category', 'name', 'estimated_users', 'icon']);

        return response()->json(['connected' => $connected]);
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        $validated = $request->validated();

        if (isset($validated['budget'])) {
            $validated['budget'] = (int) $validated['budget'];
        }

        if (! Auth::user()->hasPermission('is_admin') && ! Auth::user()->accessibleClientIds()->contains($validated['client_id'])) {
            abort(403, 'You are not authorized to assign this campaign to that client.');
        }

        if (! Auth::user()->can('editBudget', Campaign::class)) {
            unset($validated['budget']);
        }
        if (! Auth::user()->hasPermission('is_admin')) {
            unset($validated['expected_impressions']);
        }

        $locationData = $validated['locations'] ?? [];
        unset($validated['locations']);

        $oldLocations = $campaign->locations
            ->map(fn ($l) => ['name' => $l->name, 'lat' => (string) $l->lat, 'lng' => (string) $l->lng, 'radius_meters' => (int) $l->radius_meters])
            ->toArray();

        $campaign->update($validated);

        $campaign->locations()->delete();
        foreach ($locationData as $loc) {
            $campaign->locations()->create([
                'name' => $loc['name'] ?? null,
                'lat' => $loc['lat'],
                'lng' => $loc['lng'],
                'radius_meters' => $loc['radius_meters'] ?? 1000,
            ]);
        }

        $newLocations = array_map(fn ($l) => [
            'name' => $l['name'] ?? null,
            'lat' => (string) $l['lat'],
            'lng' => (string) $l['lng'],
            'radius_meters' => (int) ($l['radius_meters'] ?? 1000),
        ], $locationData);

        if (json_encode($oldLocations) !== json_encode($newLocations)) {
            $added = count($newLocations) - count($oldLocations);
            $total = count($newLocations);
            if ($total === 0) {
                $desc = 'Cleared all proximity locations from "'.$campaign->name.'"';
            } else {
                $names = implode(', ', array_filter(array_column($newLocations, 'name')));
                $desc = 'Updated proximity locations on "'.$campaign->name.'" ('.$total.' total'.($names ? ': '.$names : '').')';
            }
            app(\App\Services\ActivityLogger::class)->log('updated', $campaign, $desc, [
                'locations' => ['old' => $oldLocations, 'new' => $newLocations],
            ]);
        }

        $campaign->refresh();
        if (empty($campaign->start_date)) {
            $campaign->start_date = $campaign->created_at->toDateString();
            $campaign->save();
        }

        return redirect()->route('campaigns.edit', $campaign)->with('success', 'Campaign updated successfully.');
    }

    public function destroy($id)
    {
        $campaign = Campaign::findOrFail($id);
        $this->authorize('delete', $campaign);

        $campaign->delete();

        return redirect()->route('campaigns.index')->with('success', 'Campaign deleted successfully.');
    }
}
