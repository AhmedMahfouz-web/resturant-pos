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
            'status' => 'pending',
            'code' => 'ORD-001'
        ]);

        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 2 // 2 breads
        ]);

        // Complete the order
        $order->update(['status' => 'completed']);

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
            'status' => 'pending',
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
}
