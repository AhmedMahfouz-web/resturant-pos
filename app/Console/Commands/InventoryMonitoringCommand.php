<?php

namespace App\Console\Commands;

use App\Jobs\CheckExpiringBatchesJob;
use App\Jobs\CheckStockLevelsJob;
use App\Services\InventoryBroadcastService;
use Illuminate\Console\Command;

class InventoryMonitoringCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'inventory:monitor {--force : Force monitoring even if not scheduled}';

    /**
     * The console command description.
     */
    protected $description = 'Monitor inventory levels and expiring batches, generate alerts and broadcast updates';

    /**
     * Execute the console command.
     */
    public function handle(InventoryBroadcastService $broadcastService): int
    {
        $this->info('Starting inventory monitoring...');

        try {
            // Check stock levels
            $this->info('Checking stock levels...');
            CheckStockLevelsJob::dispatch();

            // Check expiring batches
            $this->info('Checking expiring batches...');
            CheckExpiringBatchesJob::dispatch();

            // Broadcast dashboard update
            $this->info('Broadcasting dashboard update...');
            $broadcastService->broadcastDashboardUpdate('scheduled_monitoring');

            $this->info('Inventory monitoring completed successfully.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Inventory monitoring failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
