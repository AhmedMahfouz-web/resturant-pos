<?php

use App\Models\Order;

if (!function_exists('calculate_tax_and_service')) {

    function calculate_tax_and_service($totalAmount, $type, $discount = 0, $discount_type = null, $taxPercentage = 14, $servicePercentage = 12)
    {
        if ($type == 'dine-in') {
            // Calculate service charge
            $serviceAmount = ($servicePercentage / 100) * $totalAmount;
        } else {
            $serviceAmount = 0;
        }

        // if ($discount_type != null) {
        //     if ($discount_type == 'percentage') {
        //         $discount_value = ($totalAmount + $serviceAmount) * $discount;
        //     } else {
        //         $discount_value = $discount;
        //     }
        // } else {
        //     $discount_value = 0;
        // }

        // Calculate tax
        $taxAmount = ($taxPercentage / 100) * ($totalAmount + $serviceAmount);

        // Return as an array
        return [
            'tax' => $taxAmount,
            'service' => $serviceAmount,
            'discount_value' => $discount,
            'grand_total' => $totalAmount + $taxAmount + $serviceAmount - $discount,
        ];
    }

    function updateOrderTotals($orderId)
    {
        $order = Order::find($orderId);

        if ($order) {
            $totalAmount = $order->orderItems->sum(function ($item) {
                return $item->product->price * $item->quantity;
            });

            $charges = calculate_tax_and_service($totalAmount, $order->type, $order->discount);

            $order->update([
                'sub_total' => $totalAmount,
                'total_amount' => $charges['grand_total'],
                'tax' => $charges['tax'],
                'service' => $charges['service'],
            ]);
        }
    }

    function decrementMaterials($product, $quantityOrdered)
    {
        $recipe = $product->recipe->first();
        foreach ($recipe->materials()->get() as $material) {
            $materialUsed = $material->pivot->material_quantity;
            $totalMaterialUsed = $materialUsed * $quantityOrdered;


            $material->decrement('quantity', $totalMaterialUsed);
        }
    }

    function calculate_discount($type, $amount, $sub_total)
    {
        $disount_value = 0;
        if ($type == 'cash') {
            $discount_value = $amount;
        } else {
            $discount_value = $sub_total * $amount / 100;
        }

        return $discount_value;
    }
}
