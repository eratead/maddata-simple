<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;

class CampaignByPlacementsSheet implements FromView, WithTitle
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
}
