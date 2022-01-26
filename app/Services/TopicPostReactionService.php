<?php

namespace App\Services;

use App\TopicPostReaction;
use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TopicPostReactionService
{
    public function delete(User $user, $post, $type)
    {
        return DB::table('topic_post_reactions')
            ->where('user_id', $user->id)
            ->where('post_id', $post)
            ->where('type', $type)
            ->delete();
    }

    public function count($post, $type)
    {
        return DB::table('topic_post_reactions')
            ->where('post_id', $post)
            ->where('type', $type)
            ->count();
    }

    public function format($post, Collection $reactions = null)
    {
        if (is_null($reactions)) {
            $reactions = DB::table('topic_post_reactions')
                ->select('post_id', 'user_id', 'type')
                ->where('post_id', $post)
                ->get();
        }

        return $reactions
            ->where('post_id', $post)
            ->groupBy('type')
            ->map(function ($group, $type) {
                return [
                    'type' => $type,
                    'count' => $group->count(),
                    'acted' => $group->where('user_id', Auth::id())->isNotEmpty(),
                ];
            })
            ->values();
    }

    public function react(User $user, $post, $type)
    {
        if ($type === TopicPostReaction::UP_VOTE) {
            $this->delete($user, $post, TopicPostReaction::DOWN_VOTE);
        } else if ($type === TopicPostReaction::DOWN_VOTE) {
            $this->delete($user, $post, TopicPostReaction::UP_VOTE);
        }

        if ($this->acted($user, $post, $type)) {
            return $this->delete($user, $post, $type);
        } else {
            DB::table('topic_post_reactions')->insert([
                'user_id' => $user->id,
                'post_id' => $post,
                'type' => $type,
            ]);
        }
    }

    public function acted(User $user, $post, $type)
    {
        return DB::table('topic_post_reactions')
            ->where('user_id', $user->id)
            ->where('post_id', $post)
            ->where('type', $type)
            ->exists();
    }
}
