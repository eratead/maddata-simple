<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class CampaignFullSheet implements FromView
{
    protected $campaign;
    protected $summary;
    protected $campaignData;
    protected $startDate;
    protected $endDate;

    public function __construct($campaign, $summary, $campaignData, $startDate, $endDate)
    {
        $this->campaign = $campaign;
        $this->summary = $summary;
        $this->campaignData = $campaignData;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function view(): View
    {
        return view('dashboard.export', [
            'campaign' => $this->campaign,
            'summary' => $this->summary,
            'campaignData' => $this->campaignData,
            'date' => now()->format('Y-m-d'),
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ]);
    }
}
