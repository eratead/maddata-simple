<?php

namespace App\Http\Controllers;

use App\Models\Campaign;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\CampaignData;
use App\Models\Client;
use App\Models\PlacementData;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;


class CampaignController extends Controller
{
    use AuthorizesRequests;

    public function index($client_id = 0)
    {
        $user = Auth::user();

        $campaigns = Campaign::with('client');

        if ($client_id != 0) {
            if (!$user->is_admin && !$user->clients->contains('id', $client_id)) {
                abort(403, 'Unauthorized access to this client\'s campaigns.');
            }
            $campaigns->where('client_id', $client_id);
        } elseif (!$user->is_admin) {
            // Non-admin, show only campaigns for accessible clients
            $clientIds = $user->clients()->pluck('clients.id');
            $campaigns->whereIn('client_id', $clientIds);
        }

        $campaigns = $campaigns->get();

        $clientName = null;
        if ($client_id != 0) {
            $client = Client::find($client_id);
            $clientName = $client?->name;
        }
        return view('campaigns.index', compact('campaigns', 'clientName'));
    }

    public function create()
    {
        $this->authorize('create', Campaign::class);
        $clients = Client::all();
        return view('campaigns.create', compact('clients'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Campaign::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'client_id' => 'required|exists:clients,id',
            'expected_impressions' => 'nullable|integer|min:0',
        ]);

        $campaign = Campaign::create($validated);

        return redirect()->route('campaigns.index')->with('success', 'Campaign created successfully.');
    }

    public function upload(Request $request, Campaign $campaign)
    {
        $request->validate([
            'report' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $collection = \Maatwebsite\Excel\Facades\Excel::toCollection(null, $request->file('report'))->first();

        $date = null;
        $summary = ['impressions' => 0, 'clicks' => 0, 'visible' => 0, 'uniques' => 0];

        $headers = [];
        $viewability = 0;
        foreach ($collection as $row) {
            // If headers are not yet defined, try to capture them
            if (empty($headers) && !is_numeric($row[1] ?? null)) {
                $headers = $row->toArray();
                continue;
            }

            // If headers are still not defined, skip
            if (empty($headers)) continue;

            $data = array_combine($headers, $row->toArray());
            $viewability = $viewability == 0 ? (float) ($data['Viewability Rate_%'] ?? 0) : $viewability;
            // Skip if 'Impressions' is missing or not numeric
            if (!isset($data['Impressions']) || !is_numeric($data['Impressions'])) {
                continue;
            }

            $placement = $data['Bundle_Name'] ?? null;
            $impressions = (int) $data['Impressions'];
            $clicks = (int) ($data['Clicks'] ?? 0);
            $value = $data['Report_Date'] ?? null;

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
            if (!isset($data['Impressions']) || !is_numeric($data['Impressions'])) {
                continue;
            }

            $placement = $data['Bundle_Name'] ?? null;
            $impressions = (int) $data['Impressions'];
            $clicks = (int) ($data['Clicks'] ?? 0);

            $visible = (int) round($impressions * ($viewability / 100));

            PlacementData::create([
                'campaign_id' => $campaign->id,
                'name' => $placement,
                'report_date' => $date,
                'impressions' => $impressions,
                'clicks' => $clicks,
                'visible_impressions' => $visible,
            ]);

            $summary['impressions'] += $impressions;
            $summary['clicks'] += $clicks;
            $summary['visible'] += $visible;
        }

        $lastRowWithUniques = $collection->reverse()->first(function ($row) use ($headers) {
            $data = array_combine($headers, $row->toArray());
            return isset($data['Unique_Users']) && is_numeric($data['Unique_Users']);
        });

        $summary['uniques'] = 0;
        if ($lastRowWithUniques) {
            $data = array_combine($headers, $lastRowWithUniques->toArray());
            $summary['uniques'] = isset($data['Unique_Users']) ? (int) $data['Unique_Users'] : 0;
        }

        CampaignData::updateOrCreate(
            ['campaign_id' => $campaign->id, 'report_date' => $date],
            [
                'impressions' => $summary['impressions'],
                'clicks' => $summary['clicks'],
                'visible_impressions' => $summary['visible'],
                'uniques' => $summary['uniques'],
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

        $clients = Client::all(); // Or limit by user access
        return view('campaigns.edit', compact('campaign', 'clients'));
    }

    public function update(Request $request, Campaign $campaign)
    {

        $this->authorize('update', $campaign);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'client_id' => 'required|exists:clients,id',
            'expected_impressions' => 'nullable|integer|min:0',
        ]);

        $campaign->update($validated);

        return redirect()->route('campaigns.index')->with('success', 'Campaign updated successfully.');
    }
}
