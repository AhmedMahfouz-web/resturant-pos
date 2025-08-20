<?php

namespace Tests\Unit;

use App\Models\Material;
use App\Models\MaterialReceipt;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockBatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function it_can_create_a_stock_batch()
    {
        $material = Material::factory()->create();
        $supplier = Supplier::factory()->create();

        $batch = StockBatch::create([
            'material_id' => $material->id,
            'batch_number' => 'TEST-001',
            'quantity' => 100.0,
            'remaining_quantity' => 100.0,
            'unit_cost' => 5.50,
            'received_date' => now(),
            'supplier_id' => $supplier->id
        ]);

        $this->assertDatabaseHas('stock_batches', [
            'material_id' => $material->id,
            'batch_number' => 'TEST-001',
            'quantity' => 100.0,
            'remaining_quantity' => 100.0,
            'unit_cost' => 5.50
        ]);
    }

    /** @test */
    public function it_has_correct_relationships()
    {
        $material = Material::factory()->create();
        $supplier = Supplier::factory()->create();
        $batch = StockBatch::factory()->create([
            'material_id' => $material->id,
            'supplier_id' => $supplier->id
        ]);

        $this->assertInstanceOf(Material::class, $batch->material);
        $this->assertInstanceOf(Supplier::class, $batch->supplier);
        $this->assertEquals($material->id, $batch->material->id);
        $this->assertEquals($supplier->id, $batch->supplier->id);
    }

    /** @test */
    public function available_scope_filters_batches_with_remaining_quantity()
    {
        $material = Material::factory()->create();

        StockBatch::factory()->create([
            'material_id' => $material->id,
            'remaining_quantity' => 50.0
        ]);

        StockBatch::factory()->create([
            'material_id' => $material->id,
            'remaining_quantity' => 0.0
        ]);

        $availableBatches = StockBatch::forMaterial($material->id)->available()->get();

        $this->assertCount(1, $availableBatches);
        $this->assertEquals(50.0, $availableBatches->first()->remaining_quantity);
    }

    /** @test */
    public function expired_scope_filters_expired_batches()
    {
        $material = Material::factory()->create();

        StockBatch::factory()->create([
            'material_id' => $material->id,
            'expiry_date' => now()->subDays(1)
        ]);

        StockBatch::factory()->create([
            'material_id' => $material->id,
            'expiry_date' => now()->addDays(1)
        ]);

        $expiredBatches = StockBatch::expired()->get();

        $this->assertCount(1, $expiredBatches);
        $this->assertTrue($expiredBatches->first()->expiry_date->isPast());
    }

    /** @test */
    public function expiring_within_scope_filters_batches_expiring_soon()
    {
        $material = Material::factory()->create();

        StockBatch::factory()->create([
            'material_id' => $material->id,
            'expiry_date' => now()->addDays(3)
        ]);

        StockBatch::factory()->create([
            'material_id' => $material->id,
            'expiry_date' => now()->addDays(10)
        ]);

        $expiringBatches = StockBatch::forMaterial($material->id)->expiringWithin(7)->get();

        $this->assertCount(1, $expiringBatches);
        $this->assertTrue($expiringBatches->first()->expiry_date->diffInDays(now()) <= 7);
    }

    /** @test */
    public function fifo_order_scope_orders_by_received_date()
    {
        $material = Material::factory()->create();

        $batch1 = StockBatch::factory()->create([
            'material_id' => $material->id,
            'received_date' => now()->subDays(2),
            'batch_number' => 'BATCH-001'
        ]);

        $batch2 = StockBatch::factory()->create([
            'material_id' => $material->id,
            'received_date' => now()->subDays(1),
            'batch_number' => 'BATCH-002'
        ]);

        $orderedBatches = StockBatch::forMaterial($material->id)->fifoOrder()->get();

        $this->assertEquals('BATCH-001', $orderedBatches->first()->batch_number);
        $this->assertEquals('BATCH-002', $orderedBatches->last()->batch_number);
    }

    /** @test */
    public function total_value_accessor_calculates_correctly()
    {
        $batch = StockBatch::factory()->create([
            'remaining_quantity' => 25.0,
            'unit_cost' => 4.00
        ]);

        $this->assertEquals(100.00, $batch->total_value);
    }

    /** @test */
    public function is_expired_accessor_works_correctly()
    {
        $expiredBatch = StockBatch::factory()->create([
            'expiry_date' => now()->subDays(1)
        ]);

        $validBatch = StockBatch::factory()->create([
            'expiry_date' => now()->addDays(1)
        ]);

        $this->assertTrue($expiredBatch->is_expired);
        $this->assertFalse($validBatch->is_expired);
    }

    /** @test */
    public function is_expiring_accessor_works_correctly()
    {
        $expiringBatch = StockBatch::factory()->create([
            'expiry_date' => now()->addDays(3)
        ]);

        $notExpiringBatch = StockBatch::factory()->create([
            'expiry_date' => now()->addDays(10)
        ]);

        $this->assertTrue($expiringBatch->is_expiring);
        $this->assertFalse($notExpiringBatch->is_expiring);
    }

    /** @test */
    public function usage_percentage_accessor_calculates_correctly()
    {
        $batch = StockBatch::factory()->create([
            'quantity' => 100.0,
            'remaining_quantity' => 75.0
        ]);

        $this->assertEquals(25.0, $batch->usage_percentage);
    }

    /** @test */
    public function consume_method_reduces_remaining_quantity()
    {
        $batch = StockBatch::factory()->create([
            'remaining_quantity' => 100.0
        ]);

        $batch->consume(30.0);

        $this->assertEquals(70.0, $batch->fresh()->remaining_quantity);
    }

    /** @test */
    public function consume_method_throws_exception_for_insufficient_quantity()
    {
        $batch = StockBatch::factory()->create([
            'remaining_quantity' => 50.0
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $batch->consume(60.0);
    }

    /** @test */
    public function can_consume_method_checks_availability()
    {
        $batch = StockBatch::factory()->create([
            'remaining_quantity' => 50.0
        ]);

        $this->assertTrue($batch->canConsume(30.0));
        $this->assertTrue($batch->canConsume(50.0));
        $this->assertFalse($batch->canConsume(60.0));
    }

    /** @test */
    public function is_fully_consumed_method_works_correctly()
    {
        $batch = StockBatch::factory()->create([
            'remaining_quantity' => 0.0
        ]);

        $partialBatch = StockBatch::factory()->create([
            'remaining_quantity' => 25.0
        ]);

        $this->assertTrue($batch->isFullyConsumed());
        $this->assertFalse($partialBatch->isFullyConsumed());
    }

    /** @test */
    public function is_available_method_considers_quantity_and_expiry()
    {
        $availableBatch = StockBatch::factory()->create([
            'remaining_quantity' => 50.0,
            'expiry_date' => now()->addDays(5)
        ]);

        $expiredBatch = StockBatch::factory()->create([
            'remaining_quantity' => 50.0,
            'expiry_date' => now()->subDays(1)
        ]);

        $emptyBatch = StockBatch::factory()->create([
            'remaining_quantity' => 0.0,
            'expiry_date' => now()->addDays(5)
        ]);

        $this->assertTrue($availableBatch->isAvailable());
        $this->assertFalse($expiredBatch->isAvailable());
        $this->assertFalse($emptyBatch->isAvailable());
    }

    /** @test */
    public function generate_batch_number_creates_unique_numbers()
    {
        $material = Material::factory()->create(['name' => 'Flour']);

        $batch1 = new StockBatch([
            'material_id' => $material->id,
            'received_date' => now(),
            'quantity' => 100,
            'remaining_quantity' => 100,
            'unit_cost' => 5.00
        ]);

        // Save the first batch to create a record in the database
        $batch1->batch_number = $batch1->generateBatchNumber();
        $batch1->save();

        $batch2 = new StockBatch([
            'material_id' => $material->id,
            'received_date' => now(),
            'quantity' => 100,
            'remaining_quantity' => 100,
            'unit_cost' => 5.00
        ]);

        $batchNumber1 = $batch1->batch_number;
        $batchNumber2 = $batch2->generateBatchNumber();

        $this->assertNotEquals($batchNumber1, $batchNumber2);
        $this->assertStringContainsString('FLO', $batchNumber1);
        $this->assertStringContainsString(now()->format('Ymd'), $batchNumber1);
    }

    /** @test */
    public function consume_for_material_uses_fifo_logic()
    {
        $material = Material::factory()->create();

        // Create batches with different dates and costs
        $batch1 = StockBatch::factory()->create([
            'material_id' => $material->id,
            'received_date' => now()->subDays(2),
            'quantity' => 50.0,
            'remaining_quantity' => 50.0,
            'unit_cost' => 5.00
        ]);

        $batch2 = StockBatch::factory()->create([
            'material_id' => $material->id,
            'received_date' => now()->subDays(1),
            'quantity' => 30.0,
            'remaining_quantity' => 30.0,
            'unit_cost' => 6.00
        ]);

        $result = StockBatch::consumeForMaterial($material->id, 60.0);

        $this->assertEquals(60.0, $result['total_consumed']);
        // Debug: Check actual values
        // Expected: (50 * 5.00) + (10 * 6.00) = 250 + 60 = 310
        // But test expects 330, which would be (50 * 5.00) + (10 * 8.00) = 250 + 80 = 330
        // Let's check what we actually get
        $this->assertEquals(310.0, $result['total_cost']); // (50 * 5.00) + (10 * 6.00)
        $this->assertCount(2, $result['batches']);

        // Check that batch1 is fully consumed and batch2 is partially consumed
        $this->assertEquals(0.0, $batch1->fresh()->remaining_quantity);
        $this->assertEquals(20.0, $batch2->fresh()->remaining_quantity);
    }

    /** @test */
    public function calculate_fifo_cost_returns_correct_cost()
    {
        $material = Material::factory()->create();

        StockBatch::factory()->create([
            'material_id' => $material->id,
            'received_date' => now()->subDays(2),
            'quantity' => 50.0,
            'remaining_quantity' => 50.0,
            'unit_cost' => 5.00
        ]);

        StockBatch::factory()->create([
            'material_id' => $material->id,
            'received_date' => now()->subDays(1),
            'quantity' => 30.0,
            'remaining_quantity' => 30.0,
            'unit_cost' => 6.00
        ]);

        $cost = StockBatch::calculateFifoCost($material->id, 60.0);

        $this->assertEquals(310.0, $cost); // (50 * 5.00) + (10 * 6.00)
    }
}
