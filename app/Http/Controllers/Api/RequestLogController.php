<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RequestLog;

class RequestLogController extends Controller
{
    public function index($id)
    {
        $logs = RequestLog::with('user')
            ->where('request_id', $id)
            ->latest()
            ->get();

        return response()->json($logs);
    }
}
