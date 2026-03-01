<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\RequestComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\RequestHistory;
use Illuminate\Support\Facades\DB;

class RequestCommentController extends Controller {

    public function index($id) {

        $requests = RequestComment::with([
                    'user',
                ])->where('request_id', $id)->latest()->get();

        return response()->json($requests);
    }

    public function store(Request $request) {
        $request->validate([
            'comment' => 'required'
        ]);

        DB::beginTransaction();

        try {

            $comment = RequestComment::create([
                        'request_id' => $request->request_id,
                        'user_id' => Auth::id(),
                        'comment' => $request->comment
            ]);

            // Only if comment created successfully
            if ($comment) {

                RequestHistory::create([
                    'request_id' => $request->request_id,
                    'action' => 'Comment Added',
                    'remarks' => 'New comment added: ' . $comment->comment,
                    'changed_by' => auth()->id()
                ]);
            }

            DB::commit();

            return response()->json([
                        'message' => 'Comment added successfully',
                        'data' => $comment->load('user')
                            ], 201);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                        'message' => 'Something went wrong',
                        'error' => $e->getMessage()
                            ], 500);
        }


        
    }
}
