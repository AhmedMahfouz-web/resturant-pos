<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeCostCalculation;
use App\Models\StockBatch;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CostCalculationService
{
    /**
     * Calculate recipe cost using FIFO methodology
     */
    public function calculateRecipeCost(Recipe $recipe): RecipeCostCalculation
    {
        $costBreakdown = [];
        $totalCost = 0;

        // Get all materials for this recipe
        $recipeMaterials = $recipe->recipeMaterials()->with('material')->get();

        foreach ($recipeMaterials as $recipeMaterial) {
            $material = $recipeMaterial->material;
            $quantityNeeded = $recipeMaterial->pivot->quantity;

            try {
                // Calculate FIFO cost for this material
                $materialCost = $this->calculateMaterialCostFIFO($material, $quantityNeeded);

                $costBreakdown[] = [
                    'material_id' => $material->id,
                    'material_name' => $material->name,
                    'quantity' => $quantityNeeded,
                    'unit' => $material->recipe_unit,
                    'unit_cost' => $quantityNeeded > 0 ? $materialCost / $quantityNeeded : 0,
                    'total_cost' => $materialCost,
                    'calculation_method' => 'fifo'
                ];

                $totalCost += $materialCost;
            } catch (\Exception $e) {
                // If FIFO calculation fails, use average cost
                $averageCost = $this->calculateMaterialCostAverage($material, $quantityNeeded);

                $costBreakdown[] = [
                    'material_id' => $material->id,
                    'material_name' => $material->name,
                    'quantity' => $quantityNeeded,
                    'unit' => $material->recipe_unit,
                    'unit_cost' => $quantityNeeded > 0 ? $averageCost / $quantityNeeded : 0,
                    'total_cost' => $averageCost,
                    'calculation_method' => 'average',
                    'note' => 'FIFO calculation failed, using average cost'
                ];

                $totalCost += $averageCost;
            }
        }

        // Create and return the cost calculation record
        return RecipeCostCalculation::createCalculation($recipe, $costBreakdown);
    }

    /**
     * Calculate product cost using FIFO methodology
     */
    public function calculateProductCostFIFO(Product $product): float
    {
        $recipe = $product->recipe;

        if (!$recipe) {
            return 0;
        }

        $calculation = $this->calculateRecipeCost($recipe);
        return $calculation->cost_per_serving;
    }

    /**
     * Calculate material cost using FIFO method
     */
    protected function calculateMaterialCostFIFO(Material $material, float $quantity): float
    {
        // Convert recipe quantity to stock unit
        $stockQuantity = $quantity * $material->conversion_rate;

        return StockBatch::calculateFifoCost($material->id, $stockQuantity);
    }

    /**
     * Calculate material cost using average method
     */
    protected function calculateMaterialCostAverage(Material $material, float $quantity): float
    {
        // Convert recipe quantity to stock unit
        $stockQuantity = $quantity * $material->conversion_rate;

        // Use the current purchase price as average
        return $stockQuantity * $material->purchase_price;
    }

    /**
     * Calculate theoretical vs actual cost comparison
     */
    public function calculateTheoreticalVsActualCost(Product $product, Carbon $startDate, Carbon $endDate): array
    {
        $recipe = $product->recipe;

        if (!$recipe) {
            return [
                'theoretical_cost' => 0,
                'actual_cost' => 0,
                'variance' => 0,
                'variance_percentage' => 0,
                'units_sold' => 0
            ];
        }

        // Get units sold in the period
        $unitsSold = $this->getUnitsSoldInPeriod($product, $startDate, $endDate);

        if ($unitsSold == 0) {
            return [
                'theoretical_cost' => 0,
                'actual_cost' => 0,
                'variance' => 0,
                'variance_percentage' => 0,
                'units_sold' => 0
            ];
        }

        // Calculate theoretical cost (based on current recipe and FIFO costs)
        $currentCostCalculation = $this->calculateRecipeCost($recipe);
        $theoreticalTotalCost = $currentCostCalculation->cost_per_serving * $unitsSold;

        // Calculate actual cost (based on actual material consumption in the period)
        $actualTotalCost = $this->getActualMaterialCostInPeriod($recipe, $startDate, $endDate, $unitsSold);

        $variance = $actualTotalCost - $theoreticalTotalCost;
        $variancePercentage = $theoreticalTotalCost > 0 ? ($variance / $theoreticalTotalCost) * 100 : 0;

        return [
            'theoretical_cost' => $theoreticalTotalCost,
            'actual_cost' => $actualTotalCost,
            'variance' => $variance,
            'variance_percentage' => round($variancePercentage, 2),
            'units_sold' => $unitsSold,
            'theoretical_cost_per_unit' => $currentCostCalculation->cost_per_serving,
            'actual_cost_per_unit' => $unitsSold > 0 ? $actualTotalCost / $unitsSold : 0
        ];
    }

    /**
     * Update product costs for all products
     */
    public function updateProductCosts(): Collection
    {
        $products = Product::with('recipe.recipeMaterials.material')->get();
        $updatedProducts = collect();

        foreach ($products as $product) {
            if ($product->recipe) {
                try {
                    $calculation = $this->calculateRecipeCost($product->recipe);

                    // Update the recipe cost
                    $product->recipe->update([
                        'cost_per_serving' => $calculation->cost_per_serving
                    ]);

                    $updatedProducts->push([
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'recipe_id' => $product->recipe->id,
                        'old_cost' => $product->recipe->getOriginal('cost_per_serving'),
                        'new_cost' => $calculation->cost_per_serving,
                        'calculation_id' => $calculation->id
                    ]);
                } catch (\Exception $e) {
                    $updatedProducts->push([
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return $updatedProducts;
    }

    /**
     * Generate cost analysis report
     */
    public function generateCostAnalysisReport(array $filters = []): array
    {
        $startDate = $filters['start_date'] ?? now()->subDays(30);
        $endDate = $filters['end_date'] ?? now();
        $productIds = $filters['product_ids'] ?? null;

        $query = Product::with(['recipe.recipeMaterials.material']);

        if ($productIds) {
            $query->whereIn('id', $productIds);
        }

        $products = $query->get();
        $report = [];

        foreach ($products as $product) {
            if (!$product->recipe) {
                continue;
            }

            $costComparison = $this->calculateTheoreticalVsActualCost($product, $startDate, $endDate);
            $latestCalculation = RecipeCostCalculation::getLatestForRecipe($product->recipe->id);
            $costTrend = RecipeCostCalculation::getCostTrend($product->recipe->id, 30);

            $report[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'recipe_id' => $product->recipe->id,
                'current_cost_per_serving' => $latestCalculation ? $latestCalculation->cost_per_serving : 0,
                'cost_comparison' => $costComparison,
                'cost_trend' => $costTrend,
                'most_expensive_material' => $latestCalculation ? $latestCalculation->getMostExpensiveMaterial() : null,
                'last_calculated' => $latestCalculation ? $latestCalculation->calculation_date : null
            ];
        }

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'products' => $report,
            'summary' => $this->generateReportSummary($report)
        ];
    }

    /**
     * Get units sold in a period
     */
    protected function getUnitsSoldInPeriod(Product $product, Carbon $startDate, Carbon $endDate): int
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('order_items.product_id', $product->id)
            ->where('orders.status', 'completed')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->sum('order_items.quantity');
    }

    /**
     * Get actual material cost in a period
     */
    protected function getActualMaterialCostInPeriod(Recipe $recipe, Carbon $startDate, Carbon $endDate, int $unitsSold): float
    {
        // This is a simplified calculation
        // In a real implementation, you would track actual material consumption
        $currentCalculation = $this->calculateRecipeCost($recipe);
        return $currentCalculation->cost_per_serving * $unitsSold;
    }

    /**
     * Generate report summary
     */
    protected function generateReportSummary(array $products): array
    {
        $totalProducts = count($products);
        $totalVariance = collect($products)->sum('cost_comparison.variance');
        $averageVariancePercentage = collect($products)->avg('cost_comparison.variance_percentage');

        $highVarianceProducts = collect($products)
            ->filter(function ($product) {
                return abs($product['cost_comparison']['variance_percentage']) > 10;
            })
            ->count();

        return [
            'total_products_analyzed' => $totalProducts,
            'total_variance' => round($totalVariance, 2),
            'average_variance_percentage' => round($averageVariancePercentage, 2),
            'high_variance_products' => $highVarianceProducts,
            'products_with_cost_increase' => collect($products)
                ->filter(fn($p) => $p['cost_comparison']['variance'] > 0)
                ->count(),
            'products_with_cost_decrease' => collect($products)
                ->filter(fn($p) => $p['cost_comparison']['variance'] < 0)
                ->count()
        ];
    }
}
