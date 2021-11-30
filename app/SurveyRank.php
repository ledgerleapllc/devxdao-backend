<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SurveyRank extends Model
{
    protected $table = 'survey_rank';

    protected $guarded = [];

    public function proposal() {
        return $this->hasOne('App\Proposal', 'id', 'proposal_id');	
    }
    public function survey() {
        return $this->hasOne('App\Survey', 'id', 'survey_id');	
    }
}
