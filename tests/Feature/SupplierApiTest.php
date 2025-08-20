<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SupplierApiTest extends TestCase
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
    public function it_can_list_suppliers()
    {
        Supplier::factory()->count(3)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/suppliers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'contact_person',
                            'email',
                            'phone',
                            'is_active',
                            'rating'
                        ]
                    ]
                ],
                'message'
            ]);
    }

    /** @test */
    public function it_can_create_a_supplier()
    {
        $supplierData = [
            'name' => 'Test Supplier',
            'contact_person' => 'John Doe',
            'email' => 'john@testsupplier.com',
            'phone' => '+1234567890',
            'address' => '123 Test Street',
            'payment_terms' => 'Net 30',
            'lead_time_days' => 7,
            'minimum_order_amount' => 100.00,
            'is_active' => true,
            'rating' => 4.5,
            'notes' => 'Test supplier notes'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/suppliers', $supplierData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'contact_person',
                    'email'
                ],
                'message'
            ]);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Test Supplier',
            'email' => 'john@testsupplier.com'
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_supplier()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/suppliers', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function it_can_show_a_supplier()
    {
        $supplier = Supplier::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'supplier' => [
                        'id',
                        'name',
                        'contact_person',
                        'email'
                    ],
                    'performance_metrics'
                ],
                'message'
            ]);
    }

    /** @test */
    public function it_can_update_a_supplier()
    {
        $supplier = Supplier::factory()->create();

        $updateData = [
            'name' => 'Updated Supplier Name',
            'rating' => 4.8
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/suppliers/{$supplier->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'message'
            ]);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Updated Supplier Name',
            'rating' => 4.8
        ]);
    }

    /** @test */
    public function it_can_delete_a_supplier()
    {
        $supplier = Supplier::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Supplier deleted successfully'
            ]);

        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    /** @test */
    public function it_can_get_supplier_performance_metrics()
    {
        $supplier = Supplier::factory()->create(['rating' => 4.2]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/suppliers/{$supplier->id}/performance");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_orders',
                    'on_time_delivery_rate',
                    'average_delivery_time',
                    'quality_score',
                    'materials_supplied',
                    'total_receipts',
                    'is_reliable'
                ],
                'message'
            ]);
    }

    /** @test */
    public function it_can_toggle_supplier_status()
    {
        $supplier = Supplier::factory()->create(['is_active' => true]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/suppliers/{$supplier->id}/toggle-status");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Supplier deactivated successfully'
            ]);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'is_active' => false
        ]);
    }

    /** @test */
    public function it_can_filter_suppliers_by_active_status()
    {
        Supplier::factory()->create(['is_active' => true]);
        Supplier::factory()->create(['is_active' => false]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/suppliers?active=1');

        $response->assertStatus(200);

        $suppliers = $response->json('data.data');
        $this->assertCount(1, $suppliers);
        $this->assertTrue($suppliers[0]['is_active']);
    }

    /** @test */
    public function it_can_search_suppliers()
    {
        Supplier::factory()->create(['name' => 'ABC Supplier']);
        Supplier::factory()->create(['name' => 'XYZ Supplier']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/suppliers?search=ABC');

        $response->assertStatus(200);

        $suppliers = $response->json('data.data');
        $this->assertCount(1, $suppliers);
        $this->assertEquals('ABC Supplier', $suppliers[0]['name']);
    }
}
