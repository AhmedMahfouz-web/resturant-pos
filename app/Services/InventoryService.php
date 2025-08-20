<?php

namespace App\Services;

use App\Models\Material;
use App\Models\MaterialReceipt;
use App\Models\StockAlert;
use App\Models\StockBatch;
use App\Models\InventoryTransaction;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InventoryService
{
    /**
     * Process a material receipt and update inventory
     */
    public function processReceipt(MaterialReceipt $receipt): void
    {
        DB::transaction(function () use ($receipt) {
            $material = $receipt->material;

            // Convert received quantity to material's stock unit
            $convertedQuantity = $receipt->convertToStockUnit();

            // Update material quantity
            $material->increment('quantity', $convertedQuantity);

            // Create stock batch for FIFO tracking
            StockBatch::createFromReceipt($receipt);

            // Create inventory transaction
            InventoryTransaction::create([
                'material_id' => $receipt->material_id,
                'type' => 'receipt',
                'quantity' => $convertedQuantity,
                'unit_cost' => $receipt->unit_cost,
                'reference_type' => 'material_receipt',
                'reference_id' => $receipt->id,
                'user_id' => $receipt->received_by,
                'notes' => "Material receipt: {$receipt->receipt_code}"
            ]);

            // Check and generate stock alerts
            $this->checkStockLevelsForMaterial($material);
        });
    }

    /**
     * Process order consumption and update inventory
     */
    public function processConsumption(Order $order): void
    {
        DB::transaction(function () use ($order) {
            // Get all recipes for products in the order
            foreach ($order->orderItems as $orderItem) {
                $product = $orderItem->product;
                $recipe = $product->recipe;

                if ($recipe && $recipe->recipeMaterials) {
                    foreach ($recipe->recipeMaterials as $recipeMaterial) {
                        $material = $recipeMaterial->material;
                        $quantityNeeded = $recipeMaterial->pivot->quantity * $orderItem->quantity;

                        // Convert recipe quantity to stock unit
                        $stockQuantityNeeded = $quantityNeeded * $material->conversion_rate;

                        // Consume stock using FIFO
                        $this->consumeStock($material, $stockQuantityNeeded, "Order #{$order->id}");
                    }
                }
            }
        });
    }

    /**
     * Adjust stock levels manually
     */
    public function adjustStock(Material $material, float $quantity, string $reason, int $userId = null): void
    {
        DB::transaction(function () use ($material, $quantity, $reason, $userId) {
            $oldQuantity = $material->quantity;

            if ($quantity > 0) {
                // Stock increase - create a new batch
                $material->increment('quantity', $quantity);

                // Create a stock batch for the adjustment
                $batch = StockBatch::create([
                    'material_id' => $material->id,
                    'batch_number' => $this->generateAdjustmentBatchNumber($material),
                    'quantity' => $quantity,
                    'remaining_quantity' => $quantity,
                    'unit_cost' => $material->purchase_price, // Use current purchase price
                    'received_date' => now(),
                    'expiry_date' => null, // Adjustments don't have expiry
                    'supplier_id' => null,
                    'material_receipt_id' => null
                ]);
            } else {
                // Stock decrease - consume from existing batches
                $this->consumeStock($material, abs($quantity), $reason, $userId);
            }

            // Create inventory transaction
            InventoryTransaction::create([
                'material_id' => $material->id,
                'type' => 'adjustment',
                'quantity' => $quantity,
                'unit_cost' => $material->purchase_price,
                'user_id' => $userId ?? auth()->id(),
                'notes' => $reason
            ]);

            // Check stock levels after adjustment
            $this->checkStockLevelsForMaterial($material);
        });
    }

    /**
     * Consume stock using FIFO method
     */
    protected function consumeStock(Material $material, float $quantity, string $reason, int $userId = null): void
    {
        try {
            $consumptionResult = StockBatch::consumeForMaterial($material->id, $quantity);

            // Update material quantity
            $material->decrement('quantity', $quantity);

            // Create inventory transaction
            InventoryTransaction::create([
                'material_id' => $material->id,
                'type' => 'consumption',
                'quantity' => -$quantity,
                'unit_cost' => $consumptionResult['total_cost'] / $quantity,
                'user_id' => $userId ?? auth()->id(),
                'notes' => $reason
            ]);
        } catch (\Exception $e) {
            throw new \Exception("Insufficient stock for {$material->name}. Available: {$material->quantity}, Required: {$quantity}");
        }
    }

    /**
     * Check stock levels for all materials and generate alerts
     */
    public function checkStockLevels(): Collection
    {
        $materials = Material::with(['stockBatches' => function ($query) {
            $query->available()->fifoOrder();
        }])->get();

        $alerts = collect();

        foreach ($materials as $material) {
            $materialAlerts = $this->checkStockLevelsForMaterial($material);
            $alerts = $alerts->merge($materialAlerts);
        }

        return $alerts;
    }

    /**
     * Check stock levels for a specific material
     */
    public function checkStockLevelsForMaterial(Material $material): Collection
    {
        $alerts = collect();

        // Check stock quantity alerts
        if ($material->quantity <= 0) {
            $alerts->push(StockAlert::createOutOfStockAlert($material));
        } elseif ($material->quantity <= $material->minimum_stock_level) {
            $alerts->push(StockAlert::createLowStockAlert($material));
        } elseif ($material->maximum_stock_level > 0 && $material->quantity > $material->maximum_stock_level) {
            $alerts->push(StockAlert::createOverstockAlert($material));
        }

        // Check expiry alerts for stock batches
        $expiryAlerts = $this->checkExpiryAlerts($material);
        $alerts = $alerts->merge($expiryAlerts);

        return $alerts;
    }

    /**
     * Check expiry alerts for material batches
     */
    protected function checkExpiryAlerts(Material $material): Collection
    {
        $alerts = collect();

        // Get batches expiring within 7 days
        $expiringBatches = $material->stockBatches()
            ->available()
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays(7))
            ->where('expiry_date', '>', now())
            ->get();

        foreach ($expiringBatches as $batch) {
            $daysUntilExpiry = $batch->expiry_date->diffInDays(now());

            if ($daysUntilExpiry <= 2) {
                $alerts->push(StockAlert::createExpiryCriticalAlert($batch));
            } elseif ($daysUntilExpiry <= 7) {
                $alerts->push(StockAlert::createExpiryWarningAlert($batch));
            }
        }

        return $alerts;
    }

    /**
     * Generate stock alerts for all materials
     */
    public function generateStockAlerts(): Collection
    {
        return $this->checkStockLevels();
    }

    /**
     * Calculate total stock value using FIFO methodology
     */
    public function calculateStockValue(Material $material = null): float
    {
        if ($material) {
            return $material->getCurrentStockValue();
        }

        return Material::with('stockBatches')->get()->sum(function ($material) {
            return $material->getCurrentStockValue();
        });
    }

    /**
     * Get stock movements with filters
     */
    public function getStockMovements(array $filters = []): Collection
    {
        $query = InventoryTransaction::with(['material'])
            ->orderBy('created_at', 'desc');

        if (isset($filters['material_id'])) {
            $query->where('material_id', $filters['material_id']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        $limit = $filters['limit'] ?? 100;

        return $query->limit($limit)->get();
    }

    /**
     * Get inventory summary
     */
    public function getInventorySummary(): array
    {
        $totalMaterials = Material::count();
        $totalValue = $this->calculateStockValue();
        $lowStockCount = Material::whereColumn('quantity', '<=', 'minimum_stock_level')->count();
        $outOfStockCount = Material::where('quantity', '<=', 0)->count();
        $activeAlerts = StockAlert::unresolved()->count();
        $criticalAlerts = StockAlert::unresolved()->critical()->count();

        // Get expiring batches
        $expiringBatches = StockBatch::available()
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays(7))
            ->count();

        return [
            'total_materials' => $totalMaterials,
            'total_value' => $totalValue,
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
            'active_alerts' => $activeAlerts,
            'critical_alerts' => $criticalAlerts,
            'expiring_batches' => $expiringBatches
        ];
    }

    /**
     * Generate adjustment batch number
     */
    protected function generateAdjustmentBatchNumber(Material $material): string
    {
        $prefix = 'ADJ-' . strtoupper(substr($material->name, 0, 3)) . str_pad($material->id, 3, '0', STR_PAD_LEFT);
        $date = now()->format('Ymd');

        $lastBatch = StockBatch::where('material_id', $material->id)
            ->where('batch_number', 'like', "{$prefix}-{$date}-%")
            ->orderBy('batch_number', 'desc')
            ->first();

        $sequence = 1;
        if ($lastBatch) {
            $lastSequence = (int) substr($lastBatch->batch_number, -3);
            $sequence = $lastSequence + 1;
        }

        return $prefix . '-' . $date . '-' . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }
}
