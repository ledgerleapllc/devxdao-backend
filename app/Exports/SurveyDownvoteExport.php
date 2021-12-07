<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SurveyDownvoteExport implements FromCollection,  WithHeadings, WithMapping
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
        $dowvoted = $proposal->is_approved && $proposal->downvote_approved_at ? 'Downvoted' . PHP_EOL . 'approved|' . Carbon::parse($proposal->downvote_approved_at)->format('m-d-Y') : 'Dowvoted';
        return [
            ' ' . $proposal->end_time->format('m-d-Y'),
            $proposal->survey_id,
            $proposal->rank,
            $proposal->id,
            $proposal->title,
            $proposal->is_approved ? $dowvoted : $status['label'],
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
