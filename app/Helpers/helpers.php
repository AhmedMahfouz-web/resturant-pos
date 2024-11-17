<?php

use App\Models\Order;

if (!function_exists('calculate_tax_and_service')) {

    function calculate_total_amount_for_order_item($order_type, $item, $taxPercentage = 14, $servicePercentage = 12)
    {
        $baseAmount = $item->price * $item->quantity;
        $serviceAmount = 0;

        // Check if the product includes service
        if ($order_type == 'dine-in') {
            if ($item->product->service === 'true') {
                $serviceAmount = ($servicePercentage / 100) * $baseAmount;
            }
        }

        $taxAmount = 0;
        // Check if the product includes tax
        if ($item->product->tax === 'true') {
            $taxAmount = ($taxPercentage / 100) * ($baseAmount + $serviceAmount);
        }

        // Calculate discount value based on the discount type
        $discountValue = 0;
        if ($item->product->discount_type !== null) {
            if ($item->product->discount_type === 'percentage') {
                $discountValue = ($baseAmount + $serviceAmount) * ($item->product->discount / 100);
            } else {
                $discountValue = $item->product->discount;
            }
        }

        // Calculate the grand total for the order item
        $grandTotal = $baseAmount + $taxAmount + $serviceAmount - $discountValue;

        // Return calculated amounts
        return [
            'base_amount' => $baseAmount,
            'tax' => $taxAmount,
            'service' => $serviceAmount,
            'discount' => $discountValue,
            'total_amount' => $grandTotal,
        ];
    }

    function updateOrderTotals($orderId)
    {
        // Retrieve the order with its items and related products
        $order = Order::with('orderItems.product')->find($orderId);

        if ($order) {
            $totalAmount = 0;
            $totalTax = 0;
            $totalService = 0;
            $totalDiscount = 0;

            foreach ($order->orderItems as $item) {
                $product = $item->product;

                // Calculate item-level totals
                $itemTotal = $item->price * $item->quantity;
                $discountValue = !empty($product->discount) || $product->discount == 0 ? calculateDiscount($itemTotal, $product->discount, $product->discount_type) : 0;

                if ($discountValue > $itemTotal) {
                    return response()->json([
                        'error' => true,
                        'message' => 'The discount value cannot be greater than the item price for product: ' . $product->name
                    ], 400);
                }

                $totalAfterDiscount = $itemTotal - $discountValue;
                if ($order->type == 'dine-in') {
                    $serviceAmount = $product->service == 'true' ? $totalAfterDiscount * 0.12 : 0;
                } else {
                    $serviceAmount = 0;
                }
                $taxAmount = $product->tax == 'true' ? ($totalAfterDiscount + $serviceAmount) * 0.14  : 0;

                // Accumulate values
                $totalAmount += $itemTotal;
                $totalDiscount += $discountValue;

                $item->update([
                    'sub_total' => $itemTotal,
                    'total_amount' => $itemTotal + $taxAmount + $serviceAmount - $discountValue,
                    'tax' => $taxAmount,
                    'service' => $serviceAmount,
                    'discount' => $discountValue,
                ]);
            }

            $service_tax = calculate_tax_service($totalAmount - $totalDiscount);
            // Calculate grand total for the order
            $grandTotal = $totalAmount - $totalDiscount + $service_tax['service'] + $service_tax['tax'];

            // Update the order with calculated totals
            $order->update([
                'sub_total' => $totalAmount,
                'total_amount' => $grandTotal,
                'tax' => $service_tax['tax'],
                'service' => $service_tax['service'],
                'discount' => $totalDiscount,
            ]);
        }
    }

    function calculateDiscount($totalAmount, $discount, $discountType)
    {
        if ($discountType == 'percentage') {
            return ($discount / 100) * $totalAmount;
        } elseif ($discountType == 'fixed') {
            return $discount;
        }
        return 0;
    }

    function calculatePercentage($amount, $percentage)
    {
        return ($percentage / 100) * $amount;
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

    function calculate_discount($type, $amount, $sub_total, $total_amount)
    {
        $disount_value = 0;
        if ($type == 'cash') {
            $discount_value = $amount;
        } else {
            $discount_value = $sub_total * $amount / 100;
        }

        if ($discount_value > $total_amount) {
            return 0;
        } else {
            return $discount_value;
        }
    }

    function calculate_tax_service($subTotalAfterDiscount)
    {

        $service = $subTotalAfterDiscount * 0.12;
        $tax = ($subTotalAfterDiscount + $service) * 0.14;
        return [
            'tax' => $tax,
            'service' => $service
        ];
    }
}
