<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
	protected $table = 'invoice';

	protected $appends = [
        'pdf_link_url',
    ];

	public function getPdfLinkUrlAttribute()
    {
		if(!$this->pdf_url) {
			return null;
		}
        return asset($this->pdf_url);
    }

	public function proposal()
	{
		return $this->hasOne('App\Proposal', 'id', 'proposal_id');
	}

    public function milestone()
	{
		return $this->hasOne('App\Milestone', 'id', 'milestone_id');
	}

    public function user()
	{
		return $this->hasOne('App\User', 'id', 'payee_id');
	}

    public function getMarkedPaidAtAttribute($value) {
        return $value ? (new Carbon($value))->format("Y-m-d") : $value;
    }

}
