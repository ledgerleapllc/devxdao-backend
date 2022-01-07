<?php

namespace App;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Comment extends Model
{
    protected $fillable = [
        'comment',
    ];

    protected $appends = [
        'up_voted_by_auth',
        'down_voted_by_auth',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class, 'user_id', 'user_id');
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Comment::class, 'parent_id')->recursive();
    }

    public function votes()
    {
        return $this->hasMany(CommentVote::class);
    }

    public function scopeRecursive(Builder $query)
    {
        return $query->with([
            'children' => function ($query) {
                return $query->sortByVote()->latest();
            },
            'user' => function ($query) {
                return $query
                    ->select('id')
                    ->withCount(['reputations as reputations_count' => function ($query) {
                        $query->select(DB::raw('Sum(`value`)'));
                    }]);
            },
            'profile:id,user_id,forum_name',
        ]);
    }

    public function scopeSortByVote(Builder $query)
    {
        return $query->latest(DB::raw('`up_vote` - `down_vote`'));
    }

    public function getUpVotedByAuthAttribute()
    {
        return $this->upVotedByAuth();
    }

    public function getDownVotedByAuthAttribute()
    {
        return $this->downVotedByAuth();
    }

    public function upVotedByAuth()
    {
        return $this->votes()->where('user_id', auth()->id())->where('is_up_vote', 1)->exists();
    }

    public function downVotedByAuth()
    {
        return $this->votes()->where('user_id', auth()->id())->where('is_up_vote', 0)->exists();
    }
}
