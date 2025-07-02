<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class CampaignByPlacementsSheet implements FromView
{
    protected $campaignDataByPlacement;

    public function __construct($campaignDataByPlacement)
    {
        $this->campaignDataByPlacement = $campaignDataByPlacement;
    }

    public function view(): View
    {
        return view('exports.campaign_by_placements', [
            'campaignDataByPlacement' => $this->campaignDataByPlacement,
        ]);
    }
}
