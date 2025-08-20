<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Models\SupplierPerformanceMetric;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CalculateSupplierPerformanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'suppliers:calculate-performance
                            {--period= : Specific period to calculate (YYYY-MM format)}
                            {--supplier= : Specific supplier ID to calculate}
                            {--force : Force recalculation even if metrics exist}';

    /**
     * The console command description.
     */
    protected $description = 'Calculate and update supplier performance metrics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting supplier performance calculation...');

        try {
            // Determine the period to calculate
            $period = $this->option('period')
                ? Carbon::createFromFormat('Y-m', $this->option('period'))->startOfMonth()
                : now()->subMonth()->startOfMonth();

            $this->info("Calculating metrics for period: {$period->format('Y-m')}");

            // Determine which suppliers to process
            $suppliersQuery = Supplier::active();

            if ($this->option('supplier')) {
                $suppliersQuery->where('id', $this->option('supplier'));
            }

            $suppliers = $suppliersQuery->get();

            if ($suppliers->isEmpty()) {
                $this->warn('No suppliers found to process.');
                return Command::SUCCESS;
            }

            $this->info("Processing {$suppliers->count()} suppliers...");

            $progressBar = $this->output->createProgressBar($suppliers->count());
            $progressBar->start();

            $updatedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            foreach ($suppliers as $supplier) {
                try {
                    // Check if metrics already exist for this period
                    $existingMetric = $supplier->performanceMetrics()
                        ->where('metric_period', $period->toDateString())
                        ->first();

                    if ($existingMetric && !$this->option('force')) {
                        $skippedCount++;
                        $progressBar->advance();
                        continue;
                    }

                    // Calculate and save metrics
                    $supplier->updatePerformanceMetrics($period);
                    $updatedCount++;
                } catch (\Exception $e) {
                    $this->error("\nError processing supplier {$supplier->name}: " . $e->getMessage());
                    $errorCount++;
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            // Display summary
            $this->info("Performance calculation completed!");
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Suppliers processed', $suppliers->count()],
                    ['Metrics updated', $updatedCount],
                    ['Metrics skipped', $skippedCount],
                    ['Errors encountered', $errorCount],
                    ['Period', $period->format('Y-m')]
                ]
            );

            // Show top and bottom performers
            if ($updatedCount > 0) {
                $this->showTopPerformers($period);
                $this->showBottomPerformers($period);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to calculate supplier performance: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Show top performing suppliers
     */
    private function showTopPerformers(Carbon $period): void
    {
        $topPerformers = SupplierPerformanceMetric::with('supplier:id,name')
            ->where('metric_period', $period->toDateString())
            ->orderBy('overall_rating', 'desc')
            ->limit(5)
            ->get();

        if ($topPerformers->isNotEmpty()) {
            $this->info("\nTop 5 Performers for {$period->format('Y-m')}:");
            $this->table(
                ['Supplier', 'Overall Rating', 'On-Time Rate', 'Quality Score'],
                $topPerformers->map(function ($metric) {
                    return [
                        $metric->supplier->name,
                        number_format($metric->overall_rating, 2),
                        number_format($metric->on_time_delivery_rate, 1) . '%',
                        number_format($metric->quality_score, 2)
                    ];
                })->toArray()
            );
        }
    }

    /**
     * Show bottom performing suppliers
     */
    private function showBottomPerformers(Carbon $period): void
    {
        $bottomPerformers = SupplierPerformanceMetric::with('supplier:id,name')
            ->where('metric_period', $period->toDateString())
            ->where('overall_rating', '<', 3.0) // Only show poor performers
            ->orderBy('overall_rating', 'asc')
            ->limit(5)
            ->get();

        if ($bottomPerformers->isNotEmpty()) {
            $this->warn("\nSuppliers Needing Attention for {$period->format('Y-m')}:");
            $this->table(
                ['Supplier', 'Overall Rating', 'On-Time Rate', 'Issues'],
                $bottomPerformers->map(function ($metric) {
                    $issues = [];
                    if ($metric->on_time_delivery_rate < 80) $issues[] = 'Late deliveries';
                    if ($metric->quality_score < 3.0) $issues[] = 'Quality issues';
                    if ($metric->communication_score < 3.0) $issues[] = 'Communication issues';

                    return [
                        $metric->supplier->name,
                        number_format($metric->overall_rating, 2),
                        number_format($metric->on_time_delivery_rate, 1) . '%',
                        implode(', ', $issues) ?: 'General performance'
                    ];
                })->toArray()
            );
        }
    }
}
