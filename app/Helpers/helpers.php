<?php

use App\Models\Order;

if (!function_exists('calculate_tax_and_service')) {

    function calculate_total_amount_for_order_item($order_type, $item, $taxPercentage = 14, $servicePercentage = 12)
    {
        $serviceAmount = 0;
        $calculated_discount = calculateDiscount($item);

        if ($order_type === 'dine-in' && $item['product']->service === 'true') {
            $serviceAmount = calculatePercentage($calculated_discount['sub_total'] - $calculated_discount['discount_value'], $servicePercentage);
        }

        $taxAmount = 0;
        if ($item['product']->tax === 'true') {
            $taxAmount = calculatePercentage(($calculated_discount['sub_total'] - $calculated_discount['discount_value']) + $serviceAmount, $taxPercentage);
        }

        $grandTotal = $calculated_discount['sub_total'] + $taxAmount + $serviceAmount - $calculated_discount['discount_value'];

        return [
            'base_amount' => $calculated_discount['sub_total'],
            'tax' => $taxAmount,
            'service' => $serviceAmount,
            'discount_value' => $calculated_discount['discount_value'],
            'total_amount' => $grandTotal,
            'discount' => $calculated_discount['discount'],
            'discount_type' => $calculated_discount['discount_type'],
        ];
    }

    function updateOrderTotals($orderId)
    {
        try {
            $order = Order::with('orderItems.product')->find($orderId);

            if (!$order) {
                error_log("Order not found: " . $orderId);
                return;
            }

            $totalAmount = 0;
            $totalDiscount = 0;

            foreach ($order->orderItems as $item) {
                $product = $item->product;

                $itemTotal = $item->price * $item->quantity;
                $calculated_discount = calculateDiscount($item);

                $totalAfterDiscount = $itemTotal - $calculated_discount['discount_value'];
                $serviceAmount = ($order->type == 'dine-in' && $product->service == 'true') ? calculatePercentage($totalAfterDiscount, 12) : 0;
                $taxAmount = ($product->tax == 'true') ? calculatePercentage($totalAfterDiscount + $serviceAmount, 14) : 0;

                $totalAmount += $itemTotal;
                $totalDiscount += $calculated_discount['discount_value'];

                $item->update([
                    'sub_total' => $itemTotal,
                    'total_amount' => $itemTotal + $taxAmount + $serviceAmount - $calculated_discount['discount_value'],
                    'tax' => $taxAmount,
                    'service' => $serviceAmount,
                    'discount' => $calculated_discount['discount'],
                    'discount_type' => $calculated_discount['discount_type'],
                    'discount_value' => $calculated_discount['discount_value'],
                ]);
            }

            // Add order's discount to orderItems's discounts
            $oldTotalDiscount = $totalDiscount;     // Save orderItems's total discounts value
            if ($order->discount_type == "cash") {
                $totalDiscount += $order->discount_value;
            } elseif ($order->discount_type == "percentage") {
                $totalDiscount += calculatePercentage($totalAmount, $order->discount);
            } elseif ($order->discount_type == "saved") {
                $totalDiscount += calculatePercentage($totalAmount, $order->discountSaved->amount);
            }

            $discount = $order->discount;
            $discount_type = $order->discount_type;

            // Check if total discount is bigger than sub total or not
            if ($totalAmount < $totalDiscount) {
                $totalDiscount = $oldTotalDiscount;
                $discount = 0;
                $discount_type = null;
            }

            $service_tax = calculate_tax_service($totalAmount - $totalDiscount, $order->type);
            // Calculate grand total for the order
            $grandTotal = $totalAmount - $totalDiscount + $service_tax['service'] + $service_tax['tax'];

            $order->update([
                'sub_total' => $totalAmount,
                'total_amount' => $grandTotal,
                'tax' => $service_tax['tax'],
                'service' => $service_tax['service'],
                'discount_value' => $totalDiscount,
                'discount_type' => $discount_type,
                'discount' => $discount,
            ]);
            return $order;
        } catch (\Exception $e) {
            error_log("Error updating order totals for order ID: " . $orderId . " - " . $e->getMessage());
        }
    }

    // Calculate discount for an order item
    function calculateDiscount($item)
    {
        $sub_total = $item['price'] * $item['quantity'];
        $discountValue = 0;

        // Calculate product discount
        if ($item['discount_type'] !== null) {
            if ($item->product->discount_type === 'percentage') {
                $discountValue += $sub_total * $item->product->discount / 100;
            } else {
                $discountValue += $item->product->discount;
            }
        }

        // Calculate item discount
        if ($item['discount_type'] == "cash") {
            $discountValue += $item->discount;
        } elseif ($item['discount_type'] == "percentage") {
            $discountValue += $sub_total * $item->discount / 100;
        } elseif ($item['discount_type'] == "saved") {
            $discountValue += $sub_total * $item->discountSaved->amount / 100;
        }

        // Ensure total discount does not exceed sub total
        if ($sub_total < $discountValue) {
            $discountValue = 0; // Reset discount if it exceeds sub total
        }

        return [
            'sub_total' => $sub_total,
            'discount' => $item['discount'],
            'discount_type' => $item['discount_type'],
            'discount_value' => $discountValue
        ];
    }

    // Calculate percentage of a given amount
    function calculatePercentage($amount, $percentage)
    {
        return ($percentage / 100) * $amount;
    }

    function decrementMaterials($product, $quantityOrdered)
    {
        $recipe = $product->recipe->first();
        if (!$recipe) {
            error_log("No recipe found for product ID: " . $product->id);
            return;
        }

        foreach ($recipe->materials()->get() as $material) {
            $materialUsed = $material->pivot->material_quantity;
            $totalMaterialUsed = $materialUsed * $quantityOrdered;

            if ($material->quantity < $totalMaterialUsed) {
                error_log("Insufficient material: " . $material->name);
                continue; // Skip decrementing if not enough material
            }

            $material->decrement('quantity', $totalMaterialUsed);
        }
    }

    // Calculate discuont for orderItem for orderItem calculation
    function calculate_discount($type, $amount, $sub_total)
    {
        if (!in_array($type, ['cash', 'percentage', 'saved'])) {
            error_log("Invalid discount type: " . $type);
            return 0;
        }

        if ($amount < 0) {
            error_log("Invalid discount amount: " . $amount);
            return 0;
        }

        $discount_value = 0;
        if ($type == 'cash') {
            $discount_value = $amount;
        } else {
            $discount_value = calculatePercentage($sub_total, $amount);
        }

        return [
            'sub_total' => $sub_total,
            'discount' => $amount,
            'discount_type' => $type,
            'discount_value' => $discount_value,
            'discount_id' => null,
        ];
    }

    function calculate_tax_service($subTotalAfterDiscount, $type)
    {
        $service = ($type == 'dine-in') ? calculatePercentage($subTotalAfterDiscount, 12) : 0;
        $tax = calculatePercentage($subTotalAfterDiscount + $service, 14);
        return [
            'tax' => $tax,
            'service' => $service
        ];
    }
}
