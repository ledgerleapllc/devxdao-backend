<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CommentVote extends Model
{
    protected $fillable = [
        'is_up_vote',
    ];

    protected $casts = [
        'is_up_vote' => 'boolean',
    ];

    public function comment()
    {
        return $this->belongsTo(Comment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
