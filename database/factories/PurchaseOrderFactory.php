<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        $orderDate = $this->faker->dateTimeBetween('-6 months', 'now');
        $expectedDeliveryDate = Carbon::instance($orderDate)->addDays($this->faker->numberBetween(1, 14));

        // 80% chance of being delivered
        $isDelivered = $this->faker->boolean(80);
        $actualDeliveryDate = null;

        if ($isDelivered) {
            // 70% chance of on-time delivery
            $isOnTime = $this->faker->boolean(70);
            if ($isOnTime) {
                $actualDeliveryDate = $expectedDeliveryDate->copy()->subDays($this->faker->numberBetween(0, 2));
            } else {
                $actualDeliveryDate = $expectedDeliveryDate->copy()->addDays($this->faker->numberBetween(1, 7));
            }
        }

        $status = $this->determineStatus($isDelivered, $expectedDeliveryDate);
        $totalAmount = $this->faker->randomFloat(2, 100, 5000);
        $discountAmount = $this->faker->randomFloat(2, 0, $totalAmount * 0.1);
        $taxAmount = $totalAmount * 0.1; // 10% tax
        $finalAmount = $totalAmount - $discountAmount + $taxAmount;

        return [
            'po_number' => $this->generatePONumber(),
            'supplier_id' => Supplier::factory(),
            'status' => $status,
            'order_date' => $orderDate,
            'expected_delivery_date' => $expectedDeliveryDate,
            'actual_delivery_date' => $actualDeliveryDate,
            'total_amount' => $totalAmount,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'final_amount' => $finalAmount,
            'payment_terms' => $this->faker->randomElement(['Net 30', 'Net 15', 'COD', 'Net 60']),
            'delivery_address' => $this->faker->address,
            'notes' => $this->faker->optional()->sentence,
            'created_by' => User::factory(),
            'approved_by' => $this->faker->boolean(80) ? User::factory() : null,
            'approved_at' => $this->faker->boolean(80) ? $this->faker->dateTimeBetween($orderDate, 'now') : null
        ];
    }

    private function generatePONumber(): string
    {
        $year = now()->year;
        $month = now()->format('m');
        $sequence = $this->faker->unique()->numberBetween(1, 9999);

        return "PO-{$year}{$month}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    private function determineStatus(bool $isDelivered, Carbon $expectedDeliveryDate): string
    {
        if ($isDelivered) {
            return PurchaseOrder::STATUS_RECEIVED;
        }

        if ($expectedDeliveryDate->isPast()) {
            return $this->faker->randomElement([
                PurchaseOrder::STATUS_SENT,
                PurchaseOrder::STATUS_PARTIALLY_RECEIVED
            ]);
        }

        return $this->faker->randomElement([
            PurchaseOrder::STATUS_APPROVED,
            PurchaseOrder::STATUS_SENT
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => PurchaseOrder::STATUS_DRAFT,
            'actual_delivery_date' => null,
            'approved_by' => null,
            'approved_at' => null
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => PurchaseOrder::STATUS_APPROVED,
            'approved_by' => User::factory(),
            'approved_at' => $this->faker->dateTimeBetween($attributes['order_date'], 'now')
        ]);
    }

    public function received(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'actual_delivery_date' => $this->faker->dateTimeBetween(
                $attributes['expected_delivery_date'],
                Carbon::instance($attributes['expected_delivery_date'])->addDays(5)
            )
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => PurchaseOrder::STATUS_SENT,
            'expected_delivery_date' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
            'actual_delivery_date' => null
        ]);
    }
}
