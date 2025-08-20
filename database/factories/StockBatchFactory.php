<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\MaterialReceipt;
use App\Models\StockBatch;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockBatch>
 */
class StockBatchFactory extends Factory
{
    protected $model = StockBatch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(3, 10, 1000);
        $remainingQuantity = $this->faker->randomFloat(3, 0, $quantity);

        return [
            'material_id' => Material::factory(),
            'batch_number' => $this->generateBatchNumber(),
            'quantity' => $quantity,
            'remaining_quantity' => $remainingQuantity,
            'unit_cost' => $this->faker->randomFloat(2, 1, 50),
            'received_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'expiry_date' => $this->faker->optional(0.7)->dateTimeBetween('now', '+1 year'),
            'supplier_id' => Supplier::factory(),
            'material_receipt_id' => MaterialReceipt::factory(),
        ];
    }

    /**
     * Generate a batch number for testing
     */
    private function generateBatchNumber(): string
    {
        $prefix = strtoupper($this->faker->lexify('???'));
        $date = $this->faker->date('Ymd');
        $sequence = str_pad($this->faker->numberBetween(1, 999), 3, '0', STR_PAD_LEFT);

        return "{$prefix}-{$date}-{$sequence}";
    }

    /**
     * Indicate that the batch is fully available.
     */
    public function available(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'remaining_quantity' => $attributes['quantity'],
            ];
        });
    }

    /**
     * Indicate that the batch is fully consumed.
     */
    public function consumed(): static
    {
        return $this->state(fn(array $attributes) => [
            'remaining_quantity' => 0,
        ]);
    }

    /**
     * Indicate that the batch is partially consumed.
     */
    public function partiallyConsumed(): static
    {
        return $this->state(function (array $attributes) {
            $quantity = $attributes['quantity'];
            return [
                'remaining_quantity' => $this->faker->randomFloat(3, 1, $quantity - 1),
            ];
        });
    }

    /**
     * Indicate that the batch is expired.
     */
    public function expired(): static
    {
        return $this->state(fn(array $attributes) => [
            'expiry_date' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    /**
     * Indicate that the batch is expiring soon.
     */
    public function expiringSoon(): static
    {
        return $this->state(fn(array $attributes) => [
            'expiry_date' => $this->faker->dateTimeBetween('now', '+7 days'),
        ]);
    }

    /**
     * Indicate that the batch has no expiry date.
     */
    public function noExpiry(): static
    {
        return $this->state(fn(array $attributes) => [
            'expiry_date' => null,
        ]);
    }

    /**
     * Set a specific received date.
     */
    public function receivedOn($date): static
    {
        return $this->state(fn(array $attributes) => [
            'received_date' => $date,
        ]);
    }

    /**
     * Set a specific unit cost.
     */
    public function withCost($cost): static
    {
        return $this->state(fn(array $attributes) => [
            'unit_cost' => $cost,
        ]);
    }

    /**
     * Set a specific quantity.
     */
    public function withQuantity($quantity, $remaining = null): static
    {
        return $this->state(fn(array $attributes) => [
            'quantity' => $quantity,
            'remaining_quantity' => $remaining ?? $quantity,
        ]);
    }
}
