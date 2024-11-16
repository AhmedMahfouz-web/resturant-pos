<?php

namespace App\Http\Controllers;

use App\Jobs\DecrementMaterials;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Table;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    //Get specific order
    public function show($id)
    {
        $order = Order::with('orderItems.product')->find($id);

        return response()->json($order);
    }

    // Get all orders
    public function index()
    {
        if (auth()->user()->can('old reciept')) {
            return response()->json(Order::with('orderItems.product')->latest()->get());
        } else {
            $shift_id = Shift::select('id')->first();
            return response()->json(Order::where('shift_id', $shift_id)->with('orderItems.product')->latest()->get());
        }
    }

    // Get live orders
    public function liveOrders()
    {
        $orders = Order::where('status', 'live')->with('orderItems.product')->latest()->get();
        return response()->json($orders);
    }

    // Get canceled orders
    public function canceledOrders()
    {
        if (auth()->user()->can('old reciept')) {
            $orders = Order::where('status', 'canceled')->with('orderItems.product')->latest()->get();
            return response()->json($orders);
        } else {
            $shift_id = Shift::select('id')->first();
            $orders = Order::where(['status' => 'canceled', 'shift_id' => $shift_id])->with('orderItems.product')->latest()->get();
            return response()->json($orders);
        }
    }

    // Get completed orders
    public function completedOrders()
    {
        if (auth()->user()->can('old reciept')) {
            $orders = Order::where('status', 'completed')->with('orderItems.product')->latest()->get();
            return response()->json($orders);
        } else {
            $shift_id = Shift::select('id')->first();
            $orders = Order::where(['status' => 'completed', 'shift_id' => $shift_id])->with('orderItems.product')->latest()->get();
            return response()->json($orders);
        }
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
        if (empty($request->table_id)) {
            $order = Order::create([
                'user_id' => auth()->user()->id,
                'code' => $newOrderId,
                'guest' => $request->guest,
                'type' => $request->type,
                'shift_id' => $request->shift_id,
                'status' => 'live',
                'tax' => $charges['tax'],
                'discount' => $charges['discount_value'],
                'service' => $charges['service'],
                'sub_total' => $totalAmount,
                'total_amount' => $charges['grand_total'],
            ]);
        } else {
            $order = Order::create([
                'user_id' => auth()->user()->id,
                'code' => $newOrderId,
                'guest' => $request->guest,
                'type' => $request->type,
                'table_id' => $request->table_id,
                'shift_id' => $request->shift_id,
                'status' => 'live',
                'tax' => $charges['tax'],
                'discount' => $charges['discount_value'],
                'service' => $charges['service'],
                'sub_total' => $totalAmount,
                'total_amount' => $charges['grand_total'],
            ]);
        }

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
            'order' => $order->load('orderItems.product'),
        ], 201);
    }


    // Update an order (e.g., add or remove items)
    public function updateOrder(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $order->update($request->all());
        updateOrderTotals($id);

        return response()->json(['message' => 'Order updated successfully', 'order' => Order::find($id)]);
    }

    // Delete an order
    public function cancelOrder($id, Request $request)
    {
        $order = Order::findOrFail($id);
        $order->update(['status' => 'canceled']);
        if (!empty($request->waste)) {
            DecrementMaterials::dispatch($order);
        }

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

        $discount_value = calculate_discount($request->type, $request->amount, $order->sub_total, $order->total_amount);

        if ($discount_value == 0) {
            return response()->json([
                'success' => false,
                'message' => 'Discount is more than recipt value or empty'
            ]);
        }

        $order->update(['discount' => $discount_value]);
        updateOrderTotals($id);

        return response()->json([
            'success' => 'true',
            'message' => 'Discount applied successfully',
            'order' => $order,
        ]);
    }

    // Delete discount to order
    public function cancelDiscount($id)
    {
        $order = Order::find($id);
        if (!$order) {
            return response()->json([
                'success' => 'false',
                'message' => 'Order not found'
            ], 404);
        }

        $order->update(['discount' => 0]);
        updateOrderTotals($id);

        return response()->json([
            'success' => 'true',
            'message' => 'Discount deleted successfully',
            'order' => $order,
        ]);
    }

    public function splitOrder($id, Request $request)
    {
        // Retrieve the original order and validate its existence
        $originalOrder = Order::with('orderItems')->find($id);
        if (!$originalOrder) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($originalOrder->status != 'live') {
            return response()->json(['message' => 'Order is completed'], 403);
        }

        $newOrderId = $this->generate_new_order_id();

        // Create a new order with the same metadata as the original
        $newOrder = Order::create([
            'user_id' => $originalOrder->user_id,
            'table_id' => null,
            'guest' => null,
            'type' => 'takeaway',
            'code' => $newOrderId,
            'shift_id' => $originalOrder->shift_id,
            'status' => 'live',
            'tax' => 0,
            'sub_total' => 0,
            'service' => 0,
            'discount' => 0,
            'total_amount' => 0,
        ]);

        // Loop through each item to move and split the quantities
        foreach ($request->items as $itemToMove) {
            $orderItem = $originalOrder->orderItems()->find($itemToMove['id']);
            if ($orderItem && $orderItem->quantity >= $itemToMove['quantity']) {

                $orderItem->quantity -= $itemToMove['quantity'];
                $orderItem->save();

                // Create a new order item for the new order with the moved quantity
                OrderItem::create([
                    'order_id' => $newOrder->id,
                    'product_id' => $orderItem->product_id,
                    'price' => $orderItem->price,
                    'quantity' => $itemToMove['quantity'],
                ]);
            }
        }

        // Remove any order items that now have a zero quantity
        $originalOrder->orderItems()->where('quantity', 0)->delete();

        updateOrderTotals($originalOrder->id);
        updateOrderTotals($newOrder->id);


        return response()->json([
            'message' => 'Order split successfully',
            'original_order' => $originalOrder,
            'new_order' => $newOrder,
        ], 200);
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
