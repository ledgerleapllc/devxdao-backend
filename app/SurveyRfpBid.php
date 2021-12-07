<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SurveyRfpBid extends Model
{
    protected $table = 'survey_rfp_bid';
    protected $dates = ['delivery_date'];

    protected $guarded = [];
}
