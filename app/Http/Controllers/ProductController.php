<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductController extends Controller
{
    // List all products
    public function index()
    {
        $products = Product::with(['category:id,name', 'recipe'])->get();
        return response()->json($products, 200);
    }

    // Show Specific product
    public function show($id)
    {
        $product = Product::where('id', $id)->with(['category:id,name', 'recipe'])->first();
        return response()->json($product, 200);
    }

    // Create a new product
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

    public function import(Request $request)
    {
        // Validate the uploaded file
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        try {
            $file = $request->file('file')->getRealPath();

            // Load the spreadsheet
            $spreadsheet = IOFactory::load($file);
            $sheetData = $spreadsheet->getActiveSheet()->toArray();

            // Skip header row and process data
            $products = [];
            foreach (array_slice($sheetData, 1) as $row) {
                // Validate each row
                if (!empty($row[0])) { // Check if name exists
                    $product = Product::create([
                        'name'          => $row[0],
                        'description'   => $row[1] ?? null,
                        'price'         => $row[2] ?? 0,
                        'category_id'   => $row[3] ?? null,
                        'image'         => $row[4] ?? null,
                        'discount_type' => $row[5] ?? null,
                        'discount'      => $row[6] ?? 0,
                        'tax'           => $row[7] ?? true,
                    ]);
                    $products[] = $product;
                }
            }

            return response()->json([
                'message' => 'Products imported successfully',
                'products' => $products
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error importing products: ' . $e->getMessage()
            ], 500);
        }
    }
}
