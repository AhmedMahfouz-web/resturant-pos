<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\StockAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockAlert>
 */
class StockAlertFactory extends Factory
{
    protected $model = StockAlert::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $alertType = $this->faker->randomElement(StockAlert::ALERT_TYPES);

        return [
            'material_id' => Material::factory(),
            'alert_type' => $alertType,
            'threshold_value' => $this->getThresholdValue($alertType),
            'current_value' => $this->getCurrentValue($alertType),
            'message' => $this->generateMessage($alertType),
            'is_resolved' => $this->faker->boolean(20), // 20% chance of being resolved
            'resolved_at' => $this->faker->optional(0.2)->dateTimeBetween('-7 days', 'now'),
            'resolved_by' => $this->faker->optional(0.2)->randomElement([null, User::factory()]),
        ];
    }

    /**
     * Get threshold value based on alert type
     */
    private function getThresholdValue(string $alertType): float
    {
        return match ($alertType) {
            StockAlert::ALERT_TYPE_LOW_STOCK => $this->faker->randomFloat(3, 10, 50),
            StockAlert::ALERT_TYPE_OUT_OF_STOCK => 0,
            StockAlert::ALERT_TYPE_OVERSTOCK => $this->faker->randomFloat(3, 100, 500),
            StockAlert::ALERT_TYPE_EXPIRY_WARNING => 7,
            StockAlert::ALERT_TYPE_EXPIRY_CRITICAL => 2,
            default => $this->faker->randomFloat(3, 1, 100),
        };
    }

    /**
     * Get current value based on alert type
     */
    private function getCurrentValue(string $alertType): float
    {
        return match ($alertType) {
            StockAlert::ALERT_TYPE_LOW_STOCK => $this->faker->randomFloat(3, 1, 9),
            StockAlert::ALERT_TYPE_OUT_OF_STOCK => 0,
            StockAlert::ALERT_TYPE_OVERSTOCK => $this->faker->randomFloat(3, 501, 1000),
            StockAlert::ALERT_TYPE_EXPIRY_WARNING => $this->faker->numberBetween(3, 7),
            StockAlert::ALERT_TYPE_EXPIRY_CRITICAL => $this->faker->numberBetween(0, 2),
            default => $this->faker->randomFloat(3, 1, 100),
        };
    }

    /**
     * Generate message based on alert type
     */
    private function generateMessage(string $alertType): string
    {
        return match ($alertType) {
            StockAlert::ALERT_TYPE_LOW_STOCK => 'Low stock alert: Material is below minimum level',
            StockAlert::ALERT_TYPE_OUT_OF_STOCK => 'Out of stock: Material is completely out of stock',
            StockAlert::ALERT_TYPE_OVERSTOCK => 'Overstock alert: Material exceeds maximum level',
            StockAlert::ALERT_TYPE_EXPIRY_WARNING => 'Expiry warning: Material batch expires soon',
            StockAlert::ALERT_TYPE_EXPIRY_CRITICAL => 'CRITICAL: Material batch expires very soon',
            default => 'Stock alert for material',
        };
    }

    /**
     * Indicate that the alert is unresolved.
     */
    public function unresolved(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_resolved' => false,
            'resolved_at' => null,
            'resolved_by' => null,
        ]);
    }

    /**
     * Indicate that the alert is resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_resolved' => true,
            'resolved_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'resolved_by' => User::factory(),
        ]);
    }

    /**
     * Indicate that the alert is critical.
     */
    public function critical(): static
    {
        $criticalType = $this->faker->randomElement([
            StockAlert::ALERT_TYPE_OUT_OF_STOCK,
            StockAlert::ALERT_TYPE_EXPIRY_CRITICAL
        ]);

        return $this->state(fn(array $attributes) => [
            'alert_type' => $criticalType,
            'threshold_value' => $this->getThresholdValue($criticalType),
            'current_value' => $this->getCurrentValue($criticalType),
            'message' => $this->generateMessage($criticalType),
        ]);
    }

    /**
     * Set a specific alert type.
     */
    public function ofType(string $alertType): static
    {
        return $this->state(fn(array $attributes) => [
            'alert_type' => $alertType,
            'threshold_value' => $this->getThresholdValue($alertType),
            'current_value' => $this->getCurrentValue($alertType),
            'message' => $this->generateMessage($alertType),
        ]);
    }

    /**
     * Set the alert as low stock type.
     */
    public function lowStock(): static
    {
        return $this->ofType(StockAlert::ALERT_TYPE_LOW_STOCK);
    }

    /**
     * Set the alert as out of stock type.
     */
    public function outOfStock(): static
    {
        return $this->ofType(StockAlert::ALERT_TYPE_OUT_OF_STOCK);
    }

    /**
     * Set the alert as overstock type.
     */
    public function overstock(): static
    {
        return $this->ofType(StockAlert::ALERT_TYPE_OVERSTOCK);
    }

    /**
     * Set the alert as expiry warning type.
     */
    public function expiryWarning(): static
    {
        return $this->ofType(StockAlert::ALERT_TYPE_EXPIRY_WARNING);
    }

    /**
     * Set the alert as expiry critical type.
     */
    public function expiryCritical(): static
    {
        return $this->ofType(StockAlert::ALERT_TYPE_EXPIRY_CRITICAL);
    }

    /**
     * Set the alert as recent (created within specified days).
     */
    public function recent(int $days = 7): static
    {
        return $this->state(fn(array $attributes) => [
            'created_at' => $this->faker->dateTimeBetween("-{$days} days", 'now'),
        ]);
    }
}
