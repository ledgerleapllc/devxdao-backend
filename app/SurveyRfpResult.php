<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SurveyRfpResult extends Model
{
    protected $table = 'survey_rfp_result';

    protected $guarded = [];

    public function surveyRfpBid() {
        return $this->hasOne('App\SurveyRfpBid', 'id', 'bid_id');
    }
}
