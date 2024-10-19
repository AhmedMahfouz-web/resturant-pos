<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            TableSeeder::class,
            MaterialSeeder::class,
            RecipeSeeder::class,
            DiscountSeeder::class,
        ]);
    }
}
