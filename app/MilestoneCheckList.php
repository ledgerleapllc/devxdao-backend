<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MilestoneCheckList extends Model
{
    protected $table = 'milestone_checklist';

    protected $guarded = [];

    public function milestone()
	{
		return $this->hasOne('App\Milestone', 'id', 'milestone_id');
	}
}
