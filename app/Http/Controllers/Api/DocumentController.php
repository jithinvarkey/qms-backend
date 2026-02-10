<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Document;

class DocumentController extends Controller {

    public function index(Request $request) {
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
        if (in_array('admin', $roles)) {
            // Admin sees everything
            $documents = $query->get();
        } elseif (in_array('auditor', $roles)) {
            // Auditor sees approved + under review
            $documents = $query
                    ->whereIn('status', ['Approved', 'Under Review'])
                    ->get();
        } else {
            // Normal users see only approved documents
            $documents = $query
                    ->where('status', 'Approved')
                    ->get();
        }

        return response()->json([
                    'success' => true,
                    'role' => $roles,
                    'data' => $documents
        ]);
    }
}
