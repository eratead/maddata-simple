<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ReportApiController extends Controller
{
    use AuthorizesRequests;

    private function campaignCacheVersion(Campaign $campaign): int
    {
        return (int) Cache::get("report_version_{$campaign->id}", 0);
    }

    public function summary(Campaign $campaign)
    {
        $this->authorize('view', $campaign);
        $start = request('start');
        $end = request('end');
        $canViewBudget = Auth::user()->hasPermission('can_view_budget');
        $version = $this->campaignCacheVersion($campaign);

        $cacheKey = "report_summary_{$campaign->id}_v{$version}_{$start}_{$end}_".($canViewBudget ? '1' : '0');

        $summary = Cache::remember($cacheKey, 3600, function () use ($campaign, $start, $end, $canViewBudget) {
            $data = $campaign->data()
                ->when($start && $end, fn ($q) => $q->whereBetween('report_date', [$start, $end]));

            $sumImpressions = (int) $data->sum('impressions');
            $sumClicks = (int) $data->sum('clicks');
            $latestRow = $data->orderByDesc('report_date')->first();
            $latestUniques = $latestRow?->uniques ?? 0;

            $summary = [
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
                'campaign_start' => $campaign->start_date
                    ? Carbon::parse($campaign->start_date)->toDateString()
                    : $campaign->created_at->toDateString(),
                'campaign_end' => $campaign->end_date
                    ? Carbon::parse($campaign->end_date)->toDateString()
                    : null,
                'impressions' => $sumImpressions,
                'clicks' => $sumClicks,
                'ctr' => round($sumClicks / max(1, $sumImpressions) * 100, 2),
                'uniques' => $latestUniques,
                'expected_impressions' => $campaign->expected_impressions,
                'frequency' => round($sumImpressions / max(1, $latestUniques), 2),
                'pacing' => $campaign->expected_impressions > 0 ? round($sumImpressions / $campaign->expected_impressions * 100, 2) : null,
            ];

            $spentForCalc = 0;
            if ($canViewBudget) {
                $summary['budget'] = $campaign->budget;

                $cpm = $campaign->expected_impressions > 0
                    ? ($campaign->budget / max($campaign->expected_impressions, $sumImpressions)) * 1000
                    : 0;

                $summary['cpm'] = round($cpm, 2);
                $spentForCalc = ($cpm > 0) ? ($sumImpressions * $cpm / 1000) : 0;
                $summary['spent'] = round($spentForCalc, 2);
                $summary['cpc'] = $sumClicks > 0 ? round($summary['spent'] / $sumClicks, 4) : 0;
            }

            if ($campaign->is_video) {
                $videoComplete = (int) $data->sum('video_100');
                $summary['video_complete'] = $videoComplete;
                if ($canViewBudget) {
                    $summary['cpv'] = $videoComplete > 0 ? round($spentForCalc / $videoComplete, 4) : 0;
                }
                $summary['vcr'] = $sumImpressions > 0 ? round($videoComplete / $sumImpressions * 100, 2) : 0;
            }

            return $summary;
        });

        return response()->json($summary, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function byDate(Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        $start = request('start');
        $end = request('end');
        $canViewBudget = Auth::user()->hasPermission('can_view_budget');

        $version = $this->campaignCacheVersion($campaign);
        $cacheKey = "report_by_date_{$campaign->id}_v{$version}_{$start}_{$end}_".($canViewBudget ? '1' : '0');

        $result = Cache::remember($cacheKey, 3600, function () use ($campaign, $start, $end, $canViewBudget) {
            $baseQuery = $campaign->data()
                ->when($start && $end, fn ($q) => $q->whereBetween('report_date', [$start, $end]));

            $cpm = 0;
            if ($canViewBudget) {
                $totalImpsForPeriod = (clone $baseQuery)->sum('impressions');
                if ($campaign->expected_impressions > 0) {
                    $cpm = ($campaign->budget / max($campaign->expected_impressions, $totalImpsForPeriod)) * 1000;
                }
            }

            $rows = (clone $baseQuery)
                ->selectRaw('report_date, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(video_25) as video_25, SUM(video_50) as video_50, SUM(video_75) as video_75, SUM(video_100) as video_100')
                ->groupBy('report_date')
                ->orderBy('report_date')
                ->get();

            $runningSpent = 0;
            $rows = $rows->map(function ($row) use ($campaign, $cpm, $canViewBudget, &$runningSpent) {
                $item = [
                    'date' => $row->report_date,
                    'impressions' => (int) $row->impressions,
                    'clicks' => (int) $row->clicks,
                    'ctr' => round($row->clicks / max(1, $row->impressions) * 100, 2),
                ];

                if ($campaign->is_video) {
                    $item['video_25'] = (int) $row->video_25;
                    $item['video_50'] = (int) $row->video_50;
                    $item['video_75'] = (int) $row->video_75;
                    $item['video_100'] = (int) $row->video_100;
                }

                if ($canViewBudget) {
                    $rowSpent = ($cpm > 0) ? (($row->impressions * $cpm) / 1000) : 0;
                    $runningSpent += $rowSpent;
                }

                return $item;
            });

            return [
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
                'by_date' => $rows,
            ];
        });

        return response()->json($result, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function byPlacement(Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        $start = request('start');
        $end = request('end');
        $canViewBudget = Auth::user()->hasPermission('can_view_budget');

        $version = $this->campaignCacheVersion($campaign);
        $cacheKey = "report_by_placement_{$campaign->id}_v{$version}_{$start}_{$end}_".($canViewBudget ? '1' : '0');

        $result = Cache::remember($cacheKey, 3600, function () use ($campaign, $start, $end, $canViewBudget) {
            $cpm = 0;
            if ($canViewBudget) {
                $periodQuery = $campaign->data()
                    ->when($start && $end, fn ($q) => $q->whereBetween('report_date', [$start, $end]));
                $totalImpsForPeriod = (clone $periodQuery)->sum('impressions');
                if ($campaign->expected_impressions > 0) {
                    $cpm = ($campaign->budget / max($campaign->expected_impressions, $totalImpsForPeriod)) * 1000;
                }
            }

            $rows = \App\Models\PlacementData::selectRaw('MAX(report_date) as report_date, name, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(visible_impressions) as visible_impressions, sum(video_25) as video_25, sum(video_50) as video_50, sum(video_75) as video_75, sum(video_100) as video_100')
                ->where('campaign_id', $campaign->id)
                ->when($start && $end, fn ($q) => $q->whereBetween('report_date', [$start, $end]))
                ->groupBy('name')
                ->orderByDesc('report_date')
                ->get()
                ->map(function ($row) use ($campaign) {
                    $item = [
                        'placement' => $row->name,
                        'impressions' => (int) $row->impressions,
                        'clicks' => (int) $row->clicks,
                        'ctr' => round($row->clicks / max(1, $row->impressions) * 100, 2),
                        'visible_impressions' => (int) $row->visible_impressions,
                    ];
                    if ($campaign->is_video) {
                        $item['video_25'] = (int) $row->video_25;
                        $item['video_50'] = (int) $row->video_50;
                        $item['video_75'] = (int) $row->video_75;
                        $item['video_100'] = (int) $row->video_100;
                    }

                    return $item;
                });

            return [
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
                'by_placement' => $rows,
            ];
        });

        return response()->json($result, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function campaigns()
    {
        $start = request('start');
        $end = request('end');

        $paginated = \App\Models\Campaign::with('client')
            ->when($start && $end, function ($query) use ($start, $end) {
                $query->whereBetween('created_at', [$start, $end]);
            })
            ->whereHas('client', function ($query) {
                if (! Auth::user()->hasPermission('is_admin')) {
                    $query->whereIn('id', Auth::user()->accessibleClientIds());
                }
            })
            ->orderByDesc('created_at')
            ->paginate(50);

        $paginated->getCollection()->transform(function ($campaign) {
            return [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'client_name' => $campaign->client->name ?? '',
                'client_id' => $campaign->client->id ?? '',
                'created_at' => $campaign->created_at->toDateTimeString(),
            ];
        });

        return response()->json($paginated, 200, [], JSON_UNESCAPED_UNICODE);
    }
}
