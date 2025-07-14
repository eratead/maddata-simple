<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class CampaignByDatesSheet implements FromView, WithTitle, WithColumnWidths, WithColumnFormatting
{
    protected $campaignData;
    protected $startDate;
    protected $endDate;
    protected $campaign;

    public function __construct($campaignData, $startDate, $endDate, $campaign)
    {
        $this->campaignData = $campaignData;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->campaign = $campaign;
    }
    public function title(): string
    {
        return 'Dates';
    }
    public function view(): View
    {
        return view('exports.campaign_by_dates', [
            'campaign' => $this->campaign,
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
    public function columnFormats(): array
    {
        return [
            'B' => '#,##0', // applies to column B
            'C' => '#,##0',
            'E' => '#,##0',
            'F' => '#,##0',
            'G' => '#,##0',
            'H' => '#,##0',
        ];
    }
}
