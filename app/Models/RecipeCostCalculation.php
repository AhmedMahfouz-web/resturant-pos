<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class RecipeCostCalculation extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipe_id',
        'calculation_date',
        'total_cost',
        'cost_per_serving',
        'calculation_method',
        'cost_breakdown',
        'calculated_by',
        'calculated_at'
    ];

    protected $casts = [
        'calculation_date' => 'datetime',
        'calculated_at' => 'datetime',
        'total_cost' => 'decimal:4',
        'cost_per_serving' => 'decimal:2',
        'cost_breakdown' => 'array'
    ];

    // Calculation methods
    const METHOD_PURCHASE_PRICE = 'purchase_price';
    const METHOD_FIFO = 'fifo';
    const METHOD_AVERAGE_COST = 'average_cost';

    const CALCULATION_METHODS = [
        self::METHOD_PURCHASE_PRICE,
        self::METHOD_FIFO,
        self::METHOD_AVERAGE_COST
    ];

    // Relationships
    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function calculatedBy()
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    // Scopes
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('calculation_date', '>=', now()->subDays($days));
    }

    public function scopeByMethod($query, $method)
    {
        return $query->where('calculation_method', $method);
    }

    public function scopeForRecipe($query, $recipeId)
    {
        return $query->where('recipe_id', $recipeId);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('calculation_date', 'desc');
    }

    // Accessors & Mutators
    public function getFormattedTotalCostAttribute()
    {
        return '$' . number_format($this->total_cost, 2);
    }

    public function getFormattedCostPerServingAttribute()
    {
        return '$' . number_format($this->cost_per_serving, 2);
    }

    public function getCostBreakdownSummaryAttribute()
    {
        if (!$this->cost_breakdown) {
            return [];
        }

        return collect($this->cost_breakdown)->map(function ($item) {
            return [
                'material' => $item['material_name'] ?? 'Unknown',
                'quantity' => $item['quantity'] ?? 0,
                'unit_cost' => $item['unit_cost'] ?? 0,
                'total_cost' => $item['total_cost'] ?? 0,
                'percentage' => $this->total_cost > 0 ?
                    round(($item['total_cost'] / $this->total_cost) * 100, 2) : 0
            ];
        })->toArray();
    }

    public function getAgeInDaysAttribute()
    {
        return $this->calculation_date->diffInDays(now());
    }

    // Methods
    public function isOutdated($days = 7)
    {
        return $this->calculation_date->lt(now()->subDays($days));
    }

    public function getMostExpensiveMaterial()
    {
        if (!$this->cost_breakdown) {
            return null;
        }

        return collect($this->cost_breakdown)
            ->sortByDesc('total_cost')
            ->first();
    }

    public function getCostVariance(RecipeCostCalculation $comparison)
    {
        $variance = $this->total_cost - $comparison->total_cost;
        $percentageVariance = $comparison->total_cost > 0 ?
            ($variance / $comparison->total_cost) * 100 : 0;

        return [
            'absolute_variance' => $variance,
            'percentage_variance' => round($percentageVariance, 2),
            'is_increase' => $variance > 0,
            'comparison_date' => $comparison->calculation_date
        ];
    }

    public static function createCalculation(Recipe $recipe, array $costBreakdown, string $method = self::METHOD_FIFO, int $userId = null)
    {
        $totalCost = collect($costBreakdown)->sum('total_cost');
        $costPerServing = $recipe->serving_size > 0 ? $totalCost / $recipe->serving_size : $totalCost;

        return static::create([
            'recipe_id' => $recipe->id,
            'calculation_date' => now(),
            'total_cost' => $totalCost,
            'cost_per_serving' => $costPerServing,
            'calculation_method' => $method,
            'cost_breakdown' => $costBreakdown,
            'calculated_by' => $userId ?? auth()->id()
        ]);
    }

    public static function getLatestForRecipe($recipeId)
    {
        return static::forRecipe($recipeId)
            ->latest()
            ->first();
    }

    public static function getCostTrend($recipeId, $days = 30)
    {
        return static::forRecipe($recipeId)
            ->recent($days)
            ->latest()
            ->get()
            ->map(function ($calculation) {
                return [
                    'date' => $calculation->calculation_date->toDateString(),
                    'total_cost' => $calculation->total_cost,
                    'cost_per_serving' => $calculation->cost_per_serving,
                    'method' => $calculation->calculation_method
                ];
            });
    }

    public static function createFromRecipe(Recipe $recipe, $method = self::METHOD_FIFO, $userId = null)
    {
        $breakdown = match ($method) {
            self::METHOD_FIFO => $recipe->getFifoCostBreakdown(),
            self::METHOD_PURCHASE_PRICE => self::getPurchasePriceBreakdown($recipe),
            default => $recipe->getFifoCostBreakdown()
        };

        // Calculate cost per serving (assume 1 serving if not specified)
        $servings = 1; // Default since recipes table doesn't have serving_size
        $costPerServing = $breakdown['total_cost'] / $servings;

        $calculation = static::create([
            'recipe_id' => $recipe->id,
            'calculation_method' => $method,
            'total_cost' => $breakdown['total_cost'],
            'cost_per_serving' => $costPerServing,
            'cost_breakdown' => $breakdown,
            'calculated_by' => $userId ?? auth()->id(),
            'calculation_date' => now()
        ]);

        // Broadcast cost update
        $broadcastService = app(\App\Services\InventoryBroadcastService::class);
        $broadcastService->broadcastRecipeCostUpdate($recipe, $calculation);

        return $calculation;
    }

    public static function getPurchasePriceBreakdown(Recipe $recipe)
    {
        $breakdown = [];
        $totalCost = 0;

        foreach ($recipe->recipeMaterials as $material) {
            $requiredQuantity = $material->pivot->material_quantity;
            $materialCost = $requiredQuantity * $material->purchase_price;

            $breakdown[] = [
                'material_id' => $material->id,
                'material_name' => $material->name,
                'required_quantity' => $requiredQuantity,
                'unit' => $material->recipe_unit,
                'unit_cost' => $material->purchase_price,
                'total_cost' => $materialCost,
                'calculation_method' => 'purchase_price'
            ];

            $totalCost += $materialCost;
        }

        return [
            'total_cost' => $totalCost,
            'materials' => $breakdown,
            'calculated_at' => now()->toISOString()
        ];
    }
}
