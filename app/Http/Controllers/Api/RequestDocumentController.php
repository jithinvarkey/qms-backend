<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RequestDocument;
use Illuminate\Support\Facades\Storage;

class RequestDocumentController extends Controller
{
    // 🔹 Download
    public function download($id)
    {
        $document = RequestDocument::findOrFail($id);

        return Storage::download($document->file_path);
    }

    // 🔹 Preview
    public function preview($id)
    {
        $document = RequestDocument::findOrFail($id);

        return response()->file(storage_path('app/'.$document->file_path));
    }
}

