<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class CampaignByDatesSheet implements FromView
{
    protected $campaignData;
    protected $startDate;
    protected $endDate;

    public function __construct($campaignData, $startDate, $endDate)
    {
        $this->campaignData = $campaignData;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function view(): View
    {
        return view('exports.campaign_by_dates', [
            'campaignData' => $this->campaignData,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ]);
    }
}
