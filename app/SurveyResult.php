<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SurveyResult extends Model
{
    protected $table = 'survey_result';

    protected $guarded = [];

    public function user() {
        return $this->hasOne('App\User', 'id', 'user_id');	
    }

    public function proposal() {
        return $this->hasOne('App\Proposal', 'id', 'proposal_id');	
    }
}
