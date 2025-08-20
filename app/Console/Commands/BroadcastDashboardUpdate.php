<?php

namespace App\Console\Commands;

use App\Services\InventoryBroadcastService;
use Illuminate\Console\Command;

class BroadcastDashboardUpdate extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'inventory:broadcast-dashboard';

    /**
     * The console command description.
     */
    protected $description = 'Broadcast real-time dashboard updates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $broadcastService = app(InventoryBroadcastService::class);

        try {
            $this->info('Broadcasting dashboard update...');

            $broadcastService->broadcastDashboardUpdate();

            $this->info('Dashboard update broadcasted successfully');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error broadcasting dashboard update: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
