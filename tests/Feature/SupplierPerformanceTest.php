<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\SupplierCommunication;
use App\Models\SupplierPerformanceMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Carbon\Carbon;

class SupplierPerformanceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->supplier = Supplier::factory()->create([
            'name' => 'Test Supplier',
            'rating' => 4.0
        ]);
    }

    /** @test */
    public function it_can_get_supplier_performance_metrics()
    {
        // Create test data
        PurchaseOrder::factory()->count(5)->create([
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrder::STATUS_RECEIVED
        ]);

        SupplierCommunication::factory()->count(3)->withResponse()->create([
            'supplier_id' => $this->supplier->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/suppliers/performance/{$this->supplier->id}/metrics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'supplier' => [
                        'id',
                        'name',
                        'is_active',
                        'rating',
                        'performance_grade',
                        'is_reliable'
                    ],
                    'current_metrics' => [
                        'total_orders',
                        'completed_orders',
                        'on_time_delivery_rate',
                        'quality_score',
                        'overall_rating'
                    ],
                    'communication_stats' => [
                        'total_communications',
                        'response_rate',
                        'average_response_time_hours',
                        'average_satisfaction'
                    ],
                    'recent_orders',
                    'recent_communications'
                ]
            ]);
    }

    /** @test */
    public function it_can_get_performance_comparison()
    {
        // Create multiple suppliers with different performance
        $suppliers = Supplier::factory()->count(3)->create();

        foreach ($suppliers as $supplier) {
            PurchaseOrder::factory()->count(2)->received()->create([
                'supplier_id' => $supplier->id
            ]);
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/suppliers/performance/comparison');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'suppliers' => [
                        '*' => [
                            'id',
                            'name',
                            'overall_rating',
                            'on_time_delivery_rate',
                            'completion_rate',
                            'performance_grade',
                            'is_reliable'
                        ]
                    ],
                    'industry_averages' => [
                        'overall_rating',
                        'on_time_delivery_rate',
                        'completion_rate',
                        'quality_score'
                    ],
                    'total_suppliers',
                    'reliable_suppliers'
                ]
            ]);
    }

    /** @test */
    public function it_can_update_supplier_performance_metrics()
    {
        // Create some purchase orders for the supplier
        PurchaseOrder::factory()->count(3)->received()->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => now()->subDays(15)
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/suppliers/performance/{$this->supplier->id}/metrics/update");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'supplier_id',
                    'metric_period',
                    'total_orders',
                    'completed_orders',
                    'overall_rating'
                ],
                'message'
            ]);

        // Verify metric was created in database
        $this->assertDatabaseHas('supplier_performance_metrics', [
            'supplier_id' => $this->supplier->id,
            'total_orders' => 3,
            'completed_orders' => 3
        ]);
    }

    /** @test */
    public function it_can_bulk_update_performance_metrics()
    {
        // Create multiple suppliers with orders
        $suppliers = Supplier::factory()->count(3)->create();

        foreach ($suppliers as $supplier) {
            PurchaseOrder::factory()->count(2)->received()->create([
                'supplier_id' => $supplier->id
            ]);
        }

        $response = $this->actingAs($this->user)
            ->postJson('/api/suppliers/performance/bulk-update');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'updated_suppliers',
                    'period'
                ]
            ]);

        // Verify metrics were created for all suppliers
        $this->assertEquals(4, SupplierPerformanceMetric::count()); // 3 + 1 from setUp
    }

    /** @test */
    public function it_can_get_delivery_performance()
    {
        // Create orders with different delivery statuses
        PurchaseOrder::factory()->received()->create([
            'supplier_id' => $this->supplier->id,
            'expected_delivery_date' => now()->subDays(5),
            'actual_delivery_date' => now()->subDays(5) // On time
        ]);

        PurchaseOrder::factory()->received()->create([
            'supplier_id' => $this->supplier->id,
            'expected_delivery_date' => now()->subDays(10),
            'actual_delivery_date' => now()->subDays(8) // Late
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/suppliers/performance/{$this->supplier->id}/delivery");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'supplier' => ['id', 'name'],
                    'delivery_stats' => [
                        'total_deliveries',
                        'on_time_deliveries',
                        'late_deliveries',
                        'on_time_rate',
                        'average_delay_days'
                    ],
                    'monthly_trends'
                ]
            ]);
    }

    /** @test */
    public function it_can_get_communication_analysis()
    {
        // Create communications with different response patterns
        SupplierCommunication::factory()->withResponse()->create([
            'supplier_id' => $this->supplier->id,
            'communication_type' => 'inquiry',
            'response_time_hours' => 2.5,
            'satisfaction_rating' => 4.5
        ]);

        SupplierCommunication::factory()->withoutResponse()->create([
            'supplier_id' => $this->supplier->id,
            'communication_type' => 'complaint'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/suppliers/performance/{$this->supplier->id}/communication");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'supplier' => ['id', 'name'],
                    'communication_stats' => [
                        'total_communications',
                        'by_type',
                        'by_method',
                        'response_rate',
                        'average_response_time_hours',
                        'average_satisfaction'
                    ],
                    'monthly_trends',
                    'communications'
                ]
            ]);
    }

    /** @test */
    public function it_can_create_communication_record()
    {
        $communicationData = [
            'communication_type' => 'inquiry',
            'subject' => 'Product availability inquiry',
            'message' => 'Do you have flour available for immediate delivery?',
            'method' => 'email',
            'notes' => 'Urgent request'
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/suppliers/performance/{$this->supplier->id}/communication", $communicationData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'supplier_id',
                    'communication_type',
                    'subject',
                    'message',
                    'method',
                    'initiated_by'
                ],
                'message'
            ]);

        // Verify communication was created in database
        $this->assertDatabaseHas('supplier_communications', [
            'supplier_id' => $this->supplier->id,
            'communication_type' => 'inquiry',
            'subject' => 'Product availability inquiry',
            'initiated_by' => $this->user->id
        ]);
    }

    /** @test */
    public function it_can_update_communication_response()
    {
        $communication = SupplierCommunication::factory()->withoutResponse()->create([
            'supplier_id' => $this->supplier->id
        ]);

        $responseData = [
            'satisfaction_rating' => 4.5,
            'notes' => 'Quick and helpful response'
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/suppliers/performance/communication/{$communication->id}/response", $responseData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'response_received',
                    'response_date',
                    'response_time_hours',
                    'satisfaction_rating'
                ],
                'message'
            ]);

        // Verify communication was updated
        $communication->refresh();
        $this->assertTrue($communication->response_received);
        $this->assertEquals(4.5, $communication->satisfaction_rating);
        $this->assertNotNull($communication->response_date);
        $this->assertNotNull($communication->response_time_hours);
    }

    /** @test */
    public function it_validates_communication_creation_request()
    {
        $invalidData = [
            'communication_type' => 'invalid_type',
            'subject' => '', // Required field
            'message' => '', // Required field
            'method' => 'invalid_method'
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/suppliers/performance/{$this->supplier->id}/communication", $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'communication_type',
                'subject',
                'message',
                'method'
            ]);
    }

    /** @test */
    public function it_calculates_performance_metrics_correctly()
    {
        // Create test data with known values
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        // Create 5 orders: 3 completed, 1 cancelled, 1 pending
        PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'order_date' => $startDate->copy()->addDays(1),
            'expected_delivery_date' => $startDate->copy()->addDays(5),
            'actual_delivery_date' => $startDate->copy()->addDays(5), // On time
            'final_amount' => 1000
        ]);

        PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'order_date' => $startDate->copy()->addDays(2),
            'expected_delivery_date' => $startDate->copy()->addDays(6),
            'actual_delivery_date' => $startDate->copy()->addDays(8), // Late by 2 days
            'final_amount' => 1500
        ]);

        PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrder::STATUS_CANCELLED,
            'order_date' => $startDate->copy()->addDays(3),
            'final_amount' => 500
        ]);

        // Calculate metrics
        $metricsData = SupplierPerformanceMetric::calculateForSupplier($this->supplier, $startDate, $endDate);

        // Verify calculations
        $this->assertEquals(3, $metricsData['total_orders']);
        $this->assertEquals(2, $metricsData['completed_orders']);
        $this->assertEquals(1, $metricsData['cancelled_orders']);
        $this->assertEquals(1, $metricsData['on_time_deliveries']);
        $this->assertEquals(1, $metricsData['late_deliveries']);
        $this->assertEquals(1.0, $metricsData['average_delivery_delay_days']); // Average of 0 and 2
        $this->assertEquals(3000, $metricsData['total_order_value']); // Sum of all orders
    }

    /** @test */
    public function it_requires_authentication_for_performance_endpoints()
    {
        $response = $this->getJson("/api/suppliers/performance/{$this->supplier->id}/metrics");
        $response->assertStatus(401);

        $response = $this->getJson('/api/suppliers/performance/comparison');
        $response->assertStatus(401);

        $response = $this->postJson("/api/suppliers/performance/{$this->supplier->id}/communication", []);
        $response->assertStatus(401);
    }
}
