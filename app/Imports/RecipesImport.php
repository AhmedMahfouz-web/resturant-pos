<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Recipe;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Validator;

class RecipesImport implements ToCollection, WithHeadingRow
{
    protected $errors = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;

            $validator = Validator::make($row->toArray(), [
                'product_id' => 'required|exists:products,id',
                'material_id' => 'required|exists:materials,id',
                'material_quantity' => 'required|numeric|min:0.0001'
            ]);

            if ($validator->fails()) {
                $this->errors[] = [
                    'line' => $lineNumber,
                    'errors' => $validator->errors()->all()
                ];
                continue;
            }

            try {
                DB::transaction(function () use ($row) {
                    $product = Product::findOrFail($row['product_id']);

                    // Get existing recipe through pivot
                    $recipe = $product->recipes()->first();

                    // Create new recipe if none exists
                    if (!$recipe) {
                        $recipe = Recipe::create([
                            'name' => $product->name . "'s Recipe",
                            'instructions' => null
                        ]);

                        // Create pivot relationship
                        $product->recipes()->attach($recipe->id);
                    }

                    // Update or create material relationship
                    $recipe->materials()->syncWithoutDetaching([
                        $row['material_id'] => ['material_quantity' => $row['material_quantity']]
                    ]);
                });
            } catch (\Exception $e) {
                $this->errors[] = [
                    'line' => $lineNumber,
                    'errors' => [$e->getMessage()]
                ];
            }
        }
    }

    public function getErrors()
    {
        return $this->errors;
    }
}