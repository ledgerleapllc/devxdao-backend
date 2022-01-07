<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class MyReputationExport implements FromCollection, WithHeadings, WithMapping, WithCustomCsvSettings, WithColumnFormatting, ShouldAutoSize, WithEvents
{
    public function __construct($query)
    {
        $this->query = $query;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $reputation = $this->query;
        return $reputation;
    }

    public function map($reputation): array
    {
        return [
            $reputation->event,
            $reputation->proposal_title,
            $reputation->type,
            in_array($reputation->type, ['Gained', 'Minted', 'Stake Lost', 'Lost']) ? number_format($reputation->value, 5) : null,
            $reputation->type == 'Staked' ?  number_format($reputation->staked, 5) : null,
            $reputation->type == 'Minted Pending'  ? number_format($reputation->pending, 5) : null,
            ' ' . $reputation->created_at->format('m-d-Y H:i A'),
        ];
    }
    public function headings(): array
    {
        return [
            'Event',
            'Title',
            'Transaction Type',
            'Earned/Returned/Lost',
            'Staked',
            'Pending',
            'Date',
        ];
    }

    public function getCsvSettings(): array
    {
        # Define your custom import settings for only this class
        return [
            'input_encoding' => 'UTF-8',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'E' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->setCellValue('D' . ($event->sheet->getHighestRow() + 1), '=SUM(D2:D' . $event->sheet->getHighestRow() . ')');
                $event->sheet->setCellValue('E' . ($event->sheet->getHighestRow()), '=SUM(E2:E' . $event->sheet->getHighestRow() - 1 . ')');
                $event->sheet->setCellValue('F' . ($event->sheet->getHighestRow()), '=SUM(F2:F' . $event->sheet->getHighestRow() - 1 . ')');
                $event->sheet->setCellValue('A' . ($event->sheet->getHighestRow()), 'Total');
            }
        ];
    }
}
