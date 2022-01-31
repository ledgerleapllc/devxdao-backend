<?php

namespace App\Http\Controllers\Discourse;

use App\Http\Controllers\Controller;
use App\Services\DiscourseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index(Request $request, DiscourseService $discourse)
    {
        $users = $discourse->searchUsers(
            $request->term,
            $discourse->getUsername(Auth::user()),
        );

        if (isset($users['failed'])) {
            return ['success' => false];
        }

        return ['success' => true, 'data' => $users];
    }
}
