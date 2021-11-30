<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
	protected $table = 'vote';
	protected $guard = ['id'];

	public function onboarding() {
  	return $this->hasOne('App\OnBoarding', 'vote_id');
  }

  public function proposal() {
  	return $this->hasOne('App\Proposal', 'id', 'proposal_id');	
  }

  public function results() {
  	return $this->hasMany('App\VoteResult', 'vote_id', 'id');
  }
}
