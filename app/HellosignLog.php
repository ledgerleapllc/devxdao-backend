<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class HellosignLog extends Model
{
    protected $table = 'hellosign_log';

    public function user() {
		return $this->hasOne('App\User', 'id', 'user_id');
	}
}
