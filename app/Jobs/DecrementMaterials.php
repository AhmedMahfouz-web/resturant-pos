<?php

namespace App\Jobs;

use App\Http\Controllers\MaterialController;
use App\Models\Material;
use App\Models\Order;
use App\Models\Product;
use App\Notifications\LowStockNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DecrementMaterials implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;
    /**
     * Create a new job instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $material = app(MaterialController::class);
        foreach ($this->order->orderItems as $item) {
            $product = $item->product;
            // Retrieve the recipe for the product
            $recipe = $product->recipe;

            // Loop through the materials in the recipe and decrement the quantities
            foreach ($recipe->materials as $material) {
                $materialUsed = $recipe->materials->find($material->id)->pivot->material_quantity;
                $totalMaterialUsed = $materialUsed * $item->quantity; // Multiply by the number of products ordered

                // Decrement the material's quantity
                $material->decrement('quantity', $totalMaterialUsed);

                $remainingProductCount = floor($material->quantity / $material->pivot->quantity_used);
                if ($remainingProductCount < 3) {
                    // Trigger alert if material stock is too low
                    $this->triggerLowStockAlert($item->product, $material);
                }
            }
        }
    }

    protected function triggerLowStockAlert(Product $product, Material $material)
    {
        // Logic to send alert (Notification, Event, etc.)
        $user = auth()->user();
        if ($user) {
            $user->notify(new LowStockNotification($product, $material));
        }
    }
}
