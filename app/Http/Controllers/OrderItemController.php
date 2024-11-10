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
        ]);

        $product = Product::find($request->product_id);
        $order = Order::find($request->order_id);

        //Checking if there's and order
        if (!empty($order)) {
            //Checking if the order still live or not
            if ($order->status == 'live') {
                $orderItem = OrderItem::create([
                    'order_id' => $request->order_id,
                    'product_id' => $product->id,
                    'price' => $product->price,
                    'quantity' => $request->quantity,
                ]);

                return response()->json(['message' => 'OrderItem created successfully', 'order_item' => $orderItem->load(['order', 'product'])], 201);
            } else {
                return response()->json(['message' => 'Cannot update this order'], 403);
            }
        } else {
            return response()->json(['message' => 'Order not found'], 404);
        }
    }

    public function update($id, Request $request)
    {
        $orderItem = OrderItem::find($id);
        if (!$orderItem) {
            return response()->json(['error' => 'Order item not found'], 404);
        }

        $orderItem->update([
            'quantity' => $request->quantity
        ]);


        return response()->json(['message' => 'OrderItem updated successfully', 'order_item' => $orderItem->load(['order', 'product'])], 200);
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
}
