<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\Product;
use Illuminate\Http\Request;

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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0',
            'unit' => 'required|string|max:10',
            'purchase_price' => 'required|numeric ',
        ]);

        $material = Material::create($validated);

        return response()->json(['message' => 'Material created successfully', 'material' => $material], 201);
    }

    // Update a material's quantity or name
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'quantity' => 'sometimes|numeric|min:0',
            'unit' => 'sometimes|string|max:10',
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
}
