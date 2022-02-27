<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AllVARepExport implements FromCollection, WithHeadings, WithMapping
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
        $user = $this->query;
        return $user;
    }

    public function map($user): array
    {
        return [
            $user->forum_name,
            $user->total,
            $user->total_staked,
            $user->total_available,
            $user->minted_pending,
        ];
    }
    public function headings(): array
    {
        return [
            'VA',
            'Total Rep',
            'Staked Rep',
            'Available Rep',
            'Minted Pending Rep',
        ];
    }
}
