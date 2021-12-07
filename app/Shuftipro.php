<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Shuftipro extends Model
{
	protected $table = 'shuftipro';

	/**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'reference_id',
		'document_proof',
		'address_proof',
    ];
}
