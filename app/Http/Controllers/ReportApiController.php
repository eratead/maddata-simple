<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;

class ReportApiController extends Controller
{
    use AuthorizesRequests;

    public function summary(Campaign $campaign)
    {
        $this->authorize('view', $campaign);
        $start = request('start');
        $end = request('end');

        // Apply optional date filtering for summary
        $data = $campaign->data()
            ->when($start && $end, fn($q) => $q->whereBetween('report_date', [$start, $end]));

        $sumImpressions = (int) $data->sum('impressions');
        $sumClicks = (int) $data->sum('clicks');
        $latestRow = $data->orderByDesc('report_date')->first();
        $latestUniques = $latestRow?->uniques ?? 0;

        $summary = [
            'campaign_id' => $campaign->id,
            'campaign_name' => $campaign->name,
            'campaign_start' => $campaign->start_date
                ? Carbon::parse($campaign->start_date)->toDateString()
                : null,
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
        if (Auth::user()->can_view_budget) {
            $summary['budget'] = $campaign->budget;

            // CPM is derived from budget and either expected_impressions or actual impressions in the filtered range (whichever is larger)
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
            if (Auth::user()->can_view_budget) {
                $summary['cpv'] = $videoComplete > 0 ? round($spentForCalc / $videoComplete, 4) : 0;
            }
            $summary['vcr'] = $sumImpressions > 0 ? round($videoComplete / $sumImpressions * 100, 2) : 0;
        }
        return response()->json($summary, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function byDate(Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        $start = request('start');
        $end = request('end');

        // Prepare base query with optional date filter
        $baseQuery = $campaign->data()
            ->when($start && $end, fn($q) => $q->whereBetween('report_date', [$start, $end]));

        // Compute CPM for spent calculation (only if user can view budget)
        $cpm = 0;
        if (Auth::user()->can_view_budget) {
            $totalImpsForPeriod = (clone $baseQuery)->sum('impressions');
            if ($campaign->expected_impressions > 0) {
                $cpm = ($campaign->budget / max($campaign->expected_impressions, $totalImpsForPeriod)) * 1000; // do not round yet
            }
        }

        $rows = (clone $baseQuery)
            ->selectRaw('report_date, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(video_25) as video_25, SUM(video_50) as video_50, SUM(video_75) as video_75, SUM(video_100) as video_100')
            ->groupBy('report_date')
            ->orderBy('report_date')
            ->get();

        // Add row-wise fields and cumulative spent (until the actual date)
        $runningSpent = 0;
        $rows = $rows->map(function ($row) use ($campaign, $cpm, &$runningSpent) {
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

            if (Auth::user()->can_view_budget) {
                $rowSpent = ($cpm > 0) ? (($row->impressions * $cpm) / 1000) : 0;
                $runningSpent += $rowSpent;
                // Spent until this date (cumulative)
                // $item['spent'] = round($runningSpent, 2);
            }

            return $item;
        });

        return response()->json([
            'campaign_id' => $campaign->id,
            'campaign_name' => $campaign->name,
            'by_date' => $rows,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function byPlacement(Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        $start = request('start');
        $end = request('end');

        // Compute CPM for this period (only if user can view budget)
        $cpm = 0;
        if (Auth::user()->can_view_budget) {
            $periodQuery = $campaign->data()
                ->when($start && $end, fn($q) => $q->whereBetween('report_date', [$start, $end]));
            $totalImpsForPeriod = (clone $periodQuery)->sum('impressions');
            if ($campaign->expected_impressions > 0) {
                $cpm = ($campaign->budget / max($campaign->expected_impressions, $totalImpsForPeriod)) * 1000;
            }
        }

        $rows = \App\Models\PlacementData::selectRaw('MAX(report_date) as report_date, name, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(visible_impressions) as visible_impressions, sum(video_25) as video_25, sum(video_50) as video_50, sum(video_75) as video_75, sum(video_100) as video_100')
            ->where('campaign_id', $campaign->id)
            ->when($start && $end, fn($q) => $q->whereBetween('report_date', [$start, $end]))
            ->groupBy('name')
            ->orderByDesc('report_date')
            ->get()
            ->map(function ($row) use ($campaign, $cpm) {
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
                if (Auth::user()->can_view_budget) {
                    // $item['spent'] = round(($cpm > 0) ? (($row->impressions * $cpm) / 1000) : 0, 2);
                }
                return $item;
            });

        return response()->json([
            'campaign_id' => $campaign->id,
            'campaign_name' => $campaign->name,
            'by_placement' => $rows,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function campaigns()
    {
        $start = request('start');
        $end = request('end');

        $campaigns = \App\Models\Campaign::with('client')
            ->when($start && $end, function ($query) use ($start, $end) {
                $query->whereBetween('created_at', [$start, $end]);
            })
            ->whereHas('client', function ($query) {
                if (!Auth::user()->is_admin) {
                    $query->whereIn('id', Auth::user()->clients->pluck('id'));
                }
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($campaign) {
                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'client_name' => $campaign->client->name ?? '',
                    'client_id' => $campaign->client->id ?? '',
                    'created_at' => $campaign->created_at->toDateTimeString(),
                ];
            });

        return response()->json($campaigns, 200, [], JSON_UNESCAPED_UNICODE);
    }
}
