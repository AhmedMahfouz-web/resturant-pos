<?php

namespace Tests\Unit;

use App\Models\Material;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\StockBatch;
use App\Models\InventoryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderInventoryConsumptionTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $product;
    protected $recipe;
    protected $material;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Create a material with stock batches
        $this->material = Material::factory()->create([
            'name' => 'Flour',
            'quantity' => 10.0,
            'stock_unit' => 'kg',
            'recipe_unit' => 'kg',
            'conversion_rate' => 1.0,
            'reorder_point' => 5.0
        ]);

        // Create stock batches
        StockBatch::factory()->create([
            'material_id' => $this->material->id,
            'quantity' => 5.0,
            'remaining_quantity' => 5.0,
            'unit_cost' => 2.00,
            'received_date' => now()->subDays(2)
        ]);

        StockBatch::factory()->create([
            'material_id' => $this->material->id,
            'quantity' => 5.0,
            'remaining_quantity' => 5.0,
            'unit_cost' => 2.50,
            'received_date' => now()->subDays(1)
        ]);

        // Create recipe and product
        $this->recipe = Recipe::factory()->create(['name' => 'Bread Recipe']);
        $this->recipe->recipeMaterials()->attach($this->material->id, ['material_quantity' => 2.0]);

        $this->product = Product::factory()->create(['name' => 'Bread']);
        $this->product->recipes()->attach($this->recipe->id);
    }

    /** @test */
    public function it_processes_inventory_consumption_when_order_is_completed()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'live',
            'code' => 'ORD-001'
        ]);

        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 2 // 2 breads
        ]);

        // Complete the order
        $order->update(['status' => 'completed']);
        $order->processInventoryConsumption();

        // Check that inventory transactions were created
        $this->assertDatabaseHas('inventory_transactions', [
            'material_id' => $this->material->id,
            'type' => 'consumption',
            'quantity' => 4.0, // 2 breads * 2kg flour each = 4kg
            'reference_type' => OrderItem::class,
            'reference_id' => $orderItem->id
        ]);

        // Check that material quantity was decremented
        $this->material->refresh();
        $this->assertEquals(6.0, $this->material->quantity); // 10 - 4 = 6
    }

    /** @test */
    public function it_handles_products_without_recipes()
    {
        // Create a product without a recipe
        $productWithoutRecipe = Product::factory()->create(['name' => 'Service Item']);

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'live',
            'code' => 'ORD-002'
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $productWithoutRecipe->id,
            'quantity' => 1
        ]);

        // Complete the order - should not throw an error
        $order->update(['status' => 'completed']);

        // No inventory transactions should be created
        $this->assertDatabaseMissing('inventory_transactions', [
            'reference_type' => OrderItem::class,
            'reference_id' => $order->orderItems->first()->id
        ]);
    }

    /** @test */
    public function it_reconciles_legacy_stock_without_batches_before_consumption()
    {
        $material = Material::factory()->create([
            'quantity' => 10,
            'stock_unit' => 'kg',
            'recipe_unit' => 'kg',
            'conversion_rate' => 1,
        ]);
        $recipe = Recipe::factory()->create();
        $recipe->recipeMaterials()->attach($material->id, ['material_quantity' => 2]);
        $product = Product::factory()->create();
        $product->recipes()->attach($recipe->id);
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'live',
            'code' => 'ORD-003',
        ]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $order->update(['status' => 'completed']);

        $this->assertEquals(6.0, (float) $material->fresh()->quantity);
        $this->assertEquals(6.0, (float) StockBatch::where('material_id', $material->id)->sum('remaining_quantity'));
        $this->assertSame(1, StockBatch::where('material_id', $material->id)
            ->where('batch_number', 'like', 'OPEN-' . $material->id . '-%')
            ->count());
        $this->assertDatabaseHas('inventory_transactions', [
            'material_id' => $material->id,
            'reference_type' => OrderItem::class,
            'reference_id' => $item->id,
            'quantity' => 4,
        ]);
    }

    /** @test */
    public function insufficient_stock_rolls_back_all_item_consumption_and_order_completion()
    {
        $secondMaterial = Material::factory()->create([
            'quantity' => 1,
            'stock_unit' => 'kg',
            'recipe_unit' => 'kg',
            'conversion_rate' => 1,
        ]);
        StockBatch::factory()->create([
            'material_id' => $secondMaterial->id,
            'quantity' => 1,
            'remaining_quantity' => 1,
        ]);
        $this->recipe->recipeMaterials()->attach($secondMaterial->id, ['material_quantity' => 2]);

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'live',
            'code' => 'ORD-004',
        ]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        try {
            $order->update(['status' => 'completed']);
            $this->fail('Insufficient stock should prevent order completion.');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('Insufficient stock', $exception->getMessage());
        }

        $this->assertSame('live', $order->fresh()->status);
        $this->assertEquals(10.0, (float) $this->material->fresh()->quantity);
        $this->assertEquals(10.0, (float) StockBatch::where('material_id', $this->material->id)->sum('remaining_quantity'));
        $this->assertEquals(1.0, (float) $secondMaterial->fresh()->quantity);
        $this->assertDatabaseMissing('inventory_transactions', [
            'reference_type' => OrderItem::class,
            'reference_id' => $item->id,
            'type' => 'consumption',
        ]);
    }
}
