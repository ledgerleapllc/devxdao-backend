<?php

namespace App\Http\Controllers\Discourse;

use App\Http\Controllers\Controller;
use App\Services\DiscourseService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TopicPostController extends Controller
{
    public function index(Request $request, DiscourseService $discourse, $topic)
    {
        $username = Auth::user()->profile->forum_name;

        $posts = $discourse->postsByTopicId(
            $topic,
            $request->input('post_ids'),
            $username
        );

        $posts = collect($discourse->mergeWithFlags($posts));

        $users = User::query()
            ->select('profile.forum_name', DB::raw('Sum(`reputation`.`value`) as reputation'))
            ->join('profile', 'profile.user_id', '=', 'users.id')
            ->join('reputation', 'reputation.user_id', '=', 'users.id')
            ->whereIn('profile.forum_name', $posts->pluck('username'))
            ->get();

        $posts->transform(function ($post) use ($users) {
            $post['devxdao_user'] = $users->firstWhere('forum_name', $post['username']);
            return $post;
        });

        return ['success' => true, 'data' => $posts];
    }

    public function store(Request $request, DiscourseService $discourse, $topic)
    {
        $data = [
            'raw' => $request->post,
            'topic_id' => $topic,
        ];

        if ($request->has('reply_to_post_number')) {
            $data['reply_to_post_number'] = $request->reply_to_post_number;
        }

        return $discourse->createPost($data, Auth::user()->profile->forum_name);
    }
}
