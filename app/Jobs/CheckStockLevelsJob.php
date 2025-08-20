<?php

namespace App\Jobs;

use App\Models\Material;
use App\Models\StockAlert;
use App\Services\InventoryBroadcastService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckStockLevelsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(InventoryBroadcastService $broadcastService): void
    {
        $materials = Material::where('quantity', '>', 0)
            ->orWhere('reorder_point', '>', 0)
            ->orWhere('minimum_stock_level', '>', 0)
            ->orWhere('maximum_stock_level', '>', 0)
            ->get();

        $alertsGenerated = 0;

        foreach ($materials as $material) {
            // Check for out of stock
            if ($material->quantity <= 0) {
                $existingAlert = StockAlert::where('material_id', $material->id)
                    ->where('alert_type', StockAlert::ALERT_TYPE_OUT_OF_STOCK)
                    ->unresolved()
                    ->first();

                if (!$existingAlert) {
                    StockAlert::createOutOfStockAlert($material);
                    $alertsGenerated++;
                }
            }
            // Check for low stock
            elseif ($material->minimum_stock_level > 0 && $material->quantity <= $material->minimum_stock_level) {
                $existingAlert = StockAlert::where('material_id', $material->id)
                    ->where('alert_type', StockAlert::ALERT_TYPE_LOW_STOCK)
                    ->unresolved()
                    ->first();

                if (!$existingAlert) {
                    StockAlert::createLowStockAlert($material);
                    $alertsGenerated++;
                }
            }
            // Check for overstock
            elseif ($material->maximum_stock_level > 0 && $material->quantity > $material->maximum_stock_level) {
                $existingAlert = StockAlert::where('material_id', $material->id)
                    ->where('alert_type', StockAlert::ALERT_TYPE_OVERSTOCK)
                    ->unresolved()
                    ->first();

                if (!$existingAlert) {
                    StockAlert::createOverstockAlert($material);
                    $alertsGenerated++;
                }
            }
            // Resolve alerts if stock levels are back to normal
            else {
                // Resolve low stock and out of stock alerts if quantity is above minimum
                if ($material->minimum_stock_level > 0 && $material->quantity > $material->minimum_stock_level) {
                    StockAlert::where('material_id', $material->id)
                        ->whereIn('alert_type', [StockAlert::ALERT_TYPE_LOW_STOCK, StockAlert::ALERT_TYPE_OUT_OF_STOCK])
                        ->unresolved()
                        ->update([
                            'is_resolved' => true,
                            'resolved_at' => now(),
                            'resolved_by' => null // System resolved
                        ]);
                }

                // Resolve overstock alerts if quantity is below maximum
                if ($material->maximum_stock_level > 0 && $material->quantity <= $material->maximum_stock_level) {
                    StockAlert::where('material_id', $material->id)
                        ->where('alert_type', StockAlert::ALERT_TYPE_OVERSTOCK)
                        ->unresolved()
                        ->update([
                            'is_resolved' => true,
                            'resolved_at' => now(),
                            'resolved_by' => null // System resolved
                        ]);
                }
            }
        }

        // Broadcast dashboard update if alerts were generated
        if ($alertsGenerated > 0) {
            $broadcastService->broadcastDashboardUpdate('stock_level_check');
        }
    }
}
