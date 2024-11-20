<?php

namespace App\Observers;

use App\Events\OrderEvents;
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
        $order = Order::find($orderItem->order_id);
        $quantityOrdered = $orderItem->quantity;

        // $this->decrementMaterials($product, $quantityOrdered);
        updateOrderTotals($orderItem->order_id);
    }

    /**
     * Handle the OrderItem "updated" event.
     */
    public function updated(OrderItem $orderItem): void
    {
        $order = Order::find($orderItem->order_id);
        // updateOrderTotals($orderItem->order_id);
    }

    /**
     * Handle the OrderItem "deleted" event.
     */
    public function deleted(OrderItem $orderItem): void
    {
        $order = Order::find($orderItem->order_id);
        event(new OrderEvents($order));
        updateOrderTotals($orderItem->order_id);
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
}
