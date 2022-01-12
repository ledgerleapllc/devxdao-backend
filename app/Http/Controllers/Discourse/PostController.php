<?php

namespace App\Http\Controllers\Discourse;

use App\Http\Controllers\Controller;
use App\Services\DiscourseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PostController extends Controller
{
    public function react(DiscourseService $discourse, $post)
    {
        $username = Auth::user()->profile->forum_name;

        return $discourse->isLikedTo($post, $username)
            ? $discourse->unlike($post, $username)
            : $discourse->like($post, $username);
    }

    public function show(DiscourseService $discourse, $post)
    {
        return $discourse->post($post, Auth::user()->profile->forum_name);
    }

    public function destroy(DiscourseService $discourse, $post)
    {
        return $discourse->deletePost($post, Auth::user()->profile->forum_name);
    }

    public function update(Request $request, DiscourseService $discourse, $post)
    {
        return $discourse->updatePost(
            $post,
            ['post' => ['raw' => $request->raw]],
            Auth::user()->profile->forum_name
        );
    }
}
