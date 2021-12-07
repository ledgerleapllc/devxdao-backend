<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FinalGrant extends Model
{
	protected $table = 'final_grant';

	public function proposal()
	{
		return $this->hasOne('App\Proposal', 'id', 'proposal_id');
	}

	public function user()
	{
		return $this->hasOne('App\User', 'id', 'user_id');
	}

	public function signtureGrants()
	{
		return $this->hasMany('App\SignatureGrant', 'proposal_id', 'proposal_id');
	}

	public function grantLogs()
	{
		return $this->hasMany('App\GrantLog', 'proposal_id', 'proposal_id');
	}
}
