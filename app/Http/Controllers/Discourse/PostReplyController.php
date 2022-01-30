<?php

namespace App\Http\Controllers\Discourse;

use App\Http\Controllers\Controller;
use App\Services\DiscourseService;
use Illuminate\Support\Facades\Auth;

class PostReplyController extends Controller
{
    public function index(DiscourseService $discourse, $post)
    {
        $replies = $discourse->postReplies($post, $discourse->getUsername(Auth::user()));

        if (isset($replies['failed'])) {
            return ['success' => false];
        }

        $replies = $discourse->mergePostsWithDxD($replies);

        return ['success' => true, 'data' => $replies];
    }
}
