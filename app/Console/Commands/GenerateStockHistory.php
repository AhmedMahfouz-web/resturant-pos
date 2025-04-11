<?php

namespace App\Console\Commands;

use App\Models\Material;
use App\Models\MaterialStockHistory;
use Illuminate\Console\Command;
use Carbon\Carbon;

class GenerateStockHistory extends Command
{
    protected $signature = 'stock:history';
    protected $description = 'Generate monthly stock history';

    public function handle()
    {
        $lastMonth = Carbon::now()->subMonth();
        $month = $lastMonth->format('Y-m');

        Material::chunk(200, function ($materials) use ($month) {
            foreach ($materials as $material) {
                MaterialStockHistory::updateOrCreate(
                    ['period_date' => $month . '-01'],
                    [
                        'start_stock' => $material->start_month_stock,
                        'end_stock' => $material->current_stock
                    ]
                );

                $material->update([
                    'start_month_stock' => $material->current_stock,
                    'end_month_stock' => null
                ]);
            }
        });

        $this->info('Stock history generated for ' . $month);
    }
}
