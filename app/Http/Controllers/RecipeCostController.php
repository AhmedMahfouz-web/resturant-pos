<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeCostCalculation;
use App\Services\CostCalculationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class RecipeCostController extends Controller
{
    protected $costCalculationService;

    public function __construct(CostCalculationService $costCalculationService)
    {
        $this->costCalculationService = $costCalculationService;
    }

    /**
     * Calculate cost for a specific recipe
     */
    public function calculateRecipeCost(Recipe $recipe): JsonResponse
    {
        try {
            $calculation = $this->costCalculationService->calculateRecipeCost($recipe);

            return response()->json([
                'success' => true,
                'data' => [
                    'recipe' => $recipe->load('product'),
                    'calculation' => $calculation,
                    'cost_breakdown' => $calculation->cost_breakdown_summary
                ],
                'message' => 'Recipe cost calculated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error calculating recipe cost: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cost analysis for a recipe
     */
    public function getRecipeCostAnalysis(Recipe $recipe): JsonResponse
    {
        $latestCalculation = $recipe->latestCostCalculation;
        $costTrend = $recipe->getCostTrend(30);
        $calculations = $recipe->costCalculations()
            ->with('calculatedBy:id,first_name,last_name')
            ->latest('calculation_date')
            ->limit(10)
            ->get();

        // Calculate cost variance if we have previous calculations
        $costVariance = null;
        if ($calculations->count() >= 2) {
            $current = $calculations->first();
            $previous = $calculations->skip(1)->first();
            $costVariance = $current->getCostVariance($previous);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'recipe' => $recipe->load('product'),
                'latest_calculation' => $latestCalculation,
                'cost_trend' => $costTrend,
                'recent_calculations' => $calculations,
                'cost_variance' => $costVariance,
                'needs_recalculation' => !$recipe->hasRecentCostCalculation(7)
            ],
            'message' => 'Recipe cost analysis retrieved successfully'
        ]);
    }

    /**
     * Get cost calculations history for a recipe
     */
    public function getRecipeCostHistory(Recipe $recipe, Request $request): JsonResponse
    {
        $query = $recipe->costCalculations()
            ->with('calculatedBy:id,first_name,last_name')
            ->latest('calculation_date');

        // Apply filters
        if ($request->has('method')) {
            $query->byMethod($request->input('method'));
        }

        if ($request->has('days')) {
            $query->recent($request->input('days'));
        }

        $perPage = $request->input('per_page', 15);
        $calculations = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $calculations,
            'message' => 'Recipe cost history retrieved successfully'
        ]);
    }

    /**
     * Compare recipe costs
     */
    public function compareRecipeCosts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipe_ids' => 'required|array|min:2',
            'recipe_ids.*' => 'exists:recipes,id'
        ]);

        $recipes = Recipe::with(['latestCostCalculation', 'product'])
            ->whereIn('id', $validated['recipe_ids'])
            ->get();

        $comparison = $recipes->map(function ($recipe) {
            $calculation = $recipe->latestCostCalculation;
            return [
                'recipe_id' => $recipe->id,
                'recipe_name' => $recipe->name,
                'product_name' => $recipe->product->name ?? 'No Product',
                'cost_per_serving' => $calculation ? $calculation->cost_per_serving : 0,
                'total_cost' => $calculation ? $calculation->total_cost : 0,
                'serving_size' => $recipe->serving_size ?? 1,
                'calculation_date' => $calculation ? $calculation->calculation_date : null,
                'most_expensive_material' => $calculation ? $calculation->getMostExpensiveMaterial() : null
            ];
        });

        // Calculate comparison statistics
        $costs = $comparison->pluck('cost_per_serving')->filter();
        $statistics = [
            'highest_cost' => $costs->max(),
            'lowest_cost' => $costs->min(),
            'average_cost' => $costs->avg(),
            'cost_range' => $costs->max() - $costs->min()
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'recipes' => $comparison,
                'statistics' => $statistics
            ],
            'message' => 'Recipe costs compared successfully'
        ]);
    }

    /**
     * Calculate theoretical vs actual cost for a product
     */
    public function getTheoreticalVsActualCost(Product $product, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        try {
            $comparison = $this->costCalculationService->calculateTheoreticalVsActualCost(
                $product,
                $startDate,
                $endDate
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'product' => $product->load('recipe'),
                    'period' => [
                        'start_date' => $startDate->toDateString(),
                        'end_date' => $endDate->toDateString()
                    ],
                    'comparison' => $comparison
                ],
                'message' => 'Cost comparison calculated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error calculating cost comparison: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update all product costs
     */
    public function updateAllProductCosts(): JsonResponse
    {
        try {
            $results = $this->costCalculationService->updateProductCosts();

            $successCount = $results->where('error', null)->count();
            $errorCount = $results->where('error', '!=', null)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'updated_products' => $results,
                    'summary' => [
                        'total_products' => $results->count(),
                        'successful_updates' => $successCount,
                        'failed_updates' => $errorCount
                    ]
                ],
                'message' => "Updated costs for {$successCount} products successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating product costs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate comprehensive cost analysis report
     */
    public function generateCostAnalysisReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id'
        ]);

        $filters = [
            'start_date' => $validated['start_date'] ? Carbon::parse($validated['start_date']) : now()->subDays(30),
            'end_date' => $validated['end_date'] ? Carbon::parse($validated['end_date']) : now(),
            'product_ids' => $validated['product_ids'] ?? null
        ];

        try {
            $report = $this->costCalculationService->generateCostAnalysisReport($filters);

            return response()->json([
                'success' => true,
                'data' => $report,
                'message' => 'Cost analysis report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating cost analysis report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cost calculation statistics
     */
    public function getCostCalculationStatistics(): JsonResponse
    {
        $stats = [
            'total_calculations' => RecipeCostCalculation::count(),
            'calculations_this_month' => RecipeCostCalculation::whereMonth('calculation_date', now()->month)->count(),
            'recipes_with_calculations' => RecipeCostCalculation::distinct('recipe_id')->count(),
            'average_cost_per_serving' => RecipeCostCalculation::avg('cost_per_serving'),
            'highest_cost_recipe' => RecipeCostCalculation::with('recipe')
                ->orderBy('cost_per_serving', 'desc')
                ->first(),
            'lowest_cost_recipe' => RecipeCostCalculation::with('recipe')
                ->orderBy('cost_per_serving', 'asc')
                ->first(),
            'calculations_by_method' => RecipeCostCalculation::selectRaw('calculation_method, COUNT(*) as count')
                ->groupBy('calculation_method')
                ->pluck('count', 'calculation_method'),
            'outdated_calculations' => RecipeCostCalculation::whereDate('calculation_date', '<', now()->subDays(7))
                ->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'Cost calculation statistics retrieved successfully'
        ]);
    }
}
