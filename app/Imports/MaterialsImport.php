<?php

namespace App\Imports;

use App\Models\Material;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

class MaterialsImport implements ToCollection, WithHeadingRow
{
    use Importable;

    protected $errors = [];

    /**
     * Required headers for the import file
     */
    protected $requiredHeaders = [
        'name',
        'current_stock',
        'stock_unit',
        'recipe_unit',
        'conversion_rate'
    ];

    /**
     * Validate the headers before processing the data
     */
    public function validateHeaders(array $headers)
    {
        $missingHeaders = [];

        // Check if all required headers exist
        foreach ($this->requiredHeaders as $header) {
            if (!in_array($header, $headers)) {
                $missingHeaders[] = $header;
            }
        }

        if (!empty($missingHeaders)) {
            throw new \Exception('Missing required headers: ' . implode(', ', $missingHeaders));
        }

        return true;
    }

    public function collection(Collection $rows)
    {
        // Get the headers from the first row
        if ($rows->count() > 0 && $rows->first()) {
            $headers = array_keys($rows->first()->toArray());
            $this->validateHeaders($headers);
        }

        foreach ($rows as $index => $row)
        {
            $lineNumber = $index + 2; // Adding 2 (1 for 0-index, 1 for header row)

            if (empty($row['name'])) {
                $this->errors[] = [
                    'line' => $lineNumber,
                    'errors' => ['name' => 'Name is required']
                ];
                continue;
            } else {
            try {
                DB::transaction(function () use ($row) {
                    $materialData = [
                        'name' => $row['name'],
                        'quantity' => $row['current_stock'] ?? 0,
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
    }

    if (!empty($this->errors)) {
        throw new \Exception(json_encode($this->errors));
    }
}
}
