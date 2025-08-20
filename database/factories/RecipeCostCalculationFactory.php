<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\RecipeCostCalculation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RecipeCostCalculation>
 */
class RecipeCostCalculationFactory extends Factory
{
    protected $model = RecipeCostCalculation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalCost = $this->faker->randomFloat(2, 5, 50);
        $servingSize = $this->faker->numberBetween(1, 8);
        $costPerServing = $totalCost / $servingSize;

        return [
            'recipe_id' => Recipe::factory(),
            'calculation_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'total_cost' => $totalCost,
            'cost_per_serving' => $costPerServing,
            'calculation_method' => $this->faker->randomElement(RecipeCostCalculation::CALCULATION_METHODS),
            'cost_breakdown' => $this->generateCostBreakdown($totalCost),
            'calculated_by' => User::factory(),
        ];
    }

    /**
     * Generate a sample cost breakdown
     */
    private function generateCostBreakdown(float $totalCost): array
    {
        $materials = ['Flour', 'Sugar', 'Butter', 'Eggs', 'Milk'];
        $breakdown = [];
        $remainingCost = $totalCost;

        for ($i = 0; $i < $this->faker->numberBetween(2, 4); $i++) {
            $materialCost = $i === 3 ? $remainingCost : $this->faker->randomFloat(2, 1, $remainingCost * 0.6);
            $quantity = $this->faker->randomFloat(2, 0.5, 10);
            $unitCost = $quantity > 0 ? $materialCost / $quantity : 0;

            $breakdown[] = [
                'material_id' => $this->faker->numberBetween(1, 100),
                'material_name' => $this->faker->randomElement($materials),
                'quantity' => $quantity,
                'unit' => $this->faker->randomElement(['kg', 'g', 'l', 'ml', 'pcs']),
                'unit_cost' => $unitCost,
                'total_cost' => $materialCost,
                'calculation_method' => 'fifo'
            ];

            $remainingCost -= $materialCost;
            if ($remainingCost <= 0) break;
        }

        return $breakdown;
    }

    /**
     * Set a specific calculation method.
     */
    public function method(string $method): static
    {
        return $this->state(fn(array $attributes) => [
            'calculation_method' => $method,
        ]);
    }

    /**
     * Set as FIFO calculation.
     */
    public function fifo(): static
    {
        return $this->method(RecipeCostCalculation::METHOD_FIFO);
    }

    /**
     * Set as average calculation.
     */
    public function average(): static
    {
        return $this->method(RecipeCostCalculation::METHOD_AVERAGE);
    }

    /**
     * Set a specific total cost.
     */
    public function withCost(float $totalCost, int $servingSize = 1): static
    {
        return $this->state(fn(array $attributes) => [
            'total_cost' => $totalCost,
            'cost_per_serving' => $totalCost / $servingSize,
            'cost_breakdown' => $this->generateCostBreakdown($totalCost),
        ]);
    }

    /**
     * Set a specific calculation date.
     */
    public function calculatedOn($date): static
    {
        return $this->state(fn(array $attributes) => [
            'calculation_date' => $date,
        ]);
    }

    /**
     * Set as recent calculation.
     */
    public function recent(): static
    {
        return $this->state(fn(array $attributes) => [
            'calculation_date' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Set as old calculation.
     */
    public function old(): static
    {
        return $this->state(fn(array $attributes) => [
            'calculation_date' => $this->faker->dateTimeBetween('-60 days', '-30 days'),
        ]);
    }
}
