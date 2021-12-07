<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProposalChange extends Model
{
	protected $table = 'proposal_change';

	public function user() {
		return $this->hasOne('App\User', 'id', 'user_id');
	}

	public function proposal() {
		return $this->hasOne('App\Proposal', 'id', 'proposal_id');
	}
}
