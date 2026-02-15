<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Document;
use App\DocumentType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller {

    public function index(Request $request) {
        $perPage = $request->get('per_page', 1);
        $user = $request->user();

        // Get role names (admin, auditor, user)
        $roles = $user->roles->pluck('name')->toArray();

        $query = Document::with([
                    'category:id,name',
                    'type:id,name',
                    'creator:id,name',
                    'approver:id,name'
        ]);

        // 🔐 ROLE-BASED FILTERING
        if (in_array('Admin', $roles)) {
            // Admin sees everything
            $documents = $query->paginate($perPage);
        } elseif (in_array('auditor', $roles)) {
            // Auditor sees approved + under review
            $documents = $query
                    ->whereIn('status', ['Approved', 'Under Review'])
                    ->paginate($perPage);
        } else {
            // Normal users see only approved documents
            $documents = $query
                    ->where('status', 'Approved')
                    ->paginate($perPage);
        }

        return response()->json([
                    'success' => true,
                    'role' => $roles,
                    'data' => $documents
        ]);
    }

    /* ================= CREATE DOCUMENT ================= */

    public function store(Request $request) {
        $request->validate([
            'title' => 'required',
            'department_id' => 'required',
            'request_type_id' => 'required',
             'attachments.*' => 'file|mimes:pdf,doc,docx,jpg,png|max:5120'
            
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
              | Create Folder Based on Request ID
              |--------------------------------------------------------------------------
             */

            $folderPath = 'qms/requests/' . $newRequest->id;

            if ($request->hasFile('attachments')) {

                foreach ($request->file('attachments') as $file) {

                  
                    $fileName = uniqid() . '.' . $file->getClientOriginalExtension();


                    $path = $file->storeAs(
                            $folderPath,
                            $fileName,
                            'public'
                    );

                    \App\RequestAttachment::create([
                        'request_id' => $newRequest->id,
                        'file_name' => $fileName,
                        'file_path' => $path,
                        'uploaded_by' => $request->user()->id
                    ]);
                }
            }

            \App\RequestHistory::create([
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

    /* ================= UPDATE DOCUMENT ================= */

    public function update(Request $request, $id) {
        $document = Document::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:document_categories,id',
            'type_id' => 'required|exists:document_types,id',
            'effective_date' => 'nullable|date',
            'review_date' => 'nullable|date'
        ]);

        $document = Document::create(array_merge(
                                $validated,
                                [
                                    'updated_by' => Auth::id()
                                ]
        ));

        return response()->json([
                    'success' => true,
                    'message' => 'Document updated successfully'
        ]);
    }

    /* ================= SUBMIT FOR REVIEW ================= */

    public function submitForReview($id) {
        $document = Document::findOrFail($id);

        $document->update([
            'status' => 'Under Review',
            'approve_status' => 2
        ]);

        return response()->json([
                    'success' => true,
                    'message' => 'Document submitted for review'
        ]);
    }

    /* ================= APPROVE DOCUMENT ================= */

    public function approve($id) {
        $document = Document::findOrFail($id);

        $document->update([
            'status' => 'Approved',
            'approve_status' => 3,
            'approved_by' => Auth::id(),
            'approved_at' => now()
        ]);

        return response()->json([
                    'success' => true,
                    'message' => 'Document approved'
        ]);
    }

    /* ================= REJECT DOCUMENT ================= */

    public function reject($id) {
        $document = Document::findOrFail($id);

        $document->update([
            'status' => 'Rejected',
            'approve_status' => 4
        ]);

        return response()->json([
                    'success' => true,
                    'message' => 'Document rejected'
        ]);
    }

    /* ================= DELETE ================= */

    public function destroy($id) {
        Document::findOrFail($id)->delete();

        return response()->json([
                    'success' => true,
                    'message' => 'Document deleted'
        ]);
    }

    public function view($id) {
        $document = Document::findOrFail($id);

        if (!Storage::exists($document->file_path)) {
            abort(404);
        }

        $type = strtolower(trim($document->type->name));
        $extension = strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION));

        // Forms → always preview
        if ($type === 'form') {
            return response()->file(storage_path('app/' . $document->file_path));
        }

        // Others → only pdf/jpg
        if (in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'])) {
            return response()->file(storage_path('app/' . $document->file_path));
        }

        abort(403, 'Preview not allowed');
    }

    public function download($id) {
        $document = Document::findOrFail($id);

        if (!Storage::exists($document->file_path)) {
            abort(404);
        }

        $type = strtolower(trim($document->type->name));

        // ONLY allow Forms download
        if ($type !== 'form') {
            abort(403, 'Download not allowed');
        }

        return response()->download(
                        storage_path('app/' . $document->file_path)
        );
    }
}
