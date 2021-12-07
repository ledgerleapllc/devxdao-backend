<?php

namespace App\Exports;

use App\User;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class VoteResultExport implements FromView, WithEvents, ShouldAutoSize
{
    use RegistersEventListeners;
    protected $query ;

    public function __construct($query)
    {
        $this->query = $query;
    }
    public function view(): View
    {
        return view('excel.vote_detail', [
            'proposal' => $this->query
        ]);
    }
    public static function afterSheet(AfterSheet $event)
    {
        $event->sheet->getDelegate()->mergeCells('A7:F7');
        $event->sheet->getDelegate()->mergeCells('A12:F12');
        $event->sheet->getDelegate()->getStyle("A7")->getAlignment()->setWrapText(true);
        $event->sheet->getDelegate()->getStyle("A12")->getAlignment()->setWrapText(true);
        $event->sheet->getDelegate()->getStyle("A:F")->applyFromArray([
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
            ],
        ]);
    }

}
