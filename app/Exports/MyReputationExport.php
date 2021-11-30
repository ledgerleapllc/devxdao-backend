<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class MyReputationExport implements FromCollection, WithHeadings, WithMapping, WithCustomCsvSettings, WithColumnFormatting
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
            in_array($reputation->type, ['Gained', 'Minted', 'Stake Lost', 'Lost']) ?  ' ' . (string) number_format($reputation->value, 5) : '',
            $reputation->type == 'Staked' ? ' ' . (string) number_format($reputation->staked, 5) : '',
            $reputation->type == 'Minted Pending' ? ' ' . (string) number_format($reputation->pending, 5) : '',
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
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
        ];
    }
}
