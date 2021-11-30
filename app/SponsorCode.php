<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SponsorCode extends Model
{
	protected $table = 'sponsor_codes';

	public function user() {
  	return $this->hasOne('App\User', 'id', 'user_id');	
  }
}
