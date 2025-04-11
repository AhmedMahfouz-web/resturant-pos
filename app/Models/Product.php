<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'category_id',
        'image',
        'discount_type',
        'discount',
        'tax'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_items')
            ->withPivot('quantity', 'price');
    }

    public function recipe()
    {
        return $this->belongsToMany(Recipe::class, 'recipe_product', 'product_id', 'recipe_id')->limit(1);
        // return $this->hasOne(Recipe::class, 'id', 'recipe_id')
        //     ->join('recipe_product', 'recipes.id', '=', 'recipe_product.recipe_id')
        //     ->where('recipe_product.product_id', '=', $this->id);
    }

    public function calculateBaseCost()
    {
        $totalCost = 0;
        foreach ($this->materials as $material) {
            $totalCost += $material->pivot->quantity * $material->unit_cost;
        }
        return $totalCost;
    }

    public function monthlyCost($month)
    {
        $total = 0;
        foreach ($this->materials as $material) {
            $history = $material->stockHistory()
                ->where('period_date', 'like', $month . '%')
                ->first();
        
            if ($history) {
                $consumption = $history->start_stock - $history->end_stock;
                $total += $consumption * $material->unit_cost;
            }
        }
        return $total;
    }

    public function calculateCostComparison()
    {
        $baseCost = $this->calculateBaseCost();
        $fifoCost = 0;

        foreach ($this->materials as $material) {
            $required = $material->pivot->quantity;
            $fifoCost += $material->calculateFIFOCost($required);
        }

        $difference = $fifoCost - $baseCost;
        $percentage = $baseCost != 0 
            ? round(($difference / $baseCost) * 100, 2) 
            : 0;

        return [
            'base_cost' => $baseCost,
            'fifo_cost' => $fifoCost,
            'cost_difference' => $difference,
            'percentage_change' => $percentage . '%'
        ];
    }

    public function monthlyCostComparison($month)
    {
        $baseCost = $this->calculateBaseCost();
        $fifoCost = 0;
        $salesCount = $this->orders()
            ->whereMonth('created_at', Carbon::parse($month)->month)
            ->sum('quantity');

        foreach ($this->materials as $material) {
            $consumption = $material->transactions()
                ->whereMonth('created_at', Carbon::parse($month)->month)
                ->where('type', 'consumption')
                ->sum('quantity');

            $fifoCost += $material->calculateFIFOCost($consumption);
        }

        return [
            'month' => $month,
            'base_cost' => $baseCost,
            'fifo_cost' => $fifoCost,
            'sales_count' => $salesCount,
            'total_cost' => $fifoCost * $salesCount
        ];
    }
}
