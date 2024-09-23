<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;

class OrderItemObserver
{
    /**
     * Handle the OrderItem "created" event.
     */
    public function created(OrderItem $orderItem): void
    {
        $product = Product::where('id', $orderItem->product_id)->with('recipe', function ($query) {
            $query->with('materials');
        })->first();
        $quantityOrdered = $orderItem->quantity;

        $this->decrementMaterials($product, $quantityOrdered);
        $this->updateOrderTotals($orderItem->order_id);
    }

    /**
     * Handle the OrderItem "updated" event.
     */
    public function updated(OrderItem $orderItem): void
    {
        $this->updateOrderTotals($orderItem->order_id);
    }

    /**
     * Handle the OrderItem "deleted" event.
     */
    public function deleted(OrderItem $orderItem): void
    {
        $this->updateOrderTotals($orderItem->order_id);
    }

    /**
     * Handle the OrderItem "restored" event.
     */
    public function restored(OrderItem $orderItem): void
    {
        //
    }

    /**
     * Handle the OrderItem "force deleted" event.
     */
    public function forceDeleted(OrderItem $orderItem): void
    {
        //
    }

    protected function updateOrderTotals($orderId)
    {
        $order = Order::find($orderId);

        if ($order) {
            // Recalculate the total amount
            $totalAmount = $order->orderItems->sum(function ($item) {
                return $item->product->price * $item->quantity;
            });

            // Use the helper function to calculate tax and service
            $charges = calculate_tax_and_service($totalAmount, $order->type);

            $order->update([
                'total_amount' => $charges['grand_total'],
                'tax' => $charges['tax'],
                'service' => $charges['service'],
            ]);
        }
    }

    protected function decrementMaterials($product, $quantityOrdered)
    {
        $recipe = $product->recipe->first();
        foreach ($recipe->materials()->get() as $material) {
            $materialUsed = $material->pivot->material_quantity;
            $totalMaterialUsed = $materialUsed * $quantityOrdered;


            $material->decrement('quantity', $totalMaterialUsed);
        }
    }
}
