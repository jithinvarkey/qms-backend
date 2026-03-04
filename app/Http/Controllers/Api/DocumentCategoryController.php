<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\DocumentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DocumentCategoryController extends Controller {

    /**
     * List categories
     */
    public function index(Request $request) {
        $perPage = $request->get('per_page', 20);

        $categories = DocumentCategory::orderBy('id', 'desc')
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
            'name' => 'required|string|max:100|unique:document_categories,name',
            'description' => 'nullable|string',
            'department_id'=>'nullable|exists:departments,id'
        ]);

        $category = DocumentCategory::create([
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'department_id' => $validated['department_id'] ?? null,
                    'isActive' => 1,
                    'created_by' => Auth::id()
        ]);

        return response()->json([
                    'success' => true,
                    'message' => 'Category created successfully',
                    'requestval'=>$request->all(),
                    'data' => $category
                        ], 201);
    }

    /**
     * Update category
     */
    public function update(Request $request, $id) {
        $category = DocumentCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:document_categories,name,' . $id,
            'description' => 'nullable|string',
            'department_id'=>'nullable|exists:departments,id'
        ]);

        $category->update($validated);

        return response()->json([
                    'success' => true,
                    'message' => 'Category updated successfully',
                    'data' => $category
        ]);
    }

    /**
     * Activate / Inactivate category
     */
    public function toggleStatus($id) {
        $category = DocumentCategory::findOrFail($id);

        $category->isActive = !$category->isActive;
        $category->save();

        return response()->json([
                    'success' => true,
                    'message' => 'Status updated',
                    'data' => $category
        ]);
    }

    public function dropdown() {
        $categories = DocumentCategory::where('isActive', 1)
                ->orderBy('name')
                ->get(['id', 'name']);

        return response()->json([
                    'success' => true,
                    'data' => $categories
        ]);
    }
}
