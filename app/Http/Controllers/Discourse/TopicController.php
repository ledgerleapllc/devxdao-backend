<?php

namespace App\Http\Controllers\Discourse;

use App\Http\Controllers\Controller;
use App\Http\Helper;
use App\Proposal;
use App\Services\DiscourseService;
use App\TopicRead;
use App\TopicFlag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TopicController extends Controller
{
    public function index(Request $request, DiscourseService $discourse)
    {
        $tag = config('services.discourse.tag');
        $username = $discourse->getUsername(Auth::user());
        $page = (int) $request->input('page', 0);

        if (filled($request->term)) {
            $search = $discourse->search("#{$tag} {$request->term}", $page + 1, $username);

            if (isset($search['failed'])) {
                return $search;
            }

            $data = ['topics' => $search['topics']];
        } else {
            $data = $discourse->topics($username, $page, $tag);

            if (isset($data['failed'])) {
                return $data;
            }
        }

        return ['success' => true, 'data' => $data];
    }

    public function store(Request $request, DiscourseService $discourse)
    {
        $response = $discourse->createPost([
            'title' => $request->title,
            'raw' => $request->raw,
            'tags' => config('services.discourse.tag'),
        ], $discourse->getUsername(Auth::user()));

        if (isset($response['failed'])) {
            return $response;
        }

        return ['success' => true, 'data' => $response];
    }

    public function update(Request $request, DiscourseService $discourse, string $id)
    {
        $id = (int)$id;
        $response = $discourse->updateTopic(
            $id,
            ['title' => $request->title],
            $discourse->getUsername(Auth::user())
        );

        if (isset($response['failed'])) {
            return $response;
        }

        return ['success' => true, 'data' => $response];
    }

    public function show(DiscourseService $discourse, $id)
    {
        $id = (int)$id;
        $topic = $discourse->topic($id, $discourse->getUsername(Auth::user()));

        if (isset($topic['failed'])) {
            return $topic;
        }

        $proposal = Proposal::select('id', 'status', 'dos_paid', 'topic_posts_count')
            ->where('discourse_topic_id', $id)
            ->first();

        $proposalStatus = $proposal ? Helper::getStatusProposal($proposal) : null;

        $topic['post_stream']['posts'] = $discourse->mergePostsWithDxD($topic['post_stream']['posts']);
        $topic['flags_count'] = TopicFlag::where('topic_id', $id)->count();
        $topic['attestation'] = [
            'related_to_proposal' => !is_null($proposal),
            'proposal_in_discussion' => $proposalStatus === 'In Discussion',
            'is_attestated' => TopicRead::where('topic_id', $id)->where('user_id', Auth::id())->exists(),
            'attestation_rate' => $discourse->attestationRate($id),
        ];

        if ($proposal) {
            $topic['proposal'] = [
                'id' => $proposal->id,
                'status' => $proposalStatus,
                'topic_posts_count' => $proposal->topic_posts_count,
            ];
        }

        return ['success' => true, 'data' => $topic];
    }

    public function flag(Request $request, DiscourseService $discourse, $id)
    {
        $id = (int)$id;
        $user = Auth::user();

        if (!$user->hasRole(['admin', 'super-admin', 'member'])) {
            return ['failed' => true, 'message' => 'You are not allowed to flag topics'];
        }

        $username = $discourse->getUsername($user);

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

        TopicRead::where('topic_id', $id)->delete();

        return ['success' => true];
    }

    public function markAsRead(DiscourseService $discourse, $id)
    {
        $id = (int)$id;
        $user = Auth::user();

        if (!$user->hasRole('member')) {
            return ['failed' => true, 'message' => 'You are not allowed to mark topics as read'];
        }

        $topic = $discourse->topic($id, $discourse->getUsername($user));

        if ($topic['failed'] ?? false) {
            return $topic;
        }

        $proposal = Proposal::select('id', 'status', 'dos_paid')
            ->where('discourse_topic_id', $id)
            ->first();

        if (!$proposal || Helper::getStatusProposal($proposal) !== 'In Discussion') {
            return ['failed' => true, 'message' => 'You can only mark topics as read if they are in discussion'];
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
            'attestation_rate' => $discourse->attestationRate($id),
        ]];
    }
}
