<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    public function create(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'discount' => 'nullable|numeric|min:0', // Ensure discount is numeric and non-negative
        ]);

        $product = Product::find($request->product_id);
        $order = Order::find($request->order_id);

        // Check if the order exists
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Check if the order is still live
        if ($order->status != 'live') {
            return response()->json(['message' => 'Cannot update this order'], 403);
        }

        // Ensure the discount is not greater than the price
        $discount = $request->discount ?? 0;  // If no discount is provided, default to 0
        if ($discount > $product->price) {
            return response()->json(['message' => 'Discount cannot be greater than the item price'], 400);
        }

        // Calculate the total amount for the item
        $itemTotal = $product->price * $request->quantity;
        $discountedAmount = $discount * $request->quantity; // Apply discount based on quantity
        $totalAmount = $itemTotal - $discountedAmount;
        $tax = $product->tax == 'true' ? calculatePercentage($totalAmount, 14) : 0;
        $service = $product->service == 'true' ? calculatePercentage($totalAmount, 12) : 0;

        // Create the order item
        $orderItem = OrderItem::create([
            'order_id' => $request->order_id,
            'product_id' => $product->id,
            'price' => $product->price,
            'quantity' => $request->quantity,
            'discount' => $discountedAmount,
            'total_amount' => $itemTotal - $discountedAmount + $tax + $service,
            'tax' => $tax,
            'service' => $service,
            'notes' => null,
        ]);

        // Return success response with the created order item
        return response()->json([
            'message' => 'OrderItem created successfully',
            'order_item' => $orderItem->load(['order', 'product']),
        ], 201);
    }

    public function update($id, Request $request)
    {
        $orderItem = OrderItem::where('id', $id)->with('order')->first();

        if (!$orderItem) {
            return response()->json(['error' => 'Order item not found'], 404);
        }

        if ($orderItem->order->status == 'live') {

            $orderItem->update([
                'quantity' => $request->quantity
            ]);

            updateOrderTotals($orderItem->order_id);

            return response()->json(['message' => 'OrderItem updated successfully', 'order_item' => $orderItem->load(['order', 'product'])], 200);
        } else {
            return response()->json(['message' => 'OrderItem cannot be updated'], 403);
        }
    }

    public function destroy($id)
    {
        $orderItem = OrderItem::find($id);
        if (!$orderItem) {
            return response()->json(['error' => 'OrderItem not found'], 404);
        }

        $orderItem->delete();


        return response()->json(['message' => 'OrderItem deleted successfully'], 200);
    }

    public function add_note($id, Request $request)
    {
        $orderItem = OrderItem::find($id);

        if (!$orderItem) {
            return response()->json(['error' => 'OrderItem not found'], 404);
        }

        $orderItem->update([
            'notes' => $request->notes
        ]);

        return response()->json(
            [
                'message' => 'Notes added successfully',
                'order_item' => $orderItem->load(['order', 'product'])
            ],
            200
        );
    }
}
