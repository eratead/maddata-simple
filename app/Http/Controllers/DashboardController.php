<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Campaign;
use App\Models\CampaignData;
use App\Models\PlacementData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class DashboardController extends Controller
{
    use AuthorizesRequests;

    public function show($campaignId)
    {
        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            return redirect()->route('campaigns.index')->with('error', 'Campaign not found.');
        }

        $startDate = request('start_date');
        $endDate = request('end_date');

        $this->authorize('view', $campaign);
        session(['last_campaign_id' => $campaign->id]);

        $query = CampaignData::where('campaign_id', $campaign->id);
        if ($startDate && $endDate) {
            $query->whereBetween('report_date', [$startDate, $endDate]);
        }
        $summaryBase = $query->selectRaw('SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(visible_impressions) as visible')->first()->toArray();

        $uniquesQuery = CampaignData::where('campaign_id', $campaign->id);
        if ($startDate && $endDate) {
            $uniquesQuery->whereBetween('report_date', [$startDate, $endDate]);
        }
        $latestUniques = $uniquesQuery->orderByDesc('report_date')->value('uniques');
        $summary = array_merge($summaryBase, [
            'uniques' => $latestUniques,
            'expected_impressions' => $campaign->expected_impressions,
        ]);

        $campaignData = CampaignData::where('campaign_id', $campaign->id)
            ->when($startDate && $endDate, fn($q) => $q->whereBetween('report_date', [$startDate, $endDate]))
            ->orderBy('report_date')
            ->get();
        $chartLabels = $campaignData->pluck('report_date')->map(fn($d) => \Carbon\Carbon::parse($d)->format('M d'))->toArray();
        $chartImpressions = $campaignData->pluck('impressions')->toArray();
        $chartClicks = $campaignData->pluck('clicks')->toArray();
        $placementData = PlacementData::selectRaw('MAX(report_date) as report_date, name, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(visible_impressions) as visible_impressions')
            ->where('campaign_id', $campaign->id)
            ->when($startDate && $endDate, fn($q) => $q->whereBetween('report_date', [$startDate, $endDate]))
            ->orderByDesc('report_date')
            ->groupBy("name")
            ->get();

        return view('dashboard.index', compact(
            'campaign',
            'summary',
            'campaignData',
            'placementData',
            'chartLabels',
            'chartImpressions',
            'chartClicks',
            'startDate',
            'endDate'
        ));
    }
    private function calculateSummary(Campaign $campaign): array
    {
        $summaryBase = \App\Models\CampaignData::where('campaign_id', $campaign->id)
            ->selectRaw('SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(visible_impressions) as visible')
            ->first()
            ->toArray();

        $latestUniques = \App\Models\CampaignData::where('campaign_id', $campaign->id)
            ->orderByDesc('report_date')
            ->value('uniques');

        $ctr = $summaryBase['impressions'] ? round($summaryBase['clicks'] / $summaryBase['impressions'] * 100, 2) : 0;
        $visibility = $summaryBase['impressions'] ? round($summaryBase['visible'] / $summaryBase['impressions'] * 100, 2) : 0;

        return [
            'impressions' => $summaryBase['impressions'],
            'clicks' => $summaryBase['clicks'],
            'ctr' => $ctr,
            'unique_users' => $latestUniques,
            'visibility' => $visibility,
        ];
    }
    public function exportExcel(Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        $startDate = request('start_date');
        $endDate = request('end_date');

        $campaignData = CampaignData::where('campaign_id', $campaign->id)
            ->when($startDate && $endDate, fn($q) => $q->whereBetween('report_date', [$startDate, $endDate]))
            ->orderBy('report_date')
            ->get();

        $summaryBase = CampaignData::where('campaign_id', $campaign->id)
            ->when($startDate && $endDate, fn($q) => $q->whereBetween('report_date', [$startDate, $endDate]))
            ->selectRaw('SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(visible_impressions) as visible')
            ->first()
            ->toArray();

        $latestUniques = CampaignData::where('campaign_id', $campaign->id)
            ->when($startDate && $endDate, fn($q) => $q->whereBetween('report_date', [$startDate, $endDate]))
            ->orderByDesc('report_date')
            ->value('uniques');

        $ctr = $summaryBase['impressions'] ? round($summaryBase['clicks'] / $summaryBase['impressions'] * 100, 2) : 0;
        $visibility = $summaryBase['impressions'] ? round($summaryBase['visible'] / $summaryBase['impressions'] * 100, 2) : 0;

        $summary = [
            'impressions' => $summaryBase['impressions'],
            'clicks' => $summaryBase['clicks'],
            'ctr' => $ctr,
            'unique_users' => $latestUniques,
            'visibility' => $visibility,
            'expected_impressions' => $campaign->expected_impressions,
        ];

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\CampaignExport($campaign, $summary, $campaignData, $startDate, $endDate),
            'campaign_' . $campaign->name . '_report.xlsx'
        );
    }
}
