<?php

namespace App\Exports;

use App\Milestone;
use App\Vote;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;


class ProposalExport implements FromCollection, WithHeadings, WithMapping
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
        // $description =  $proposal->short_description ? substr($proposal->short_description, 0, 150) : substr($proposal->member_reason, 0, 150);
        return [
            $proposal->id,
            $proposal->title,
            $proposal->forum_name,
            $proposal->telegram,
            ucfirst($proposal->type),
            $this->getEuro($proposal),
            $this->getStatus($proposal),
            $proposal->comments ? " $proposal->comments" : ' 0',
            $proposal->changes ? " $proposal->changes" : ' 0',
            ' ' . $proposal->created_at->format('m-d-Y  H:i A')
        ];
    }
    public function headings(): array
    {
        return [
            '#',
            'Title',
            'Forumn Name',
            'Telegram',
            'Type',
            'Euros',
            'Status',
            'Comments',
            'Changes',
            'Date',
        ];
    }

    public function getEuro($proposal)
    {
        $vote = Vote::where('proposal_id', $proposal->id)->orderBy('created_at', 'desc')->first();
        if ($vote) {
            if ($vote->content_type == 'simple') {
                return ' ';
            } else if ($vote->content_type == 'grant') {
                return  $proposal->total_grant ? '€' . number_format($proposal->total_grant, 2, ',', '.') : '';
            } else if ($vote->content_type == 'milestone') {
                $milestone = Milestone::where('id', $vote->milestone_id)->first();
                return $milestone->grant ? '€' . number_format($milestone->grant, 2, ',', '.') : '';;
            } else {
                return '';
            }
        }
        return ' ';
    }

    public function getStatus($proposal)
    {
        $dos_paid = $proposal->dos_paid;
        if ($proposal->status == 'payment') {
            if ($dos_paid) {
                return 'Payment Clearing';
            } else {
                return 'Payment Waiting';
            }
        } else if ($proposal->status == 'pending') {
            return 'Pending';
        } else if ($proposal->status == 'denied') {
            return 'Denied';
        } else if ($proposal->status == 'completed') {
            return 'Completed';
        } else if ($proposal->status == 'approved') {
            $vote = Vote::where('proposal_id', $proposal->id)->orderBy('created_at', 'desc')->first();
            if ($vote) {
                $type = $vote->type == 'formal' ? "Formal Voting" : "Informal Voting";
                if ($vote->status == 'active') {
                    return "$type - Live";
                } else if ($vote->result  == 'success') {
                    return "$type - Passed";
                } else if ($vote->result  == 'no-quorum') {
                    return "$type - No Quorum";
                } else {
                    return "$type - Failed";
                }
            } else {
                return 'In Discussion';
            }
        } else {
            return '';
        }
    }
}
