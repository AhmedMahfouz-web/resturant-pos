<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\MaterialStockHistory;
use App\Services\InventoryBroadcastService;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'quantity',
        'purchase_price',
        'unit',
        'start_month_stock',
        'end_month_stock',
        'stock_unit',
        'recipe_unit',
        'conversion_rate',
        'minimum_stock_level',
        'maximum_stock_level',
        'reorder_point',
        'reorder_quantity',
        'default_supplier_id',
        'storage_location',
        'shelf_life_days',
        'is_perishable',
        'barcode',
        'sku',
        'category_id'
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'purchase_price' => 'decimal:2',
        'minimum_stock_level' => 'decimal:3',
        'maximum_stock_level' => 'decimal:3',
        'reorder_point' => 'decimal:3',
        'reorder_quantity' => 'decimal:3',
        'is_perishable' => 'boolean',
        'shelf_life_days' => 'integer'
    ];

    protected static function booted()
    {
        static::updated(function ($material) {
            $broadcastService = app(InventoryBroadcastService::class);

            // Check if quantity was changed
            if ($material->isDirty('quantity')) {
                $broadcastService->broadcastInventoryUpdate(
                    $material,
                    'quantity_updated',
                    [
                        'previous_quantity' => $material->getOriginal('quantity'),
                        'change_amount' => $material->quantity - $material->getOriginal('quantity')
                    ]
                );
            }

            // Check if purchase price was changed
            if ($material->isDirty('purchase_price')) {
                $oldPrice = $material->getOriginal('purchase_price');
                $newPrice = $material->purchase_price;

                $broadcastService->broadcastMaterialPriceUpdate($material, $oldPrice, $newPrice);
            }
        });
    }

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

    public function materialReceipts()
    {
        return $this->hasMany(MaterialReceipt::class);
    }

    /**
     * Get the default supplier for this material
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'default_supplier_id');
    }

    /**
     * Get the stock alerts for this material
     */
    public function stockAlerts()
    {
        return $this->hasMany(StockAlert::class);
    }

    /**
     * Get the stock batches for this material
     */
    public function stockBatches()
    {
        return $this->hasMany(StockBatch::class);
    }

    /**
     * Get available stock batches in FIFO order
     */
    public function availableStockBatches()
    {
        return $this->stockBatches()
            ->available()
            ->fifoOrder();
    }

    /**
     * Get the category for this material
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Check if material is below minimum stock level
     */
    public function isBelowMinimumStock()
    {
        return $this->quantity < $this->minimum_stock_level;
    }

    /**
     * Check if material is at reorder point
     */
    public function isAtReorderPoint()
    {
        return $this->quantity <= $this->reorder_point;
    }

    /**
     * Check if material is above maximum stock level
     */
    public function isAboveMaximumStock()
    {
        return $this->maximum_stock_level > 0 && $this->quantity > $this->maximum_stock_level;
    }

    public function calculateFIFOCost($quantity)
    {
        return StockBatch::calculateFifoCost($this->id, $quantity);
    }

    /**
     * Consume stock using FIFO method
     */
    public function consumeStock($quantity, $reason = 'consumption')
    {
        $consumptionResult = StockBatch::consumeForMaterial($this->id, $quantity);

        // Update material quantity
        $this->decrement('quantity', $quantity);

        // Create inventory transaction
        InventoryTransaction::create([
            'material_id' => $this->id,
            'type' => 'consumption',
            'quantity' => -$quantity,
            'unit_cost' => $consumptionResult['total_cost'] / $quantity,
            'user_id' => auth()->id() ?? 1,
            'notes' => $reason
        ]);

        return $consumptionResult;
    }

    /**
     * Get current stock value using FIFO
     */
    public function getCurrentStockValue()
    {
        return $this->availableStockBatches()
            ->get()
            ->sum('total_value');
    }
}
