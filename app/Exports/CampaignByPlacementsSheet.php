<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class CampaignByPlacementsSheet implements FromView, WithTitle, WithColumnWidths, WithColumnFormatting
{
    protected $campaignDataByPlacement;
    protected $campaign;

    public function __construct($campaignDataByPlacement, $campaign)
    {
        $this->campaignDataByPlacement = $campaignDataByPlacement;
        $this->campaign = $campaign;
    }
    public function title(): string
    {
        return 'Placements';
    }
    public function view(): View
    {
        return view('exports.campaign_by_placements', [
            'campaign' => $this->campaign,
            'campaignDataByPlacement' => $this->campaignDataByPlacement,
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
