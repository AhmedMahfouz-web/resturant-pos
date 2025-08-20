<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\MaterialReceipt;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MaterialReceipt>
 */
class MaterialReceiptFactory extends Factory
{
    protected $model = MaterialReceipt::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(3, 1, 500);
        $unitCost = $this->faker->randomFloat(2, 1, 50);

        return [
            'receipt_code' => $this->generateReceiptCode(),
            'material_id' => Material::factory(),
            'supplier_id' => Supplier::factory(),
            'quantity_received' => $quantity,
            'unit' => $this->faker->randomElement(['kg', 'g', 'l', 'ml', 'pcs', 'dozen']),
            'unit_cost' => $unitCost,
            'total_cost' => $quantity * $unitCost,
            'source_type' => $this->faker->randomElement(['company_purchase', 'company_transfer', 'external_supplier']),
            'supplier_name' => $this->faker->company(),
            'invoice_number' => $this->faker->optional()->bothify('INV-####-???'),
            'invoice_date' => $this->faker->optional()->dateTimeBetween('-30 days', 'now'),
            'expiry_date' => $this->faker->optional(0.6)->dateTimeBetween('now', '+1 year'),
            'notes' => $this->faker->optional()->sentence(),
            'received_by' => User::factory(),
            'received_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Generate a receipt code for testing
     */
    private function generateReceiptCode(): string
    {
        $date = $this->faker->date('Ymd');
        $number = str_pad($this->faker->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT);

        return "RCP-{$date}-{$number}";
    }

    /**
     * Indicate that the receipt is from an external supplier.
     */
    public function fromExternalSupplier(): static
    {
        return $this->state(fn(array $attributes) => [
            'source_type' => 'external_supplier',
        ]);
    }

    /**
     * Indicate that the receipt is from a company purchase.
     */
    public function fromCompanyPurchase(): static
    {
        return $this->state(fn(array $attributes) => [
            'source_type' => 'company_purchase',
        ]);
    }

    /**
     * Indicate that the receipt is from a company transfer.
     */
    public function fromCompanyTransfer(): static
    {
        return $this->state(fn(array $attributes) => [
            'source_type' => 'company_transfer',
        ]);
    }

    /**
     * Indicate that the material has an expiry date.
     */
    public function withExpiry(): static
    {
        return $this->state(fn(array $attributes) => [
            'expiry_date' => $this->faker->dateTimeBetween('+1 day', '+1 year'),
        ]);
    }

    /**
     * Indicate that the material has no expiry date.
     */
    public function withoutExpiry(): static
    {
        return $this->state(fn(array $attributes) => [
            'expiry_date' => null,
        ]);
    }

    /**
     * Set a specific quantity and recalculate total cost.
     */
    public function withQuantity($quantity): static
    {
        return $this->state(function (array $attributes) use ($quantity) {
            $unitCost = $attributes['unit_cost'] ?? $this->faker->randomFloat(2, 1, 50);
            return [
                'quantity_received' => $quantity,
                'total_cost' => $quantity * $unitCost,
            ];
        });
    }

    /**
     * Set a specific unit cost and recalculate total cost.
     */
    public function withUnitCost($unitCost): static
    {
        return $this->state(function (array $attributes) use ($unitCost) {
            $quantity = $attributes['quantity_received'] ?? $this->faker->randomFloat(3, 1, 500);
            return [
                'unit_cost' => $unitCost,
                'total_cost' => $quantity * $unitCost,
            ];
        });
    }

    /**
     * Set a specific received date.
     */
    public function receivedOn($date): static
    {
        return $this->state(fn(array $attributes) => [
            'received_at' => $date,
        ]);
    }
}
