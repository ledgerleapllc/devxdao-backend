<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Proposal extends Model
{
	protected $table = 'proposal';

  /**
   * The attributes that should be hidden for arrays.
   *
   * @var array
   */
  protected $hidden = [
    'bank', 'crypto', 'user',
  ];

	public function user() {
		return $this->hasOne('App\User', 'id', 'user_id');
	}

	public function bank() {
    return $this->hasOne('App\Bank', 'proposal_id');
  }

  public function crypto() {
  	return $this->hasOne('App\Crypto', 'proposal_id');
  }

  public function grants() {
  	return $this->hasMany('App\Grant', 'proposal_id');
  }

  public function milestones() {
  	return $this->hasMany('App\Milestone', 'proposal_id');
  }

  public function signatures() {
    return $this->hasMany('App\Signature', 'proposal_id');
  }

  public function members() {
  	return $this->hasMany('App\Team', 'proposal_id');
  }

  public function citations() {
    return $this->hasMany('App\Citation', 'proposal_id');
  }

  public function files() {
    return $this->hasMany('App\ProposalFile', 'proposal_id');
  }

  public function votes() {
    return $this->hasMany('App\Vote', 'proposal_id');
  }

  public function onboarding() {
    return $this->hasOne('App\OnBoarding', 'proposal_id');
  }

  public function changes() {
    return $this->hasMany('App\ProposalChange', 'proposal_id');
  }
  
  public function teams() {
    return $this->hasMany('App\Team', 'proposal_id');
  }

  public function surveyRanks() {
    return $this->hasMany('App\SurveyRank', 'proposal_id');
  }

  public function milestoneSubmitHistories() {
    return $this->hasMany('App\MilestoneSubmitHistory', 'proposal_id');
  }

  public function signtureGrants()
	{
		return $this->hasMany('App\SignatureGrant', 'proposal_id');
	}

  public function grantLogs()
	{
		return $this->hasMany('App\GrantLog', 'proposal_id');
	}

  public function surveyDownVoteRanks() {
    return $this->hasMany('App\SurveyDownVoteRank', 'proposal_id');
  }

  public function getDeliveredAtAttribute($value) {
    return $value ? (new Carbon($value))->format("Y-m-d") : $value;
  }

  public function proposalRequestPayment() {
    return $this->hasOne('App\Proposal', 'id', 'proposal_request_payment');
  }

  public function proposalRequestFrom() {
    return $this->hasOne('App\Proposal', 'id', 'proposal_request_from');
  }

  public function sponsor() {
    return $this->hasOne('App\SponsorCode', 'id', 'sponsor_code_id');
  }
}
