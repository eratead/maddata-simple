<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class CampaignByPlacementsSheet implements FromView, WithTitle, WithColumnWidths
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
}
