<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Citation extends Model
{
	protected $table = 'citation';
	
	public function repProposal() {
		return $this->hasOne('App\Proposal', 'id', 'rep_proposal_id');
	}
}
