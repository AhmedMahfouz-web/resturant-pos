<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(Category::all());
    }

    // Add a new category
    public function createCategory() {}

    // Store a new category
    public function storeCategory(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        $category = Category::create($request->all());

        return response()->json(['message' => 'Category created successfully', 'category' => $category], 201);
    }

    // Update a category
    public function updateCategory(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        $category->update($request->all());

        return response()->json(['message' => 'Category updated successfully', 'category' => $category]);
    }

    // Delete a category
    public function deleteCategory($id)
    {
        Category::findOrFail($id)->delete();
        return response()->json(['message' => 'Category deleted successfully']);
    }
}
