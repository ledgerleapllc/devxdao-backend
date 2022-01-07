<?php

namespace App\Http\Controllers\Discourse;

use App\Http\Controllers\Controller;
use App\Services\DiscourseService;
use App\TopicRead;
use App\TopicFlag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TopicController extends Controller
{
    public function index(Request $request, DiscourseService $discourse)
    {
        $username = Auth::user()->profile->forum_name;
        $page = (int) $request->input('page', 0);

        return ['success' => true, 'data' => $discourse->topics($username, $page)];
    }

    public function store(Request $request, DiscourseService $discourse)
    {
        $response = $discourse->createPost([
            'title' => $request->title,
            'raw' => $request->raw,
        ], Auth::user()->profile->forum_name);

        if (isset($response['failed'])) {
            return $response;
        }

        return ['success' => true, 'data' => $response];
    }

    public function update(Request $request, DiscourseService $discourse, string $id)
    {
        $response = $discourse->updateTopic(
            $id,
            ['title' => $request->title],
            Auth::user()->profile->forum_name
        );

        if (isset($response['failed'])) {
            return $response;
        }

        return ['success' => true, 'data' => $response];
    }

    public function show(DiscourseService $discourse, $id)
    {
        $username = Auth::user()->profile->forum_name;

        $topic = $discourse->topic($id, $username);

        $topic['post_stream']['posts'] = $discourse->mergeWithFlags($topic['post_stream']['posts']);
        $topic['flags_count'] = TopicFlag::where('topic_id', $id)->count();
        $topic['ready_to_vote'] = TopicRead::where('topic_id', $id)->where('user_id', Auth::id())->exists();
        $topic['ready_va_rate'] = $discourse->topicVARate($id);

        return ['success' => true, 'data' => $topic];
    }

    public function flag(Request $request, DiscourseService $discourse, $id)
    {
        $user = Auth::user();

        if (!$user->hasRole(['admin', 'super-admin', 'member'])) {
            return ['failed' => true, 'message' => 'You are not allowed to flag topics'];
        }

        $username = $user->profile->forum_name;

        $post = $discourse->createPost([
            'raw' => $request->reason,
            'topic_id' => $id,
        ], $username);

        if ($post['failed'] ?? false) {
            return $post;
        }

        $flag = new TopicFlag([
            'topic_id' => $id,
            'post_id' => $post['id'],
            'reason' => $request->reason,
        ]);

        $flag->user()->associate($user);
        $flag->save();

        return ['success' => true];
    }

    public function markAsRead(DiscourseService $discourse, $id)
    {
        $user = Auth::user();

        if (!$user->hasRole('member')) {
            return ['failed' => true, 'message' => 'You are not allowed to mark topics as read'];
        }

        $topic = $discourse->topic($id, $user->profile->forum_name);

        if ($topic['failed'] ?? false) {
            return $topic;
        }

        $checkedBefore = TopicRead::query()
            ->where('topic_id', $id)
            ->where('user_id', $user->id)
            ->exists();

        if ($checkedBefore) {
            return ['failed' => true, 'message' => 'You have already checked this topic'];
        }

        $check = new TopicRead(['topic_id' => $id]);
        $check->user()->associate($user);
        $check->save();

        return ['success' => true, 'data' => [
            'ready_va_rate' => $discourse->topicVARate($id),
        ]];
    }
}
