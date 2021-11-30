<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProposalMentorExport implements FromCollection, WithHeadings, WithMapping
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
        return [
            $proposal->id,
            $proposal->title,
            $proposal->total_hours_mentor,
            ' ' . $proposal->created_at->format('m-d-Y'),
          
        ];
    }
    public function headings(): array
    {
        return [
            'Proposal #',
            'Title',
            'Mentor hours',
            'Submitted date',
        ];
    }
}
