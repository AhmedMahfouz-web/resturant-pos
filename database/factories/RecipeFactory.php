<?php

namespace Database\Factories;

use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Recipe>
 */
class RecipeFactory extends Factory
{
    protected $model = Recipe::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true) . ' Recipe',
            'instructions' => $this->faker->paragraphs(3, true),
        ];
    }

    /**
     * Create a simple recipe.
     */
    public function simple(): static
    {
        return $this->state(fn(array $attributes) => [
            'instructions' => 'Simple recipe instructions',
        ]);
    }
}
