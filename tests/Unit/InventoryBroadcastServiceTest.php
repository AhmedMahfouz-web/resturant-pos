<?php

namespace Tests\Unit;

use App\Models\Material;
use App\Models\StockAlert;
use App\Models\Recipe;
use App\Models\RecipeCostCalculation;
use App\Models\Order;
use App\Models\User;
use App\Services\InventoryBroadcastService;
use App\Events\InventoryUpdated;
use App\Events\StockAlertTriggered;
use App\Events\RecipeCostUpdated;
use App\Events\OrderInventoryProcessed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class InventoryBroadcastServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $broadcastService;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->broadcastService = new InventoryBroadcastService();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        Event::fake();
    }

    /** @test */
    public function it_broadcasts_inventory_updates()
    {
        $material = Material::factory()->create([
            'name' => 'Test Material',
            'quantity' => 10.0,
            'reorder_point' => 5.0
        ]);

        // Set previous quantity in cache
        Cache::put("material_quantity_{$material->id}", 15.0);

        $this->broadcastService->broadcastInventoryUpdate($material, 'consumption', [
            'order_id' => 123,
            'consumed_quantity' => 5.0
        ]);

        Event::assertDispatched(InventoryUpdated::class, function ($event) use ($material) {
            return $event->material->id === $material->id &&
                $event->changeType === 'consumption' &&
                $event->previousQuantity === 15.0 &&
                $event->newQuantity === 10.0;
        });
    }

    /** @test */
    public function it_broadcasts_stock_alerts()
    {
        $material = Material::factory()->create();
        $alert = StockAlert::factory()->create([
            'material_id' => $material->id,
            'alert_type' => 'low_stock'
        ]);

        $this->broadcastService->broadcastStockAlert($alert);

        Event::assertDispatched(StockAlertTriggered::class, function ($event) use ($alert) {
            return $event->alert->id === $alert->id;
        });
    }

    /** @test */
    public function it_broadcasts_recipe_cost_updates()
    {
        $recipe = Recipe::factory()->create(['name' => 'Test Recipe']);
        $costCalculation = RecipeCostCalculation::factory()->create([
            'recipe_id' => $recipe->id,
            'total_cost' => 15.50
        ]);

        // Set previous cost in cache
        Cache::put("recipe_cost_{$recipe->id}", 12.00);

        $this->broadcastService->broadcastRecipeCostUpdate($recipe, $costCalculation);

        Event::assertDispatched(RecipeCostUpdated::class, function ($event) use ($recipe, $costCalculation) {
            return $event->recipe->id === $recipe->id &&
                $event->costCalculation->id === $costCalculation->id &&
                $event->previousCost === 12.00;
        });
    }

    /** @test */
    public function it_broadcasts_order_inventory_processing()
    {
        $order = Order::factory()->create(['code' => 'ORD-001']);
        $consumptionData = [
            [
                'order_item_id' => 1,
                'product_name' => 'Test Product',
                'total_cost' => 10.50,
                'materials' => [
                    [
                        'material_id' => 1,
                        'material_name' => 'Test Material',
                        'consumed_stock_quantity' => 2.0
                    ]
                ]
            ]
        ];

        $this->broadcastService->broadcastOrderInventoryProcessed($order, $consumptionData);

        Event::assertDispatched(OrderInventoryProcessed::class, function ($event) use ($order) {
            return $event->order->id === $order->id &&
                $event->order->code === 'ORD-001';
        });
    }

    /** @test */
    public function it_broadcasts_material_receipt()
    {
        $material = Material::factory()->create([
            'quantity' => 10.0
        ]);

        $receiptData = [
            'receipt_id' => 123,
            'quantity' => 5.0,
            'unit_cost' => 2.50,
            'supplier_id' => 1,
            'batch_number' => 'BATCH-001'
        ];

        $this->broadcastService->broadcastMaterialReceipt($material, $receiptData);

        Event::assertDispatched(InventoryUpdated::class, function ($event) use ($material) {
            return $event->material->id === $material->id &&
                $event->changeType === 'receipt' &&
                isset($event->changeData['receipt_id']) &&
                $event->changeData['receipt_id'] === 123;
        });
    }

    /** @test */
    public function it_broadcasts_stock_adjustments()
    {
        $material = Material::factory()->create([
            'quantity' => 10.0
        ]);

        $adjustmentData = [
            'quantity' => -2.0,
            'reason' => 'Damaged goods',
            'user_id' => $this->user->id
        ];

        $this->broadcastService->broadcastStockAdjustment($material, $adjustmentData);

        Event::assertDispatched(InventoryUpdated::class, function ($event) use ($material) {
            return $event->material->id === $material->id &&
                $event->changeType === 'adjustment' &&
                $event->changeData['adjustment_quantity'] === -2.0 &&
                $event->changeData['adjustment_reason'] === 'Damaged goods';
        });
    }

    /** @test */
    public function it_creates_low_stock_alert_when_below_reorder_point()
    {
        $material = Material::factory()->create([
            'name' => 'Test Material',
            'quantity' => 3.0,
            'reorder_point' => 5.0
        ]);

        $this->broadcastService->broadcastInventoryUpdate($material, 'consumption');

        // Check that a low stock alert was created
        $this->assertDatabaseHas('stock_alerts', [
            'material_id' => $material->id,
            'alert_type' => 'low_stock',
            'is_resolved' => false
        ]);

        Event::assertDispatched(StockAlertTriggered::class);
    }

    /** @test */
    public function it_does_not_create_duplicate_low_stock_alerts()
    {
        $material = Material::factory()->create([
            'quantity' => 3.0,
            'reorder_point' => 5.0
        ]);

        // Create existing alert
        StockAlert::factory()->create([
            'material_id' => $material->id,
            'alert_type' => 'low_stock',
            'is_resolved' => false
        ]);

        $this->broadcastService->broadcastInventoryUpdate($material, 'consumption');

        // Should still only have one alert
        $alertCount = StockAlert::where('material_id', $material->id)
            ->where('alert_type', 'low_stock')
            ->where('is_resolved', false)
            ->count();

        $this->assertEquals(1, $alertCount);
    }

    /** @test */
    public function it_creates_overstock_alert_when_above_maximum_level()
    {
        $material = Material::factory()->create([
            'name' => 'Test Material',
            'quantity' => 150.0,
            'maximum_stock_level' => 100.0
        ]);

        $this->broadcastService->broadcastInventoryUpdate($material, 'receipt');

        // Check that an overstock alert was created
        $this->assertDatabaseHas('stock_alerts', [
            'material_id' => $material->id,
            'alert_type' => 'overstock',
            'is_resolved' => false
        ]);

        Event::assertDispatched(StockAlertTriggered::class);
    }

    /** @test */
    public function it_generates_dashboard_data()
    {
        // Create test data
        $material = Material::factory()->create([
            'quantity' => 10.0,
            'reorder_point' => 15.0 // Below reorder point
        ]);

        StockAlert::factory()->create([
            'material_id' => $material->id,
            'alert_type' => 'low_stock',
            'is_resolved' => false
        ]);

        $dashboardData = $this->broadcastService->getDashboardData();

        $this->assertArrayHasKey('summary', $dashboardData);
        $this->assertArrayHasKey('recent_alerts', $dashboardData);
        $this->assertArrayHasKey('low_stock_materials', $dashboardData);
        $this->assertArrayHasKey('timestamp', $dashboardData);

        $this->assertCount(1, $dashboardData['recent_alerts']);
        $this->assertCount(1, $dashboardData['low_stock_materials']);
    }

    /** @test */
    public function it_broadcasts_batch_expiry_warnings()
    {
        $material = Material::factory()->create(['name' => 'Perishable Item']);

        $expiringBatches = [
            [
                'batch_number' => 'BATCH-001',
                'remaining_quantity' => 5.0,
                'expiry_date' => now()->addDays(3)->format('Y-m-d'),
                'days_until_expiry' => 3
            ]
        ];

        $this->broadcastService->broadcastBatchExpiryWarning($material, $expiringBatches);

        Event::assertDispatched(InventoryUpdated::class, function ($event) use ($material) {
            return $event->material->id === $material->id &&
                $event->changeType === 'expiry_warning' &&
                isset($event->changeData['expiring_batches']) &&
                count($event->changeData['expiring_batches']) === 1;
        });
    }
}
