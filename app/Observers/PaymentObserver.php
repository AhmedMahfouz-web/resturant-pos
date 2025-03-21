<?php

namespace App\Observers;

use App\Events\OrderEvents;
use App\Events\ShiftEvent;
use App\Jobs\DecrementMaterials;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shift;

class PaymentObserver
{
    public function created(Payment $payment): void
    {
        $order = Order::where('id', $payment->order_id)->first();
        $shift = Shift::find($payment->shift_id);
        // Decrement materialas for each product
        DecrementMaterials::dispatch($order);
        event(new ShiftEvent($shift));


        // Another way to decrement materials if the first one is not working

        // $materialsToDecrement = [];

        // foreach ($order->items as $orderItem) {
        //     $productId = $orderItem->product_id;
        //     $quantityOrdered = $orderItem->quantity;

        //     // Collect quantities for batch processing
        //     if (isset($materialsToDecrement[$productId])) {
        //         $materialsToDecrement[$productId] += $quantityOrdered;
        //     } else {
        //         $materialsToDecrement[$productId] = $quantityOrdered;
        //     }
        // }

        // // Perform a bulk update using raw SQL
        // foreach ($materialsToDecrement as $productId => $quantity) {
        //     DB::table('materials')
        //     ->where('product_id', $productId)
        //         ->decrement('quantity', $quantity); // Assuming 'quantity' is the column to decrement
        // }
    }
}
