<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DocumentTypeController extends Controller {

    /**
     * List type
     */
    public function index(Request $request) {
        $perPage = $request->get('per_page', 1);

        $categories = DocumentType::orderBy('id', 'desc')
                ->paginate($perPage);

        return response()->json([
                    'success' => true,
                    'data' => $categories
        ]);
    }

    /**
     * Store new category
     */
    public function store(Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:document_types,name',
            'description' => 'nullable|string'
        ]);

        $category = DocumentType::create([
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'isActive' => 1,
                    'created_by' => Auth::id()
        ]);

        return response()->json([
                    'success' => true,
                    'message' => 'Type created successfully',
                    'data' => $category
                        ], 201);
    }

    /**
     * Update type
     */
    public function update(Request $request, $id) {
        $doctype = DocumentType::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:document_categories,name,' . $id,
            'description' => 'nullable|string'
        ]);

        $doctype->update($validated);

        return response()->json([
                    'success' => true,
                    'message' => 'Category updated successfully',
                    'data' => $doctype
        ]);
    }

    /**
     * Activate / Inactivate category
     */
    public function toggleStatus($id) {
        $doctype = DocumentType::findOrFail($id);

        $doctype->isActive = !$doctype->isActive;
        $doctype->save();

        return response()->json([
                    'success' => true,
                    'message' => 'Status updated',
                    'data' => $doctype
        ]);
    }

    public function dropdown() {
        $types = DocumentType::where('isActive', 1)
                ->orderBy('name')
                ->get(['id', 'name']);

        return response()->json([
                    'success' => true,
                    'data' => $types
        ]);
    }
}
