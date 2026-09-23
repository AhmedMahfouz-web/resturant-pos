<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Material;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\StockBatch;
use App\Models\InventoryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = JWTAuth::fromUser($this->user);
        $this->paymentMethod = PaymentMethod::create(['name' => 'Cash']);
    }

    public function test_successful_payment_completes_order_and_keeps_change_response(): void
    {
        $material = Material::factory()->create([
            'quantity' => 5,
            'stock_unit' => 'kg',
            'recipe_unit' => 'kg',
            'conversion_rate' => 1,
        ]);
        $oldestBatch = StockBatch::factory()->create([
            'material_id' => $material->id,
            'quantity' => 2,
            'remaining_quantity' => 2,
            'unit_cost' => 4,
            'received_date' => now()->subDays(2),
            'material_receipt_id' => null,
        ]);
        $newerBatch = StockBatch::factory()->create([
            'material_id' => $material->id,
            'quantity' => 3,
            'remaining_quantity' => 3,
            'unit_cost' => 5,
            'received_date' => now()->subDay(),
            'material_receipt_id' => null,
        ]);
        $recipe = Recipe::factory()->create();
        $recipe->recipeMaterials()->attach($material->id, ['material_quantity' => 2]);
        $product = Product::factory()->create();
        $product->recipes()->attach($recipe->id);

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'live',
            'type' => 'takeaway',
            'total_amount' => 10,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 10,
            'sub_total' => 10,
            'total_amount' => 10,
        ]);
        $order->refresh();
        $payload = [
            'order_id' => $order->id,
            'amount' => (float) $order->total_amount + 2,
            'payment_method_id' => $this->paymentMethod->id,
        ];

        $this->withToken($this->token)->postJson('/api/payment', $payload)
            ->assertCreated()->assertJsonPath('change', 2);
        $this->withToken($this->token)->postJson('/api/payment', $payload)->assertStatus(409);

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
        $this->assertSame(1, InventoryTransaction::where('reference_type', OrderItem::class)->count());
        $this->assertEquals(3, (float) $material->fresh()->quantity);
        $this->assertEquals(0, (float) $oldestBatch->fresh()->remaining_quantity);
        $this->assertEquals(3, (float) $newerBatch->fresh()->remaining_quantity);
    }

    public function test_insufficient_stock_rolls_back_payment_order_and_inventory(): void
    {
        $material = Material::factory()->create([
            'quantity' => 1,
            'stock_unit' => 'kg',
            'recipe_unit' => 'kg',
            'conversion_rate' => 1,
        ]);
        $batch = StockBatch::factory()->create([
            'material_id' => $material->id,
            'quantity' => 1,
            'remaining_quantity' => 1,
            'material_receipt_id' => null,
        ]);
        $recipe = Recipe::factory()->create();
        $recipe->recipeMaterials()->attach($material->id, ['material_quantity' => 2]);
        $product = Product::factory()->create();
        $product->recipes()->attach($recipe->id);
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'live',
            'type' => 'takeaway',
            'total_amount' => 10,
        ]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->withToken($this->token)->postJson('/api/payment', [
            'order_id' => $order->id,
            'amount' => 10,
            'payment_method_id' => $this->paymentMethod->id,
        ])->assertUnprocessable();

        $this->assertSame('live', $order->fresh()->status);
        $this->assertSame(0, Payment::where('order_id', $order->id)->count());
        $this->assertEquals(1, (float) $material->fresh()->quantity);
        $this->assertEquals(1, (float) $batch->fresh()->remaining_quantity);
        $this->assertDatabaseMissing('inventory_transactions', [
            'reference_type' => OrderItem::class,
            'reference_id' => $item->id,
            'type' => 'consumption',
        ]);
    }

    public function test_short_payment_is_rejected_without_completing_the_order(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'live',
            'type' => 'takeaway',
            'total_amount' => 10,
        ]);

        $this->withToken($this->token)->postJson('/api/payment', [
            'order_id' => $order->id,
            'amount' => 9,
            'payment_method_id' => $this->paymentMethod->id,
        ])->assertUnprocessable();

        $this->assertSame('live', $order->fresh()->status);
        $this->assertSame(0, Payment::where('order_id', $order->id)->count());
    }

    public function test_order_history_returns_an_empty_page_before_the_first_shift(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_inventory_dashboard_values_loaded_stock_batches(): void
    {
        $material = Material::factory()->create(['quantity' => 5]);
        StockBatch::factory()->create([
            'material_id' => $material->id,
            'quantity' => 5,
            'remaining_quantity' => 5,
            'unit_cost' => 4,
            'material_receipt_id' => null,
            'supplier_id' => null,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/inventory/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.total_stock_value', 20);
    }
}
