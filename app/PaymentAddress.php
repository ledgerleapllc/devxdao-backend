<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PaymentAddress extends Model
{
    protected $table = 'payment_address';

    public function paymentAddressChanges()
	{
		return $this->hasMany('App\PaymentAddressChange', 'user_id', 'user_id');
	}
}
