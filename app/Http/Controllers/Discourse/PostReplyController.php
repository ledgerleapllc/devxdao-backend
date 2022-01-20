<?php

namespace App\Http\Controllers\Discourse;

use App\Http\Controllers\Controller;
use App\Services\DiscourseService;
use Illuminate\Support\Facades\Auth;

class PostReplyController extends Controller
{
    public function index(DiscourseService $discourse, $post)
    {
        $username = $discourse->getUsername(Auth::user());
        $replies = $discourse->postReplies($post, $username);

        if (isset($replies['failed'])) {
            return ['success' => false];
        }

        return ['success' => true, 'data' => $replies];
    }
}
