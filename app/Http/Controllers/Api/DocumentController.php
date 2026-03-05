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

        /*         * ********************************************************************SORTING COLUMN FUNCTIONALITY SETTING ************************************************************* */
        $sortColumn = $request->sort_column ?? 'created_at';
        $sortDirection = $request->sort_direction ?? 'desc';
        $sortColumn = $request->sort_column ?? 'created_at';
        $sortDirection = $request->sort_direction ?? 'desc';

        switch ($sortColumn) {

            case 'category':

                $query->leftJoin('document_categories as category', 'category.id', '=', 'documents.category_id')
                        ->orderBy('category.name', $sortDirection)
                        ->select('documents.*');

                break;

            case 'type':

                $query->leftJoin('document_types', 'document_types.id', '=', 'documents.type_id')
                        ->orderBy('document_types.name', $sortDirection)
                        ->select('documents.*');

                break;

            case 'creator':

                $query->leftJoin('creator', 'creator.id', '=', 'documents.created_by')
                        ->orderBy('creator.name', $sortDirection)
                        ->select('requests.*');

                break;
            
             

            default:

                $query->orderBy($sortColumn, $sortDirection);
        }
        /*         * ********************************************************************SORTING COLUMN FUNCTIONALITY SETTING ************************************************************* */


        // 🔐 ROLE-BASED FILTERING

        if (in_array('Quality Manager', $roles)) {
            $query
                    ->where(function ($q) use ($user) {

                        $q->where('created_by', $user->id)
                        ->orWhere(function ($sub) {
                            $sub->whereIn('status', ['Approved', 'Under Review', 'Rejected']);
                        });
                    });
        } elseif (in_array('Quality officer', $roles)) {

            $query
                    ->where(function ($q) use ($user) {

                        $q->where('created_by', $user->id)
                        ->orWhere(function ($sub) {
                            $sub->whereIn('status', ['Approved']);
                        });
                    });

//            return response()->json([
//                        'sql' => $documents->toSql(),
//                        'bindings' => $documents->getBindings()
//            ]);
        } elseif (in_array('User', $roles)) {


            $query
                    ->whereHas('category', function ($q) use ($user) {
                        $q->whereIn('department_id', [$user->department_id, (int) env('DEPARTMENT_ALL_ID')]);
                    })
                    ->where(function ($q) {
                        $q->whereIn('status', ['Approved']);
                    });
        } elseif (in_array('auditor', $roles)) {
            // Auditor sees approved + under review
            $query
                    ->whereIn('status', ['Approved', 'Under Review']);
        } else {
            // Normal users see only approved documents
            $query
                    ->where('status', 'Approved');
        }


        if ($request->search) {

            $search = $request->search;

            $query->where(function ($q) use ($search) {

                $q->where('document_code', 'LIKE', "%{$search}%")
                        ->orWhere('title', 'LIKE', "%{$search}%")
                        ->orWhere('version', 'LIKE', "%{$search}%")
                        ->orWhere('status', 'LIKE', "%{$search}%")
                        ->orWhereHas('category', function ($sub) use ($search) {
                            $sub->where('name', 'LIKE', "%{$search}%");
                        })
                        ->orWhereHas('type', function ($sub) use ($search) {
                            $sub->where('name', 'LIKE', "%{$search}%");
                        })
                        ->orWhereHas('creator', function ($sub) use ($search) {
                            $sub->where('name', 'LIKE', "%{$search}%");
                        });
            });
        }


        // 🎯 FILTERS
        if ($request->category_id) {
            $query->where('category_id', '=', $request->category_id);
        }

        if ($request->status_id) {
            $query->where('status', '=', $request->status_id);
        }
        if ($request->type_id) {
            $query->where('type_id', $request->type_id);
        }

        // 🔥 Clone query BEFORE pagination for counts
        $countQuery = clone $query;
//            return response()->json([
//                        'sql' => $query->toSql(),
//                        'bindings' => $query->getBindings()
//            ]);
        $documents = $query->latest()->paginate(25);

        // 🔥 COUNTS (respecting role + filters)
        $counts = [
            'total' => $countQuery->count(),
            'review' => (clone $countQuery)->where('status', 'Under Review')->count(),
            'approved' => (clone $countQuery)->where('status', 'Approved')->count(),
            'rejected' => (clone $countQuery)->where('status', 'Rejected')->count()
        ];

        return response()->json([
                    'success' => true,
                    'role' => $roles,
                    'data' => $documents->items(),
                    'current_page' => $documents->currentPage(),
                    'last_page' => $documents->lastPage(),
                    'total' => $documents->total(),
                    'per_page' => $documents->perPage(),
                    'counts' => $counts
        ]);
    }

    /* ================= CREATE DOCUMENT ================= */

    public function store(Request $request) {

        $validated = $request->validate([
            'document_code' => 'required|string|max:50|unique:documents,document_code',
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:document_categories,id',
            'type_id' => 'required|exists:document_types,id',
            'version' => 'required|string|max:10',
            'effective_date' => 'required|date',
            'file' => 'required|file|max:5120' // 5MB max
        ]);
        $type = DocumentType::find($request->type_id);
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        if ($type->name === 'Form') {
            if ($extension !== 'pdf') {
                return response()->json([
                            'message' => 'Only PDF allowed for Form type'
                                ], 422);
            }
        } else {
            $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg'];
            if (!in_array($extension, $allowed)) {
                return response()->json([
                            'message' => 'Invalid file type'
                                ], 422);
            }
        }


        $typeName = strtolower(trim($type->name)); // form, policy, manual

        $folder = 'documents/' . $typeName . '/' . $validated['document_code'];

        $filename = 'v' . $validated['version'] . '.' . $extension;

        if (Storage::exists($folder . '/' . $filename)) {
            return response()->json([
                        'message' => 'Version already exists.'
                            ], 422);
        }

// Store in PRIVATE storage (NOT public)
        $path = $file->storeAs($folder, $filename);

        $document = Document::create(array_merge(
                                $validated,
                                [
                                    'status' => 'Draft',
                                    'approve_status' => 1,
                                    'created_by' => Auth::id(),
                                    'file_path' => $path
                                ]
        ));

        return response()->json([
                    'success' => true,
                    'message' => 'Document created successfully',
                    'data' => $document
                        ], 201);
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

        $document->update(array_merge(
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

    public function submitForReview(Request $request, $id) {
        $document = Document::findOrFail($id);
        $user = $request->user();

        // Get role names (admin, auditor, user)
        $roles = $user->roles->pluck('name')->toArray();
        if (in_array('Quality Manager', $roles)) {
            $document->update([
                'status' => 'Under Review',
                'approve_status' => 2
            ]);
            $document->update([
                'status' => 'Approved',
                'approve_status' => 3,
                'approved_by' => Auth::id(),
                'approved_at' => now()
            ]);
        } else {
            $document->update([
                'status' => 'Under Review',
                'approve_status' => 2
            ]);
        }



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
