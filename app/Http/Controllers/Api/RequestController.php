<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\QmsRequest;
use App\Status;
use App\User;
use App\RequestHistory;
use App\RequestAttachment;
use App\RequestStatusSla;
use DB;

class RequestController extends Controller {

    public function index(Request $request) {
        $user = $request->user();
        $roles = $user->roles->pluck('name')->toArray();

        $query = QmsRequest::with([
                    'department',
                    'type',
                    'status',
                    'creator',
                    'assigner'
        ]);
/**********************************************************************SORTING COLUMN FUNCTIONALITY SETTING **************************************************************/
        $sortColumn = $request->sort_column ?? 'created_at';
        $sortDirection = $request->sort_direction ?? 'desc';
        $sortColumn = $request->sort_column ?? 'created_at';
        $sortDirection = $request->sort_direction ?? 'desc';

        switch ($sortColumn) {

            case 'requested_by':

                $query->leftJoin('users as creator', 'creator.id', '=', 'requests.created_by')
                        ->orderBy('creator.name', $sortDirection)
                        ->select('requests.*');

                break;

            case 'department':

                $query->leftJoin('departments', 'departments.id', '=', 'requests.department_id')
                        ->orderBy('departments.name', $sortDirection)
                        ->select('requests.*');

                break;

            case 'status':

                $query->leftJoin('statuses', 'statuses.id', '=', 'requests.status')
                        ->orderBy('statuses.code', $sortDirection)
                        ->select('requests.*');

                break;

            default:

                $query->orderBy($sortColumn, $sortDirection);
        }
/**********************************************************************SORTING COLUMN FUNCTIONALITY SETTING **************************************************************/
        //  ROLE FILTERING
        if (in_array('Admin', $roles)) {

            // no restriction
        } elseif (in_array('Manager', $roles)) {

            $reviewRejectStatusIds = Status::whereIn('name', ['review', 'reject'])
                    ->pluck('id');

            $query->where(function ($q) use ($user, $reviewRejectStatusIds) {
                $q->where('created_by', $user->id)
                        ->orWhere(function ($sub) use ($user, $reviewRejectStatusIds) {
                            $sub->where('department_id', $user->department_id)
                            ->whereIn('status', $reviewRejectStatusIds);
                        });
            });
        } elseif (in_array('Quality Manager', $roles) || in_array('Quality officer', $roles)) {

            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                        ->orWhere('status', '>=', 4);
            });
        } else {

            $query->where('created_by', $user->id);
        }

//SEARCH functionality
        if ($request->search) {

            $search = $request->search;

            $query->where(function ($q) use ($search) {

                $q->where('request_no', 'LIKE', "%{$search}%")
                        ->orWhere('title', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%")
                        ->orWhereHas('creator', function ($sub) use ($search) {
                            $sub->where('name', 'LIKE', "%{$search}%");
                        });
            });
        }


        // 🎯 FILTERS
        if ($request->department_id) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->status_id) {
            $query->where('status', $request->status_id);
        }


        // 🔥 Clone query BEFORE pagination for counts
        $countQuery = clone $query;
        
        $requests = $query->latest()->paginate(25);

        // 🔥 COUNTS (respecting role + filters)
        $counts = [
            'total' => $countQuery->count(),
            'open' => (clone $countQuery)->whereIn('status', [1, 2, 4, 5, 6, 7])->count(),
            'overdue' => (clone $countQuery)
                    ->where('due_date', '<', now())
                    ->whereNotIn('status', [8])
                    ->count(),
            'rejected' => (clone $countQuery)->where('status', 3)->count(),
            'completed' => (clone $countQuery)->where('status', 8)->count(),
        ];

        return response()->json([
                    'data' => $requests->items(),
                    'current_page' => $requests->currentPage(),
                    'last_page' => $requests->lastPage(),
                    'total' => $requests->total(),
                    'per_page' => $requests->perPage(),
                    'counts' => $counts
        ]);
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

            $draftStatus = \App\Status::where('name', 'draft')->first();
            $departmentDetails = \App\Department::where('id', $request->department_id)->first();

            $newRequest = \App\QmsRequest::create([
                        'request_no' => 'REQ-' . time(),
                        'title' => $request->title,
                        'description' => $request->description,
                        'department_id' => $request->department_id,
                        'request_type_id' => $request->request_type_id,
                        'approved_by' => $departmentDetails->manager_id,
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

            /*
              |--------------------------------------------------------------------------
              | Approval table entry
              |--------------------------------------------------------------------------
             */

            \App\RequestApproval::create([
                'request_id' => $newRequest->id,
                'approver_id' => $departmentDetails->manager_id
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
                        'message' => 'Something went wrong:...',
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
                    'approvals.approver',
                    'status',
                    'assigner',
                    'comments.user',
                    'attachments.uploader',
                    'histories.user',
                    'statusSla.status'
                ])->findOrFail($id);

        return response()->json($request);
    }

    public function submit($id) {
        DB::beginTransaction();

        try {

            $request = QmsRequest::findOrFail($id);

            if ($request->status !== 1) {
                return response()->json(['message' => 'Already submitted'], 400);
            }
            $reviewStatus = Status::where('name', 'review')->first();

            $request->update([
                'status' => $reviewStatus->id
            ]);

            RequestHistory::create([
                'request_id' => $id,
                'action' => 'Submitted',
                'remarks' => 'Request submitted for manager review',
                'changed_by' => auth()->id()
            ]);

            DB::commit();
            $currentrequest = QmsRequest::with([
                        'department',
                        'type',
                        'status',
                        'creator'
                    ])->findOrFail($id);

            return response()->json(['success' => true, 'message' => 'Submitted successfully', 'data' => $currentrequest]);
        } catch (\Exception $e) {

            DB::rollback();

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function approve($id) {
        DB::beginTransaction();
        try {
            $request = QmsRequest::findOrFail($id);

            if ($request->status !== 2) {
                return response()->json(['message' => 'Invalid status'], 400);
            }
            $approveStatus = Status::where('name', 'approve')->first();
            $updateddate = date('Y-m-d H:i:s');
            $request->update([
                'status' => $approveStatus->id,
                'approved_by' => auth()->id(),
                'approved_at' => $updateddate
            ]);

            RequestHistory::create([
                'request_id' => $id,
                'action' => 'Approved',
                'remarks' => 'Approved by Manager and forwarded to Quality',
                'changed_by' => auth()->id()
            ]);

            \App\RequestApproval::where('request_id', $id)
                    ->update([
                        'approver_id' => auth()->id(),
                        'status' => 2
            ]);

            //enter new status entry time
            RequestStatusSla::create([
                'request_id' => $id,
                'status_id' => 5,
                'entered_at' => now(),
                'changed_by' => auth()->id(),
            ]);

            DB::commit();
            $currentrequest = QmsRequest::with([
                        'department',
                        'type',
                        'status',
                        'creator'
                    ])->findOrFail($id);

            return response()->json(['message' => 'Approved', 'data' => $currentrequest]);
        } catch (Exception $ex) {
            DB::rollback();

            return response()->json(['success' => false, 'error' => $ex->getMessage()], 500);
        }
    }

    // 🔹 Reject
    public function reject(Request $req, $id) {
        DB::beginTransaction();
        try {

            $req->validate([
                'reason' => 'required|string'
            ]);
            $request = QmsRequest::findOrFail($id);

            if ($request->status !== 2) {
                return response()->json(['message' => 'Invalid status'], 400);
            }
            $rejectStatus = Status::where('name', 'reject')->first();
            $request->update([
                'status' => $rejectStatus->id,
                'rejected_reason' => $req->reason
            ]);

            RequestHistory::create([
                'request_id' => $id,
                'action' => 'Rejected',
                'remarks' => $req->reason,
                'changed_by' => auth()->id()
            ]);

            DB::commit();
            $currentrequest = QmsRequest::with([
                        'department',
                        'type',
                        'status',
                        'creator'
                    ])->findOrFail($id);

            return response()->json(['message' => 'rejected', 'data' => $currentrequest]);
        } catch (Exception $ex) {
            DB::rollback();

            return response()->json(['success' => false, 'error' => $ex->getMessage()], 500);
        }
    }

    public function updateStatus(Request $req, $id) {
        DB::beginTransaction();
        $statusDet = [];
        try {
            $req->validate([
                'status' => 'required|in:open,pending,under process,close',
                'due_date' => 'required_if:status,open|date|after_or_equal:today'
            ]);

            $request = QmsRequest::findOrFail($id);
            $statusDet = Status::where('name', $req->status)->first();

            if ($request->status > $statusDet->id) {
                return response()->json(['message' => 'This status has already been selected for the same request'], 400);
            }
            if ($req->status === 'open') {
                $request->update([
                    'status' => $statusDet->id,
                    'due_date' => $req->due_date
                ]);
            } elseif ($req->status === 'close') {
                $request->update([
                    'status' => $statusDet->id,
                    'closed_date' => date('Y-m-d H:i:s')
                ]);
            } else {
                $request->update([
                    'status' => $statusDet->id
                ]);
            }


            RequestHistory::create([
                'request_id' => $id,
                'action' => 'Status Updated to ' . $req->status,
                'remarks' => 'Changed to ' . $req->status,
                'changed_by' => auth()->id()
            ]);

            //update old sttaus exit time  
            $time = now();
            RequestStatusSla::where('request_id', $id)
                    ->whereNull('exited_at')
                    ->update(['exited_at' => $time]);
            //enter new status entry time
            RequestStatusSla::create([
                'request_id' => $id,
                'status_id' => $statusDet->id,
                'entered_at' => $time,
                'exited_at' => $statusDet->is_final ? $time : null,
                'changed_by' => auth()->id(),
            ]);

            DB::commit();
            $currentrequest = QmsRequest::with([
                        'department',
                        'type',
                        'status',
                        'creator'
                    ])->findOrFail($id);

            return response()->json(['message' => 'Request status has been changed', 'data' => $currentrequest]);
        } catch (Exception $ex) {
            DB::rollback();

            return response()->json(['success' => false, 'error' => $ex->getMessage(), 'status' => $statusDet], 500);
        }
    }

    public function assign(Request $request, $id) {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $qmsRequest = QmsRequest::findOrFail($id);
        $qmsRequest->assigned_to = $request->user_id;
        $qmsRequest->save();
        $user = User::findOrFail($request->user_id);
        RequestHistory::create([
            'request_id' => $id,
            'action' => 'Assigned',
            'remarks' => 'assign to: ' . $user->name,
            'changed_by' => auth()->id()
        ]);

        return response()->json(['message' => 'Assigned successfully']);
    }

    public function update(Request $request, $id) {
        $qmsRequest = QmsRequest::findOrFail($id);

        // Only allow draft editing
        if (!in_array($qmsRequest->status, [1, 2])) {
            return response()->json([
                        'message' => 'Only draft/review requests can be edited.',
                        'status_id' => $qmsRequest->status
                            ], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'department_id' => 'required|exists:departments,id',
            'request_type_id' => 'required|exists:request_types,id',
            'priority' => 'required|string',
            'description' => 'required|string'
        ]);

        $qmsRequest->update($validated);

        RequestHistory::create([
            'request_id' => $id,
            'action' => 'Updated',
            'remarks' => '',
            'changed_by' => auth()->id()
        ]);

        return response()->json([
                    'message' => 'Request updated successfully'
        ]);
    }

    public function close(Request $req, $id) {
        DB::beginTransaction();
        try {

            $req->validate([
                'reason' => 'required|string'
            ]);
            $request = QmsRequest::findOrFail($id);

            if (!in_array($request->status, [5, 6, 7])) {
                return response()->json(['message' => 'Invalid status'], 400);
            }
            $closeStatus = Status::where('name', 'close')->first();
            $request->update([
                'status' => $closeStatus->id,
                'delay_reason' => $req->reason
            ]);

            RequestHistory::create([
                'request_id' => $id,
                'action' => 'Closed',
                'remarks' => $req->reason,
                'changed_by' => auth()->id()
            ]);
            //update old sttaus exit time  
            RequestStatusSla::where('request_id', $id)
                    ->whereNull('exited_at')
                    ->update(['exited_at' => now()]);
            //enter new status entry time
            RequestStatusSla::create([
                'request_id' => $id,
                'status_id' => $closeStatus->id,
                'entered_at' => now(),
                'changed_by' => auth()->id(),
            ]);

            DB::commit();
            $currentrequest = QmsRequest::with([
                        'department',
                        'type',
                        'status',
                        'creator'
                    ])->findOrFail($id);

            return response()->json(['message' => 'closed', 'data' => $currentrequest, 'delay_reason' => $req->reason]);
        } catch (Exception $ex) {
            DB::rollback();

            return response()->json(['success' => false, 'error' => $ex->getMessage()], 500);
        }
    }
}
