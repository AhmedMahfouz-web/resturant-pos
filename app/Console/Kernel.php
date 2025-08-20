<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('stock:history')
            ->monthlyOn(1, '00:00');

        // Check for expiring batches every day at 8 AM
        $schedule->command('inventory:check-expiring-batches')
            ->dailyAt('08:00');

        // Broadcast dashboard updates every 5 minutes during business hours
        $schedule->command('inventory:broadcast-dashboard')
            ->everyFiveMinutes()
            ->between('08:00', '22:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
