<?php

namespace App\Services;

use App\Models\Material;
use App\Models\StockBatch;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Recipe;
use App\Models\RecipeCostCalculation;
use App\Models\StockAlert;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportingService
{
    /**
     * Generate stock valuation report using FIFO methodology
     */
    public function generateStockValuationReport($asOfDate = null): array
    {
        $asOfDate = $asOfDate ? Carbon::parse($asOfDate) : now();

        $materials = Material::with(['stockBatches' => function ($query) use ($asOfDate) {
            $query->where('received_date', '<=', $asOfDate)
                ->where('remaining_quantity', '>', 0)
                ->orderBy('received_date', 'asc');
        }])->get();

        $valuation = [];
        $totalValue = 0;

        foreach ($materials as $material) {
            $materialValue = 0;
            $totalQuantity = 0;
            $batches = [];

            foreach ($material->stockBatches as $batch) {
                $batchValue = $batch->remaining_quantity * $batch->unit_cost;
                $materialValue += $batchValue;
                $totalQuantity += $batch->remaining_quantity;

                $batches[] = [
                    'batch_number' => $batch->batch_number,
                    'quantity' => $batch->remaining_quantity,
                    'unit_cost' => $batch->unit_cost,
                    'total_value' => $batchValue,
                    'received_date' => $batch->received_date->format('Y-m-d'),
                    'days_in_stock' => $batch->received_date->diffInDays($asOfDate)
                ];
            }

            $averageCost = $totalQuantity > 0 ? $materialValue / $totalQuantity : 0;

            $valuation[] = [
                'material_id' => $material->id,
                'material_name' => $material->name,
                'total_quantity' => $totalQuantity,
                'unit' => $material->stock_unit,
                'average_cost' => $averageCost,
                'total_value' => $materialValue,
                'batch_count' => count($batches),
                'batches' => $batches
            ];

            $totalValue += $materialValue;
        }

        return [
            'report_date' => $asOfDate->format('Y-m-d H:i:s'),
            'total_inventory_value' => $totalValue,
            'material_count' => count($valuation),
            'materials' => $valuation
        ];
    }

    /**
     * Generate inventory movement report
     */
    public function generateInventoryMovementReport($startDate, $endDate, $materialId = null): array
    {
        $startDate = Carbon::parse($startDate)->startOfDay();
        $endDate = Carbon::parse($endDate)->endOfDay();

        $query = InventoryTransaction::with(['material', 'user'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($materialId) {
            $query->where('material_id', $materialId);
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        $summary = [
            'total_receipts' => 0,
            'total_consumption' => 0,
            'total_adjustments' => 0,
            'receipt_value' => 0,
            'consumption_value' => 0,
            'adjustment_value' => 0
        ];

        $movements = [];

        foreach ($transactions as $transaction) {
            $value = $transaction->quantity * $transaction->unit_cost;

            switch ($transaction->type) {
                case 'receipt':
                    $summary['total_receipts'] += $transaction->quantity;
                    $summary['receipt_value'] += $value;
                    break;
                case 'consumption':
                    $summary['total_consumption'] += $transaction->quantity;
                    $summary['consumption_value'] += $value;
                    break;
                case 'adjustment':
                    $summary['total_adjustments'] += $transaction->quantity;
                    $summary['adjustment_value'] += $value;
                    break;
            }

            $movements[] = [
                'date' => $transaction->created_at->format('Y-m-d H:i:s'),
                'material_name' => $transaction->material->name,
                'type' => $transaction->type,
                'quantity' => $transaction->quantity,
                'unit_cost' => $transaction->unit_cost,
                'total_value' => $value,
                'reference_type' => $transaction->reference_type,
                'reference_id' => $transaction->reference_id,
                'user_name' => $transaction->user ? $transaction->user->first_name . ' ' . $transaction->user->last_name : 'System',
                'notes' => $transaction->notes
            ];
        }

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d')
            ],
            'summary' => $summary,
            'transaction_count' => count($movements),
            'movements' => $movements
        ];
    }

    /**
     * Generate stock aging report
     */
    public function generateStockAgingReport(): array
    {
        $batches = StockBatch::with(['material', 'supplier'])
            ->where('remaining_quantity', '>', 0)
            ->orderBy('received_date', 'asc')
            ->get();

        $agingCategories = [
            '0-30' => ['min' => 0, 'max' => 30, 'batches' => [], 'total_value' => 0],
            '31-60' => ['min' => 31, 'max' => 60, 'batches' => [], 'total_value' => 0],
            '61-90' => ['min' => 61, 'max' => 90, 'batches' => [], 'total_value' => 0],
            '91-180' => ['min' => 91, 'max' => 180, 'batches' => [], 'total_value' => 0],
            '180+' => ['min' => 181, 'max' => null, 'batches' => [], 'total_value' => 0]
        ];

        $totalValue = 0;

        foreach ($batches as $batch) {
            $daysInStock = $batch->received_date->diffInDays(now());
            $batchValue = $batch->remaining_quantity * $batch->unit_cost;
            $totalValue += $batchValue;

            $batchData = [
                'batch_number' => $batch->batch_number,
                'material_name' => $batch->material->name,
                'supplier_name' => $batch->supplier ? $batch->supplier->name : 'Unknown',
                'received_date' => $batch->received_date->format('Y-m-d'),
                'days_in_stock' => $daysInStock,
                'quantity' => $batch->remaining_quantity,
                'unit_cost' => $batch->unit_cost,
                'total_value' => $batchValue,
                'expiry_date' => $batch->expiry_date ? $batch->expiry_date->format('Y-m-d') : null,
                'is_expired' => $batch->is_expired,
                'is_expiring' => $batch->is_expiring
            ];

            // Categorize by age
            foreach ($agingCategories as $category => &$data) {
                if ($daysInStock >= $data['min'] && ($data['max'] === null || $daysInStock <= $data['max'])) {
                    $data['batches'][] = $batchData;
                    $data['total_value'] += $batchValue;
                    break;
                }
            }
        }

        return [
            'report_date' => now()->format('Y-m-d H:i:s'),
            'total_inventory_value' => $totalValue,
            'total_batches' => count($batches),
            'aging_categories' => $agingCategories
        ];
    }

    /**
     * Generate waste tracking report
     */
    public function generateWasteReport($startDate, $endDate): array
    {
        $startDate = Carbon::parse($startDate)->startOfDay();
        $endDate = Carbon::parse($endDate)->endOfDay();

        // Get expired batches
        $expiredBatches = StockBatch::with(['material', 'supplier'])
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now())
            ->where('remaining_quantity', '>', 0)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        // Get waste adjustments (negative adjustments)
        $wasteAdjustments = InventoryTransaction::with(['material', 'user'])
            ->where('type', 'adjustment')
            ->where('quantity', '<', 0)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $expiredWaste = [];
        $expiredValue = 0;

        foreach ($expiredBatches as $batch) {
            $wasteValue = $batch->remaining_quantity * $batch->unit_cost;
            $expiredValue += $wasteValue;

            $expiredWaste[] = [
                'batch_number' => $batch->batch_number,
                'material_name' => $batch->material->name,
                'supplier_name' => $batch->supplier ? $batch->supplier->name : 'Unknown',
                'quantity' => $batch->remaining_quantity,
                'unit_cost' => $batch->unit_cost,
                'waste_value' => $wasteValue,
                'expiry_date' => $batch->expiry_date->format('Y-m-d'),
                'days_expired' => $batch->expiry_date->diffInDays(now())
            ];
        }

        $adjustmentWaste = [];
        $adjustmentValue = 0;

        foreach ($wasteAdjustments as $adjustment) {
            $wasteValue = abs($adjustment->quantity) * $adjustment->unit_cost;
            $adjustmentValue += $wasteValue;

            $adjustmentWaste[] = [
                'date' => $adjustment->created_at->format('Y-m-d H:i:s'),
                'material_name' => $adjustment->material->name,
                'quantity' => abs($adjustment->quantity),
                'unit_cost' => $adjustment->unit_cost,
                'waste_value' => $wasteValue,
                'reason' => $adjustment->notes,
                'user_name' => $adjustment->user ? $adjustment->user->first_name . ' ' . $adjustment->user->last_name : 'System'
            ];
        }

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d')
            ],
            'summary' => [
                'total_waste_value' => $expiredValue + $adjustmentValue,
                'expired_waste_value' => $expiredValue,
                'adjustment_waste_value' => $adjustmentValue,
                'expired_items_count' => count($expiredWaste),
                'adjustment_items_count' => count($adjustmentWaste)
            ],
            'expired_waste' => $expiredWaste,
            'adjustment_waste' => $adjustmentWaste
        ];
    }

    /**
     * Generate cost analysis report
     */
    public function generateCostAnalysisReport($startDate, $endDate): array
    {
        $startDate = Carbon::parse($startDate)->startOfDay();
        $endDate = Carbon::parse($endDate)->endOfDay();

        // Get recipe cost calculations in the period
        $costCalculations = RecipeCostCalculation::with(['recipe', 'calculatedBy'])
            ->whereBetween('calculation_date', [$startDate, $endDate])
            ->orderBy('calculation_date', 'desc')
            ->get();

        $recipeAnalysis = [];
        $methodComparison = [
            'fifo' => ['count' => 0, 'total_cost' => 0, 'avg_cost' => 0],
            'purchase_price' => ['count' => 0, 'total_cost' => 0, 'avg_cost' => 0]
        ];

        foreach ($costCalculations as $calculation) {
            $recipeId = $calculation->recipe_id;

            if (!isset($recipeAnalysis[$recipeId])) {
                $recipeAnalysis[$recipeId] = [
                    'recipe_name' => $calculation->recipe->name,
                    'calculations' => [],
                    'cost_trend' => [],
                    'avg_cost' => 0,
                    'min_cost' => null,
                    'max_cost' => null,
                    'cost_variance' => 0
                ];
            }

            $recipeAnalysis[$recipeId]['calculations'][] = [
                'date' => $calculation->calculation_date->format('Y-m-d H:i:s'),
                'method' => $calculation->calculation_method,
                'total_cost' => $calculation->total_cost,
                'cost_per_serving' => $calculation->cost_per_serving,
                'calculated_by' => $calculation->calculatedBy ?
                    $calculation->calculatedBy->first_name . ' ' . $calculation->calculatedBy->last_name : 'System'
            ];

            // Update method comparison
            if (isset($methodComparison[$calculation->calculation_method])) {
                $methodComparison[$calculation->calculation_method]['count']++;
                $methodComparison[$calculation->calculation_method]['total_cost'] += $calculation->total_cost;
            }

            // Update recipe analysis
            $costs = collect($recipeAnalysis[$recipeId]['calculations'])->pluck('total_cost');
            $recipeAnalysis[$recipeId]['avg_cost'] = $costs->avg();
            $recipeAnalysis[$recipeId]['min_cost'] = $costs->min();
            $recipeAnalysis[$recipeId]['max_cost'] = $costs->max();
            $recipeAnalysis[$recipeId]['cost_variance'] = $costs->max() - $costs->min();
        }

        // Calculate averages for method comparison
        foreach ($methodComparison as $method => &$data) {
            $data['avg_cost'] = $data['count'] > 0 ? $data['total_cost'] / $data['count'] : 0;
        }

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d')
            ],
            'summary' => [
                'total_calculations' => count($costCalculations),
                'unique_recipes' => count($recipeAnalysis),
                'method_comparison' => $methodComparison
            ],
            'recipe_analysis' => array_values($recipeAnalysis)
        ];
    }

    /**
     * Generate profitability report
     */
    public function generateProfitabilityReport($startDate, $endDate): array
    {
        $startDate = Carbon::parse($startDate)->startOfDay();
        $endDate = Carbon::parse($endDate)->endOfDay();

        // Get orders in the period
        $orders = Order::with(['orderItems.product.recipes'])
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $productProfitability = [];
        $totalRevenue = 0;
        $totalCost = 0;

        foreach ($orders as $order) {
            foreach ($order->orderItems as $item) {
                $productId = $item->product_id;
                $productName = $item->product->name;
                $itemRevenue = $item->total_amount;
                $itemCost = 0;

                // Calculate item cost using recipe
                $recipe = $item->product->recipes()->first();
                if ($recipe) {
                    $itemCost = $recipe->calculateFifoCost() * $item->quantity;
                }

                $itemProfit = $itemRevenue - $itemCost;
                $profitMargin = $itemRevenue > 0 ? ($itemProfit / $itemRevenue) * 100 : 0;

                if (!isset($productProfitability[$productId])) {
                    $productProfitability[$productId] = [
                        'product_name' => $productName,
                        'total_revenue' => 0,
                        'total_cost' => 0,
                        'total_profit' => 0,
                        'units_sold' => 0,
                        'avg_selling_price' => 0,
                        'avg_cost' => 0,
                        'profit_margin' => 0
                    ];
                }

                $productProfitability[$productId]['total_revenue'] += $itemRevenue;
                $productProfitability[$productId]['total_cost'] += $itemCost;
                $productProfitability[$productId]['total_profit'] += $itemProfit;
                $productProfitability[$productId]['units_sold'] += $item->quantity;

                $totalRevenue += $itemRevenue;
                $totalCost += $itemCost;
            }
        }

        // Calculate averages and margins
        foreach ($productProfitability as &$product) {
            $product['avg_selling_price'] = $product['units_sold'] > 0 ?
                $product['total_revenue'] / $product['units_sold'] : 0;
            $product['avg_cost'] = $product['units_sold'] > 0 ?
                $product['total_cost'] / $product['units_sold'] : 0;
            $product['profit_margin'] = $product['total_revenue'] > 0 ?
                ($product['total_profit'] / $product['total_revenue']) * 100 : 0;
        }

        // Sort by profitability
        uasort($productProfitability, function ($a, $b) {
            return $b['total_profit'] <=> $a['total_profit'];
        });

        $totalProfit = $totalRevenue - $totalCost;
        $overallMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d')
            ],
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'total_profit' => $totalProfit,
                'profit_margin' => $overallMargin,
                'orders_count' => count($orders),
                'products_count' => count($productProfitability)
            ],
            'product_profitability' => array_values($productProfitability)
        ];
    }

    /**
     * Generate dashboard summary
     */
    public function generateDashboardSummary(): array
    {
        $today = now();
        $weekAgo = $today->copy()->subWeek();
        $monthAgo = $today->copy()->subMonth();

        // Current stock value
        $stockValuation = $this->generateStockValuationReport();

        // Low stock alerts
        $lowStockAlerts = StockAlert::where('alert_type', 'low_stock')
            ->where('is_resolved', false)
            ->count();

        // Expiring items (next 7 days)
        $expiringItems = StockBatch::expiringWithin(7)
            ->where('remaining_quantity', '>', 0)
            ->count();

        // Recent movements (last 7 days)
        $recentMovements = InventoryTransaction::whereBetween('created_at', [$weekAgo, $today])
            ->count();

        // Cost calculations this month
        $monthlyCostCalculations = RecipeCostCalculation::whereBetween('calculation_date', [$monthAgo, $today])
            ->count();

        return [
            'current_stock_value' => $stockValuation['total_inventory_value'],
            'material_count' => $stockValuation['material_count'],
            'low_stock_alerts' => $lowStockAlerts,
            'expiring_items' => $expiringItems,
            'recent_movements' => $recentMovements,
            'monthly_cost_calculations' => $monthlyCostCalculations,
            'last_updated' => $today->format('Y-m-d H:i:s')
        ];
    }
}
