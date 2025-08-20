<?php

namespace Database\Factories;

use App\Models\SupplierCommunication;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierCommunicationFactory extends Factory
{
    protected $model = SupplierCommunication::class;

    public function definition(): array
    {
        $communicationDate = $this->faker->dateTimeBetween('-3 months', 'now');
        $responseReceived = $this->faker->boolean(75); // 75% response rate

        $responseDate = null;
        $responseTimeHours = null;
        $satisfactionRating = null;

        if ($responseReceived) {
            $responseDate = $this->faker->dateTimeBetween(
                $communicationDate,
                $communicationDate->copy()->addDays(5)
            );
            $responseTimeHours = $this->faker->randomFloat(1, 0.5, 72);
            $satisfactionRating = $this->faker->randomFloat(1, 2.0, 5.0);
        }

        return [
            'supplier_id' => Supplier::factory(),
            'communication_type' => $this->faker->randomElement(SupplierCommunication::TYPES),
            'subject' => $this->faker->sentence,
            'message' => $this->faker->paragraph,
            'communication_date' => $communicationDate,
            'method' => $this->faker->randomElement(SupplierCommunication::METHODS),
            'initiated_by' => User::factory(),
            'response_received' => $responseReceived,
            'response_date' => $responseDate,
            'response_time_hours' => $responseTimeHours,
            'satisfaction_rating' => $satisfactionRating,
            'notes' => $this->faker->optional()->sentence
        ];
    }

    public function inquiry(): static
    {
        return $this->state(fn(array $attributes) => [
            'communication_type' => SupplierCommunication::TYPE_INQUIRY,
            'subject' => 'Product Inquiry - ' . $this->faker->words(3, true)
        ]);
    }

    public function complaint(): static
    {
        return $this->state(fn(array $attributes) => [
            'communication_type' => SupplierCommunication::TYPE_COMPLAINT,
            'subject' => 'Issue with ' . $this->faker->words(2, true),
            'satisfaction_rating' => $this->faker->randomFloat(1, 1.0, 3.0) // Lower satisfaction for complaints
        ]);
    }

    public function withResponse(): static
    {
        return $this->state(fn(array $attributes) => [
            'response_received' => true,
            'response_date' => $this->faker->dateTimeBetween(
                $attributes['communication_date'],
                $attributes['communication_date']->copy()->addDays(3)
            ),
            'response_time_hours' => $this->faker->randomFloat(1, 1, 48),
            'satisfaction_rating' => $this->faker->randomFloat(1, 3.0, 5.0)
        ]);
    }

    public function withoutResponse(): static
    {
        return $this->state(fn(array $attributes) => [
            'response_received' => false,
            'response_date' => null,
            'response_time_hours' => null,
            'satisfaction_rating' => null
        ]);
    }

    public function quickResponse(): static
    {
        return $this->state(fn(array $attributes) => [
            'response_received' => true,
            'response_time_hours' => $this->faker->randomFloat(1, 0.5, 4),
            'satisfaction_rating' => $this->faker->randomFloat(1, 4.0, 5.0)
        ]);
    }

    public function slowResponse(): static
    {
        return $this->state(fn(array $attributes) => [
            'response_received' => true,
            'response_time_hours' => $this->faker->randomFloat(1, 48, 120),
            'satisfaction_rating' => $this->faker->randomFloat(1, 2.0, 3.5)
        ]);
    }
}
