<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class MilestoneReview extends Model
{
    protected $table = 'milestone_review';
    protected $guarded = [];

    public function getAssignedAtttribute($value)
    {
        return Carbon::parse($value);
    }

    public function getReviewedAtAtttribute($value)
    {
        return Carbon::parse($value);
    }

    public function milestones() {
        return $this->hasMany('App\Milestone', 'proposal_id', 'proposal_id')->orderBy('id', 'asc');
    }

    public function user() {
		return $this->hasOne('App\OpsUser', 'id', 'reviewer');
	}

    public function milestoneCheckList()
	{
		return $this->hasOne('App\MilestoneCheckList', 'milestone_review_id');
	}

    public function milestoneSubmitHistory() {
        return $this->hasOne('App\MilestoneSubmitHistory', 'milestone_review_id');
      }

    public function proposal()
	{
		return $this->hasOne('App\Proposal', 'id', 'proposal_id');
	}
}
