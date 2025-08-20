<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = [
            'Appetizers',
            'Main Courses',
            'Desserts',
            'Beverages',
            'Salads',
            'Soups',
            'Pasta',
            'Pizza',
            'Seafood',
            'Vegetarian'
        ];

        return [
            'name' => $this->faker->randomElement($categories),
            'icon' => $this->faker->optional()->word(),
            'color' => $this->faker->optional()->hexColor(),
        ];
    }

    /**
     * Set a specific name.
     */
    public function withName(string $name): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => $name,
        ]);
    }

    /**
     * Set icon and color.
     */
    public function withStyle(string $icon, string $color): static
    {
        return $this->state(fn(array $attributes) => [
            'icon' => $icon,
            'color' => $color,
        ]);
    }
}
