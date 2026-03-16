<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Models\Audience;
use App\Models\Campaign;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\CampaignData;
use App\Models\Client;
use App\Models\PlacementData;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Carbon;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;


class CampaignController extends Controller
{
    use AuthorizesRequests;

    public function index($client_id = 0)
    {
        $user = Auth::user();

        $campaigns = Campaign::with('client');

        if ($client_id != 0) {
            if (!$user->hasPermission('is_admin') && !$user->clients->contains('id', $client_id)) {
                abort(403, 'Unauthorized access to this client\'s campaigns.');
            }
            $campaigns->where('client_id', $client_id);
        } elseif (!$user->hasPermission('is_admin')) {
            // Non-admin, show only campaigns for accessible clients
            $clientIds = $user->clients()->pluck('clients.id');
            $campaigns->whereIn('client_id', $clientIds);
        }

        $campaigns = $campaigns->orderBy('created_at', 'desc')->get();

        // Calculate pacing data for campaigns (impressions vs expected impressions)
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
        $clients = $user->hasPermission('is_admin') ? Client::all() : $user->clients;

        // Calculate Overview Summary Metrics for the top boxes
        $totalClients = $campaigns->pluck('client_id')->unique()->count();
        $activeCampaignsCount = $campaigns->where('status', 'active')->count();

        $yesterday = Carbon::yesterday()->toDateString();
        // Get yesterday's data for the visible campaigns
        $yesterdayData = CampaignData::whereIn('campaign_id', $campaignIds)
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
        $clients = Auth::user()->hasPermission('is_admin') ? Client::all() : Auth::user()->clients;
        return view('campaigns.create', compact('clients'));
    }

    public function store(StoreCampaignRequest $request)
    {
        $this->authorize('create', Campaign::class);

        $validated = $request->validated();

        if (!Auth::user()->hasPermission('is_admin') && !Auth::user()->clients->contains('id', $validated['client_id'])) {
            abort(403, 'You are not authorized to create campaigns for this client.');
        }

        if (!Auth::user()->hasPermission('can_view_budget')) {
            unset($validated['budget']);
        }

        $campaign = Campaign::create($validated);

        if (empty($campaign->start_date)) {
            $campaign->start_date = $campaign->created_at->toDateString();
            $campaign->save();
        }

        return redirect()->route('campaigns.index')->with('success', 'Campaign created successfully.');
    }

    public function upload(Request $request, Campaign $campaign)
    {
        $fieldMap = [
            'placement' => 'Bundle_Name',
            'impressions' => 'Impressions',
            'clicks' => 'Clicks',
            'viewability' => 'Viewability Rate_%',
            'uniques' => 'Unique_Users',
            'report_date' => 'Report_Date',
            'video_25' => 'Video_Views_25%',
            'video_50' => 'Video_Views_50%',
            'video_75' => 'Video_Views_75%',
            'video_100' => 'Video_Completes',
        ];

        $user = Auth::user();
        if (!$user->hasPermission('is_admin') && !($user->hasPermission('can_upload_reports') && $user->clients->contains($campaign->client_id))) {
            abort(403, 'You do not have permission to upload a report for this campaign.');
        }
        $request->validate([
            'report' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $collection = \Maatwebsite\Excel\Facades\Excel::toCollection(null, $request->file('report'))->first();

        $date = null;
        $summary = ['impressions' => 0, 'clicks' => 0, 'visible' => 0, 'uniques' => 0, 'video_25' => 0, 'video_50' => 0, 'video_75' => 0, 'video_100' => 0];

        $headers = [];
        $viewability = 0;
        foreach ($collection as $row) {
            // If headers are not yet defined, try to capture them
            if (empty($headers) && !is_numeric($row[1] ?? null)) {
                $headers = $row->toArray();
                // Check for video fields and update campaign is_video if present
                $videoFields = [
                    $fieldMap['video_25'],
                    $fieldMap['video_50'],
                    $fieldMap['video_75'],
                    $fieldMap['video_100']
                ];

                if (array_intersect($videoFields, $headers)) {
                    $campaign->update(['is_video' => true]);
                }
                continue;
            }

            // If headers are still not defined, skip
            if (empty($headers)) continue;

            $data = array_combine($headers, $row->toArray());
            $viewability = $viewability == 0 ? (float) ($data[$fieldMap['viewability']] ?? 0) : $viewability;
            // Skip if 'Impressions' is missing or not numeric
            if (!isset($data[$fieldMap['impressions']]) || !is_numeric($data[$fieldMap['impressions']])) {
                continue;
            }

            $placement = $data[$fieldMap['placement']] ?? null;
            $impressions = (int) $data[$fieldMap['impressions']];
            $clicks = (int) ($data[$fieldMap['clicks']] ?? 0);
            $value = $data[$fieldMap['report_date']] ?? null;

            if (!$date && $value) {
                if (is_numeric($value)) {
                    $date = Carbon::createFromDate(1900, 1, 1)->addDays($value - 2)->format('Y-m-d');
                } else {
                    $date = Carbon::parse($value)->format('Y-m-d');
                }
            }
        }

        if (!$date) {
            $date = now()->format('Y-m-d');
        }
        // Delete previous placement data for this campaign and date
        PlacementData::where('campaign_id', $campaign->id)
            ->where('report_date', $date)
            ->delete();

        foreach ($collection as $row) {
            // If headers are not yet defined, try to capture them
            if (empty($headers) && !is_numeric($row[1] ?? null)) {
                $headers = $row->toArray();
                continue;
            }

            // If headers are still not defined, skip
            if (empty($headers)) continue;

            $data = array_combine($headers, $row->toArray());

            // Skip if 'Impressions' is missing or not numeric
            if (!isset($data[$fieldMap['impressions']]) || !is_numeric($data[$fieldMap['impressions']])) {
                continue;
            }

            $placement = $data[$fieldMap['placement']] ?? null;
            $impressions = (int) $data[$fieldMap['impressions']];
            $clicks = (int) ($data[$fieldMap['clicks']] ?? 0);

            $video25 = isset($data[$fieldMap['video_25']]) && is_numeric($data[$fieldMap['video_25']]) ? (int) $data[$fieldMap['video_25']] : 0;
            $video50 = isset($data[$fieldMap['video_50']]) && is_numeric($data[$fieldMap['video_50']]) ? (int) $data[$fieldMap['video_50']] : 0;
            $video75 = isset($data[$fieldMap['video_75']]) && is_numeric($data[$fieldMap['video_75']]) ? (int) $data[$fieldMap['video_75']] : 0;
            $video100 = isset($data[$fieldMap['video_100']]) && is_numeric($data[$fieldMap['video_100']]) ? (int) $data[$fieldMap['video_100']] : 0;
            $visible = (int) round($impressions * ($viewability / 100));

            PlacementData::create([
                'campaign_id' => $campaign->id,
                'name' => $placement,
                'report_date' => $date,
                'impressions' => $impressions,
                'clicks' => $clicks,
                'visible_impressions' => $visible,
                'video_25' => $video25,
                'video_50' => $video50,
                'video_75' => $video75,
                'video_100' => $video100,
            ]);

            $summary['impressions'] += $impressions;
            $summary['clicks'] += $clicks;
            $summary['visible'] += $visible;
            $summary['video_25'] += $video25;
            $summary['video_50'] += $video50;
            $summary['video_75'] += $video75;
            $summary['video_100'] += $video100;
            // dd($summary);
        }

        $lastRowWithUniques = $collection->reverse()->first(function ($row) use ($headers, $fieldMap) {
            $data = array_combine($headers, $row->toArray());
            return isset($data[$fieldMap['uniques']]) && is_numeric($data[$fieldMap['uniques']]);
        });

        $summary['uniques'] = 0;
        if ($lastRowWithUniques) {
            $data = array_combine($headers, $lastRowWithUniques->toArray());
            $summary['uniques'] = isset($data[$fieldMap['uniques']]) ? (int) $data[$fieldMap['uniques']] : 0;
        }

        $campaign_data = CampaignData::updateOrCreate(
            ['campaign_id' => $campaign->id, 'report_date' => $date],
            [
                'impressions' => $summary['impressions'],
                'clicks' => $summary['clicks'],
                'visible_impressions' => $summary['visible'],
                'uniques' => $summary['uniques'],
                'video_25' => $summary['video_25'],
                'video_50' => $summary['video_50'],
                'video_75' => $summary['video_75'],
                'video_100' => $summary['video_100'],
            ]
        );

        $campaign->update([
            'uniques' => $summary['uniques']
        ]);

        return redirect()->route('campaigns.index')->with('success', 'Uploaded for campaign "' . $campaign->name . '" on ' . $date . ': ' .
            $summary['impressions'] . ' impressions, ' .
            $summary['clicks'] . ' clicks, ' .
            $summary['visible'] . ' visible, ' .
            $summary['uniques'] . ' uniques.');
    }


    public function edit(Campaign $campaign)
    {
        $this->authorize('update', $campaign);
        $campaign->load(['creatives', 'audiences', 'locations']);

        $clients = Auth::user()->hasPermission('is_admin') ? Client::all() : Auth::user()->clients;
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
        $after  = $request->input('audience_ids', []);

        $campaign->audiences()->sync($after);

        $added   = array_diff($after, $before);
        $removed = array_diff($before, $after);

        if (!empty($added) || !empty($removed)) {
            $logger = app(ActivityLogger::class);

            if (!empty($added)) {
                $names = Audience::whereIn('id', $added)->pluck('name')->join(', ');
                $logger->log('updated', $campaign, "Connected audiences to \"{$campaign->name}\": {$names}");
            }

            if (!empty($removed)) {
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

        if (!Auth::user()->hasPermission('is_admin') && !Auth::user()->clients->contains('id', $validated['client_id'])) {
            abort(403, 'You are not authorized to assign this campaign to that client.');
        }

        if (!Auth::user()->can('editBudget', Campaign::class)) {
            unset($validated['budget']);
        }
        if (!Auth::user()->hasPermission('is_admin')) {
            unset($validated['expected_impressions']);
        }

        $locationData = $validated['locations'] ?? [];
        unset($validated['locations']);

        $oldLocations = $campaign->locations
            ->map(fn($l) => ['name' => $l->name, 'lat' => (string) $l->lat, 'lng' => (string) $l->lng, 'radius_meters' => (int) $l->radius_meters])
            ->toArray();

        $campaign->update($validated);

        $campaign->locations()->delete();
        foreach ($locationData as $loc) {
            $campaign->locations()->create([
                'name'          => $loc['name'] ?? null,
                'lat'           => $loc['lat'],
                'lng'           => $loc['lng'],
                'radius_meters' => $loc['radius_meters'] ?? 1000,
            ]);
        }

        $newLocations = array_map(fn($l) => [
            'name'          => $l['name'] ?? null,
            'lat'           => (string) $l['lat'],
            'lng'           => (string) $l['lng'],
            'radius_meters' => (int) ($l['radius_meters'] ?? 1000),
        ], $locationData);

        if (json_encode($oldLocations) !== json_encode($newLocations)) {
            $added   = count($newLocations) - count($oldLocations);
            $total   = count($newLocations);
            if ($total === 0) {
                $desc = 'Cleared all proximity locations from "' . $campaign->name . '"';
            } else {
                $names = implode(', ', array_filter(array_column($newLocations, 'name')));
                $desc  = 'Updated proximity locations on "' . $campaign->name . '" (' . $total . ' total' . ($names ? ': ' . $names : '') . ')';
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

        return redirect()->route('campaigns.index')->with('success', 'Campaign updated successfully.');
    }

    public function destroy($id)
    {
        $campaign = Campaign::findOrFail($id);
        $this->authorize('delete', $campaign);

        $campaign->delete();

        return redirect()->route('campaigns.index')->with('success', 'Campaign deleted successfully.');
    }
}
