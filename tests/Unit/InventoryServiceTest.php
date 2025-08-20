<?php

namespace Tests\Unit;

use App\Models\Material;
use App\Models\StockAlert;
use App\Models\StockBatch;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryService = new InventoryService();
    }

    /** @test */
    public function it_can_check_stock_levels_for_material()
    {
        $material = Material::factory()->create([
            'name' => 'Test Material',
            'quantity' => 5.0,
            'minimum_stock_level' => 10.0,
            'maximum_stock_level' => 100.0
        ]);

        $alerts = $this->inventoryService->checkStockLevelsForMaterial($material);

        $this->assertCount(1, $alerts);
        $this->assertEquals(StockAlert::ALERT_TYPE_LOW_STOCK, $alerts->first()->alert_type);
    }

    /** @test */
    public function it_generates_out_of_stock_alert_when_quantity_is_zero()
    {
        $material = Material::factory()->create([
            'name' => 'Test Material',
            'quantity' => 0.0,
            'minimum_stock_level' => 10.0
        ]);

        $alerts = $this->inventoryService->checkStockLevelsForMaterial($material);

        $this->assertCount(1, $alerts);
        $this->assertEquals(StockAlert::ALERT_TYPE_OUT_OF_STOCK, $alerts->first()->alert_type);
    }

    /** @test */
    public function it_generates_overstock_alert_when_quantity_exceeds_maximum()
    {
        $material = Material::factory()->create([
            'name' => 'Test Material',
            'quantity' => 150.0,
            'minimum_stock_level' => 10.0,
            'maximum_stock_level' => 100.0
        ]);

        $alerts = $this->inventoryService->checkStockLevelsForMaterial($material);

        $this->assertCount(1, $alerts);
        $this->assertEquals(StockAlert::ALERT_TYPE_OVERSTOCK, $alerts->first()->alert_type);
    }

    /** @test */
    public function it_generates_expiry_alerts_for_expiring_batches()
    {
        $material = Material::factory()->create([
            'name' => 'Test Material',
            'quantity' => 50.0,
            'minimum_stock_level' => 10.0
        ]);

        // Create a batch expiring in 5 days (warning)
        StockBatch::factory()->create([
            'material_id' => $material->id,
            'remaining_quantity' => 25.0,
            'expiry_date' => now()->addDays(5)
        ]);

        // Create a batch expiring in 1 day (critical)
        StockBatch::factory()->create([
            'material_id' => $material->id,
            'remaining_quantity' => 25.0,
            'expiry_date' => now()->addDays(1)
        ]);

        $alerts = $this->inventoryService->checkStockLevelsForMaterial($material);

        $this->assertCount(2, $alerts);
        $this->assertTrue($alerts->contains('alert_type', StockAlert::ALERT_TYPE_EXPIRY_WARNING));
        $this->assertTrue($alerts->contains('alert_type', StockAlert::ALERT_TYPE_EXPIRY_CRITICAL));
    }

    /** @test */
    public function it_calculates_stock_value_for_material()
    {
        $material = Material::factory()->create();

        // Create stock batches with different costs
        StockBatch::factory()->create([
            'material_id' => $material->id,
            'remaining_quantity' => 10.0,
            'unit_cost' => 5.00
        ]);

        StockBatch::factory()->create([
            'material_id' => $material->id,
            'remaining_quantity' => 20.0,
            'unit_cost' => 6.00
        ]);

        $totalValue = $this->inventoryService->calculateStockValue($material);

        // Expected: (10 * 5.00) + (20 * 6.00) = 50 + 120 = 170
        $this->assertEquals(170.0, $totalValue);
    }

    /** @test */
    public function it_generates_inventory_summary()
    {
        // Create materials with different stock levels
        Material::factory()->create([
            'quantity' => 5.0,
            'minimum_stock_level' => 10.0
        ]);

        Material::factory()->create([
            'quantity' => 0.0,
            'minimum_stock_level' => 10.0
        ]);

        Material::factory()->create([
            'quantity' => 50.0,
            'minimum_stock_level' => 10.0
        ]);

        // Create some alerts
        StockAlert::factory()->unresolved()->create();
        StockAlert::factory()->critical()->unresolved()->create();

        $summary = $this->inventoryService->getInventorySummary();

        $this->assertArrayHasKey('total_materials', $summary);
        $this->assertArrayHasKey('low_stock_count', $summary);
        $this->assertArrayHasKey('out_of_stock_count', $summary);
        $this->assertArrayHasKey('active_alerts', $summary);
        $this->assertArrayHasKey('critical_alerts', $summary);

        $this->assertGreaterThanOrEqual(3, $summary['total_materials']);
        $this->assertGreaterThanOrEqual(1, $summary['low_stock_count']);
        $this->assertGreaterThanOrEqual(1, $summary['out_of_stock_count']);
        $this->assertGreaterThanOrEqual(2, $summary['active_alerts']);
        $this->assertGreaterThanOrEqual(1, $summary['critical_alerts']);
    }
}
