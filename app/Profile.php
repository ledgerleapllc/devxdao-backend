<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
	protected $table = 'profile';

	/**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'twoFA_login_code',
		'rep',
		'rep_pending',
		// 'signature_request_id',
		// 'hellosign_form'
        'company',
        'dob',
        'country_citizenship',
        'country_residence',
        'address',
        'city',
        'zip',
    ];
}
