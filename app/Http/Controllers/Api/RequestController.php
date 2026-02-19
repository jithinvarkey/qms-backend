<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\QmsRequest;
use App\Status;
use App\RequestHistory;
use App\RequestAttachment;
use Auth;
use DB;
use Illuminate\Support\Facades\Storage;

class RequestController extends Controller {

    public function index() {
        $requests = QmsRequest::with([
                    'department',
                    'type',
                    'status',
                    'creator'
                ])->latest()->paginate(10);

        return response()->json($requests);
    }

    public function store(Request $request) {
        $request->validate([
            'title' => 'required',
            'department_id' => 'required',
            'request_type_id' => 'required',
            'attachments.*' => 'file|max:2048'
        ]);

        DB::beginTransaction();

        try {

            $draftStatus = \App\Status::where('code', 'draft')->first();

            $newRequest = \App\QmsRequest::create([
                        'request_no' => 'REQ-' . time(),
                        'title' => $request->title,
                        'description' => $request->description,
                        'department_id' => $request->department_id,
                        'request_type_id' => $request->request_type_id,
                        'status' => $draftStatus->id,
                        'created_by' => $request->user()->id
            ]);

            /*
              |--------------------------------------------------------------------------
              | Save Attachments (if exists)
              |--------------------------------------------------------------------------
             */

            if ($request->hasFile('attachments') && count($request->file('attachments')) > 3) {
                return response()->json([
                            'message' => 'Maximum 3 attachments allowed'
                                ], 422);
            }
            if ($request->hasFile('attachments')) {

                $folder = 'requests/' . $newRequest->request_no;

                foreach ($request->file('attachments') as $file) {


                    $fileName = uniqid() . '.' . $file->getClientOriginalExtension();

                    $path = $file->storeAs($folder, $fileName);

                    RequestAttachment::create([
                        'request_id' => $newRequest->id,
                        'file_name' => $fileName,
                        'file_path' => $path,
                        'uploaded_by' => $request->user()->id
                    ]);
                }
            }

            /*
              |--------------------------------------------------------------------------
              | Log History
              |--------------------------------------------------------------------------
             */

            RequestHistory::create([
                'request_id' => $newRequest->id,
                'action' => 'Created',
                'old_status' => null,
                'new_status' => $draftStatus->id,
                'changed_by' => $request->user()->id,
                'remarks' => 'Request created'
            ]);

            DB::commit();

            return response()->json([
                        'success' => true,
                        'message' => 'Request created successfully',
                        'data' => $newRequest->load('attachments')
            ]);
        } catch (\Exception $e) {

            DB::rollback();

            return response()->json([
                        'success' => false,
                        'message' => 'Something went wrong',
                        'error' => $e->getMessage()
                            ], 500);
        }
    }

    // 🔹 View Request with all relations
    public function show($id) {
        $request = QmsRequest::with([
                    'department',
                    'type',
                    'creator',
                    'status',
                    'comments.user',
                    'attachments.uploader',
                    'histories.user'
                ])->findOrFail($id);

        return response()->json($request);
    }

    public function submit($id) {
        DB::beginTransaction();

        try {

            $request = QmsRequest::findOrFail($id);

            if ($request->status !== 'draft') {
                return response()->json(['message' => 'Already submitted'], 400);
            }

            $request->update([
                'status' => 'review'
            ]);

            RequestHistory::create([
                'request_id' => $id,
                'action' => 'Submitted',
                'remarks' => 'Request submitted for manager review',
                'changed_by' => auth()->id()
            ]);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Submitted successfully']);
        } catch (\Exception $e) {

            DB::rollback();
            return response()->json(['success' => false], 500);
        }
    }

    public function approve($id) {
        $request = RequestModel::findOrFail($id);

        if ($request->status !== 'review') {
            return response()->json(['message' => 'Invalid status'], 400);
        }

        $request->update([
            'status' => 'open'
        ]);

        RequestHistory::create([
            'request_id' => $id,
            'action' => 'Approved',
            'remarks' => 'Approved by Manager and forwarded to Quality',
            'changed_by' => auth()->id()
        ]);

        return response()->json(['message' => 'Approved']);
    }

    // 🔹 Reject
    public function reject(Request $req, $id) {
        $req->validate([
            'reason' => 'required|string'
        ]);

        $request = RequestModel::findOrFail($id);

        $request->update([
            'status' => 'rejected'
        ]);

        RequestHistory::create([
            'request_id' => $id,
            'action' => 'Rejected',
            'remarks' => $req->reason,
            'changed_by' => auth()->id()
        ]);

        return response()->json(['message' => 'Rejected']);
    }

    public function updateStatus(Request $req, $id) {
        $req->validate([
            'status' => 'required|in:open,pending,under_process,closed'
        ]);

        $request = RequestModel::findOrFail($id);

        $request->update([
            'status' => $req->status
        ]);

        RequestHistory::create([
            'request_id' => $id,
            'action' => 'Status Updated',
            'remarks' => 'Changed to ' . $req->status,
            'changed_by' => auth()->id()
        ]);

        return response()->json(['message' => 'Status updated']);
    }
}
