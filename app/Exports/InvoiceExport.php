<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;


class InvoiceExport implements FromCollection, WithHeadings, WithMapping
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
        $invoices = $this->query;
        return $invoices;
    }

    public function map($invoice): array
    {
        return [
            $invoice->code,
            $invoice->sent_at ? (new Carbon($invoice->sent_at))->format('m-d-Y H:i A') : '',
            $invoice->payee_email,
            $invoice->proposal_id,
            $invoice->milestone_id,
            $invoice->proposal->title,
            $invoice->paid ? 'Yes' : 'No',
            $invoice->marked_paid_at,
        ];
    }
    public function headings(): array
    {
        return [
            'Invoice',
            'Sent Date',
            'Payee',
            'Grant',
            'Milestone',
            'Proposal Title',
            'Paid',
            'Date Marked Paid',
        ];
    }
}
