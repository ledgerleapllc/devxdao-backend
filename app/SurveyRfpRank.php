<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SurveyRfpRank extends Model
{
    protected $table = 'survey_rfp_rank';

    protected $guarded = [];

    public function surveyRfpBid() {
        return $this->hasOne('App\SurveyRfpBid', 'id', 'bid_id');
    }
}
