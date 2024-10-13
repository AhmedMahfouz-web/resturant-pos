<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // List all products
    public function index()
    {
        return response()->json(Product::all());
    }

    public function createProduct()
    {
        $categories = Category::all();

        return response()->json(['categories' => $categories]);
    }

    // Add a new product
    public function storeProduct(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'category_id' => 'required|exists:categories,id',
        ]);

        $product = Product::create($request->all());

        return response()->json(['message' => 'Product created successfully', 'product' => $product], 201);
    }

    // Update a product
    public function updateProduct(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $product->update($request->all());

        return response()->json(['message' => 'Product updated successfully', 'product' => $product]);
    }

    // Delete a product
    public function deleteProduct($id)
    {
        Product::findOrFail($id)->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }
}
