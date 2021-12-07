<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SurveyVoteExport implements FromCollection, WithHeadings, WithMapping
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
        $proposal = $this->query;
        return $proposal;
    }

    public function map($proposal): array
    {
        $respone = [
            $proposal->id,
            ' ' . $proposal->created_at->format('m-d-Y'),
            $proposal->title,
            $proposal->total_vote ?  $proposal->total_vote : ' 0 ',
        ];
        $reponse_place = [];
        for ($i = 1; $i <= $this->survey->number_response; $i++) {
            $key = 'place_' . $i;
            array_push($reponse_place,' ' . $proposal->$key);
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
            'Proposal number',
            'Date discussion started',
            'Proposal title',
            'Total votes',
        ];
        $headings = array_merge($titles, $headesReponse);
        return $headings;
    }
}
