<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Table;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    // Get all orders
    public function index()
    {
        return response()->json(Order::with('orderItems.product')->get());
    }

    // Get live orders
    public function liveOrders()
    {
        $orders = Order::where('status', 'live')->with('orderItems.product')->get();
        return response()->json($orders);
    }

    // Get canceled orders
    public function canceledOrders()
    {
        $orders = Order::where('status', 'canceled')->with('orderItems.product')->get();
        return response()->json($orders);
    }

    // Get completed orders
    public function completedOrders()
    {
        $orders = Order::where('status', 'completed')->with('orderItems.product')->latest();

        return response()->json($orders);
    }

    // Create a new order
    public function createOrder(Request $request)
    {
        $validated = $request->validate([
            'table_id' => 'exists:tables,id',
            'items' => 'required|array', // Array of items
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $newOrderId = $this->generate_new_order_id();

        $totalAmount = 0;
        foreach ($validated['items'] as $item) {
            $product = Product::find($item['product_id']);
            $totalAmount += $product->price * $item['quantity'];
        }

        $charges = calculate_tax_and_service($totalAmount, $request->type);

        $order = Order::create([
            'user_id' => auth()->user()->id,
            'code' => $newOrderId,
            'guest' => $request->guest,
            'table_id' => $request->table_id,
            'shift_id' => $request->shift_id,
            'status' => 'live',
            'tax' => $charges['tax'],
            'discount' => $charges['discount_value'],
            'service' => $charges['service'],
            'sub_total' => $totalAmount,
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
            'order' => $order->load('orderItems'),
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
    public function cancelOrder($id)
    {
        $order = Order::findOrFail($id);
        $order->update(['status' => 'canceled']);

        return response()->json(['message' => 'Order canceled successfully']);
    }

    // Add discount to order
    public function discount(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) {
            return response()->json([
                'success' => 'false',
                'message' => 'Order not found'
            ], 404);
        }

        $discount_value = calculate_discount($request->type, $request->amount, $order->sub_total);

        $order->update(['discount' => $discount_value]);

        return response()->json([
            'success' => 'true',
            'message' => 'Discount applied successfully',
            'order' => $order->load('orderItems'),
        ]);
    }

    // Generate ids for orders
    private function generate_new_order_id()
    {
        $lastOrder = Order::whereDate('created_at', Carbon::today())->orderBy('created_at', 'desc')->first();

        if ($lastOrder) {
            $lastIncrement = (int) substr($lastOrder->code, -3);
            $newIncrement = str_pad($lastIncrement + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newIncrement = '0001';
        }
        $newOrderId =  date('Ydm') . '-' . $newIncrement;

        return $newOrderId;
    }
}
