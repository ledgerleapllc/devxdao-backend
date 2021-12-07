<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VoteResult extends Model
{
	protected $table = 'vote_result';

	public function proposal() {
		return $this->hasOne('App\Proposal', 'id', 'proposal_id');
	}

	public function vote() {
		return $this->hasOne('App\Vote', 'id', 'vote_id');
	}

	public function user() {
		return $this->hasOne('App\User', 'id', 'user_id');
	}
}
