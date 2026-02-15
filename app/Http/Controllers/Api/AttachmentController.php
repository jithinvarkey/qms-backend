<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AttachmentController extends Controller {

    public function store(Request $request, $id) {
        $file = $request->file('file');

        $path = $file->store('attachments');

        $attachment = \App\RequestAttachment::create([
                    'request_id' => $id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'uploaded_by' => Auth::id()
        ]);

        return response()->json($attachment);
    }
}
