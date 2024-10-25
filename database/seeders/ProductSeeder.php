<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Recipe;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $faker->addProvider(new \FakerRestaurant\Provider\en_US\Restaurant($faker));
        $categories = Category::all();

        for ($i = 0; $i < 100; $i++) {
            $product = Product::create([
                'name' => $faker->foodName(),
                'price' => $faker->randomFloat(2, 1, 20), // Price between $1 and $20
                'description' => $faker->sentence,
                'category_id' => $categories->random()->id,
                'image' => $faker->imageUrl(360, 360, 'food', true)
            ]);

            // Assign random recipes to each product
            $recipes = Recipe::inRandomOrder()->take(rand(1, 2))->get();
            foreach ($recipes as $recipe) {
                $product->recipe()->attach($recipe->id);
            }
        }
    }
}
