<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UserExport implements FromCollection, WithHeadings, WithMapping
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
        $total_rep =  $user->total_rep != 0 ? number_format($user->total_rep, 5) . ' ' :  number_format('0', 5);
        return [
            $user->id,
            $user->email,
            $user->telegram,
            $user->first_name,
            $user->last_name,
            $user->forum_name,
            $this->getPercentVote($user),
           " $total_rep ",
            $user->is_member == 1 ? 'Voting Associate' : 'Associate',
            ' ' . $user->created_at->format('m-d-Y H:i A'),
        ];
    }
    public function headings(): array
    {
        return [
            'User Id',
            'Email',
            'Telegram',
            'First Name',
            'Last Name',
            'Forumn Name',
            'V%',
            'Total Rep',
            'User Type',
            'Registered Date',
        ];
    }

    public function getPercentVote($user)
    {
        if ($user->is_member == 1) {
            if ($user->total_informal_votes == 0 || !$user->total_informal_votes) {
                return 0 . '%';
            } else {
                $percent = ($user->total_voted / $user->total_informal_votes) * 100;
                return number_format($percent, 2) . '%';
            }
        }
        return null;
    }
}
