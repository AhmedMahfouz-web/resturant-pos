<?php

namespace Tests\Unit;

use App\Models\Material;
use App\Models\StockAlert;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function it_can_create_a_stock_alert()
    {
        $material = Material::factory()->create();

        $alert = StockAlert::create([
            'material_id' => $material->id,
            'alert_type' => StockAlert::ALERT_TYPE_LOW_STOCK,
            'threshold_value' => 10.0,
            'current_value' => 5.0,
            'message' => 'Low stock alert for test material'
        ]);

        $this->assertDatabaseHas('stock_alerts', [
            'material_id' => $material->id,
            'alert_type' => StockAlert::ALERT_TYPE_LOW_STOCK,
            'threshold_value' => 10.0,
            'current_value' => 5.0,
            'is_resolved' => false
        ]);
    }

    /** @test */
    public function it_has_correct_relationships()
    {
        $material = Material::factory()->create();
        $user = User::factory()->create();

        $alert = StockAlert::factory()->create([
            'material_id' => $material->id,
            'resolved_by' => $user->id,
            'is_resolved' => true,
            'resolved_at' => now()
        ]);

        $this->assertInstanceOf(Material::class, $alert->material);
        $this->assertInstanceOf(User::class, $alert->resolvedBy);
        $this->assertEquals($material->id, $alert->material->id);
        $this->assertEquals($user->id, $alert->resolvedBy->id);
    }

    /** @test */
    public function unresolved_scope_filters_unresolved_alerts()
    {
        $material = Material::factory()->create();

        StockAlert::factory()->create([
            'material_id' => $material->id,
            'is_resolved' => false
        ]);

        StockAlert::factory()->create([
            'material_id' => $material->id,
            'is_resolved' => true
        ]);

        $unresolvedAlerts = StockAlert::unresolved()->get();

        $this->assertCount(1, $unresolvedAlerts);
        $this->assertFalse($unresolvedAlerts->first()->is_resolved);
    }

    /** @test */
    public function resolved_scope_filters_resolved_alerts()
    {
        $material = Material::factory()->create();

        StockAlert::factory()->create([
            'material_id' => $material->id,
            'is_resolved' => false
        ]);

        StockAlert::factory()->create([
            'material_id' => $material->id,
            'is_resolved' => true
        ]);

        $resolvedAlerts = StockAlert::resolved()->get();

        $this->assertCount(1, $resolvedAlerts);
        $this->assertTrue($resolvedAlerts->first()->is_resolved);
    }

    /** @test */
    public function by_type_scope_filters_by_alert_type()
    {
        $material = Material::factory()->create();

        StockAlert::factory()->create([
            'material_id' => $material->id,
            'alert_type' => StockAlert::ALERT_TYPE_LOW_STOCK
        ]);

        StockAlert::factory()->create([
            'material_id' => $material->id,
            'alert_type' => StockAlert::ALERT_TYPE_OUT_OF_STOCK
        ]);

        $lowStockAlerts = StockAlert::byType(StockAlert::ALERT_TYPE_LOW_STOCK)->get();

        $this->assertCount(1, $lowStockAlerts);
        $this->assertEquals(StockAlert::ALERT_TYPE_LOW_STOCK, $lowStockAlerts->first()->alert_type);
    }

    /** @test */
    public function critical_scope_filters_critical_alerts()
    {
        $material = Material::factory()->create();

        StockAlert::factory()->create([
            'material_id' => $material->id,
            'alert_type' => StockAlert::ALERT_TYPE_OUT_OF_STOCK
        ]);

        StockAlert::factory()->create([
            'material_id' => $material->id,
            'alert_type' => StockAlert::ALERT_TYPE_EXPIRY_CRITICAL
        ]);

        StockAlert::factory()->create([
            'material_id' => $material->id,
            'alert_type' => StockAlert::ALERT_TYPE_LOW_STOCK
        ]);

        $criticalAlerts = StockAlert::critical()->get();

        $this->assertCount(2, $criticalAlerts);
        $this->assertTrue($criticalAlerts->contains('alert_type', StockAlert::ALERT_TYPE_OUT_OF_STOCK));
        $this->assertTrue($criticalAlerts->contains('alert_type', StockAlert::ALERT_TYPE_EXPIRY_CRITICAL));
    }

    /** @test */
    public function is_critical_accessor_works_correctly()
    {
        $material = Material::factory()->create();

        $criticalAlert = StockAlert::factory()->create([
            'material_id' => $material->id,
            'alert_type' => StockAlert::ALERT_TYPE_OUT_OF_STOCK
        ]);

        $normalAlert = StockAlert::factory()->create([
            'material_id' => $material->id,
            'alert_type' => StockAlert::ALERT_TYPE_LOW_STOCK
        ]);

        $this->assertTrue($criticalAlert->is_critical);
        $this->assertFalse($normalAlert->is_critical);
    }

    /** @test */
    public function priority_accessor_returns_correct_priority()
    {
        $material = Material::factory()->create();

        $outOfStockAlert = StockAlert::factory()->create([
            'material_id' => $material->id,
            'alert_type' => StockAlert::ALERT_TYPE_OUT_OF_STOCK
        ]);

        $lowStockAlert = StockAlert::factory()->create([
            'material_id' => $material->id,
            'alert_type' => StockAlert::ALERT_TYPE_LOW_STOCK
        ]);

        $overstockAlert = StockAlert::factory()->create([
            'material_id' => $material->id,
            'alert_type' => StockAlert::ALERT_TYPE_OVERSTOCK
        ]);

        $this->assertEquals(5, $outOfStockAlert->priority);
        $this->assertEquals(3, $lowStockAlert->priority);
        $this->assertEquals(1, $overstockAlert->priority);
    }

    /** @test */
    public function age_in_hours_accessor_calculates_correctly()
    {
        $material = Material::factory()->create();

        $alert = StockAlert::factory()->create([
            'material_id' => $material->id,
            'created_at' => now()->subHours(5)
        ]);

        $this->assertEquals(5, $alert->age_in_hours);
    }

    /** @test */
    public function resolve_method_resolves_alert()
    {
        $material = Material::factory()->create();
        $user = User::factory()->create();

        $alert = StockAlert::factory()->create([
            'material_id' => $material->id,
            'is_resolved' => false
        ]);

        $alert->resolve($user->id);

        $this->assertTrue($alert->fresh()->is_resolved);
        $this->assertEquals($user->id, $alert->fresh()->resolved_by);
        $this->assertNotNull($alert->fresh()->resolved_at);
    }

    /** @test */
    public function unresolve_method_unresolves_alert()
    {
        $material = Material::factory()->create();
        $user = User::factory()->create();

        $alert = StockAlert::factory()->create([
            'material_id' => $material->id,
            'is_resolved' => true,
            'resolved_by' => $user->id,
            'resolved_at' => now()
        ]);

        $alert->unresolve();

        $this->assertFalse($alert->fresh()->is_resolved);
        $this->assertNull($alert->fresh()->resolved_by);
        $this->assertNull($alert->fresh()->resolved_at);
    }

    /** @test */
    public function is_expired_method_works_correctly()
    {
        $material = Material::factory()->create();

        $expiryAlert = StockAlert::factory()->create([
            'material_id' => $material->id,
            'alert_type' => StockAlert::ALERT_TYPE_EXPIRY_WARNING
        ]);

        $stockAlert = StockAlert::factory()->create([
            'material_id' => $material->id,
            'alert_type' => StockAlert::ALERT_TYPE_LOW_STOCK
        ]);

        $this->assertTrue($expiryAlert->isExpired());
        $this->assertFalse($stockAlert->isExpired());
    }

    /** @test */
    public function is_stock_related_method_works_correctly()
    {
        $material = Material::factory()->create();

        $stockAlert = StockAlert::factory()->create([
            'material_id' => $material->id,
            'alert_type' => StockAlert::ALERT_TYPE_LOW_STOCK
        ]);

        $expiryAlert = StockAlert::factory()->create([
            'material_id' => $material->id,
            'alert_type' => StockAlert::ALERT_TYPE_EXPIRY_WARNING
        ]);

        $this->assertTrue($stockAlert->isStockRelated());
        $this->assertFalse($expiryAlert->isStockRelated());
    }

    /** @test */
    public function create_low_stock_alert_creates_or_updates_alert()
    {
        $material = Material::factory()->create([
            'name' => 'Test Material',
            'quantity' => 5.0,
            'minimum_stock_level' => 10.0
        ]);

        $alert = StockAlert::createLowStockAlert($material);

        $this->assertDatabaseHas('stock_alerts', [
            'material_id' => $material->id,
            'alert_type' => StockAlert::ALERT_TYPE_LOW_STOCK,
            'threshold_value' => 10.0,
            'current_value' => 5.0,
            'is_resolved' => false
        ]);

        // Test updating existing alert
        $material->update(['quantity' => 3.0]);
        $updatedAlert = StockAlert::createLowStockAlert($material);

        $this->assertEquals($alert->id, $updatedAlert->id);
        $this->assertEquals(3.0, $updatedAlert->current_value);
    }

    /** @test */
    public function create_out_of_stock_alert_creates_or_updates_alert()
    {
        $material = Material::factory()->create([
            'name' => 'Test Material',
            'quantity' => 0.0
        ]);

        $alert = StockAlert::createOutOfStockAlert($material);

        $this->assertDatabaseHas('stock_alerts', [
            'material_id' => $material->id,
            'alert_type' => StockAlert::ALERT_TYPE_OUT_OF_STOCK,
            'threshold_value' => 0,
            'current_value' => 0.0,
            'is_resolved' => false
        ]);
    }

    /** @test */
    public function create_overstock_alert_creates_or_updates_alert()
    {
        $material = Material::factory()->create([
            'name' => 'Test Material',
            'quantity' => 150.0,
            'maximum_stock_level' => 100.0
        ]);

        $alert = StockAlert::createOverstockAlert($material);

        $this->assertDatabaseHas('stock_alerts', [
            'material_id' => $material->id,
            'alert_type' => StockAlert::ALERT_TYPE_OVERSTOCK,
            'threshold_value' => 100.0,
            'current_value' => 150.0,
            'is_resolved' => false
        ]);
    }

    /** @test */
    public function create_expiry_warning_alert_creates_or_updates_alert()
    {
        $material = Material::factory()->create(['name' => 'Test Material']);
        $expiryDate = now()->startOfDay()->addDays(5);
        $batch = StockBatch::factory()->create([
            'material_id' => $material->id,
            'batch_number' => 'TEST-001',
            'expiry_date' => $expiryDate
        ]);

        $alert = StockAlert::createExpiryWarningAlert($batch);
        $expectedDays = $expiryDate->diffInDays(now());

        $this->assertDatabaseHas('stock_alerts', [
            'material_id' => $material->id,
            'alert_type' => StockAlert::ALERT_TYPE_EXPIRY_WARNING,
            'threshold_value' => 7,
            'current_value' => $expectedDays,
            'is_resolved' => false
        ]);
    }

    /** @test */
    public function create_expiry_critical_alert_creates_or_updates_alert()
    {
        $material = Material::factory()->create(['name' => 'Test Material']);
        $expiryDate = now()->startOfDay()->addDays(1);
        $batch = StockBatch::factory()->create([
            'material_id' => $material->id,
            'batch_number' => 'TEST-001',
            'expiry_date' => $expiryDate
        ]);

        $alert = StockAlert::createExpiryCriticalAlert($batch);
        $expectedDays = $expiryDate->diffInDays(now());

        $this->assertDatabaseHas('stock_alerts', [
            'material_id' => $material->id,
            'alert_type' => StockAlert::ALERT_TYPE_EXPIRY_CRITICAL,
            'threshold_value' => 2,
            'current_value' => $expectedDays,
            'is_resolved' => false
        ]);
    }
}
