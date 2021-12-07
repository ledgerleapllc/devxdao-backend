<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ActiveGrantExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */
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
            $proposal->proposal_id,
            $proposal->title,
            $proposal->email,
            ucfirst($proposal->status),
            ' ' . $proposal->created_at->format('m-d-Y H:i A'),
            " $proposal->milestones_complete/$proposal->milestones_total"
        ];
    }
    public function headings(): array
    {
        return [
            '#',
            'Title',
            'OP Email',
            'Status',
            'Start Date',
            'Milestones Complete',
        ];
    }
}
