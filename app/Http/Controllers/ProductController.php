<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Recipe;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // List all products
    public function index()
    {
        $products = Product::with('category:id,name recipe')->get();
        return response()->json($products);
    }

    public function createProduct()
    {
        $categories = Category::all();

        return response()->json([
            'categories' => $categories,
        ], 200);
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

    // Edit a product
    public function editProduct($id)
    {
        $categories = Category::all();
        $product = Product::where('id', $id)->with('category:id,name')->first();

        return response()->json([
            'categories' => $categories,
            'product' => $product
        ], 200);
    }

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
