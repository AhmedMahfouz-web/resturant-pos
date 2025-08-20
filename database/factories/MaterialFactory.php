<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Material>
 */
class MaterialFactory extends Factory
{
    protected $model = Material::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $materials = [
            'Flour',
            'Sugar',
            'Salt',
            'Butter',
            'Eggs',
            'Milk',
            'Cheese',
            'Tomatoes',
            'Onions',
            'Garlic',
            'Olive Oil',
            'Chicken',
            'Beef',
            'Rice',
            'Pasta'
        ];

        $units = ['kg', 'g', 'l', 'ml', 'pcs', 'dozen'];

        return [
            'name' => $this->faker->randomElement($materials) . ' ' . $this->faker->word(),
            'quantity' => $this->faker->randomFloat(3, 0, 1000),
            'purchase_price' => $this->faker->randomFloat(2, 1, 100),
            'stock_unit' => $this->faker->randomElement($units),
            'recipe_unit' => $this->faker->randomElement($units),
            'conversion_rate' => $this->faker->randomFloat(3, 0.1, 10),
            'minimum_stock_level' => $this->faker->randomFloat(3, 5, 50),
            'maximum_stock_level' => $this->faker->randomFloat(3, 100, 1000),
            'reorder_point' => $this->faker->randomFloat(3, 10, 100),
            'reorder_quantity' => $this->faker->randomFloat(3, 50, 500),
            'default_supplier_id' => Supplier::factory(),
            'storage_location' => $this->faker->optional()->word(),
            'shelf_life_days' => $this->faker->optional()->numberBetween(1, 365),
            'is_perishable' => $this->faker->boolean(30),
            'barcode' => $this->faker->optional()->ean13(),
            'sku' => $this->faker->optional()->bothify('SKU-####-???'),
            'category_id' => null, // We'll handle categories later if needed
        ];
    }

    /**
     * Indicate that the material is perishable.
     */
    public function perishable(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_perishable' => true,
            'shelf_life_days' => $this->faker->numberBetween(1, 30),
        ]);
    }

    /**
     * Indicate that the material is non-perishable.
     */
    public function nonPerishable(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_perishable' => false,
            'shelf_life_days' => null,
        ]);
    }

    /**
     * Indicate that the material is low in stock.
     */
    public function lowStock(): static
    {
        return $this->state(function (array $attributes) {
            $minLevel = $this->faker->randomFloat(3, 5, 20);
            return [
                'minimum_stock_level' => $minLevel,
                'quantity' => $this->faker->randomFloat(3, 0, $minLevel - 1),
            ];
        });
    }

    /**
     * Indicate that the material is out of stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn(array $attributes) => [
            'quantity' => 0,
        ]);
    }

    /**
     * Set a specific quantity.
     */
    public function withQuantity($quantity): static
    {
        return $this->state(fn(array $attributes) => [
            'quantity' => $quantity,
        ]);
    }

    /**
     * Set specific stock levels.
     */
    public function withStockLevels($min, $max, $reorder): static
    {
        return $this->state(fn(array $attributes) => [
            'minimum_stock_level' => $min,
            'maximum_stock_level' => $max,
            'reorder_point' => $reorder,
        ]);
    }
}
