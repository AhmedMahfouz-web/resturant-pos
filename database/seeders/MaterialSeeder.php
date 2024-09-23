<?php

namespace Database\Seeders;

use App\Models\Material;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MaterialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $materials = [
            ['name' => 'Flour', 'purchase_price' => 12.00, 'quantity' => 10000, 'unit' => 'grams'],
            ['name' => 'Sugar', 'purchase_price' => 6.50, 'quantity' => 5000, 'unit' => 'grams'],
            ['name' => 'Butter', 'purchase_price' => 20.00, 'quantity' => 1000, 'unit' => 'grams'],
            ['name' => 'Eggs', 'purchase_price' => 0.50, 'quantity' => 100, 'unit' => 'pieces'],
            ['name' => 'Milk', 'purchase_price' => 2.00, 'quantity' => 50, 'unit' => 'liters'],
            ['name' => 'Vanilla Extract', 'purchase_price' => 15.00, 'quantity' => 500, 'unit' => 'milliliters'],
            ['name' => 'Baking Powder', 'purchase_price' => 8.00, 'quantity' => 200, 'unit' => 'grams'],
            ['name' => 'Cocoa Powder', 'purchase_price' => 25.00, 'quantity' => 300, 'unit' => 'grams'],
            ['name' => 'Yeast', 'purchase_price' => 4.00, 'quantity' => 100, 'unit' => 'grams'],
            ['name' => 'Salt', 'purchase_price' => 1.00, 'quantity' => 1000, 'unit' => 'grams'],
        ];

        foreach ($materials as $material) {
            Material::create($material);
        }
    }
}
