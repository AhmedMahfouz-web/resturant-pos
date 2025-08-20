<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\StockBatch;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'instructions'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function materials()
    {
        return $this->belongsToMany(Material::class, 'material_recipe', 'recipe_id', 'material_id')
            ->withPivot('material_quantity');
    }

    public function recipeMaterials()
    {
        return $this->belongsToMany(Material::class, 'material_recipe', 'recipe_id', 'material_id')
            ->withPivot('material_quantity')
            ->withTimestamps();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Calculate the total cost of this recipe based on current material prices
     */
    public function calculateCost()
    {
        return $this->recipeMaterials->sum(function ($material) {
            return $material->pivot->material_quantity * $material->purchase_price;
        });
    }

    /**
     * Calculate the total cost using FIFO methodology
     */
    public function calculateFifoCost()
    {
        $totalCost = 0;

        foreach ($this->recipeMaterials as $material) {
            $requiredQuantity = $material->pivot->material_quantity;

            try {
                // Convert recipe quantity to stock unit if needed
                $stockQuantity = $requiredQuantity * $material->conversion_rate;

                // Calculate FIFO cost for this material
                $materialCost = StockBatch::calculateFifoCost($material->id, $stockQuantity);
                $totalCost += $materialCost;
            } catch (\Exception $e) {
                // If FIFO calculation fails, fall back to purchase price
                $totalCost += $requiredQuantity * $material->purchase_price;
            }
        }

        return $totalCost;
    }

    /**
     * Get detailed cost breakdown using FIFO methodology
     */
    public function getFifoCostBreakdown()
    {
        $breakdown = [];
        $totalCost = 0;

        foreach ($this->recipeMaterials as $material) {
            $requiredQuantity = $material->pivot->material_quantity;
            $stockQuantity = $requiredQuantity * $material->conversion_rate;

            try {
                $materialCost = StockBatch::calculateFifoCost($material->id, $stockQuantity);
                $unitCost = $materialCost / $requiredQuantity;

                $breakdown[] = [
                    'material_id' => $material->id,
                    'material_name' => $material->name,
                    'required_quantity' => $requiredQuantity,
                    'unit' => $material->recipe_unit,
                    'stock_quantity' => $stockQuantity,
                    'stock_unit' => $material->stock_unit,
                    'unit_cost' => $unitCost,
                    'total_cost' => $materialCost,
                    'calculation_method' => 'fifo'
                ];

                $totalCost += $materialCost;
            } catch (\Exception $e) {
                // Fall back to purchase price if FIFO fails
                $fallbackCost = $requiredQuantity * $material->purchase_price;

                $breakdown[] = [
                    'material_id' => $material->id,
                    'material_name' => $material->name,
                    'required_quantity' => $requiredQuantity,
                    'unit' => $material->recipe_unit,
                    'stock_quantity' => $stockQuantity,
                    'stock_unit' => $material->stock_unit,
                    'unit_cost' => $material->purchase_price,
                    'total_cost' => $fallbackCost,
                    'calculation_method' => 'purchase_price',
                    'error' => $e->getMessage()
                ];

                $totalCost += $fallbackCost;
            }
        }

        return [
            'total_cost' => $totalCost,
            'materials' => $breakdown,
            'calculated_at' => now()->toISOString()
        ];
    }

    /**
     * Check if recipe can be prepared with current stock
     */
    public function canBePrepared()
    {
        foreach ($this->recipeMaterials as $material) {
            $requiredQuantity = $material->pivot->material_quantity * $material->conversion_rate;

            if ($material->quantity < $requiredQuantity) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get materials that are insufficient for this recipe
     */
    public function getInsufficientMaterials()
    {
        $insufficient = [];

        foreach ($this->recipeMaterials as $material) {
            $requiredQuantity = $material->pivot->material_quantity * $material->conversion_rate;

            if ($material->quantity < $requiredQuantity) {
                $insufficient[] = [
                    'material_id' => $material->id,
                    'material_name' => $material->name,
                    'required_quantity' => $material->pivot->material_quantity,
                    'required_stock_quantity' => $requiredQuantity,
                    'available_quantity' => $material->quantity,
                    'shortage' => $requiredQuantity - $material->quantity,
                    'unit' => $material->stock_unit
                ];
            }
        }

        return $insufficient;
    }

    /**
     * Get the cost calculations for this recipe
     */
    public function costCalculations()
    {
        return $this->hasMany(RecipeCostCalculation::class);
    }

    /**
     * Get the latest cost calculation for this recipe
     */
    public function latestCostCalculation()
    {
        return $this->hasOne(RecipeCostCalculation::class)->latest('calculation_date');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByVersion($query, $version)
    {
        return $query->where('version', $version);
    }

    // Accessors & Mutators
    public function getTotalTimeAttribute()
    {
        return ($this->preparation_time ?? 0) + ($this->cooking_time ?? 0);
    }

    public function getFormattedCostPerServingAttribute()
    {
        return '$' . number_format($this->cost_per_serving ?? 0, 2);
    }

    // Methods
    public function calculateCurrentCost()
    {
        $costCalculationService = app(\App\Services\CostCalculationService::class);
        return $costCalculationService->calculateRecipeCost($this);
    }

    public function updateCost()
    {
        $calculation = $this->calculateCurrentCost();
        $this->update(['cost_per_serving' => $calculation->cost_per_serving]);
        return $calculation;
    }

    public function getCostTrend($days = 30)
    {
        return RecipeCostCalculation::getCostTrend($this->id, $days);
    }

    public function hasRecentCostCalculation($days = 7)
    {
        return $this->costCalculations()
            ->where('calculation_date', '>=', now()->subDays($days))
            ->exists();
    }
}
