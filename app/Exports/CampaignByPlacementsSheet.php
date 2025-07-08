<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class CampaignByPlacementsSheet implements FromView, WithTitle, WithColumnWidths
{
    protected $campaignDataByPlacement;

    public function __construct($campaignDataByPlacement)
    {
        $this->campaignDataByPlacement = $campaignDataByPlacement;
    }
    public function title(): string
    {
        return 'Placements';
    }
    public function view(): View
    {
        return view('exports.campaign_by_placements', [
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
}
