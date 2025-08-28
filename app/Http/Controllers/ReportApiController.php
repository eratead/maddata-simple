<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ReportApiController extends Controller
{
    use AuthorizesRequests;

    public function summary(Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        $data = $campaign->data();

        $summary = [
            'campaign_id' => $campaign->id,
            'campaign_name' => $campaign->name,
            'impressions' => $data->sum('impressions'),
            'clicks' => $data->sum('clicks'),
            'ctr' => round($data->sum('clicks') / max(1, $data->sum('impressions')) * 100, 2),
            'uniques' => $data->orderByDesc('report_date')->first()?->uniques ?? 0,
            'expected_impressions' => $campaign->expected_impressions,
            'frequency' => round($data->sum('impressions') / max(1, $data->orderByDesc('report_date')->first()?->uniques ?? 1), 2),
            'pacing' => $campaign->expected_impressions > 0 ? round($data->sum('impressions') / $campaign->expected_impressions * 100, 2) : null,
        ];

        if ($campaign->is_video) {
            $videoComplete = $data->sum('video_100');
            $summary['video_complete'] = $videoComplete;
            $spent = $campaign->expected_impressions > 0
                ? $data->sum('impressions') * (($campaign->budget / max($campaign->expected_impressions, $data->sum('impressions'))) * 1000) / 1000
                : 0;
            $summary['cpv'] = $videoComplete > 0 ? round($spent / $videoComplete, 4) : 0;
            $summary['vcr'] = $summary['impressions'] > 0 ? round($videoComplete / $summary['impressions'] * 100, 2) : 0;
        }

        if (Auth::user()->can_view_budget) {
            $summary['budget'] = $campaign->budget;
            $summary['spent'] = round($data->sum('impressions') * $campaign->cpm / 1000, 2);
            $summary['cpm'] = $campaign->expected_impressions > 0
                ? round(($campaign->budget / max($campaign->expected_impressions, $data->sum('impressions'))) * 1000, 2)
                : 0;
            $summary['cpc'] = $summary['clicks'] > 0 ? round($summary['spent'] / $summary['clicks'], 4) : 0;
        }

        return response()->json($summary, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function byDate(Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        $start = request('start');
        $end = request('end');

        $rows = $campaign->data()
            ->selectRaw('report_date, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(video_25) as video_25, SUM(video_50) as video_50, SUM(video_75) as video_75, SUM(video_100) as video_100')
            ->when($start && $end, fn($q) => $q->whereBetween('report_date', [$start, $end]))
            ->groupBy('report_date')
            ->orderBy('report_date')
            ->get()
            ->map(function ($row) use ($campaign) {
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

        $rows = \App\Models\PlacementData::selectRaw('MAX(report_date) as report_date, name, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(visible_impressions) as visible_impressions, sum(video_25) as video_25, sum(video_50) as video_50, sum(video_75) as video_75, sum(video_100) as video_100')
            ->where('campaign_id', $campaign->id)
            ->when($start && $end, fn($q) => $q->whereBetween('report_date', [$start, $end]))
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
                    'created_at' => $campaign->created_at->toDateTimeString(),
                ];
            });

        return response()->json($campaigns, 200, [], JSON_UNESCAPED_UNICODE);
    }
}
