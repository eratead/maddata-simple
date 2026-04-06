<?php

namespace App\Http\Controllers;

use App\Exports\CampaignExport;
use App\Models\Campaign;
use App\Services\CampaignMetricsService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class DashboardController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CampaignMetricsService $metricsService
    ) {}

    public function show(Campaign $campaign)
    {
        $startDate = request('start_date');
        $endDate = request('end_date');

        $this->authorize('view', $campaign);
        session(['last_campaign_id' => $campaign->id]);

        $metrics = $this->metricsService->getMetrics($campaign, $startDate, $endDate);

        return view('dashboard.index', [
            'campaign' => $campaign,
            'summary' => $metrics['summary'],
            'campaignData' => $metrics['campaignData'],
            'placementData' => $metrics['placementData'],
            'dashDateRows' => $metrics['dashDateRows'],
            'dashPlacementRows' => $metrics['dashPlacementRows'],
            'chartLabels' => $metrics['chartLabels'],
            'chartImpressions' => $metrics['chartImpressions'],
            'chartClicks' => $metrics['chartClicks'],
            'startDate' => $startDate,
            'endDate' => $endDate,
            'firstReportDate' => $metrics['firstReportDate'],
            'budget' => $metrics['budget'],
            'spent' => $metrics['spent'],
            'cpm' => $metrics['cpm'],
            'cpc' => $metrics['cpc'],
        ]);
    }

    public function exportExcel(Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        $startDate = request('start_date');
        $endDate = request('end_date');

        $exportData = $this->metricsService->getExportData($campaign, $startDate, $endDate);

        return Excel::download(
            new CampaignExport(
                $campaign,
                $exportData['summary'],
                $exportData['campaignData'],
                $exportData['placementData'],
                $startDate,
                $endDate
            ),
            'MadData_'.Str::slug($campaign->name).'.xlsx'
        );
    }
}
