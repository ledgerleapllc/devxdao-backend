<?php

namespace App\Http\Controllers\Discourse;

use App\Http\Controllers\Controller;
use App\Services\DiscourseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    public function index(Request $request, DiscourseService $discourse)
    {
        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'folder' => ['nullable', 'string', 'in:inbox,sent,new,unread,archive'],
        ]);

        $messages = $discourse->messages(
            $discourse->getUsername(Auth::user()),
            $request->input('folder', ''),
            (int) $request->input('page', 0)
        );

        if (isset($messages['failed'])) {
            return ['success' => false];
        }

        return ['success' => true, 'data' => $messages];
    }

    public function store(Request $request, DiscourseService $discourse)
    {
        $response = $discourse->createPost([
            'title' => $request->title,
            'raw' => $request->raw,
            'target_recipients' => implode(',', (array) $request->recipients),
            'archetype' => 'private_message',
        ], $discourse->getUsername(Auth::user()));

        if (isset($response['failed'])) {
            return $response;
        }

        return ['success' => true, 'data' => $response];
    }
}
