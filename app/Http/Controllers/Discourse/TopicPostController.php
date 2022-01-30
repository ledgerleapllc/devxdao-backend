<?php

namespace App\Http\Controllers\Discourse;

use App\Http\Controllers\Controller;
use App\Proposal;
use App\Services\DiscourseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TopicPostController extends Controller
{
    public function index(Request $request, DiscourseService $discourse, $topic)
    {
        $posts = $discourse->postsByTopicId(
            $topic,
            $request->input('post_ids'),
            $discourse->getUsername(Auth::user())
        );

        $posts = $discourse->mergePostsWithDxD($posts);

        return ['success' => true, 'data' => $posts];
    }

    public function store(Request $request, DiscourseService $discourse, $topic)
    {
        $proposal = Proposal::where('discourse_topic_id', $topic)->first();

        if (
            !$request->user()->hasRole('member')
            && !$request->user()->hasRole('admin')
            && !$request->user()->hasRole('super_admin')
            && $request->user()->id !== $proposal->user_id
        ) {
            return ['failed' => true, 'message' => 'You are not allowed to post to this topic.'];
        }

        $data = [
            'raw' => $request->post,
            'topic_id' => $topic,
        ];

        if ($request->has('reply_to_post_number')) {
            $data['reply_to_post_number'] = $request->reply_to_post_number;
        }

        $response = $discourse->createPost(
            $data,
            $discourse->getUsername($request->user())
        );

        if (isset($response['failed'])) {
            return $response;
        }

        if ($proposal) {
            $proposal->topic_posts_count = $response['post_number'];
            $proposal->save();
        }

        $posts = $discourse->mergePostsWithDxD([$response]);

        return head($posts);
    }
}
