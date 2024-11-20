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
            'sub_total' => $itemTotal,
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

    public function discount(Request $request, $id)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:percentage,cash,saved',
        ]);

        $orderItem = OrderItem::with('product', 'order')->find($id);

        if (!$orderItem) {
            return response()->json(['message' => 'Order item not found'], 404);
        }

        $itemTotal = $orderItem->price * $orderItem->quantity;
        if ($validated['type'] == 'cash') {
            $orderDiscountValue = $validated['amount'];
        } else {
            $orderDiscountValue = ($orderItem->sub_total * $validated['amount']) / 100;
        }

        // Ensure discount does not exceed the item total
        if ($orderDiscountValue > $orderItem->sub_total) {
            return response()->json(['message' => 'Total discount exceeds the sub-total amount.'], 400);
        }

        $service_tax = calculate_tax_service($orderItem->sub_total - $orderDiscountValue, Order::select('type')->where('id', $orderItem->id)->first());

        // Apply the discount and update the order item
        $orderItem->discount = $validated['discount'];
        $orderItem->total_amount = $orderItem->sub_total + $service_tax['service'] + $service_tax['tax'] - $orderItem->discount;
        $orderItem->service = $service_tax['service'];
        $orderItem->tax = $service_tax['tax'];
        $orderItem->save();

        // Update the order totals
        updateOrderTotals($orderItem->order_id);

        return response()->json([
            'message' => 'Discount applied to order item successfully',
            'order_item' => $orderItem->load(['order', 'product']),
        ]);
    }
}
