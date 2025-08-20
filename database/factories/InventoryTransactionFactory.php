<?php

namespace Database\Factories;

use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryTransaction>
 */
class InventoryTransactionFactory extends Factory
{
    protected $model = InventoryTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'material_id' => Material::factory(),
            'type' => $this->faker->randomElement(['receipt', 'consumption', 'adjustment']),
            'quantity' => $this->faker->randomFloat(3, 0.1, 100),
            'unit_cost' => $this->faker->randomFloat(2, 0.5, 50),
            'user_id' => User::factory(),
            'remaining_quantity' => null,
            'reference_type' => null,
            'reference_id' => null,
            'notes' => $this->faker->optional()->sentence()
        ];
    }

    /**
     * Create a receipt transaction.
     */
    public function receipt(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'receipt',
            'quantity' => $this->faker->randomFloat(3, 1, 50),
        ]);
    }

    /**
     * Create a consumption transaction.
     */
    public function consumption(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'consumption',
            'quantity' => $this->faker->randomFloat(3, 0.1, 20),
        ]);
    }

    /**
     * Create an adjustment transaction.
     */
    public function adjustment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'adjustment',
            'quantity' => $this->faker->randomFloat(3, -10, 10),
            'notes' => $this->faker->randomElement([
                'Stock count adjustment',
                'Damaged goods',
                'Expired items',
                'System correction'
            ])
        ]);
    }

    /**
     * Create a negative adjustment (waste).
     */
    public function waste(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'adjustment',
            'quantity' => $this->faker->randomFloat(3, -10, -0.1),
            'notes' => $this->faker->randomElement([
                'Damaged goods',
                'Expired items',
                'Spoilage',
                'Breakage'
            ])
        ]);
    }
}ser
