<?php

namespace Tests\Unit;

use App\Models\Material;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeCostCalculation;
use App\Models\StockBatch;
use App\Services\CostCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CostCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $costCalculationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->costCalculationService = new CostCalculationService();
    }

    /** @test */
    public function it_can_calculate_recipe_cost_using_fifo()
    {
        // Create materials with stock batches
        $flour = Material::factory()->create([
            'name' => 'Flour',
            'conversion_rate' => 1.0
        ]);

        // Create stock batches with different costs
        StockBatch::factory()->create([
            'material_id' => $flour->id,
            'remaining_quantity' => 100.0,
            'unit_cost' => 2.00
        ]);

        // Create recipe with basic fields only
        $recipe = Recipe::create([
            'name' => 'Test Recipe',
            'instructions' => 'Test instructions',
            'serving_size' => 4
        ]);

        // Manually create the pivot relationship
        DB::table('material_recipe')->insert([
            'recipe_id' => $recipe->id,
            'material_id' => $flour->id,
            'quantity' => 10.0
        ]);

        $calculation = $this->costCalculationService->calculateRecipeCost($recipe);

        $this->assertInstanceOf(RecipeCostCalculation::class, $calculation);
        $this->assertEquals($recipe->id, $calculation->recipe_id);
        $this->assertGreaterThan(0, $calculation->total_cost);
        $this->assertGreaterThan(0, $calculation->cost_per_serving);
        $this->assertIsArray($calculation->cost_breakdown);
    }

    /** @test */
    public function it_calculates_cost_per_serving_correctly()
    {
        $material = Material::factory()->create();

        StockBatch::factory()->create([
            'material_id' => $material->id,
            'remaining_quantity' => 100.0,
            'unit_cost' => 5.00
        ]);

        $recipe = Recipe::factory()->create([
            'name' => 'Test Recipe',
            'serving_size' => 2
        ]);

        $recipe->recipeMaterials()->attach($material->id, ['quantity' => 10.0]);

        $calculation = $this->costCalculationService->calculateRecipeCost($recipe);

        // Expected: 10 * 5.00 = 50.00 total cost, 50.00 / 2 servings = 25.00 per serving
        $this->assertEquals(50.00, $calculation->total_cost);
        $this->assertEquals(25.00, $calculation->cost_per_serving);
    }

    /** @test */
    public function it_falls_back_to_average_cost_when_fifo_fails()
    {
        $material = Material::factory()->create([
            'purchase_price' => 4.00,
            'conversion_rate' => 1.0
        ]);

        // No stock batches available - should fall back to average cost
        $recipe = Recipe::factory()->create([
            'name' => 'Test Recipe',
            'serving_size' => 1
        ]);

        $recipe->recipeMaterials()->attach($material->id, ['quantity' => 5.0]);

        $calculation = $this->costCalculationService->calculateRecipeCost($recipe);

        $this->assertInstanceOf(RecipeCostCalculation::class, $calculation);
        $this->assertEquals(20.00, $calculation->total_cost); // 5 * 4.00
        $this->assertEquals(20.00, $calculation->cost_per_serving);

        // Check that the cost breakdown indicates average method was used
        $breakdown = $calculation->cost_breakdown;
        $this->assertEquals('average', $breakdown[0]['calculation_method']);
    }

    /** @test */
    public function it_can_calculate_product_cost_fifo()
    {
        $material = Material::factory()->create();

        StockBatch::factory()->create([
            'material_id' => $material->id,
            'remaining_quantity' => 100.0,
            'unit_cost' => 3.00
        ]);

        $recipe = Recipe::factory()->create(['serving_size' => 1]);
        $recipe->recipeMaterials()->attach($material->id, ['quantity' => 8.0]);

        $product = Product::factory()->create();
        $product->recipe()->associate($recipe);
        $product->save();

        $cost = $this->costCalculationService->calculateProductCostFIFO($product);

        $this->assertEquals(24.00, $cost); // 8 * 3.00
    }

    /** @test */
    public function it_returns_zero_cost_for_product_without_recipe()
    {
        $product = Product::factory()->create();
        // No recipe associated

        $cost = $this->costCalculationService->calculateProductCostFIFO($product);

        $this->assertEquals(0, $cost);
    }

    /** @test */
    public function it_can_update_all_product_costs()
    {
        // Create materials with stock batches
        $material1 = Material::factory()->create();
        $material2 = Material::factory()->create();

        StockBatch::factory()->create([
            'material_id' => $material1->id,
            'remaining_quantity' => 100.0,
            'unit_cost' => 2.00
        ]);

        StockBatch::factory()->create([
            'material_id' => $material2->id,
            'remaining_quantity' => 100.0,
            'unit_cost' => 3.00
        ]);

        // Create recipes and products
        $recipe1 = Recipe::factory()->create(['serving_size' => 1, 'cost_per_serving' => 0]);
        $recipe1->recipeMaterials()->attach($material1->id, ['quantity' => 5.0]);

        $recipe2 = Recipe::factory()->create(['serving_size' => 1, 'cost_per_serving' => 0]);
        $recipe2->recipeMaterials()->attach($material2->id, ['quantity' => 4.0]);

        $product1 = Product::factory()->create();
        $product1->recipe()->associate($recipe1);
        $product1->save();

        $product2 = Product::factory()->create();
        $product2->recipe()->associate($recipe2);
        $product2->save();

        $results = $this->costCalculationService->updateProductCosts();

        $this->assertCount(2, $results);

        // Check that recipes were updated with new costs
        $this->assertEquals(10.00, $recipe1->fresh()->cost_per_serving); // 5 * 2.00
        $this->assertEquals(12.00, $recipe2->fresh()->cost_per_serving); // 4 * 3.00
    }

    /** @test */
    public function it_handles_conversion_rates_correctly()
    {
        $material = Material::factory()->create([
            'conversion_rate' => 2.0 // 1 recipe unit = 2 stock units
        ]);

        StockBatch::factory()->create([
            'material_id' => $material->id,
            'remaining_quantity' => 100.0,
            'unit_cost' => 1.50
        ]);

        $recipe = Recipe::factory()->create(['serving_size' => 1]);
        $recipe->recipeMaterials()->attach($material->id, ['quantity' => 5.0]); // 5 recipe units

        $calculation = $this->costCalculationService->calculateRecipeCost($recipe);

        // Expected: 5 recipe units * 2 conversion rate * 1.50 unit cost = 15.00
        $this->assertEquals(15.00, $calculation->total_cost);
        $this->assertEquals(15.00, $calculation->cost_per_serving);
    }
}
