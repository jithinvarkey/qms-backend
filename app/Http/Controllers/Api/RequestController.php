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
            'request_type_id' => 'required'
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
                        'status_id' => $draftStatus->id,
                        'created_by' => $request->user()->id
            ]);

            /*
              |--------------------------------------------------------------------------
              | Save Attachments (if exists)
              |--------------------------------------------------------------------------
             */

            if ($request->hasFile('attachments')) {

                foreach ($request->file('attachments') as $file) {

                    $path = $file->store('qms/attachments', 'public');

                    RequestAttachment::create([
                        'request_id' => $newRequest->id,
                        'file_name' => $file->getClientOriginalName(),
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

    public function submit($id) {
        DB::beginTransaction();

        try {

            $request = QmsRequest::findOrFail($id);

            $submitted = Status::where('code', 'submitted')->first();

            $oldStatus = $request->status_id;

            $request->update([
                'status_id' => $submitted->id
            ]);

            RequestHistory::create([
                'request_id' => $request->id,
                'action' => 'Submitted',
                'old_status' => $oldStatus,
                'new_status' => $submitted->id,
                'changed_by' => Auth::id()
            ]);

            DB::commit();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {

            DB::rollback();
            return response()->json(['success' => false], 500);
        }
    }
}
