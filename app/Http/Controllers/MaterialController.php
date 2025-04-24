<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\MaterialsImport;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MaterialController extends Controller
{
    public function index()
    {
        $materials = Material::all();
        return response()->json([
            'materials' => $materials,
            'headers' => [
                'name',
                'current_stock',
                'stock_unit',
                'recipe_unit',
                'conversion_rate'
            ],
        ]);
    }

    // Show a single material by ID
    public function show($id)
    {
        $material = Material::findOrFail($id);
        return response()->json($material);
    }

    // Create a new material
    // public function store(Request $request)
    // {
    //     $validatedData = $request->validate([
    //         'name' => 'required|string|max:255',
    //         'stock_unit' => 'required|string',
    //         'recipe_unit' => 'required|string',
    //         'conversion_rate' => 'required|numeric'
    //     ]);

    //     $material = Material::create($validatedData);

    //     return response()->json(['message' => 'Material created successfully', 'material' => $material], 201);
    // }

    // Import materials from Excel/CSV
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $file = $request->file('file')->getRealPath();
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        $sheetData = $spreadsheet->getActiveSheet()->toArray();
        $headers = $sheetData[0];
        $requiredHeaders = ['name', 'current_stock', 'stock_unit', 'recipe_unit', 'conversion_rate'];

        if (array_diff($requiredHeaders, $headers)) {
            return response()->json([
                'error' => 'Invalid headers in the file'
            ], 400);
        } else {
            try {
                $materials = [];
                foreach (array_slice($sheetData, 1) as $row) {
                    if (!empty($row[0])) {
                        $material = Material::create([
                            'name' => $row[0],
                            'purchase_price' => 0,
                            'quantity' => 0,
                            'stock_unit' => $row[2],
                            'recipe_unit' => $row[3],
                            'conversion_rate' => $row[4],
                        ]);
                        $materials[] = $material;
                    }
                }

                return response()->json([
                    'message' => 'Materials imported successfully',
                    'materials' => $materials
                ], 201);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Error importing materials: ' . $e->getMessage()
                ], 500);
            }
        }
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
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

    // public function import(Request $request)
    // {
    //     $request->validate([
    //         'file' => 'required|mimes:xlsx,xls'
    //     ]);

    //     try {
    //         // Check if the file exists and is valid
    //         if (!$request->hasFile('file') || !$request->file('file')->isValid()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Invalid file upload'
    //             ], 400);
    //         }

    //         $import = new MaterialsImport();
    //         Excel::import($import, $request->file('file'));

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Materials imported successfully'
    //         ]);
    //     } catch (\Exception $e) {
    //         // Log the full exception for debugging
    //         Log::error('Material import error: ' . $e->getMessage());
    //         Log::error($e->getTraceAsString());

    //         $errors = json_decode($e->getMessage(), true);

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Import failed',
    //             'error' => $e->getMessage(),
    //             'errors' => json_last_error() === JSON_ERROR_NONE ? $errors : [$e->getMessage()]
    //         ], 500);
    //     }
    // }

    // public function excel()
    // {
    //     return response()->json([
    //         'success' => true,
    //         'headers' => [
    //             'name',
    //             'current_stock',
    //             'stock_unit',
    //             'recipe_unit',
    //             'conversion_rate'
    //         ],
    //     ]);
    // }
}
