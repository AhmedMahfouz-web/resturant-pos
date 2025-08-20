<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'contact_person' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'payment_terms' => $this->faker->randomElement(['Net 15', 'Net 30', 'Net 45', 'COD', '2/10 Net 30']),
            'lead_time_days' => $this->faker->numberBetween(1, 30),
            'minimum_order_amount' => $this->faker->randomFloat(2, 50, 1000),
            'is_active' => $this->faker->boolean(85), // 85% chance of being active
            'rating' => $this->faker->optional(0.7)->randomFloat(2, 1, 5), // 70% chance of having a rating
            'notes' => $this->faker->optional(0.3)->sentence(), // 30% chance of having notes
        ];
    }

    /**
     * Indicate that the supplier is active.
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the supplier is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the supplier has a high rating.
     */
    public function highRated(): static
    {
        return $this->state(fn(array $attributes) => [
            'rating' => $this->faker->randomFloat(2, 4.0, 5.0),
        ]);
    }

    /**
     * Indicate that the supplier has a low rating.
     */
    public function lowRated(): static
    {
        return $this->state(fn(array $attributes) => [
            'rating' => $this->faker->randomFloat(2, 1.0, 3.0),
        ]);
    }

    /**
     * Indicate that the supplier has no rating.
     */
    public function unrated(): static
    {
        return $this->state(fn(array $attributes) => [
            'rating' => null,
        ]);
    }

    /**
     * Indicate that the supplier is reliable (active and well-rated).
     */
    public function reliable(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => true,
            'rating' => $this->faker->randomFloat(2, 4.0, 5.0),
            'lead_time_days' => $this->faker->numberBetween(1, 7), // Fast delivery
        ]);
    }

    /**
     * Indicate that the supplier has fast delivery.
     */
    public function fastDelivery(): static
    {
        return $this->state(fn(array $attributes) => [
            'lead_time_days' => $this->faker->numberBetween(1, 3),
        ]);
    }

    /**
     * Indicate that the supplier has slow delivery.
     */
    public function slowDelivery(): static
    {
        return $this->state(fn(array $attributes) => [
            'lead_time_days' => $this->faker->numberBetween(14, 30),
        ]);
    }
}
