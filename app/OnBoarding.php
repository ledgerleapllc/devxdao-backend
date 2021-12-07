<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OnBoarding extends Model
{
  protected $table = 'onboarding';
  protected $guarded = [];

  protected $hidden = [
    'compliance_token',
  ];

  public function proposal()
  {
    return $this->hasOne('App\Proposal', 'id', 'proposal_id');
  }

  public function user()
  {
    return $this->hasOne('App\User', 'id', 'user_id');
  }

  public function vote()
  {
    return $this->hasOne('App\Vote', 'id', 'vote_id');
  }
}
