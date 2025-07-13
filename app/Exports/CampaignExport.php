<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\Exportable;

class CampaignExport implements WithMultipleSheets
{
    use Exportable;

    protected $campaign;
    protected $summary;
    protected $campaignData;
    protected $campaignDataByPlacement;
    protected $startDate;
    protected $endDate;

    public function __construct($campaign, $summary, $campaignData, $campaignDataByPlacement, $startDate, $endDate)
    {
        $this->campaign = $campaign;
        $this->summary = $summary;
        $this->campaignData = $campaignData;
        $this->campaignDataByPlacement = $campaignDataByPlacement;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function sheets(): array
    {
        return [
            new CampaignSummarySheet($this->campaign, $this->summary),
            new CampaignByDatesSheet($this->campaignData, $this->startDate, $this->endDate, $this->campaign),
            new CampaignByPlacementsSheet($this->campaignDataByPlacement, $this->campaign),
        ];
    }
}
