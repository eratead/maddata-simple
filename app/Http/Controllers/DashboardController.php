<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Campaign;
use App\Models\CampaignData;
use App\Models\PlacementData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CampaignExport;

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
        // Add video metrics if campaign is video
        if ($campaign->is_video) {
            $videoComplete = \App\Models\CampaignData::where('campaign_id', $campaign->id)
                ->when($startDate && $endDate ?? false, fn($q) => $q->whereBetween('report_date', [$startDate, $endDate]))
                ->sum('video_100');

            $summary['video_complete'] = $videoComplete;
            $spent = ($summary['impressions'] ?? 0) * (($campaign->expected_impressions > 0 ? ($campaign->budget / max($campaign->expected_impressions, \App\Models\CampaignData::where('campaign_id', $campaign->id)->sum('impressions'))) * 1000 : 0) / 1000);
            $summary['cpv'] = $videoComplete > 0 ? round($spent / $videoComplete, 4) : 0;
            $summary['vcr'] = $summary['impressions'] > 0 ? round($videoComplete / $summary['impressions'] * 100, 2) : 0;
        }
        $campaignData = CampaignData::where('campaign_id', $campaign->id)
            ->when($startDate && $endDate, fn($q) => $q->whereBetween('report_date', [$startDate, $endDate]))
            ->orderBy('report_date')
            ->get();

        $chartLabels = $campaignData->pluck('report_date')->map(fn($d) => \Carbon\Carbon::parse($d)->format('M d'))->toArray();
        $chartImpressions = $campaignData->pluck('impressions')->toArray();
        $chartClicks = $campaignData->pluck('clicks')->toArray();
        $placementData = PlacementData::selectRaw('MAX(report_date) as report_date, name, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(visible_impressions) as visible_impressions, sum(video_25) as video_25, sum(video_50) as video_50, sum(video_75) as video_75, sum(video_100) as video_100')
            ->where('campaign_id', $campaign->id)
            ->when($startDate && $endDate, fn($q) => $q->whereBetween('report_date', [$startDate, $endDate]))
            ->orderByDesc('report_date')
            ->groupBy("name")
            ->get();

        $firstReportDate = CampaignData::where('campaign_id', $campaign->id)->min('report_date');
        if ($startDate || $endDate) {
            $allImpressions = \App\Models\CampaignData::where('campaign_id', $campaign->id)->whereBetween('report_date', [$startDate, $endDate])->sum('impressions');
        } else {
            $allImpressions = \App\Models\CampaignData::where('campaign_id', $campaign->id)->sum('impressions');
        }
        if ($startDate || $endDate) {
            $allClicks = \App\Models\CampaignData::where('campaign_id', $campaign->id)->whereBetween('report_date', [$startDate, $endDate])->sum('clicks');
        } else {
            $allClicks = \App\Models\CampaignData::where('campaign_id', $campaign->id)->sum('clicks');
        }

        $cpm = 0;
        $cpc = 0;
        $spent = 0;
        $budget = $campaign->budget;

        if ($campaign->expected_impressions > 0) {
            $baseImpressions = max($campaign->expected_impressions, $allImpressions);
            $cpm = ($campaign->budget / $baseImpressions) * 1000;
        }

        $spent = ($summary['impressions'] ?? 0) * $cpm / 1000;

        if ($allClicks > 0) {
            $cpc = $spent / $allClicks;
        }

        return view('dashboard.index', compact(
            'campaign',
            'summary',
            'campaignData',
            'placementData',
            'chartLabels',
            'chartImpressions',
            'chartClicks',
            'startDate',
            'endDate',
            'firstReportDate',
            'budget',
            'spent',
            'cpm',
            'cpc'
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

        $campaignDataByDate = CampaignData::where('campaign_id', $campaign->id)
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

        $budget = $campaign->budget;

        if ($startDate || $endDate) {
            $allImpressions = CampaignData::where('campaign_id', $campaign->id)->whereBetween('report_date', [$startDate, $endDate])->sum('impressions');
        } else {
            $allImpressions = CampaignData::where('campaign_id', $campaign->id)->sum('impressions');
        }
        if ($startDate || $endDate) {
            $allClicks = CampaignData::where('campaign_id', $campaign->id)->whereBetween('report_date', [$startDate, $endDate])->sum('clicks');
        } else {
            $allClicks = CampaignData::where('campaign_id', $campaign->id)->sum('clicks');
        }

        $cpm = 0;
        if ($campaign->expected_impressions > 0) {
            $cpm = ($campaign->budget / max($campaign->expected_impressions, $allImpressions)) * 1000;
        }

        $spent = ($summaryBase['impressions'] ?? 0) * $cpm / 1000;

        $cpc = 0;
        if ($allClicks > 0) {
            $cpc = $spent / $allClicks;
        }

        $summary = [
            'impressions' => $summaryBase['impressions'],
            'clicks' => $summaryBase['clicks'],
            'ctr' => $ctr,
            'unique_users' => $latestUniques,
            'visibility' => $visibility,
            'expected_impressions' => $campaign->expected_impressions,
            'budget' => $budget,
            'spent' => $spent,
            'cpm' => $cpm,
            'cpc' => $cpc,
        ];
        // Add video metrics if campaign is video
        if ($campaign->is_video) {
            $videoComplete = \App\Models\CampaignData::where('campaign_id', $campaign->id)
                ->when($startDate && $endDate ?? false, fn($q) => $q->whereBetween('report_date', [$startDate, $endDate]))
                ->sum('video_100');

            $summary['video_complete'] = $videoComplete;
            $summary['cpv'] = $videoComplete > 0 ? round($spent / $videoComplete, 4) : 0;
            $summary['vcr'] = $summary['impressions'] > 0 ? round($videoComplete / $summary['impressions'] * 100, 2) : 0;
        }

        $campaignDataByPlacement = PlacementData::where('campaign_id', $campaign->id)
            ->selectRaw('name as name, sum(impressions) as impressions, sum(clicks) as clicks, sum(visible_impressions) as visible, sum(video_25) video_25, sum(video_50) video_50, sum(video_75) video_75, sum(video_100) video_100')
            ->groupBy('name')
            ->orderByDesc('impressions')
            ->get()
            ->toArray();
        return Excel::download(
            new CampaignExport($campaign, $summary, $campaignDataByDate, $campaignDataByPlacement, $startDate, $endDate),
            'MadData_' . $campaign->name . '.xlsx'
        );
    }
}
