<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialReceipt;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class MaterialReceiptEnhancedTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user and generate JWT token
        $this->user = User::factory()->create();
        $this->token = JWTAuth::fromUser($this->user);
    }

    /** @test */
    public function it_can_create_material_receipt_with_supplier()
    {
        $material = Material::factory()->create();
        $supplier = Supplier::factory()->create();

        $receiptData = [
            'material_id' => $material->id,
            'supplier_id' => $supplier->id,
            'quantity_received' => 100.0,
            'unit_cost' => 5.50,
            'source_type' => 'external_supplier',
            'supplier_name' => $supplier->name,
            'invoice_number' => 'INV-001',
            'invoice_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'notes' => 'Test receipt with supplier'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/material-receipts', $receiptData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'receipt' => [
                    'id',
                    'receipt_code',
                    'material_id',
                    'supplier_id',
                    'quantity_received',
                    'unit_cost',
                    'total_cost',
                    'material',
                    'supplier',
                    'stock_batch'
                ]
            ]);

        $this->assertDatabaseHas('material_receipts', [
            'material_id' => $material->id,
            'supplier_id' => $supplier->id,
            'quantity_received' => 100.0,
            'unit_cost' => 5.50
        ]);

        // Check that stock batch was created
        $this->assertDatabaseHas('stock_batches', [
            'material_id' => $material->id,
            'quantity' => 100.0,
            'remaining_quantity' => 100.0,
            'unit_cost' => 5.50,
            'supplier_id' => $supplier->id
        ]);
    }

    /** @test */
    public function it_includes_suppliers_in_create_form_data()
    {
        Supplier::factory()->count(3)->create(['is_active' => true]);
        Supplier::factory()->create(['is_active' => false]); // Inactive supplier

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/material-receipts/create');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'materials',
                'suppliers',
                'source_types'
            ]);

        $suppliers = $response->json('suppliers');
        $this->assertCount(3, $suppliers); // Only active suppliers
    }

    /** @test */
    public function it_can_get_batch_information_for_receipt()
    {
        $material = Material::factory()->create();
        $supplier = Supplier::factory()->create();

        $receipt = MaterialReceipt::factory()->create([
            'material_id' => $material->id,
            'supplier_id' => $supplier->id
        ]);

        // Create associated stock batch
        $batch = StockBatch::factory()->create([
            'material_id' => $material->id,
            'supplier_id' => $supplier->id,
            'material_receipt_id' => $receipt->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/material-receipts/{$receipt->id}/batch");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'batch' => [
                    'id',
                    'batch_number',
                    'quantity',
                    'remaining_quantity',
                    'unit_cost',
                    'received_date',
                    'expiry_date',
                    'material'
                ]
            ]);
    }

    /** @test */
    public function it_prevents_deleting_receipt_with_consumed_batch()
    {
        $material = Material::factory()->create();
        $receipt = MaterialReceipt::factory()->create([
            'material_id' => $material->id
        ]);

        // Create a partially consumed batch and associate it with the receipt
        $batch = StockBatch::factory()->create([
            'material_id' => $material->id,
            'material_receipt_id' => $receipt->id,
            'quantity' => 100.0,
            'remaining_quantity' => 50.0 // Partially consumed
        ]);

        // Manually set the relationship since factory doesn't trigger model events
        $receipt->setRelation('stockBatch', $batch);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson("/api/material-receipts/{$receipt->id}");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete receipt: stock batch has been partially consumed'
            ]);

        // Receipt should still exist
        $this->assertDatabaseHas('material_receipts', [
            'id' => $receipt->id
        ]);
    }

    /** @test */
    public function it_can_delete_receipt_with_unconsumed_batch()
    {
        $material = Material::factory()->create(['quantity' => 100.0]);
        $receipt = MaterialReceipt::factory()->create([
            'material_id' => $material->id,
            'quantity_received' => 50.0
        ]);

        // Create an unconsumed batch and associate it with the receipt
        $batch = StockBatch::factory()->create([
            'material_id' => $material->id,
            'material_receipt_id' => $receipt->id,
            'quantity' => 50.0,
            'remaining_quantity' => 50.0 // Fully available
        ]);

        // Manually set the relationship since factory doesn't trigger model events
        $receipt->setRelation('stockBatch', $batch);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson("/api/material-receipts/{$receipt->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Material receipt deleted successfully'
            ]);

        // Receipt and batch should be deleted
        $this->assertDatabaseMissing('material_receipts', [
            'id' => $receipt->id
        ]);

        $this->assertDatabaseMissing('stock_batches', [
            'id' => $batch->id
        ]);
    }

    /** @test */
    public function it_includes_batch_information_in_receipt_listing()
    {
        $material = Material::factory()->create();
        $supplier = Supplier::factory()->create();

        $receipt = MaterialReceipt::factory()->create([
            'material_id' => $material->id,
            'supplier_id' => $supplier->id
        ]);

        $batch = StockBatch::factory()->create([
            'material_id' => $material->id,
            'supplier_id' => $supplier->id,
            'material_receipt_id' => $receipt->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/material-receipts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'receipts' => [
                    'data' => [
                        '*' => [
                            'id',
                            'material',
                            'supplier',
                            'stock_batch'
                        ]
                    ]
                ]
            ]);
    }
}
