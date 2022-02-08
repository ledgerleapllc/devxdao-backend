<?php

namespace App\Exports;

use App\Http\Helper;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AdminVoteCompletedExport implements FromCollection,  WithHeadings, WithMapping
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
        $vote = $this->query;
        return $vote;
    }

    public function map($vote): array
    {
        $totalMember = Helper::getTotalMembers();
        return [
            $vote->proposal_id,
            $vote->title,
            ucfirst($vote->type),
            $this->getTypeProposal($vote->proposalType),
            '€' . number_format($vote->euros, 2, ',', '.'),
            ucfirst($vote->status),
            $vote->for_value . '/' . rtrim(sprintf('%f', floatval($vote->against_value))),
            $vote->result_count . '/' . $totalMember,
            $vote->updated_at->format('m-d-Y H:i A'),
        ];
    }
    public function headings(): array
    {
        return [
            '#',
            'Title',
            'Type',
            'Ballot Type',
            'Euros',
            'Result',
            'Stake For/Against',
            'Quorum',
            'Completed Date',
        ];
    }

    public function getTypeProposal($type)
    {
        if ($type == "grant")
            return "Grant";
        else if ($type == "simple")
            return "Simple";
        else if ($type == "admin-grant")
            return "Admin Grant";
        else if ($type == "advance-payment")
            return "Advance Payment";
    }
}
