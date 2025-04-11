<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\Product;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\MaterialsImport;

class MaterialController extends Controller
{
    public function index()
    {
        $materials = Material::all();
        return response()->json($materials);
    }

    // Show a single material by ID
    public function show($id)
    {
        $material = Material::findOrFail($id);
        return response()->json($material);
    }

    // Create a new material
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0',
            'stock_unit' => 'required|string',
            'recipe_unit' => 'required|string',
            'conversion_rate' => 'required|numeric'
        ]);

        $material = Material::create($validatedData);

        return response()->json(['message' => 'Material created successfully', 'material' => $material], 201);
    }

    // Update a material's quantity or name
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'quantity' => 'sometimes|numeric|min:0',
            'stock_unit' => 'sometimes|string',
            'recipe_unit' => 'sometimes|string',
            'conversion_rate' => 'sometimes|numeric'
        ]);

        $material = Material::findOrFail($id);
        $material->update($validated);

        return response()->json(['message' => 'Material updated successfully', 'material' => $material]);
    }

    // Delete a material
    public function destroy($id)
    {
        $material = Material::findOrFail($id);
        $material->delete();

        return response()->json(['message' => 'Material deleted successfully']);
    }

    public function decrementMaterials(Product $product, $quantityOrdered)
    {

        // Retrieve the recipe for the product
        $recipe = $product->recipe;

        // Loop through the materials in the recipe and decrement the quantities
        foreach ($recipe->materials as $material) {
            $materialUsed = $recipe->materials->find($material->id)->pivot->material_quantity;
            $totalMaterialUsed = $materialUsed * $quantityOrdered; // Multiply by the number of products ordered

            // Decrement the material's quantity
            $material->decrement('quantity', $totalMaterialUsed);
        }
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls'
        ]);

        try {
            Excel::import(new MaterialsImport, $request->file('file'));
            return response()->json([
                'success' => true,
                'message' => 'Materials imported successfully'
            ]);
        } catch (\Exception $e) {
            $errors = json_decode($e->getMessage(), true);

            return response()->json([
                'success' => false,
                'errors' => json_last_error() === JSON_ERROR_NONE ? $errors : [$e->getMessage()]
            ], 422);
        }
    }
}
