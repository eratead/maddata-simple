<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignData;
use App\Models\PlacementData;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CampaignMetricsService
{
    /**
     * Fetch and compute all metrics for a campaign within an optional date range.
     *
     * @return array{
     *     campaignData: Collection,
     *     summary: array,
     *     chartLabels: array,
     *     chartImpressions: array,
     *     chartClicks: array,
     *     placementData: Collection,
     *     dashDateRows: Collection,
     *     dashPlacementRows: Collection,
     *     firstReportDate: mixed,
     *     budget: float|null,
     *     spent: float,
     *     cpm: float,
     *     cpc: float,
     * }
     */
    public function getMetrics(Campaign $campaign, ?string $startDate = null, ?string $endDate = null): array
    {
        $campaignData = CampaignData::where('campaign_id', $campaign->id)
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('report_date', [$startDate, $endDate]))
            ->orderBy('report_date')
            ->get();

        $totalImpressions = (int) $campaignData->sum('impressions');
        $totalClicks = (int) $campaignData->sum('clicks');
        $totalVisible = (int) $campaignData->sum('visible_impressions');
        $latestUniques = $campaignData->sortByDesc('report_date')->first()?->uniques;
        $firstReportDate = $campaignData->min('report_date');

        $summary = [
            'impressions' => $totalImpressions,
            'clicks' => $totalClicks,
            'visible' => $totalVisible,
            'uniques' => $latestUniques,
            'expected_impressions' => $campaign->expected_impressions,
        ];

        if ($campaign->is_video) {
            $videoComplete = (int) $campaignData->sum('video_100');
            $summary['video_complete'] = $videoComplete;

            $cpmForVideo = 0;
            if ($campaign->expected_impressions > 0) {
                $cpmForVideo = ($campaign->budget / max($campaign->expected_impressions, $totalImpressions)) * 1000;
            }
            $spentForVideo = $totalImpressions * $cpmForVideo / 1000;
            $summary['cpv'] = $videoComplete > 0 ? round($spentForVideo / $videoComplete, 4) : 0;
            $summary['vcr'] = $totalImpressions > 0 ? round($videoComplete / $totalImpressions * 100, 2) : 0;
        }

        $chartLabels = $campaignData->pluck('report_date')->map(fn ($d) => Carbon::parse($d)->format('M d'))->toArray();
        $chartImpressions = $campaignData->pluck('impressions')->toArray();
        $chartClicks = $campaignData->pluck('clicks')->toArray();

        $placementData = PlacementData::selectRaw('MAX(report_date) as report_date, name, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(visible_impressions) as visible_impressions, sum(video_25) as video_25, sum(video_50) as video_50, sum(video_75) as video_75, sum(video_100) as video_100')
            ->where('campaign_id', $campaign->id)
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('report_date', [$startDate, $endDate]))
            ->orderByDesc('report_date')
            ->groupBy('name')
            ->get();

        $dashDateRows = $campaignData->map(fn ($r) => [
            'date' => Carbon::parse($r->report_date)->format('Y-m-d'),
            'impr' => (int) $r->impressions,
            'clicks' => (int) $r->clicks,
            'v25' => (int) $r->video_25,
            'v50' => (int) $r->video_50,
            'v75' => (int) $r->video_75,
            'v100' => (int) $r->video_100,
        ]);

        $dashPlacementRows = $placementData->map(fn ($r) => [
            'name' => $r->name,
            'impr' => (int) $r->impressions,
            'clicks' => (int) $r->clicks,
            'visible' => (int) $r->visible_impressions,
            'v25' => (int) $r->video_25,
            'v50' => (int) $r->video_50,
            'v75' => (int) $r->video_75,
            'v100' => (int) $r->video_100,
        ]);

        $budget = null;
        $cpm = 0;
        $cpc = 0;
        $spent = 0;

        if (Auth::user()->hasPermission('can_view_budget')) {
            $budget = $campaign->budget;

            if ($campaign->expected_impressions > 0) {
                $baseImpressions = max($campaign->expected_impressions, $totalImpressions);
                $cpm = ($campaign->budget / $baseImpressions) * 1000;
            }

            $spent = ($summary['impressions'] ?? 0) * $cpm / 1000;

            if ($totalClicks > 0) {
                $cpc = $spent / $totalClicks;
            }
        }

        return [
            'campaignData' => $campaignData,
            'summary' => $summary,
            'chartLabels' => $chartLabels,
            'chartImpressions' => $chartImpressions,
            'chartClicks' => $chartClicks,
            'placementData' => $placementData,
            'dashDateRows' => $dashDateRows,
            'dashPlacementRows' => $dashPlacementRows,
            'firstReportDate' => $firstReportDate,
            'budget' => $budget,
            'spent' => $spent,
            'cpm' => $cpm,
            'cpc' => $cpc,
        ];
    }

    /**
     * Get summary data formatted for Excel export.
     */
    public function getExportData(Campaign $campaign, ?string $startDate = null, ?string $endDate = null): array
    {
        $campaignData = CampaignData::where('campaign_id', $campaign->id)
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('report_date', [$startDate, $endDate]))
            ->orderBy('report_date')
            ->get();

        $totalImpressions = (int) $campaignData->sum('impressions');
        $totalClicks = (int) $campaignData->sum('clicks');
        $totalVisible = (int) $campaignData->sum('visible_impressions');
        $latestUniques = $campaignData->sortByDesc('report_date')->first()?->uniques;

        $ctr = $totalImpressions ? round($totalClicks / $totalImpressions * 100, 2) : 0;
        $visibility = $totalImpressions ? round($totalVisible / $totalImpressions * 100, 2) : 0;

        $canViewBudget = Auth::user()->hasPermission('can_view_budget');

        $budget = null;
        $cpm = 0;
        $spent = 0;
        $cpc = 0;

        if ($canViewBudget) {
            $budget = $campaign->budget;

            if ($campaign->expected_impressions > 0) {
                $cpm = ($campaign->budget / max($campaign->expected_impressions, $totalImpressions)) * 1000;
            }

            $spent = $totalImpressions * $cpm / 1000;

            if ($totalClicks > 0) {
                $cpc = $spent / $totalClicks;
            }
        }

        $summary = [
            'impressions' => $totalImpressions,
            'clicks' => $totalClicks,
            'ctr' => $ctr,
            'unique_users' => $latestUniques,
            'visibility' => $visibility,
            'expected_impressions' => $campaign->expected_impressions,
            'budget' => $budget,
            'spent' => $spent,
            'cpm' => $cpm,
            'cpc' => $cpc,
        ];

        if ($campaign->is_video) {
            $videoComplete = (int) $campaignData->sum('video_100');
            $summary['video_complete'] = $videoComplete;
            $summary['cpv'] = $videoComplete > 0 ? round($spent / $videoComplete, 4) : 0;
            $summary['vcr'] = $totalImpressions > 0 ? round($videoComplete / $totalImpressions * 100, 2) : 0;
        }

        $placementData = PlacementData::where('campaign_id', $campaign->id)
            ->selectRaw('name as name, sum(impressions) as impressions, sum(clicks) as clicks, sum(visible_impressions) as visible, sum(video_25) video_25, sum(video_50) video_50, sum(video_75) video_75, sum(video_100) video_100')
            ->groupBy('name')
            ->orderByDesc('impressions')
            ->get()
            ->toArray();

        return [
            'summary' => $summary,
            'campaignData' => $campaignData,
            'placementData' => $placementData,
        ];
    }
}
