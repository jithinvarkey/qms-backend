<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Status;
use App\RequestType;
use App\Department;

class MasterController extends Controller {

    //Status of qms request
    public function statuses() {
        $statuses = Status::where('is_active', 1)
                ->orderBy('name')
                ->get(['id', 'name']);

        return response()->json([
                    'success' => true,
                    'data' => $types
        ]);
    }

    //Types of qms request
    public function types() {
        $types = RequestType::where('is_active', 1)
                ->orderBy('name')
                ->get(['id', 'name']);

        return response()->json([
                    'success' => true,
                    'data' => $types
        ]);
    }

    //Department of qms request
    public function departments() {
        $department = Department::where('is_active', 1)
                ->orderBy('name')
                ->get(['id', 'name']);

        return response()->json([
                    'success' => true,
                    'data' => $department
        ]);
    }
}
