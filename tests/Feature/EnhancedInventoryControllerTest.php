<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Material;
use App\Models\StockBatch;
use App\Models\StockAlert;
use App\Models\InventoryTransaction;
use App\Models\Supplier;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Carbon\Carbon;

class EnhancedInventoryControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $material;
    protected $supplier;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();

        // Create test supplier and category
        $this->supplier = Supplier::factory()->create();
        $this->category = Category::factory()->create();

        // Create test material
        $this->material = Material::factory()->create([
            'name' => 'Test Flour',
            'quantity' => 50,
            'minimum_stock_level' => 20,
            'maximum_stock_level' => 200,
            'reorder_point' => 30,
            'default_supplier_id' => $this->supplier->id,
            'category_id' => $this->category->id
        ]);

        // Create test stock batch
        StockBatch::factory()->create([
            'material_id' => $this->material->id,
            'quantity' => 50,
            'remaining_quantity' => 50,
            'unit_cost' => 2.50,
            'supplier_id' => $this->supplier->id
        ]);
    }

    /** @test */
    public function it_can_get_inventory_dashboard()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/inventory/enhanced/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_materials',
                        'low_stock_count',
                        'out_of_stock_count',
                        'active_alerts_count',
                        'total_stock_value',
                        'expiring_batches_count'
                    ],
                    'recent_movements',
                    'timestamp'
                ]
            ]);
    }

    /** @test */
    public function it_can_get_materials_list()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/inventory/enhanced/materials');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'quantity',
                            'stock_unit',
                            'current_stock_value',
                            'is_low_stock',
                            'is_at_reorder_point',
                            'is_overstock'
                        ]
                    ],
                    'per_page',
                    'total'
                ]
            ]);
    }

    /** @test */
    public function it_can_filter_materials_by_low_stock()
    {
        // Create a low stock material
        $lowStockMaterial = Material::factory()->create([
            'quantity' => 10,
            'minimum_stock_level' => 20
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/inventory/enhanced/materials?low_stock=true');

        $response->assertStatus(200);

        $materials = $response->json('data.data');
        $this->assertCount(1, $materials);
        $this->assertEquals($lowStockMaterial->id, $materials[0]['id']);
        $this->assertTrue($materials[0]['is_low_stock']);
    }

    /** @test */
    public function it_can_create_stock_adjustment_increase()
    {
        $adjustmentData = [
            'material_id' => $this->material->id,
            'adjustment_type' => 'increase',
            'quantity' => 25,
            'reason' => 'New stock delivery',
            'unit_cost' => 2.75,
            'notes' => 'Test adjustment'
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/inventory/enhanced/adjustments', $adjustmentData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'transaction_id',
                    'material_id',
                    'old_quantity',
                    'new_quantity',
                    'adjustment_quantity'
                ]
            ]);

        // Verify material quantity was updated
        $this->material->refresh();
        $this->assertEquals(75, $this->material->quantity);

        // Verify transaction was created
        $this->assertDatabaseHas('inventory_transactions', [
            'material_id' => $this->material->id,
            'type' => 'adjustment',
            'quantity' => 25,
            'user_id' => $this->user->id
        ]);

        // Verify stock batch was created
        $this->assertDatabaseHas('stock_batches', [
            'material_id' => $this->material->id,
            'quantity' => 25,
            'remaining_quantity' => 25,
            'unit_cost' => 2.75
        ]);
    }

    /** @test */
    public function it_can_create_stock_adjustment_decrease()
    {
        $adjustmentData = [
            'material_id' => $this->material->id,
            'adjustment_type' => 'decrease',
            'quantity' => 15,
            'reason' => 'Damaged stock removal'
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/inventory/enhanced/adjustments', $adjustmentData);

        $response->assertStatus(200);

        // Verify material quantity was updated
        $this->material->refresh();
        $this->assertEquals(35, $this->material->quantity);

        // Verify transaction was created
        $this->assertDatabaseHas('inventory_transactions', [
            'material_id' => $this->material->id,
            'type' => 'adjustment',
            'quantity' => -15,
            'user_id' => $this->user->id
        ]);
    }

    /** @test */
    public function it_validates_stock_adjustment_request()
    {
        $invalidData = [
            'material_id' => 999, // Non-existent material
            'adjustment_type' => 'invalid_type',
            'quantity' => -5, // Negative quantity
            'reason' => '' // Empty reason
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/inventory/enhanced/adjustments', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'material_id',
                'adjustment_type',
                'quantity',
                'reason'
            ]);
    }

    /** @test */
    public function it_can_get_inventory_valuation()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/inventory/enhanced/valuation');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'materials' => [
                        '*' => [
                            'material_id',
                            'material_name',
                            'quantity',
                            'stock_unit',
                            'average_cost',
                            'total_value',
                            'purchase_price',
                            'batches_count'
                        ]
                    ],
                    'summary' => [
                        'total_materials',
                        'total_value',
                        'average_value_per_material'
                    ],
                    'generated_at'
                ]
            ]);
    }

    /** @test */
    public function it_can_get_movement_history()
    {
        // Create some test transactions
        InventoryTransaction::factory()->count(3)->create([
            'material_id' => $this->material->id,
            'user_id' => $this->user->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/inventory/enhanced/movements');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'id',
                            'material_id',
                            'material_name',
                            'type',
                            'quantity',
                            'stock_unit',
                            'unit_cost',
                            'total_cost',
                            'user_id',
                            'notes',
                            'created_at'
                        ]
                    ],
                    'per_page',
                    'total'
                ]
            ]);
    }

    /** @test */
    public function it_can_filter_movement_history_by_material()
    {
        // Create transactions for different materials
        $otherMaterial = Material::factory()->create();

        InventoryTransaction::factory()->create([
            'material_id' => $this->material->id,
            'type' => 'receipt'
        ]);

        InventoryTransaction::factory()->create([
            'material_id' => $otherMaterial->id,
            'type' => 'adjustment'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/inventory/enhanced/movements?material_id={$this->material->id}");

        $response->assertStatus(200);

        $movements = $response->json('data.data');
        $this->assertCount(1, $movements);
        $this->assertEquals($this->material->id, $movements[0]['material_id']);
    }

    /** @test */
    public function it_can_get_stock_batches()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/inventory/enhanced/batches');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'id',
                            'batch_number',
                            'material_id',
                            'material_name',
                            'quantity',
                            'remaining_quantity',
                            'unit_cost',
                            'total_value',
                            'received_date',
                            'is_available'
                        ]
                    ],
                    'per_page',
                    'total'
                ]
            ]);
    }

    /** @test */
    public function it_can_get_material_specific_batches()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/inventory/enhanced/materials/{$this->material->id}/batches");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'material' => [
                        'id',
                        'name',
                        'current_quantity',
                        'stock_unit'
                    ],
                    'batches',
                    'summary' => [
                        'total_batches',
                        'available_batches',
                        'expired_batches',
                        'expiring_batches',
                        'total_value'
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_get_expiry_tracking()
    {
        // Create expiring batch
        StockBatch::factory()->create([
            'material_id' => $this->material->id,
            'expiry_date' => Carbon::now()->addDays(3),
            'remaining_quantity' => 10
        ]);

        // Create expired batch
        StockBatch::factory()->create([
            'material_id' => $this->material->id,
            'expiry_date' => Carbon::now()->subDays(2),
            'remaining_quantity' => 5
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/inventory/enhanced/expiry-tracking');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'expiring_batches',
                    'expired_batches',
                    'summary' => [
                        'total_expiring',
                        'total_expired',
                        'value_at_risk',
                        'expired_value',
                        'by_urgency'
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->getJson('/api/inventory/enhanced/dashboard');
        $response->assertStatus(401);
    }

    /** @test */
    public function it_handles_non_existent_material_in_batches_endpoint()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/inventory/enhanced/materials/999/batches');

        $response->assertStatus(404);
    }
}
