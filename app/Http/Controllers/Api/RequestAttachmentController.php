<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use Illuminate\Support\Facades\Storage;
use App\RequestAttachment;
use App\RequestHistory;
use Illuminate\Support\Facades\DB;

class RequestAttachmentController extends Controller {

    public function store(Request $request, $id) {
        DB::beginTransaction();

        try {
            $file = $request->file('file');
            $qmsRequest = \App\QmsRequest::findOrFail($id);

            $folder = 'requests/' . $qmsRequest->request_no;

            $fileName = uniqid() . '.' . $file->getClientOriginalExtension();

            $path = $file->storeAs($folder, $fileName);

            $attachment = RequestAttachment::create([
                        'request_id' => $id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'uploaded_by' => Auth::id()
            ]);
            if ($attachment) {
                // ✅ Store History
                RequestHistory::create([
                    'request_id' => $id,
                    'action' => 'Document Attached',
                    'remarks' => 'New document uploaded: ' . $fileName,
                    'changed_by' => auth()->id()
                ]);
            }


            DB::commit();

            return response()->json([
                        'message' => 'Document added successfully',
                        
                            ], 201);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                        'message' => 'Something went wrong',
                        'error' => $e->getMessage()
                            ], 500);
        }




        return response()->json($attachment);
    }

    /**
     * 
     * @param type $id
     * @return type
     */
    public function preview($id) {
        $attachment = RequestAttachment::findOrFail($id);

        $extension = strtolower(pathinfo($attachment->file_name, PATHINFO_EXTENSION));

        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];

        if (!in_array($extension, $allowed)) {
            return response()->json([
                        'message' => 'Preview not allowed for this file type'
                            ], 403);
        }

        return response()->file(storage_path('app/' . $attachment->file_path));
    }

    /**
     * 
     * @param type $id
     * @return type
     */
    public function download($id) {
        $attachment = RequestAttachment::findOrFail($id);

        return response()->download(
                        storage_path('app/' . $attachment->file_path),
                        $attachment->file_name
        );
    }

    public function list(Request $request, $id) {
        $perPage = $request->get('per_page', 10);
        $requests = RequestAttachment::with([
                    'uploader',
                ])->where('request_id', $id)->latest()->paginate($perPage);

        return response()->json($requests);
    }
}
