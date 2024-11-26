<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function createOrder($orderData, $items)
    {
        return DB::transaction(function () use ($orderData, $items) {
            $order = Order::create($orderData);

            $orderItemsData = [];
            $totalAmount = 0;
            $totalTax = 0;
            $totalService = 0;
            $totalDiscount = 0;

            foreach ($items as $item) {
                $product = $item['product'];
                $orderItemData = [
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product' => $product,
                    'price' => $product->price,
                    'quantity' => $item['quantity'],
                    'discount' => 0,
                    'discount_type' => null,
                    'discount_id' => null,
                    'discount_value' => 0,
                    'sub_total' => $product->price * $item['quantity'],
                ];

                $calculated = calculate_total_amount_for_order_item($orderData['type'], (object)$orderItemData);
                $orderItemData['tax'] = $calculated['tax'];
                $orderItemData['service'] = $calculated['service'];
                $orderItemData['discount'] = $calculated['discount'];
                $orderItemData['total_amount'] = $calculated['total_amount'];

                $orderItemsData[] = $orderItemData;

                $totalAmount += $calculated['base_amount'];
                $totalTax += $calculated['tax'];
                $totalService += $calculated['service'];
                $totalDiscount += $calculated['discount'];
            }

            $orderItemsData = array_map(function ($item) {
                unset($item['product']); // Exclude the 'product' field
                return $item;
            }, $orderItemsData);

            OrderItem::insert($orderItemsData);

            $order->update([
                'tax' => $totalTax,
                'discount' => $totalDiscount,
                'service' => $totalService,
                'sub_total' => $totalAmount,
                'total_amount' => $totalAmount + $totalTax + $totalService - $totalDiscount,
            ]);

            return $order;
        });
    }
}