<?php

namespace App\Exports;

use App\Http\Helper;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class MilestoneExport implements FromCollection, WithHeadings, WithMapping, WithCustomCsvSettings, WithColumnFormatting
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
        $mlilestone = $this->query;
        return $mlilestone;
    }

    public function map($mlilestone): array
    {
        $result =  Helper::getResultMilestone($mlilestone);
        return [
            $result['Milestone Number'],
            ($mlilestone->submitted_time || count($mlilestone->votes)) ? 'Submitted' : 'Not Submitted',
            $mlilestone->email,
            $mlilestone->proposal_id,
            $mlilestone->proposal_title,
            $result['Milestone'],
            $mlilestone->grant ? '€' . number_format($mlilestone->grant, 2, ',', '.') : '',
            $mlilestone->deadline ? ' ' . date('m-d-Y',strtotime($mlilestone->deadline)): '',
            $mlilestone->submitted_time ? (' '. $mlilestone->submitted_time->format('m-d-Y')) : '',
            $this->getReviewStatusMilestone($mlilestone),
            Helper::getVoteMilestone($mlilestone),
            $mlilestone->paid ? 'Yes' : 'No',
            $mlilestone->paid_time ? (' ' . $mlilestone->paid_time->format('m-d-Y')) : '',
        ];
    }

    public function headings(): array
    {
        return [
            'Milestone number',
            'Status',
            'OP email',
            'Prop #',
            'Proposal title',
            'Milestone',
            'Euro Value',
            'Due Date',
            'Submitted date',
            'Review status',
            'Vote result',
            'Paid?',
            'Paid Date',
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
            'H' => NumberFormat::FORMAT_TEXT,
            'I' => NumberFormat::FORMAT_TEXT,
            'L' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function getReviewStatusMilestone($mlilestone)
    {
        $status =  $mlilestone->milestone_review_status;
        if($status == 'pending') {
            return 'Not assigned';
        } else if ($status == 'active') {
            return 'Assigned';
        }
        else if ($status == 'denied') {
            return 'Denied';
        }
        else if ($status == 'approved') {
            return 'Approved';
        } else if ($status == 'Not Submitted') {
            return 'Not Submitted';
        } else {
           return 'Approved';
        }
    }
}
