<?php

namespace App\Http\Controllers;

use App\Jobs\DecrementMaterials;
use App\Models\Discount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Table;
use App\Services\OrderService;
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
    public function createOrder(Request $request, OrderService $orderService)
    {
        $validated = $request->validate([
            'table_id' => 'exists:tables,id',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $newOrderId = $this->generate_new_order_id();

        $orderData = [
            'user_id' => auth()->user()->id,
            'code' => $newOrderId,
            'guest' => $request->guest,
            'type' => $request->type,
            'shift_id' => $request->shift_id,
            'status' => 'live',
            'tax' => 0,
            'discount' => 0,
            'service' => 0,
            'sub_total' => 0,
            'total_amount' => 0,
        ];

        if (!empty($request->table_id)) {
            $orderData['table_id'] = $request->table_id;
        }

        $items = array_map(function ($item) {
            $product = Product::find($item['product_id']);
            return [
                'product' => $product,
                'quantity' => $item['quantity'],
            ];
        }, $validated['items']);

        $order = $orderService->createOrder($orderData, $items);

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

        if (!empty($order->table_id)) {
            $table = Table::find($order->table_id);
            $table->update(['is_free' => 1]);
        }

        $order->update([
            'type' => $request->type,
            'table_id' => $request->table_id
        ]);

        $order = updateOrderTotals($id);

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
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0', // Ensuring the discount is non-negative
            'type' => 'required|in:percentage,cash,saved', // Validate type of discount
        ]);

        // Retrieve the order and related items
        $order = Order::with('orderItems')->find($id);
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Calculate current total item-level discounts
        $currentItemDiscounts = $order->orderItems->sum('discount_value');

        $calculated = calculate_discount($validated['type'], $validated['amount'], $order->sub_total);
        $total_discount = $calculated['discount_value'] + $currentItemDiscounts;

        // Ensure whole-order discount + item-level discounts do not exceed sub-total
        if ($total_discount > $order->sub_total) {
            return response()->json(['message' => 'Total discount exceeds the sub-total amount.'], 400);
        }

        $service_tax = calculate_tax_service($order->sub_total - $total_discount, $order->type);

        // Update the order with the whole-order discount
        $order->discount = $calculated['discount'];
        $order->discount_value = $total_discount;
        $order->discount_type = $calculated['discount_type'];
        $order->discount_id = $calculated['discount_id'];
        $order->total_amount = $order->sub_total + $service_tax['service'] + $service_tax['tax'] - $total_discount;
        $order->service = $service_tax['service'];
        $order->tax = $service_tax['tax'];
        $order->save();

        return response()->json([
            'message' => 'Whole-order discount applied successfully',
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

        $currentItemDiscounts = $order->orderItems->sum('discount_value');
        $order->update(['discount' => 0, 'discount_type' => null, 'discount_value' => $currentItemDiscounts]);
        $order = updateOrderTotals($id);

        return response()->json([
            'success' => 'true',
            'message' => 'Discount deleted successfully',
            'order' => $order,
        ]);
    }

    public function splitOrder($id, Request $request, OrderService $orderService)
    {
        $originalOrder = Order::with('orderItems')->find($id);
        if (!$originalOrder) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($originalOrder->status != 'live') {
            return response()->json(['message' => 'Order is completed'], 403);
        }

        $newOrderId = $this->generate_new_order_id();

        $orderData = [
            'user_id' => $originalOrder->user_id,
            'table_id' => null,
            'guest' => null,
            'type' => 'takeaway',
            'code' => $newOrderId,
            'shift_id' => $originalOrder->shift_id,
            'status' => 'live',
            'tax' => 0,
            'discount' => 0,
            'service' => 0,
            'sub_total' => 0,
            'total_amount' => 0,
        ];

        $items = [];
        foreach ($request->items as $itemToMove) {
            $orderItem = $originalOrder->orderItems()->find($itemToMove['id']);
            if ($orderItem && $orderItem->quantity >= $itemToMove['quantity']) {
                $orderItem->decrement('quantity', $itemToMove['quantity']);
                $product = Product::find($orderItem->product_id);

                $items[] = [
                    'product' => $product,
                    'quantity' => $itemToMove['quantity'],
                ];
            }
        }

        $newOrder = $orderService->createOrder($orderData, $items);

        $originalOrder->orderItems()->where('quantity', 0)->delete();

        $originalOrder = updateOrderTotals($originalOrder->id);

        return response()->json([
            'message' => 'Order split successfully',
            'original_order' => $originalOrder->load('orderItems.product'),
            'new_order' => $newOrder->load('orderItems.product'),
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
