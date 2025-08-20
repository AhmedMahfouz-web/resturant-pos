<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Shift;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'ORD-' . $this->faker->unique()->numberBetween(1000, 9999),
            'status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'cancelled']),
            'tax' => $this->faker->randomFloat(2, 0, 10),
            'service' => $this->faker->randomFloat(2, 0, 5),
            'discount_value' => $this->faker->randomFloat(2, 0, 20),
            'discount_type' => $this->faker->randomElement(['percentage', 'cash']),
            'discount' => $this->faker->randomFloat(2, 0, 15),
            'sub_total' => $this->faker->randomFloat(2, 10, 100),
            'total_amount' => $this->faker->randomFloat(2, 10, 120),
            'type' => $this->faker->randomElement(['dine_in', 'takeaway', 'delivery']),
            'user_id' => User::factory(),
            'shift_id' => Shift::factory(),
        ];
    }
}
