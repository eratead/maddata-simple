<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class CampaignByDatesSheet implements FromView, WithTitle, WithColumnWidths
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
    public function title(): string
    {
        return 'Dates';
    }
    public function view(): View
    {
        return view('exports.campaign_by_dates', [
            'campaignData' => $this->campaignData,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ]);
    }
    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 15,
        ];
    }
}
