<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\Recipe;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class RecipeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $faker = Faker::create();
        $faker->addProvider(new \FakerRestaurant\Provider\en_US\Restaurant($faker));

        for ($i = 0; $i < 10; $i++) {
            $recipe = Recipe::create([
                'name' => $faker->foodname() . ' Recipe',
                'instructions' => $faker->paragraph,
            ]);

            // Randomly assign materials to each recipe
            $materials = Material::inRandomOrder()->take(rand(3, 5))->get();
            foreach ($materials as $material) {
                $recipe->materials()->attach($material->id, ['material_quantity' => $faker->randomFloat(2, 100, 500)]);
            }
        }
    }
}
