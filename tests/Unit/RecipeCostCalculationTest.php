<?php

namespace Tests\Unit;

use App\Models\Material;
use App\Models\Recipe;
use App\Models\RecipeCostCalculation;
use App\Models\StockBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeCostCalculationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_calculate_recipe_cost_using_purchase_price()
    {
        $recipe = Recipe::factory()->create(['name' => 'Test Recipe']);

        // Create materials with known prices
        $material1 = Material::factory()->create([
            'name' => 'Flour',
            'purchase_price' => 2.50,
            'recipe_unit' => 'kg'
        ]);

        $material2 = Material::factory()->create([
            'name' => 'Sugar',
            'purchase_price' => 1.80,
            'recipe_unit' => 'kg'
        ]);

        // Attach materials to recipe with quantities
        $recipe->recipeMaterials()->attach($material1->id, ['material_quantity' => 2.0]); // 2kg flour
        $recipe->recipeMaterials()->attach($material2->id, ['material_quantity' => 1.5]); // 1.5kg sugar

        $totalCost = $recipe->calculateCost();

        // Expected: (2.0 * 2.50) + (1.5 * 1.80) = 5.00 + 2.70 = 7.70
        $this->assertEquals(7.70, $totalCost);
    }

    /** @test */
    public function it_can_calculate_recipe_cost_using_fifo()
    {
        $recipe = Recipe::factory()->create(['name' => 'Test Recipe']);

        $material = Material::factory()->create([
            'name' => 'Flour',
            'purchase_price' => 2.50,
            'recipe_unit' => 'kg',
            'stock_unit' => 'kg',
            'conversion_rate' => 1.0,
            'quantity' => 10.0
        ]);

        // Create stock batches with different costs
        StockBatch::factory()->create([
            'material_id' => $material->id,
            'quantity' => 5.0,
            'remaining_quantity' => 5.0,
            'unit_cost' => 2.00,
            'received_date' => now()->subDays(2)
        ]);

        StockBatch::factory()->create([
            'material_id' => $material->id,
            'quantity' => 5.0,
            'remaining_quantity' => 5.0,
            'unit_cost' => 3.00,
            'received_date' => now()->subDays(1)
        ]);

        // Attach material to recipe
        $recipe->recipeMaterials()->attach($material->id, ['material_quantity' => 3.0]); // 3kg flour

        $fifoCost = $recipe->calculateFifoCost();

        // Expected: 3kg from first batch at 2.00 = 6.00
        $this->assertEquals(6.00, $fifoCost);
    }

    /** @test */
    public function it_can_get_detailed_fifo_cost_breakdown()
    {
        $recipe = Recipe::factory()->create(['name' => 'Test Recipe']);

        $material = Material::factory()->create([
            'name' => 'Flour',
            'recipe_unit' => 'kg',
            'stock_unit' => 'kg',
            'conversion_rate' => 1.0,
            'quantity' => 10.0
        ]);

        StockBatch::factory()->create([
            'material_id' => $material->id,
            'quantity' => 5.0,
            'remaining_quantity' => 5.0,
            'unit_cost' => 2.00
        ]);

        $recipe->recipeMaterials()->attach($material->id, ['material_quantity' => 2.0]);

        $breakdown = $recipe->getFifoCostBreakdown();

        $this->assertArrayHasKey('total_cost', $breakdown);
        $this->assertArrayHasKey('materials', $breakdown);
        $this->assertArrayHasKey('calculated_at', $breakdown);

        $this->assertEquals(4.00, $breakdown['total_cost']); // 2kg * 2.00
        $this->assertCount(1, $breakdown['materials']);

        $materialBreakdown = $breakdown['materials'][0];
        $this->assertEquals($material->id, $materialBreakdown['material_id']);
        $this->assertEquals('Flour', $materialBreakdown['material_name']);
        $this->assertEquals(2.0, $materialBreakdown['required_quantity']);
        $this->assertEquals(4.00, $materialBreakdown['total_cost']);
        $this->assertEquals('fifo', $materialBreakdown['calculation_method']);
    }

    /** @test */
    public function it_can_check_if_recipe_can_be_prepared()
    {
        $recipe = Recipe::factory()->create();

        $material1 = Material::factory()->create([
            'quantity' => 10.0,
            'conversion_rate' => 1.0
        ]);

        $material2 = Material::factory()->create([
            'quantity' => 5.0,
            'conversion_rate' => 1.0
        ]);

        // Recipe requires 8kg of material1 and 3kg of material2
        $recipe->recipeMaterials()->attach($material1->id, ['material_quantity' => 8.0]);
        $recipe->recipeMaterials()->attach($material2->id, ['material_quantity' => 3.0]);

        $this->assertTrue($recipe->canBePrepared());

        // Update material2 to have insufficient stock
        $material2->update(['quantity' => 2.0]);

        $this->assertFalse($recipe->fresh()->canBePrepared());
    }

    /** @test */
    public function it_can_create_recipe_cost_calculation_record()
    {
        $user = User::factory()->create();
        $recipe = Recipe::factory()->create(['name' => 'Test Recipe']);

        $material = Material::factory()->create([
            'purchase_price' => 2.50,
            'recipe_unit' => 'kg'
        ]);

        $recipe->recipeMaterials()->attach($material->id, ['material_quantity' => 2.0]);

        $calculation = RecipeCostCalculation::createFromRecipe(
            $recipe,
            'purchase_price',
            $user->id
        );

        $this->assertDatabaseHas('recipe_cost_calculations', [
            'recipe_id' => $recipe->id,
            'calculation_method' => 'purchase_price',
            'total_cost' => 5.00,
            'calculated_by' => $user->id
        ]);

        $this->assertInstanceOf(RecipeCostCalculation::class, $calculation);
        $this->assertEquals(5.00, $calculation->total_cost);
        $this->assertIsArray($calculation->cost_breakdown);
    }
}
