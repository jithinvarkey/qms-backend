<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CommentController extends Controller
{
   public function store(Request $request, $id)
{
    $comment = \App\RequestComment::create([
        'request_id' => $id,
        'user_id' => Auth::id(),
        'comment' => $request->comment
    ]);

    return response()->json($comment);
}

}
