<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    public function index()
    {
        $recipes = Recipe::with('materials')->get();
        return response()->json($recipes);
    }

    // Show a specific recipe
    public function show($id)
    {
        $recipe = Recipe::with('materials')->findOrFail($id);
        return response()->json($recipe);
    }

    // Create a new recipe
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'materials' => 'required|array', // Array of material IDs and quantities
            'materials.*.material_id' => 'required|exists:materials,id',
            'materials.*.material_quantity' => 'required|numeric|min:0.01',
        ]);

        // Create the recipe for the product
        $recipe = Recipe::create([
            'product_id' => $validated['product_id']
        ]);

        // Attach materials to the recipe
        foreach ($validated['materials'] as $material) {
            $recipe->materials()->attach($material['material_id'], [
                'material_quantity' => $material['material_quantity'],
            ]);
        }

        return response()->json(['message' => 'Recipe created successfully', 'recipe' => $recipe], 201);
    }

    // Update an existing recipe
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'materials' => 'required|array', // Array of material IDs and quantities
            'materials.*.material_id' => 'required|exists:materials,id',
            'materials.*.material_quantity' => 'required|numeric|min:0.01',
        ]);

        $recipe = Recipe::findOrFail($id);

        // Update the materials attached to the recipe
        $recipe->materials()->sync([]);
        foreach ($validated['materials'] as $material) {
            $recipe->materials()->attach($material['material_id'], [
                'material_quantity' => $material['material_quantity'],
            ]);
        }

        return response()->json(['message' => 'Recipe updated successfully', 'recipe' => $recipe]);
    }

    // Delete a recipe
    public function destroy($id)
    {
        $recipe = Recipe::findOrFail($id);
        $recipe->materials()->detach();
        $recipe->delete();

        return response()->json(['message' => 'Recipe deleted successfully']);
    }
}
