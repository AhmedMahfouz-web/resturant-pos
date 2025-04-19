<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\MaterialStockHistory;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'quantity',
        'purchase_price',
        'start_month_stock',
        'end_month_stock',
        'stock_unit',
        'recipe_unit',
        'conversion_rate'
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'recipe_material')
            ->withPivot('quantity');
    }

    public function transactions()
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function getLatestPriceAttribute()
    {
        return $this->adjustments()->latest()->value('unit_price') ?? $this->purchase_price;
    }

    public function stockHistory()
    {
        return $this->hasMany(MaterialStockHistory::class);
    }

    public function calculateFIFOCost($quantity)
    {
        $transactions = $this->transactions()
            ->where('type', 'receipt')
            ->orderBy('created_at')
            ->get();

        $totalCost = 0;
        $remaining = $quantity;

        foreach ($transactions as $transaction) {
            if ($remaining <= 0) break;

            $used = min($transaction->remaining_quantity, $remaining);
            $totalCost += $used * $transaction->unit_cost;
            $remaining -= $used;
        }

        return $totalCost;
    }
}
