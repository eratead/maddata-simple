<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignData;
use App\Models\PlacementData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;

class ReportImportService
{
    private array $fieldMap = [
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

    /**
     * Import an Excel report file for a campaign.
     *
     * @return array{date: string, summary: array}
     */
    public function import(Campaign $campaign, UploadedFile $file): array
    {
        $collection = Excel::toCollection(null, $file)->first();

        $date = null;
        $headers = [];
        $viewability = 0;

        // First pass: detect headers, date, viewability, and video fields
        foreach ($collection as $row) {
            if (empty($headers) && ! is_numeric($row[1] ?? null)) {
                $headers = $row->toArray();

                $videoFields = [
                    $this->fieldMap['video_25'],
                    $this->fieldMap['video_50'],
                    $this->fieldMap['video_75'],
                    $this->fieldMap['video_100'],
                ];

                if (array_intersect($videoFields, $headers)) {
                    $campaign->update(['is_video' => true]);
                }

                continue;
            }

            if (empty($headers)) {
                continue;
            }

            $data = array_combine($headers, $row->toArray());
            $viewability = $viewability == 0 ? (float) ($data[$this->fieldMap['viewability']] ?? 0) : $viewability;

            if (! isset($data[$this->fieldMap['impressions']]) || ! is_numeric($data[$this->fieldMap['impressions']])) {
                continue;
            }

            $value = $data[$this->fieldMap['report_date']] ?? null;

            if (! $date && $value) {
                if (is_numeric($value)) {
                    $date = Carbon::createFromDate(1900, 1, 1)->addDays($value - 2)->format('Y-m-d');
                } else {
                    $date = Carbon::parse($value)->format('Y-m-d');
                }
            }
        }

        if (! $date) {
            $date = now()->format('Y-m-d');
        }

        // Delete previous placement data for this campaign and date
        PlacementData::where('campaign_id', $campaign->id)
            ->where('report_date', $date)
            ->delete();

        // Second pass: collect placement rows for bulk insert and build summary
        $summary = ['impressions' => 0, 'clicks' => 0, 'visible' => 0, 'uniques' => 0, 'video_25' => 0, 'video_50' => 0, 'video_75' => 0, 'video_100' => 0];
        $placementRows = [];
        $now = now();

        foreach ($collection as $row) {
            if (empty($headers) && ! is_numeric($row[1] ?? null)) {
                $headers = $row->toArray();

                continue;
            }

            if (empty($headers)) {
                continue;
            }

            $data = array_combine($headers, $row->toArray());

            if (! isset($data[$this->fieldMap['impressions']]) || ! is_numeric($data[$this->fieldMap['impressions']])) {
                continue;
            }

            $placement = $data[$this->fieldMap['placement']] ?? null;
            $impressions = (int) $data[$this->fieldMap['impressions']];
            $clicks = (int) ($data[$this->fieldMap['clicks']] ?? 0);

            $video25 = isset($data[$this->fieldMap['video_25']]) && is_numeric($data[$this->fieldMap['video_25']]) ? (int) $data[$this->fieldMap['video_25']] : 0;
            $video50 = isset($data[$this->fieldMap['video_50']]) && is_numeric($data[$this->fieldMap['video_50']]) ? (int) $data[$this->fieldMap['video_50']] : 0;
            $video75 = isset($data[$this->fieldMap['video_75']]) && is_numeric($data[$this->fieldMap['video_75']]) ? (int) $data[$this->fieldMap['video_75']] : 0;
            $video100 = isset($data[$this->fieldMap['video_100']]) && is_numeric($data[$this->fieldMap['video_100']]) ? (int) $data[$this->fieldMap['video_100']] : 0;
            $visible = (int) round($impressions * ($viewability / 100));

            $placementRows[] = [
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
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $summary['impressions'] += $impressions;
            $summary['clicks'] += $clicks;
            $summary['visible'] += $visible;
            $summary['video_25'] += $video25;
            $summary['video_50'] += $video50;
            $summary['video_75'] += $video75;
            $summary['video_100'] += $video100;
        }

        // PERF-M4: Bulk insert all placement rows at once
        if (! empty($placementRows)) {
            foreach (array_chunk($placementRows, 500) as $chunk) {
                PlacementData::insert($chunk);
            }
        }

        // Extract uniques from last row
        $lastRowWithUniques = $collection->reverse()->first(function ($row) use ($headers) {
            $data = array_combine($headers, $row->toArray());

            return isset($data[$this->fieldMap['uniques']]) && is_numeric($data[$this->fieldMap['uniques']]);
        });

        $summary['uniques'] = 0;
        if ($lastRowWithUniques) {
            $data = array_combine($headers, $lastRowWithUniques->toArray());
            $summary['uniques'] = isset($data[$this->fieldMap['uniques']]) ? (int) $data[$this->fieldMap['uniques']] : 0;
        }

        CampaignData::updateOrCreate(
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
            'uniques' => $summary['uniques'],
        ]);

        // Invalidate report API caches for this campaign
        $this->invalidateReportCache($campaign);

        return [
            'date' => $date,
            'summary' => $summary,
        ];
    }

    /**
     * Invalidate cached report API responses for a campaign by bumping the version counter.
     * Old cache entries will naturally expire (TTL 3600s) and won't be read because
     * the version in their key no longer matches.
     */
    private function invalidateReportCache(Campaign $campaign): void
    {
        Cache::increment("report_version_{$campaign->id}");
    }
}
