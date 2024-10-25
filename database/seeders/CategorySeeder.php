<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('categories')->insert([
            ['name' => 'Appetizers', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Main Course', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Beverages', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Desserts', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Salads', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Soups', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Snacks', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Breakfast', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
