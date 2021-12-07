<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
	protected $table = 'survey';

    protected $guarded = [];

	public function surveyRanks() {
        return $this->hasMany('App\SurveyRank', 'survey_id', 'id');
    }

    public function surveyDownvoteRanks() {
        return $this->hasMany('App\SurveyDownVoteRank', 'survey_id', 'id');
    }

    public function surveyRfpRanks() {
        return $this->hasMany('App\SurveyRfpRank', 'survey_id', 'id');
    }

    public function surveyRfpBids() {
        return $this->hasMany('App\SurveyRfpBid', 'survey_id', 'id');
    }

    public function surveyResults() {
        return $this->hasMany('App\SurveyResult', 'survey_id', 'id');
    }

    public function surveyDownvoteResults() {
        return $this->hasMany('App\SurveyDownVoteResult', 'survey_id', 'id');
    }

    public function surveyRfpResults() {
        return $this->hasMany('App\SurveyRfpResult', 'survey_id', 'id');
    }

    public function getEndTimeAttribute($value)
    {
        return Carbon::parse($value);
    }

    public function getStartDateAttribute($value)
    {
        return Carbon::parse($value);
    }

    public function getEndDateAttribute($value)
    {
        return Carbon::parse($value);
    }
}
