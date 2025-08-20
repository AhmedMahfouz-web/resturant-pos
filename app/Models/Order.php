<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\StockBatch;
use App\Models\InventoryTransaction;
use App\Models\RecipeCostCalculation;
use App\Models\StockAlert;
use App\Models\Material;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_id',
        'user_id',
        'shift_id',
        'status',
        'code',
        'tax',
        'service',
        'discount_value', // Calculated discount value
        'discount_type', // {percentage, cash, saved}
        'discount_id', // Discount id form Discount model
        'discount', // Discount value without orderItems's discounts
        'sub_total',
        'total_amount',
        'close_at',
        'type',
        'reason',
        'manual_reason',
    ];

    protected static function booted()
    {
        static::updating(function ($order) {
            // Only process inventory consumption when order status changes to completed
            if ($order->isDirty('status') && $order->status === 'completed') {
                $order->processInventoryConsumption();
            }
        });

        static::updated(function ($order) {
            // Trigger cost calculations and stock alerts after order completion
            if ($order->status === 'completed') {
                $order->triggerPostCompletionTasks();
            }
        });
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function totalQuantity()
    {
        return $this->items()->sum('quantity');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'order_items')
            ->withPivot('quantity', 'price');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function discountSaved()
    {
        return $this->hasOne(Discount::class);
    }

    /**
     * Process inventory consumption using FIFO batch logic
     */
    public function processInventoryConsumption()
    {
        $consumptionLog = [];
        $errors = [];

        foreach ($this->orderItems as $item) {
            try {
                $itemConsumption = $this->processOrderItemConsumption($item);
                $consumptionLog[] = $itemConsumption;
            } catch (\Exception $e) {
                $errors[] = [
                    'order_item_id' => $item->id,
                    'product_name' => $item->product->name,
                    'error' => $e->getMessage()
                ];
            }
        }

        // Log the consumption summary
        $this->logInventoryConsumption($consumptionLog, $errors);

        // Broadcast inventory processing completion
        $broadcastService = app(\App\Services\InventoryBroadcastService::class);
        $broadcastService->broadcastOrderInventoryProcessed($this, $consumptionLog);

        if (!empty($errors)) {
            throw new \Exception('Some items could not be processed: ' . json_encode($errors));
        }

        return $consumptionLog;
    }

    /**
     * Process consumption for a single order item
     */
    protected function processOrderItemConsumption(OrderItem $item)
    {
        $product = $item->product;
        $itemQuantity = $item->quantity;
        $materialConsumptions = [];

        // Get the recipe for this product
        $recipe = $product->recipe()->first();

        if (!$recipe) {
            // If no recipe, skip inventory consumption
            return [
                'order_item_id' => $item->id,
                'product_name' => $product->name,
                'quantity' => $itemQuantity,
                'materials' => [],
                'message' => 'No recipe found - no inventory consumption'
            ];
        }

        // Process each material in the recipe
        foreach ($recipe->recipeMaterials as $material) {
            $requiredQuantity = $material->pivot->material_quantity * $itemQuantity;

            // Convert to stock units if needed
            $stockQuantity = $requiredQuantity * ($material->conversion_rate ?? 1);

            try {
                // Consume using FIFO batch logic
                $consumption = StockBatch::consumeForMaterial($material->id, $stockQuantity);

                // Create inventory transaction record
                $transaction = InventoryTransaction::create([
                    'material_id' => $material->id,
                    'type' => 'consumption',
                    'quantity' => $stockQuantity,
                    'unit_cost' => $consumption['total_cost'] / $stockQuantity, // Average cost from FIFO
                    'user_id' => auth()->id() ?? $this->user_id,
                    'reference_type' => OrderItem::class,
                    'reference_id' => $item->id,
                    'notes' => "Order #{$this->code} - {$product->name} (Qty: {$itemQuantity})"
                ]);

                // Update material stock quantity
                $material->decrement('quantity', $stockQuantity);

                $materialConsumptions[] = [
                    'material_id' => $material->id,
                    'material_name' => $material->name,
                    'required_recipe_quantity' => $requiredQuantity,
                    'consumed_stock_quantity' => $stockQuantity,
                    'unit' => $material->stock_unit,
                    'total_cost' => $consumption['total_cost'],
                    'batches_used' => $consumption['batches'],
                    'transaction_id' => $transaction->id
                ];
            } catch (\Exception $e) {
                throw new \Exception("Failed to consume {$material->name}: " . $e->getMessage());
            }
        }

        return [
            'order_item_id' => $item->id,
            'product_name' => $product->name,
            'recipe_name' => $recipe->name,
            'quantity' => $itemQuantity,
            'materials' => $materialConsumptions,
            'total_cost' => collect($materialConsumptions)->sum('total_cost')
        ];
    }

    /**
     * Log inventory consumption details
     */
    protected function logInventoryConsumption($consumptionLog, $errors = [])
    {
        $logData = [
            'order_id' => $this->id,
            'order_code' => $this->code,
            'processed_at' => now(),
            'consumption_details' => $consumptionLog,
            'errors' => $errors,
            'total_items' => count($consumptionLog),
            'total_cost' => collect($consumptionLog)->sum('total_cost')
        ];

        // You could store this in a dedicated log table or file
        \Log::info('Order Inventory Consumption', $logData);
    }

    /**
     * Trigger post-completion tasks like cost calculations and stock alerts
     */
    public function triggerPostCompletionTasks()
    {
        try {
            // Update recipe costs for products in this order
            $this->updateRecipeCosts();

            // Generate stock alerts for low inventory
            $this->generateStockAlerts();
        } catch (\Exception $e) {
            \Log::error('Post-completion tasks failed for order ' . $this->code, [
                'error' => $e->getMessage(),
                'order_id' => $this->id
            ]);
        }
    }

    /**
     * Update recipe costs for products in this order
     */
    protected function updateRecipeCosts()
    {
        $updatedRecipes = [];

        foreach ($this->orderItems as $item) {
            $recipe = $item->product->recipe()->first();

            if ($recipe && !in_array($recipe->id, $updatedRecipes)) {
                try {
                    // Create a new cost calculation using FIFO method
                    RecipeCostCalculation::createFromRecipe(
                        $recipe,
                        RecipeCostCalculation::METHOD_FIFO,
                        auth()->id() ?? $this->user_id
                    );

                    $updatedRecipes[] = $recipe->id;
                } catch (\Exception $e) {
                    \Log::warning("Failed to update cost for recipe {$recipe->name}", [
                        'recipe_id' => $recipe->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Generate stock alerts for materials that are now low
     */
    protected function generateStockAlerts()
    {
        $materialIds = [];

        // Collect all materials used in this order
        foreach ($this->orderItems as $item) {
            $recipe = $item->product->recipe()->first();
            if ($recipe) {
                foreach ($recipe->recipeMaterials as $material) {
                    $materialIds[] = $material->id;
                }
            }
        }

        // Generate alerts for unique materials
        $uniqueMaterialIds = array_unique($materialIds);

        foreach ($uniqueMaterialIds as $materialId) {
            try {
                $material = Material::find($materialId);
                if ($material && $material->quantity <= $material->reorder_point) {
                    StockAlert::create([
                        'material_id' => $materialId,
                        'alert_type' => 'low_stock',
                        'current_quantity' => $material->quantity,
                        'threshold_quantity' => $material->reorder_point,
                        'message' => "Low stock alert triggered by order #{$this->code}",
                        'severity' => $material->quantity <= ($material->reorder_point * 0.5) ? 'high' : 'medium'
                    ]);
                }
            } catch (\Exception $e) {
                \Log::warning("Failed to generate stock alert for material {$materialId}", [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Get inventory consumption details for this order
     */
    public function getInventoryConsumption()
    {
        return InventoryTransaction::where('reference_type', OrderItem::class)
            ->whereIn('reference_id', $this->orderItems->pluck('id'))
            ->with(['material', 'user'])
            ->get()
            ->groupBy('reference_id')
            ->map(function ($transactions, $orderItemId) {
                $orderItem = $this->orderItems->find($orderItemId);
                return [
                    'order_item' => $orderItem,
                    'transactions' => $transactions,
                    'total_cost' => $transactions->sum(function ($t) {
                        return $t->quantity * $t->unit_cost;
                    })
                ];
            });
    }

    /**
     * Check if order can be completed (sufficient inventory)
     */
    public function canBeCompleted()
    {
        foreach ($this->orderItems as $item) {
            $recipe = $item->product->recipe()->first();

            if ($recipe) {
                $insufficient = $recipe->getInsufficientMaterials();
                if (!empty($insufficient)) {
                    return [
                        'can_complete' => false,
                        'reason' => 'Insufficient materials',
                        'insufficient_materials' => $insufficient,
                        'product' => $item->product->name
                    ];
                }
            }
        }

        return ['can_complete' => true];
    }
}
