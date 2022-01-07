<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TopicFlag extends Model
{
    protected $fillable = [
        'reason',
        'topic_id',
        'post_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
