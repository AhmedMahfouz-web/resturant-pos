<?php

namespace App\Imports;

use App\Models\Material;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Validator;

class MaterialsImport implements ToCollection, WithHeadingRow
{
    protected $errors = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2; // Adding 2 (1 for 0-index, 1 for header row)

            $validator = Validator::make($row->toArray(), [
                'name' => 'required|string|max:255',
                'current_stock' => 'required|numeric|min:0',
                'stock_unit' => 'required|string',
                'recipe_unit' => 'required|string',
                'conversion_rate' => 'required|numeric|min:0.0001',
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
                    $materialData = [
                        'name' => $row['name'],
                        'current_stock' => $row['current_stock'],
                        'stock_unit' => $row['stock_unit'],
                        'recipe_unit' => $row['recipe_unit'],
                        'conversion_rate' => $row['conversion_rate'],
                    ];

                    if (!empty($row['id']) && Material::where('id', $row['id'])->exists()) {
                        Material::where('id', $row['id'])->update($materialData);
                    } else {
                        Material::create($materialData);
                    }
                });
            } catch (\Exception $e) {
                $this->errors[] = [
                    'line' => $lineNumber,
                    'errors' => [$e->getMessage()]
                ];
            }
        }

        if (!empty($this->errors)) {
            throw new \Exception(json_encode($this->errors));
        }
    }
}
