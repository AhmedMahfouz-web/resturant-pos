<?php

namespace App\Console\Commands;

use App\Models\StockBatch;
use App\Services\InventoryBroadcastService;
use Illuminate\Console\Command;

class CheckExpiringBatches extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'inventory:check-expiring-batches {--days=7 : Number of days to check for expiring batches}';

    /**
     * The console command description.
     */
    protected $description = 'Check for expiring stock batches and broadcast warnings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        $broadcastService = app(InventoryBroadcastService::class);

        $this->info("Checking for batches expiring within {$days} days...");

        // Get expiring batches grouped by material
        $expiringBatches = StockBatch::with('material')
            ->expiringWithin($days)
            ->where('remaining_quantity', '>', 0)
            ->get()
            ->groupBy('material_id');

        $totalBatches = 0;
        $totalMaterials = 0;

        foreach ($expiringBatches as $materialId => $batches) {
            $material = $batches->first()->material;
            $totalMaterials++;
            $totalBatches += $batches->count();

            $expiringBatchData = $batches->map(function ($batch) {
                return [
                    'batch_number' => $batch->batch_number,
                    'remaining_quantity' => $batch->remaining_quantity,
                    'expiry_date' => $batch->expiry_date->format('Y-m-d'),
                    'days_until_expiry' => $batch->expiry_date->diffInDays(now()),
                    'unit_cost' => $batch->unit_cost,
                    'total_value' => $batch->remaining_quantity * $batch->unit_cost
                ];
            })->toArray();

            // Broadcast expiry warning
            $broadcastService->broadcastBatchExpiryWarning($material, $expiringBatchData);

            $this->line("Material: {$material->name} - {$batches->count()} expiring batches");
        }

        $this->info("Found {$totalBatches} expiring batches across {$totalMaterials} materials");

        // Also check for already expired batches
        $expiredBatches = StockBatch::with('material')
            ->expired()
            ->where('remaining_quantity', '>', 0)
            ->get();

        if ($expiredBatches->count() > 0) {
            $this->warn("Found {$expiredBatches->count()} expired batches with remaining stock:");

            foreach ($expiredBatches as $batch) {
                $this->line("- {$batch->material->name}: {$batch->batch_number} (expired {$batch->expiry_date->diffForHumans()})");
            }
        }

        return Command::SUCCESS;
    }
}
