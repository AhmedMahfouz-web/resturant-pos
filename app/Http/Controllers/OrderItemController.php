<?php

namespace App\Http\Controllers;

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

        $orderItem = OrderItem::create([
            'order_id' => $request->order_id,
            'product_id' => $product->id,
            'price' => $product->price,
            'quantity' => $request->quantity,
        ]);


        return response()->json(['message' => 'OrderItemcx created successfully', 'order_item' => $orderItem], 201);
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


        return response()->json(['message' => 'OrderItem updated successfully', 'order_item' => $orderItem], 200);
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
