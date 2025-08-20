<?php

namespace Tests\Unit;

use App\Models\Material;
use App\Models\MaterialReceipt;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function it_can_create_a_supplier_with_required_fields()
    {
        $supplier = Supplier::create([
            'name' => 'Test Supplier',
            'email' => 'test@supplier.com'
        ]);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Test Supplier',
            'email' => 'test@supplier.com',
            'is_active' => true,
            'lead_time_days' => 0,
            'minimum_order_amount' => 0.00
        ]);
    }

    /** @test */
    public function it_can_create_a_supplier_with_all_fields()
    {
        $supplierData = [
            'name' => 'Complete Supplier',
            'contact_person' => 'John Doe',
            'email' => 'john@supplier.com',
            'phone' => '+1234567890',
            'address' => '123 Supplier Street',
            'payment_terms' => 'Net 30',
            'lead_time_days' => 7,
            'minimum_order_amount' => 100.00,
            'is_active' => true,
            'rating' => 4.5,
            'notes' => 'Reliable supplier'
        ];

        $supplier = Supplier::create($supplierData);

        $this->assertDatabaseHas('suppliers', $supplierData);
        $this->assertEquals(4.5, $supplier->rating);
        $this->assertEquals(100.00, $supplier->minimum_order_amount);
    }

    /** @test */
    public function it_has_correct_default_values()
    {
        $supplier = new Supplier([
            'name' => 'Test Supplier'
        ]);

        $this->assertTrue($supplier->is_active);
        $this->assertEquals(0, $supplier->lead_time_days);
        $this->assertEquals(0.00, $supplier->minimum_order_amount);
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $supplier = Supplier::create([
            'name' => 'Test Supplier',
            'is_active' => '1',
            'rating' => '4.50',
            'minimum_order_amount' => '100.00',
            'lead_time_days' => '7'
        ]);

        $this->assertIsBool($supplier->is_active);
        $this->assertTrue($supplier->is_active);
        $this->assertEquals(4.50, $supplier->rating);
        $this->assertEquals(100.00, $supplier->minimum_order_amount);
        $this->assertIsInt($supplier->lead_time_days);
        $this->assertEquals(7, $supplier->lead_time_days);
    }

    /** @test */
    public function it_has_materials_relationship()
    {
        $supplier = Supplier::factory()->create();

        // This test will be more meaningful once we have Material factory
        // For now, just test the relationship exists
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $supplier->materials());
    }

    /** @test */
    public function it_has_material_receipts_relationship()
    {
        $supplier = Supplier::factory()->create();

        // This test will be more meaningful once we have MaterialReceipt relationship
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $supplier->materialReceipts());
    }

    /** @test */
    public function active_scope_filters_active_suppliers()
    {
        Supplier::factory()->create(['name' => 'Active Supplier', 'is_active' => true]);
        Supplier::factory()->create(['name' => 'Inactive Supplier', 'is_active' => false]);

        $activeSuppliers = Supplier::active()->get();

        $this->assertCount(1, $activeSuppliers);
        $this->assertEquals('Active Supplier', $activeSuppliers->first()->name);
    }

    /** @test */
    public function by_rating_scope_filters_by_minimum_rating()
    {
        Supplier::factory()->create(['name' => 'High Rated', 'rating' => 4.5]);
        Supplier::factory()->create(['name' => 'Medium Rated', 'rating' => 3.0]);
        Supplier::factory()->create(['name' => 'Low Rated', 'rating' => 2.0]);

        $highRatedSuppliers = Supplier::byRating(4.0)->get();

        $this->assertCount(1, $highRatedSuppliers);
        $this->assertEquals('High Rated', $highRatedSuppliers->first()->name);
    }

    /** @test */
    public function by_rating_scope_returns_all_when_no_rating_specified()
    {
        Supplier::factory()->create(['rating' => 4.5]);
        Supplier::factory()->create(['rating' => 3.0]);
        Supplier::factory()->create(['rating' => 2.0]);

        $allSuppliers = Supplier::byRating()->get();

        $this->assertCount(3, $allSuppliers);
    }

    /** @test */
    public function formatted_rating_accessor_returns_correct_format()
    {
        $supplier = Supplier::factory()->create(['rating' => 4.5]);
        $this->assertEquals('4.5/5.0', $supplier->formatted_rating);

        $supplierNoRating = Supplier::factory()->create(['rating' => null]);
        $this->assertEquals('Not rated', $supplierNoRating->formatted_rating);
    }

    /** @test */
    public function formatted_minimum_order_accessor_returns_correct_format()
    {
        $supplier = Supplier::factory()->create(['minimum_order_amount' => 150.75]);
        $this->assertEquals('$150.75', $supplier->formatted_minimum_order);

        $supplierZero = Supplier::factory()->create(['minimum_order_amount' => 0]);
        $this->assertEquals('$0.00', $supplierZero->formatted_minimum_order);
    }

    /** @test */
    public function calculate_performance_metrics_returns_default_values()
    {
        $supplier = Supplier::factory()->create(['rating' => 4.2]);

        $metrics = $supplier->calculatePerformanceMetrics();

        $this->assertEquals([
            'total_orders' => 0,
            'on_time_delivery_rate' => 0,
            'average_delivery_time' => 0,
            'quality_score' => 4.2
        ], $metrics);
    }

    /** @test */
    public function is_reliable_returns_true_for_active_high_rated_supplier()
    {
        $supplier = Supplier::factory()->create([
            'is_active' => true,
            'rating' => 4.5
        ]);

        $this->assertTrue($supplier->isReliable());
    }

    /** @test */
    public function is_reliable_returns_true_for_active_unrated_supplier()
    {
        $supplier = Supplier::factory()->create([
            'is_active' => true,
            'rating' => null
        ]);

        $this->assertTrue($supplier->isReliable());
    }

    /** @test */
    public function is_reliable_returns_false_for_inactive_supplier()
    {
        $supplier = Supplier::factory()->create([
            'is_active' => false,
            'rating' => 4.5
        ]);

        $this->assertFalse($supplier->isReliable());
    }

    /** @test */
    public function is_reliable_returns_false_for_low_rated_supplier()
    {
        $supplier = Supplier::factory()->create([
            'is_active' => true,
            'rating' => 3.0
        ]);

        $this->assertFalse($supplier->isReliable());
    }

    /** @test */
    public function it_uses_soft_deletes()
    {
        $supplier = Supplier::factory()->create(['name' => 'Test Supplier']);

        $supplier->delete();

        $this->assertSoftDeleted('suppliers', ['name' => 'Test Supplier']);
        $this->assertCount(0, Supplier::all());
        $this->assertCount(1, Supplier::withTrashed()->get());
    }
}
