<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SurveyWinExport implements FromCollection, WithHeadings, WithMapping
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
        $proposal = $this->query;
        return $proposal;
    }

    public function map($proposal): array
    {
        $status = $proposal->status;
        return [
            ' ' . $proposal->end_time->format('m-d-Y'),
            $proposal->survey_id,
            $proposal->rank,
            $proposal->id,
            $proposal->title,
            $status['label'],
        ];
    }
    public function headings(): array
    {
        return [
            'Survey End',
            'Survey #',
            'Spot #',
            'Proposal #',
            'Title',
            'Current Status',
        ];
    }
}
