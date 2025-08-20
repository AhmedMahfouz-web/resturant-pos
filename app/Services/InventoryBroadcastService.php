<?php

namespace App\Services;

use App\Events\InventoryUpdated;
use App\Events\StockAlertTriggered;
use App\Events\RecipeCostUpdated;
use App\Events\OrderInventoryProcessed;
use App\Events\DashboardUpdated;
use App\Models\Material;
use App\Models\StockAlert;
use App\Models\Recipe;
use App\Models\RecipeCostCalculation;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;

class InventoryBroadcastService
{
    /**
     * Broadcast inventory update for a material
     */
    public function broadcastInventoryUpdate(Material $material, string $changeType, array $changeData = []): void
    {
        // Get previous quantity from cache or change data
        $previousQuantity = $changeData['previous_quantity'] ??
            Cache::get("material_quantity_{$material->id}", $material->quantity);

        // Update cache with new quantity
        Cache::put("material_quantity_{$material->id}", $material->quantity, now()->addHours(24));

        // Broadcast the update
        broadcast(new InventoryUpdated($material, $changeType, array_merge($changeData, [
            'previous_quantity' => $previousQuantity
        ])));

        // Check if this triggers any alerts
        $this->checkAndBroadcastAlerts($material);
    }

    /**
     * Broadcast stock alert
     */
    public function broadcastStockAlert(StockAlert $alert): void
    {
        broadcast(new StockAlertTriggered($alert));
    }

    /**
     * Broadcast recipe cost update
     */
    public function broadcastRecipeCostUpdate(Recipe $recipe, RecipeCostCalculation $costCalculation): void
    {
        // Get previous cost from cache
        $previousCost = Cache::get("recipe_cost_{$recipe->id}");

        // Update cache with new cost
        Cache::put("recipe_cost_{$recipe->id}", $costCalculation->total_cost, now()->addHours(24));

        broadcast(new RecipeCostUpdated($recipe, $costCalculation, $previousCost));
    }

    /**
     * Broadcast order inventory processing completion
     */
    public function broadcastOrderInventoryProcessed(Order $order, array $consumptionData): void
    {
        broadcast(new OrderInventoryProcessed($order, $consumptionData));

        // Broadcast individual material updates for each affected material
        $materialUpdates = collect($consumptionData)
            ->pluck('materials')
            ->flatten(1)
            ->groupBy('material_id');

        foreach ($materialUpdates as $materialId => $updates) {
            $material = Material::find($materialId);
            if ($material) {
                $totalConsumed = $updates->sum('consumed_stock_quantity');
                $this->broadcastInventoryUpdate($material, 'order_consumption', [
                    'order_id' => $order->id,
                    'order_code' => $order->code,
                    'consumed_quantity' => $totalConsumed,
                    'batches_used' => $updates->pluck('batches_used')->flatten(1)->toArray()
                ]);
            }
        }
    }

    /**
     * Broadcast material receipt processing
     */
    public function broadcastMaterialReceipt(Material $material, array $receiptData): void
    {
        $this->broadcastInventoryUpdate($material, 'receipt', [
            'receipt_id' => $receiptData['receipt_id'] ?? null,
            'received_quantity' => $receiptData['quantity'] ?? 0,
            'unit_cost' => $receiptData['unit_cost'] ?? 0,
            'supplier_id' => $receiptData['supplier_id'] ?? null,
            'batch_number' => $receiptData['batch_number'] ?? null,
            'expiry_date' => $receiptData['expiry_date'] ?? null
        ]);
    }

    /**
     * Broadcast stock adjustment
     */
    public function broadcastStockAdjustment(Material $material, array $adjustmentData): void
    {
        $this->broadcastInventoryUpdate($material, 'adjustment', [
            'adjustment_quantity' => $adjustmentData['quantity'] ?? 0,
            'adjustment_reason' => $adjustmentData['reason'] ?? 'Manual adjustment',
            'adjusted_by' => $adjustmentData['user_id'] ?? null
        ]);
    }

    /**
     * Broadcast batch expiry warning
     */
    public function broadcastBatchExpiryWarning(Material $material, array $expiringBatches): void
    {
        broadcast(new InventoryUpdated($material, 'expiry_warning', [
            'expiring_batches' => $expiringBatches,
            'total_expiring_quantity' => collect($expiringBatches)->sum('remaining_quantity')
        ]));
    }

    /**
     * Check and broadcast alerts for a material
     */
    protected function checkAndBroadcastAlerts(Material $material): void
    {
        // Check for low stock
        if ($material->quantity <= $material->reorder_point) {
            $existingAlert = StockAlert::where('material_id', $material->id)
                ->where('alert_type', 'low_stock')
                ->where('is_resolved', false)
                ->first();

            if (!$existingAlert) {
                $alert = StockAlert::create([
                    'material_id' => $material->id,
                    'alert_type' => 'low_stock',
                    'current_quantity' => $material->quantity,
                    'threshold_quantity' => $material->reorder_point,
                    'message' => "Low stock alert: {$material->name} is below reorder point",
                    'severity' => $material->quantity <= ($material->reorder_point * 0.5) ? 'high' : 'medium'
                ]);

                $this->broadcastStockAlert($alert);
            }
        }

        // Check for overstock (if maximum level is set)
        if ($material->maximum_stock_level > 0 && $material->quantity > $material->maximum_stock_level) {
            $existingAlert = StockAlert::where('material_id', $material->id)
                ->where('alert_type', 'overstock')
                ->where('is_resolved', false)
                ->first();

            if (!$existingAlert) {
                $alert = StockAlert::create([
                    'material_id' => $material->id,
                    'alert_type' => 'overstock',
                    'current_quantity' => $material->quantity,
                    'threshold_quantity' => $material->maximum_stock_level,
                    'message' => "Overstock alert: {$material->name} exceeds maximum stock level",
                    'severity' => 'low'
                ]);

                $this->broadcastStockAlert($alert);
            }
        }
    }

    /**
     * Get real-time dashboard data
     */
    public function getDashboardData(): array
    {
        $reportingService = app(ReportingService::class);

        return [
            'summary' => $reportingService->generateDashboardSummary(),
            'recent_alerts' => StockAlert::with('material')
                ->where('is_resolved', false)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($alert) {
                    return [
                        'id' => $alert->id,
                        'material_name' => $alert->material->name,
                        'alert_type' => $alert->alert_type,
                        'severity' => $alert->severity,
                        'message' => $alert->message,
                        'created_at' => $alert->created_at->toISOString()
                    ];
                }),
            'low_stock_materials' => Material::whereRaw('quantity <= reorder_point')
                ->select('id', 'name', 'quantity', 'reorder_point', 'stock_unit')
                ->limit(10)
                ->get(),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Broadcast dashboard update
     */
    public function broadcastDashboardUpdate(string $updateType = 'general'): void
    {
        $dashboardData = $this->getDashboardData();
        broadcast(new DashboardUpdated($dashboardData, $updateType));
    }

    /**
     * Broadcast real-time cost update when material prices change
     */
    public function broadcastMaterialPriceUpdate(Material $material, $oldPrice, $newPrice): void
    {
        // Find all recipes that use this material
        $affectedRecipes = \App\Models\Recipe::whereHas('materials', function ($query) use ($material) {
            $query->where('material_id', $material->id);
        })->get();

        // Broadcast price change
        broadcast(new InventoryUpdated($material, 'price_updated', [
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'price_change' => $newPrice - $oldPrice,
            'affected_recipes_count' => $affectedRecipes->count()
        ]));

        // Trigger recipe cost recalculations
        foreach ($affectedRecipes as $recipe) {
            $costCalculationService = app(\App\Services\CostCalculationService::class);
            $newCostCalculation = $costCalculationService->calculateRecipeCost($recipe);
            $this->broadcastRecipeCostUpdate($recipe, $newCostCalculation);
        }

        // Update dashboard
        $this->broadcastDashboardUpdate('price_update');
    }
}
