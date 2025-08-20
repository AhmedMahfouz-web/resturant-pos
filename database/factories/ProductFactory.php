<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'image' => null,
            'tax' => $this->faker->randomElement(['true', 'false']),
            'service' => $this->faker->randomElement(['true', 'false']),
            'status' => 'true',
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 5, 50),
            'discount_type' => null,
            'discount' => null,
            'category_id' => \App\Models\Category::factory(),
        ];
    }
}
