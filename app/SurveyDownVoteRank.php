<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SurveyDownVoteRank extends Model
{
    protected $table = 'survey_downvote_rank';

    protected $guarded = [];

    public function proposal() {
        return $this->hasOne('App\Proposal', 'id', 'proposal_id');	
    }
    public function survey() {
        return $this->hasOne('App\Survey', 'id', 'survey_id');	
    }
}
