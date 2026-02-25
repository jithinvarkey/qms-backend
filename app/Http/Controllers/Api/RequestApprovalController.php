<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\RequestApproval;
use App\QmsRequest;
use App\Status;
use Illuminate\Http\Request;
use DB;

class RequestApprovalController extends Controller {

    public function approve($id) {
        DB::beginTransaction();

        try {

            $approval = RequestApproval::findOrFail($id);

            $approval->update([
                'approval_status' => 'approved',
                'approved_at' => now()
            ]);

            // Check if all approvals done
            $pending = RequestApproval::where('request_id', $approval->request_id)
                    ->where('approval_status', 'pending')
                    ->count();

            if ($pending == 0) {

                $approvedStatus = Status::where('code', 'approved')->first();

                QmsRequest::where('id', $approval->request_id)
                        ->update(['status_id' => $approvedStatus->id]);
            }

            DB::commit();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false], 500);
        }
    }
    
    public function reject(Request $request, $id)
{
    DB::beginTransaction();

    try {

        $approval = RequestApproval::findOrFail($id);

        $approval->update([
            'approval_status' => 'rejected',
            'approved_at' => now(),
            'remarks' => $request->remarks
        ]);

        $rejectedStatus = Status::where('code', 'rejected')->first();

        QmsRequest::where('id', $approval->request_id)
            ->update(['status_id' => $rejectedStatus->id]);

        DB::commit();

        return response()->json(['success' => true]);

    } catch (\Exception $e) {
        DB::rollback();
        return response()->json(['success' => false], 500);
    }
}

}
