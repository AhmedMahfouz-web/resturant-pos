<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\Product;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecipeController extends Controller
{
    public function index()
    {
        $recipes = Recipe::with('materials')->get();
        return response()->json($recipes, 200);
    }

    // Show a specific recipe
    public function show($id)
    {
        $recipe = Recipe::with(['materials', 'product'])->findOrFail($id);
        return response()->json($recipe, 200);
    }

    // Create a new recipe
    public function create()
    {
        $materials = Material::all();
        $products = Product::all();

        return response()->json([
            'materials' => $materials,
            'products' => $products,
        ], 200);
    }

    // Create a new recipe
    public function store(Request $request)
    {
        // $validated = $request->validate([
        //     'product_id' => 'required|exists:products,id|unique',
        //     'materials' => 'required|array', // Array of material IDs and quantities
        //     'materials.*.material_id' => 'required|exists:materials,id',
        //     'materials.*.material_quantity' => 'required|numeric|min:0.01',
        // ]);

        // Create the recipe for the product
        $recipe = Recipe::create([
            'product_id' => $request->product_id,
            'name' => $request->name,
            'instructions' => $request->instructions
        ]);

        DB::table('recipe_product')->insert([
            'recipe_id' => $recipe->id,
            'product_id' => $request->product_id
        ]);

        // Attach materials to the recipe
        foreach ($request->materials as $material) {
            $recipe->materials()->attach($material['material_id'], [
                'material_quantity' => $material['material_quantity'],
            ]);
        }

        return response()->json(['message' => 'Recipe created successfully', 'recipe' => $recipe], 201);
    }

    // Edit a new recipe
    public function edit($id)
    {
        $materials = Material::all();
        $products = Product::all();
        $recipe = Recipe::with(['materials', 'product'])->findOrFail($id);

        return response()->json([
            'materials' => $materials,
            'products' => $products,
        ], 200);
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
        $recipe->update([$request->name, $request->instructions]);
        // Update the materials attached to the recipe
        $recipe->materials()->sync([]);
        foreach ($validated['materials'] as $material) {
            $recipe->materials()->attach($material['material_id'], [
                'material_quantity' => $material['material_quantity'],
            ]);
        }

        return response()->json(['message' => 'Recipe updated successfully', 'recipe' => $recipe], 201);
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
