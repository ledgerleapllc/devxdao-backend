<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TopicPostReaction extends Model
{
    const UP_VOTE = 1;
    const DOWN_VOTE = 2;

    protected $fillable = [
        'post_id',
        'type',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
