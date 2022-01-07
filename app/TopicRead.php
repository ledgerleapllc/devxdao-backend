<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TopicRead extends Model
{
    protected $fillable = [
        'topic_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
