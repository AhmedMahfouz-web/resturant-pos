<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\Product;
use App\Models\Recipe;
use App\Imports\RecipesImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

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
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'name' => 'nullable|string|max:255',
            'instructions' => 'nullable|string',
            'materials' => 'required|array', // Array of material IDs and quantities
            'materials.*.material_id' => 'required|exists:materials,id',
            'materials.*.material_quantity' => 'required|numeric|min:0.01',
        ]);

        // Create the recipe for the product
        $recipe = Recipe::create([
            'name' => $request->name ?? Product::find($request->product_id)->name . "'s Recipe",
            'instructions' => $request->instructions ?? null
        ]);

        DB::table('recipe_product')->insert([
            'recipe_id' => $recipe->id,
            'product_id' => $request->product_id
        ]);

        // Attach materials to the recipe
        $recipe->materials()->syncWithoutDetaching($validated['materials']);

        return response()->json(['message' => 'Recipe created successfully', 'recipe' => $recipe->load('materials')], 201);
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
            'recipe' => $recipe,
        ], 200);
    }

    // Update an existing recipe
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'instructions' => 'nullable|string',
            'materials' => 'required|array', // Array of material IDs and quantities
            'materials.*.material_id' => 'required|exists:materials,id',
            'materials.*.material_quantity' => 'required|numeric|min:0.01',
        ]);

        $recipe = Recipe::findOrFail($id);
        $recipe->update([
            'name' => $request->name,
            'instructions' => $request->instructions ?? null
        ]);
        // Update the materials attached to the recipe
        $recipe->materials()->syncWithoutDetaching($validated['materials']);

        return response()->json(['message' => 'Recipe updated successfully', 'recipe' => $recipe->load('materials')], 201);
    }

    // Delete a recipe
    public function destroy($id)
    {
        $recipe = Recipe::findOrFail($id);
        $recipe->materials()->detach();
        $recipe->delete();

        return response()->json(['message' => 'Recipe deleted successfully']);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        $import = new RecipesImport();
        Excel::import($import, $request->file('file'));

        return response()->json([
            'message' => 'Recipes imported successfully',
            'count' => Recipe::count(),
            'errors' => $import->getErrors()
        ]);
    }
}
