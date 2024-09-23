<?php

// Calculate tax and service charges based on a percentage
if (!function_exists('calculate_tax_and_service')) {
    function calculate_tax_and_service($totalAmount, $type, $discount = 0, $discount_type = null, $taxPercentage = 14, $servicePercentage = 10)
    {
        if ($type == 'dine-in') {
            // Calculate service charge
            $serviceAmount = ($servicePercentage / 100) * $totalAmount;
        } else {
            $serviceAmount = 0;
        }

        if ($discount_type != null) {
            if ($discount_type == 'percentage') {
                $discount_value = ($totalAmount + $serviceAmount) * $discount;
            } else {
                $discount_value = $discount;
            }
        } else {
            $discount_value = 0;
        }

        // Calculate tax
        $taxAmount = ($taxPercentage / 100) * ($totalAmount + $serviceAmount);

        // Return as an array
        return [
            'tax' => $taxAmount,
            'service' => $serviceAmount,
            'discount_value' => $discount_value,
            'grand_total' => $totalAmount + $taxAmount + $serviceAmount - $discount_value,
        ];
    }
}
