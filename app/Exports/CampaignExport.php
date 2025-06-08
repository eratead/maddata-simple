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

    public function sheets(): array
    {
        return [
            new CampaignFullSheet($this->campaign, $this->summary, $this->campaignData, $this->startDate, $this->endDate),
        ];
    }
}
