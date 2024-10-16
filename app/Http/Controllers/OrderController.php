<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        return response()->json(Order::with('orderItems')->get());
    }

    public function liveOrders()
    {
        $orders = Order::where('status', 'live')->with('orderItems')->get();

        return response()->json($orders);
    }

    public function latestOrders()
    {
        $orders = Order::where('status', 'completed')->with('orderItems')->latest();

        return response()->json($orders);
    }

    // Create a new order
    public function createOrder(Request $request)
    {
        $validated = $request->validate([
            'table_id' => 'required|exists:tables,id',
            'items' => 'required|array', // Array of items
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $lastOrder = Order::whereDate('created_at', Carbon::today())->orderBy('created_at', 'desc')->first();

        if ($lastOrder) {
            $lastIncrement = (int) substr($lastOrder->code, -3);
            $newIncrement = str_pad($lastIncrement + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newIncrement = '0001';
        }

        $totalAmount = 0;
        foreach ($validated['items'] as $item) {
            $product = Product::find($item['product_id']);
            $totalAmount += $product->price * $item['quantity'];
        }

        $charges = calculate_tax_and_service($totalAmount, $request->type);

        $newOrderId = date('Ydm') . '-' . $newIncrement;

        $order = Order::create([
            'user_id' => auth()->user()->id,
            'code' => $newOrderId,
            'guest' => $request->guest,
            'table_id' => $request->table_id,
            'tax' => $charges['tax'],
            'discount' => $charges['discount_value'],
            'service' => $charges['service'],
            'total_amount' => $charges['grand_total'],
        ]);

        foreach ($request->items as $item) {
            $product = Product::find($item['product_id']);
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'price' => $product->price,
                'quantity' => $item['quantity'],
            ]);

        }


        return response()->json([
            'success' => 'true',
            'message' => 'Order created successfully',
            'order' => $order
        ], 201);
    }


    // Update an order (e.g., add or remove items)
    public function updateOrder(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $order->update($request->all());
        return response()->json(['message' => 'Order updated successfully', 'order' => $order]);
    }

    // Delete an order
    public function deleteOrder($id)
    {
        Order::findOrFail($id)->delete();
        return response()->json(['message' => 'Order deleted successfully']);
    }
}
