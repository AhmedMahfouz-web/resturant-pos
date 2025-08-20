<?php

namespace Tests\Unit;

use App\Models\Material;
use App\Models\StockBatch;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeCostCalculation;
use App\Models\StockAlert;
use App\Models\User;
use App\Services\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class ReportingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $reportingService;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reportingService = new ReportingService();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function it_can_generate_stock_valuation_report()
    {
        // Create materials with stock batches
        $material1 = Material::factory()->create([
            'name' => 'Flour',
            'stock_unit' => 'kg'
        ]);

        $material2 = Material::factory()->create([
            'name' => 'Sugar',
            'stock_unit' => 'kg'
        ]);

        // Create stock batches
        StockBatch::factory()->create([
            'material_id' => $material1->id,
            'quantity' => 10.0,
            'remaining_quantity' => 8.0,
            'unit_cost' => 2.50,
            'received_date' => now()->subDays(1)
        ]);

        StockBatch::factory()->create([
            'material_id' => $material2->id,
            'quantity' => 5.0,
            'remaining_quantity' => 5.0,
            'unit_cost' => 3.00,
            'received_date' => now()->subDays(2)
        ]);

        $report = $this->reportingService->generateStockValuationReport();

        $this->assertArrayHasKey('total_inventory_value', $report);
        $this->assertArrayHasKey('material_count', $report);
        $this->assertArrayHasKey('materials', $report);

        // Expected total value: (8.0 * 2.50) + (5.0 * 3.00) = 20.00 + 15.00 = 35.00
        $this->assertEquals(35.00, $report['total_inventory_value']);
        $this->assertEquals(2, $report['material_count']);
    }

    /** @test */
    public function it_can_generate_inventory_movement_report()
    {
        $material = Material::factory()->create(['name' => 'Test Material']);

        // Create inventory transactions
        InventoryTransaction::factory()->create([
            'material_id' => $material->id,
            'type' => 'receipt',
            'quantity' => 10.0,
            'unit_cost' => 2.00,
            'user_id' => $this->user->id,
            'created_at' => now()->subDays(1)
        ]);

        InventoryTransaction::factory()->create([
            'material_id' => $material->id,
            'type' => 'consumption',
            'quantity' => 3.0,
            'unit_cost' => 2.00,
            'user_id' => $this->user->id,
            'created_at' => now()
        ]);

        $startDate = now()->subDays(2)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $report = $this->reportingService->generateInventoryMovementReport($startDate, $endDate);

        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('movements', $report);
        $this->assertArrayHasKey('transaction_count', $report);

        $this->assertEquals(10.0, $report['summary']['total_receipts']);
        $this->assertEquals(3.0, $report['summary']['total_consumption']);
        $this->assertEquals(20.0, $report['summary']['receipt_value']); // 10.0 * 2.00
        $this->assertEquals(6.0, $report['summary']['consumption_value']); // 3.0 * 2.00
        $this->assertEquals(2, $report['transaction_count']);
    }

    /** @test */
    public function it_can_generate_stock_aging_report()
    {
        $material = Material::factory()->create(['name' => 'Test Material']);

        // Create batches of different ages
        StockBatch::factory()->create([
            'material_id' => $material->id,
            'quantity' => 5.0,
            'remaining_quantity' => 5.0,
            'unit_cost' => 2.00,
            'received_date' => now()->subDays(15) // 0-30 days category
        ]);

        StockBatch::factory()->create([
            'material_id' => $material->id,
            'quantity' => 3.0,
            'remaining_quantity' => 3.0,
            'unit_cost' => 2.50,
            'received_date' => now()->subDays(45) // 31-60 days category
        ]);

        $report = $this->reportingService->generateStockAgingReport();

        $this->assertArrayHasKey('aging_categories', $report);
        $this->assertArrayHasKey('total_inventory_value', $report);

        $agingCategories = $report['aging_categories'];

        // Check 0-30 days category
        $this->assertCount(1, $agingCategories['0-30']['batches']);
        $this->assertEquals(10.0, $agingCategories['0-30']['total_value']); // 5.0 * 2.00

        // Check 31-60 days category
        $this->assertCount(1, $agingCategories['31-60']['batches']);
        $this->assertEquals(7.5, $agingCategories['31-60']['total_value']); // 3.0 * 2.50

        // Total value should be 17.5
        $this->assertEquals(17.5, $report['total_inventory_value']);
    }

    /** @test */
    public function it_can_generate_waste_report()
    {
        $material = Material::factory()->create(['name' => 'Perishable Item']);

        // Create expired batch
        StockBatch::factory()->create([
            'material_id' => $material->id,
            'quantity' => 5.0,
            'remaining_quantity' => 2.0,
            'unit_cost' => 3.00,
            'expiry_date' => now()->subDays(5), // Expired 5 days ago
            'created_at' => now()->subDays(10)
        ]);

        // Create waste adjustment
        InventoryTransaction::factory()->create([
            'material_id' => $material->id,
            'type' => 'adjustment',
            'quantity' => -1.0, // Negative for waste
            'unit_cost' => 3.00,
            'user_id' => $this->user->id,
            'notes' => 'Damaged goods',
            'created_at' => now()->subDays(1)
        ]);

        $startDate = now()->subDays(15)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $report = $this->reportingService->generateWasteReport($startDate, $endDate);

        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('expired_waste', $report);
        $this->assertArrayHasKey('adjustment_waste', $report);

        // Check expired waste
        $this->assertCount(1, $report['expired_waste']);
        $this->assertEquals(6.0, $report['summary']['expired_waste_value']); // 2.0 * 3.00

        // Check adjustment waste
        $this->assertCount(1, $report['adjustment_waste']);
        $this->assertEquals(3.0, $report['summary']['adjustment_waste_value']); // 1.0 * 3.00

        // Total waste value
        $this->assertEquals(9.0, $report['summary']['total_waste_value']);
    }

    /** @test */
    public function it_can_generate_cost_analysis_report()
    {
        $recipe = Recipe::factory()->create(['name' => 'Test Recipe']);

        // Create cost calculations
        RecipeCostCalculation::factory()->create([
            'recipe_id' => $recipe->id,
            'calculation_method' => 'fifo',
            'total_cost' => 15.50,
            'cost_per_serving' => 3.10,
            'calculated_by' => $this->user->id,
            'calculation_date' => now()->subDays(1)
        ]);

        RecipeCostCalculation::factory()->create([
            'recipe_id' => $recipe->id,
            'calculation_method' => 'purchase_price',
            'total_cost' => 14.00,
            'cost_per_serving' => 2.80,
            'calculated_by' => $this->user->id,
            'calculation_date' => now()
        ]);

        $startDate = now()->subDays(2)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $report = $this->reportingService->generateCostAnalysisReport($startDate, $endDate);

        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('recipe_analysis', $report);

        $this->assertEquals(2, $report['summary']['total_calculations']);
        $this->assertEquals(1, $report['summary']['unique_recipes']);

        // Check method comparison
        $methodComparison = $report['summary']['method_comparison'];
        $this->assertEquals(1, $methodComparison['fifo']['count']);
        $this->assertEquals(15.50, $methodComparison['fifo']['avg_cost']);
        $this->assertEquals(1, $methodComparison['purchase_price']['count']);
        $this->assertEquals(14.00, $methodComparison['purchase_price']['avg_cost']);

        // Check recipe analysis
        $recipeAnalysis = $report['recipe_analysis'][0];
        $this->assertEquals('Test Recipe', $recipeAnalysis['recipe_name']);
        $this->assertCount(2, $recipeAnalysis['calculations']);
        $this->assertEquals(1.50, $recipeAnalysis['cost_variance']); // 15.50 - 14.00
    }

    /** @test */
    public function it_can_generate_dashboard_summary()
    {
        // Create some test data
        $material = Material::factory()->create();

        StockBatch::factory()->create([
            'material_id' => $material->id,
            'quantity' => 10.0,
            'remaining_quantity' => 10.0,
            'unit_cost' => 5.00
        ]);

        StockAlert::factory()->create([
            'material_id' => $material->id,
            'alert_type' => 'low_stock',
            'is_resolved' => false
        ]);

        $summary = $this->reportingService->generateDashboardSummary();

        $this->assertArrayHasKey('current_stock_value', $summary);
        $this->assertArrayHasKey('material_count', $summary);
        $this->assertArrayHasKey('low_stock_alerts', $summary);
        $this->assertArrayHasKey('expiring_items', $summary);
        $this->assertArrayHasKey('recent_movements', $summary);
        $this->assertArrayHasKey('monthly_cost_calculations', $summary);
        $this->assertArrayHasKey('last_updated', $summary);

        $this->assertEquals(50.0, $summary['current_stock_value']); // 10.0 * 5.00
        $this->assertEquals(1, $summary['material_count']);
        $this->assertEquals(1, $summary['low_stock_alerts']);
    }

    /** @test */
    public function it_filters_movement_report_by_material()
    {
        $material1 = Material::factory()->create(['name' => 'Material 1']);
        $material2 = Material::factory()->create(['name' => 'Material 2']);

        // Create transactions for both materials
        InventoryTransaction::factory()->create([
            'material_id' => $material1->id,
            'type' => 'receipt',
            'quantity' => 5.0,
            'unit_cost' => 2.00,
            'user_id' => $this->user->id
        ]);

        InventoryTransaction::factory()->create([
            'material_id' => $material2->id,
            'type' => 'receipt',
            'quantity' => 3.0,
            'unit_cost' => 3.00,
            'user_id' => $this->user->id
        ]);

        $startDate = now()->subDays(1)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        // Get report filtered by material1
        $report = $this->reportingService->generateInventoryMovementReport(
            $startDate,
            $endDate,
            $material1->id
        );

        $this->assertEquals(1, $report['transaction_count']);
        $this->assertEquals(5.0, $report['summary']['total_receipts']);
        $this->assertEquals('Material 1', $report['movements'][0]['material_name']);
    }
}
