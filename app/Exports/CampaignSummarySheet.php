<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class CampaignSummarySheet implements FromView, WithTitle, WithDrawings, WithColumnWidths, WithColumnFormatting, WithStyles
{
    protected $campaign;
    protected $summary;

    public function __construct($campaign, $summary)
    {
        $this->campaign = $campaign;
        $this->summary = $summary;
    }
    public function title(): string
    {
        return 'Summary';
    }

    public function view(): View
    {
        return view('exports.campaign_summary', [
            'campaign' => $this->campaign,
            'summary' => $this->summary,
            'user' => \Illuminate\Support\Facades\Auth::user(),

        ]);
    }

    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('MadData Logo');
        $drawing->setPath(public_path('images/logo.png')); // Ensure this path is correct
        $drawing->setHeight(40); // Resize the image
        $drawing->setCoordinates('C1'); // Position in the sheet

        return $drawing;
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
            'B' => '#,##0.00', // applies to column B
        ];
    }
    public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
    {
        $sheet->getStyle('B:B')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
    }
}
