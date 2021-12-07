<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SurveyVoteRfpExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct($query, $survey)
    {
        $this->query = $query;
        $this->survey = $survey;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $bid = $this->query;
        return $bid;
    }

    public function map($bid): array
    {
        // dd( $bid->delivery_date->format('m-d-Y'));
        $respone = [
            $bid->id,
            $bid->forum,
            $bid->amount_of_bid,
            ' ' . $bid->delivery_date->format('m-d-Y'),
            $bid->total_vote ?  $bid->total_vote : ' 0 ',
        ];
        $reponse_place = [];
        for ($i = 1; $i <= $this->survey->number_response; $i++) {
            $key = $i . '_place';
            array_push($reponse_place,' ' . $bid->$key);
        }
        return array_merge($respone, $reponse_place);
    }
    public function headings(): array
    {
        $headesReponse = [];
        for ($i = 1; $i <= $this->survey->number_response; $i++) {
            if ($i == 1) {
                array_push($headesReponse, '1_st place');
            } else if ($i == 2) {
                array_push($headesReponse, '2_nd place');
            } else if ($i == 3) {
                array_push($headesReponse, '3_rd place');
            } else {
                array_push($headesReponse, $i . '_th place');
            }
        }
        $titles =  [
            'Bid #',
            'Forum name',
            'Price',
            'Delivery date',
            'Total votes',
        ];
        $headings = array_merge($titles, $headesReponse);
        return $headings;
    }
}
