<?php

namespace App\Http\Controllers\Discourse;

use App\Http\Controllers\Controller;
use App\Services\DiscourseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request, DiscourseService $discourse)
    {
        $notifications = $discourse->notifications(
            $discourse->getUsername(Auth::user()),
            (bool) $request->input('recent', false)
        );

        if (isset($notifications['failed'])) {
            return ['success' => false];
        }

        return ['success' => true, 'data' => $notifications];
    }

    public function markAsRead(DiscourseService $discourse, $id)
    {
        $response = $discourse->markAsReadNotification($id, $discourse->getUsername(Auth::user()));

        if (isset($response['failed'])) {
            return ['success' => false];
        }

        return ['success' => true];
    }
}
