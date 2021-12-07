<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Crypto extends Model
{
	protected $table = 'crypto';

	/**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'public_address',
    ];
}
