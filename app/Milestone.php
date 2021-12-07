<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Milestone extends Model
{
	protected $table = 'milestone';
	protected $dates= ['submitted_time', 'created_at', 'paid_time'];

	protected $appends = [
        'support_file_url',
    ];

	public function getSupportFileUrlAttribute()
    {
		if(!$this->support_file) {
			return null;
		}
        return asset($this->support_file);
    }

	public function votes()
	{
		return $this->hasMany('App\Vote', 'milestone_id');
	}

	public function milestones()
	{
		return $this->hasMany('App\Milestone', 'proposal_id', 'proposal_id')->orderBy('id', 'asc');
	}

	public function milestoneReview()
	{
		return $this->hasMany('App\MilestoneReview', 'milestone_id');
	}

	public function milestoneCheckList()
	{
		return $this->hasOne('App\MilestoneCheckList', 'milestone_id');
	}

	public function proposal()
	{
		return $this->hasOne('App\Proposal', 'id', 'proposal_id');
	}

	public function milestoneSubmitHistories()
	{
		return $this->hasMany('App\MilestoneSubmitHistory', 'milestone_id');
	}
}
