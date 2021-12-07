<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DosFeeExport implements FromCollection, WithHeadings, WithMapping
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
        $value = $this->getTypeAndAmount($proposal);
        return [
            ' ' . $proposal->created_at->format('m-d-Y H:i A'),
            $proposal->id,
            $proposal->email,
            $value['type'],
            '€' .  number_format($proposal->dos_amount, 2, ',', '.'),
            $proposal->dos_eth_amount ? number_format($proposal->dos_eth_amount, 4) : '',
            $proposal->dos_txid,
            $proposal->dos_txid == config('services.crypto.eth.secret_code') ? 'Yes' : 'No',
        ];
    }
    public function headings(): array
    {
        return [
            'Date and time',
            'Grant number',
            'OP',
            'Type',
            'Amount',
            'ETH',
            'TXID',
            'Bypass?',
        ];
    }

    public function getTypeAndAmount($proposal)
    {
        if ($proposal->dos_eth_amount > 0) {
            return [
                'type' => 'ETH',
                'amount' => $proposal->dos_eth_amount
            ];
        } else if ($proposal->dos_cc_amount > 0) {
            return [
                'type' => 'CC',
                'amount' => '€' .  $proposal->dos_cc_amount
            ];
        } else {
            return [
                'type' => '',
                'amount' => 0
            ];
        }
    }
}
