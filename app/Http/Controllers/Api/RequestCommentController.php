<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RequestComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequestCommentController extends Controller
{
    public function store(Request $request, $id)
    {
        $request->validate([
            'comment' => 'required'
        ]);

        $comment = RequestComment::create([
            'request_id' => $id,
            'user_id' => Auth::id(),
            'comment' => $request->comment
        ]);

        return response()->json([
            'message' => 'Comment added successfully',
            'data' => $comment->load('user')
        ]);
    }
}
