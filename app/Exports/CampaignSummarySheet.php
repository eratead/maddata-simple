<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Support\Facades\Auth;

class CampaignSummarySheet implements FromView
{
    protected $campaign;
    protected $summary;

    public function __construct($campaign, $summary)
    {
        $this->campaign = $campaign;
        $this->summary = $summary;
    }

    public function view(): View
    {
        return view('exports.campaign_summary', [
            'campaign' => $this->campaign,
            'summary' => $this->summary,
            'user' => \Illuminate\Support\Facades\Auth::user(),

        ]);
    }
}
