<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\DocumentCategory;
use Illuminate\Http\Request;

class DocumentCategoryController extends Controller
{
    /**
     * List categories
     */
    public function index()
    {
        $categories = DocumentCategory::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Store new category
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:document_categories,name',
            'description' => 'nullable|string'
        ]);

        $category = DocumentCategory::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => 1
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $category
        ], 201);
    }

    /**
     * Update category
     */
    public function update(Request $request, $id)
    {
        $category = DocumentCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:document_categories,name,' . $id,
            'description' => 'nullable|string'
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
    public function toggleStatus($id)
    {
        $category = DocumentCategory::findOrFail($id);

        $category->is_active = !$category->is_active;
        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Status updated',
            'data' => $category
        ]);
    }
}
