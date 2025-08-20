<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\Product;
use App\Models\Recipe;
use App\Imports\RecipesImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;

class RecipeController extends Controller
{
    // Import recipes from Excel/CSV
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        try {
            $file = $request->file('file')->getRealPath();
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
            $sheetData = $spreadsheet->getActiveSheet()->toArray();

            $recipes = [];
            foreach (array_slice($sheetData, 1) as $row) {
                if (!empty($row[0]) && !empty($row[1]) && !empty($row[2])) {
                    $recipe = Recipe::create([
                        'product_id' => $row[0],
                        'material_id' => $row[1],
                        'material_quantity' => $row[2],
                    ]);
                    $recipes[] = $recipe;
                }
            }

            return response()->json([
                'message' => 'Recipes imported successfully',
                'recipes' => $recipes
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error importing recipes: ' . $e->getMessage()
            ], 500);
        }
    }

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

        // Check if the product already has a recipe
        if (DB::table('recipe_product')->where('product_id', $validated['product_id'])->exists()) {
            return response()->json([
                'message' => 'Product already has a recipe'
            ], 400);
        }

        // Create the recipe for the product
        $recipe = Recipe::create([
            'name' => $validated['name'] ?? Product::find($validated['product_id'])->name . "'s Recipe",
            'instructions' => $validated['instructions'] ?? null,
        ]);

        DB::table('recipe_product')->insert([
            'recipe_id' => $recipe->id,
            'product_id' => $validated['product_id']
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

    // public function import(Request $request)
    // {
    //     $request->validate([
    //         'file' => 'required|file|mimes:xlsx,xls',
    //     ]);

    //     $import = new RecipesImport();
    //     Excel::import($import, $request->file('file'));

    //     return response()->json([
    //         'message' => 'Recipes imported successfully',
    //         'count' => Recipe::count(),
    //         'errors' => $import->getErrors()
    //     ]);
    // }
}
