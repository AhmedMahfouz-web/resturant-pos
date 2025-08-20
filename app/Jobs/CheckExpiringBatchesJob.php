<?php

namespace App\Jobs;

use App\Models\StockBatch;
use App\Models\StockAlert;
use App\Services\InventoryBroadcastService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class CheckExpiringBatchesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(InventoryBroadcastService $broadcastService): void
    {
        // Check for batches expiring within 7 days (warning)
        $expiringBatches = StockBatch::with('material')
            ->expiringWithin(7)
            ->available()
            ->get();

        foreach ($expiringBatches as $batch) {
            $daysUntilExpiry = $batch->expiry_date->diffInDays(now());

            if ($daysUntilExpiry <= 2) {
                // Critical expiry alert (2 days or less)
                StockAlert::createExpiryCriticalAlert($batch);
            } elseif ($daysUntilExpiry <= 7) {
                // Warning expiry alert (7 days or less)
                StockAlert::createExpiryWarningAlert($batch);
            }
        }

        // Group expiring batches by material for broadcasting
        $expiringByMaterial = $expiringBatches->groupBy('material_id');

        foreach ($expiringByMaterial as $materialId => $batches) {
            $material = $batches->first()->material;
            $batchData = $batches->map(function ($batch) {
                return [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'remaining_quantity' => $batch->remaining_quantity,
                    'expiry_date' => $batch->expiry_date->toDateString(),
                    'days_until_expiry' => $batch->days_until_expiry,
                    'is_critical' => $batch->days_until_expiry <= 2
                ];
            })->toArray();

            $broadcastService->broadcastBatchExpiryWarning($material, $batchData);
        }

        // Check for expired batches that haven't been marked
        $expiredBatches = StockBatch::with('material')
            ->expired()
            ->available()
            ->get();

        foreach ($expiredBatches as $batch) {
            // Create expired batch alert
            StockAlert::create([
                'material_id' => $batch->material_id,
                'alert_type' => 'expired_batch',
                'threshold_value' => 0,
                'current_value' => $batch->days_until_expiry,
                'message' => "EXPIRED: {$batch->material->name} batch {$batch->batch_number} has expired"
            ]);

            // Optionally, you might want to automatically adjust the batch to zero
            // or mark it as unusable depending on your business logic
        }

        // Broadcast dashboard update if there were any changes
        if ($expiringBatches->count() > 0 || $expiredBatches->count() > 0) {
            $broadcastService->broadcastDashboardUpdate('expiry_check');
        }
    }
}
