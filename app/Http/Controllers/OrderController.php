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
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    //Get specific order
    public function show($id)
    {
        $order = Order::with(['orderItems.product', 'user'])->find($id);

        return response()->json($order);
    }

    // Get all orders
    public function index()
    {
        if (auth()->user()->can('old reciept')) {
            return response()->json(Order::with(['orderItems.product', 'user'])->latest()->get());
        } else {
            $shift_id = Shift::select('id')->first();
            return response()->json(Order::where('shift_id', $shift_id)->with(['orderItems.product', 'user'])->latest()->take(100)->get());
        }
    }

    // Get live orders
    public function liveOrders()
    {
        $orders = Order::where('status', 'live')->with(['orderItems.product', 'user'])->latest()->take(100)->get();
        return response()->json($orders);
    }

    // Get canceled orders
    public function canceledOrders()
    {
        if (auth()->user()->can('old reciept')) {
            $orders = Order::where('status', 'canceled')->with(['orderItems.product', 'user'])->latest()->get();
            return response()->json($orders);
        } else {
            $shift_id = Shift::select('id')->first();
            $orders = Order::where(['status' => 'canceled', 'shift_id' => $shift_id])->with(['orderItems.product', 'user'])->latest()->take(100)->get();
            return response()->json($orders);
        }
    }

    // Get completed orders
    public function completedOrders()
    {
        if (auth()->user()->can('old reciept')) {
            $orders = Order::where('status', 'completed')->with(['orderItems.product', 'user'])->latest()->get();
            return response()->json($orders);
        } else {
            $shift_id = Shift::select('id')->first();
            $orders = Order::where(['status' => 'completed', 'shift_id' => $shift_id])->with(['orderItems.product', 'user'])->latest()->take(100)->get();
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

        // Fetch all products in a single query
        $productIds = array_column($validated['items'], 'product_id');
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $items = array_map(function ($item) use ($products) {
            $product = $products->get($item['product_id']);
            return [
                'product' => $product,
                'quantity' => $item['quantity'],
            ];
        }, $validated['items']);
        // Use a transaction to ensure data integrity
        $order = $orderService->createOrder($orderData, $items);

        return response()->json([
            'success' => 'true',
            'message' => 'Order created successfully',
            'order' => $order->load(['orderItems.product', 'user']),
        ], 201);
    }

    // Update an order (e.g., add or remove items)
    public function updateOrder(Request $request, $id)
    {
        $order = Order::with(['orderItems.product', 'user'])->findOrFail($id);

        if (!empty($order->table_id)) {
            $table = Table::find($order->table_id);
            $table->update(['is_free' => 1]);
        }

        $order->update([
            'type' => $request->type,
            'table_id' => $request->table_id
        ]);

        $order = updateOrderTotals($id);

        return response()->json(['message' => 'Order updated successfully', 'order' => $order->load(['orderItems.product', 'user'])]);
    }

    // Delete an order
    public function cancelOrder($id, Request $request)
    {
        $order = Order::findOrFail($id);

        // Validate the request for reasons
        $request->validate([
            'reason' => 'nullable|string|max:255',
            'manual_reason' => 'nullable|string|max:255',
        ]);

        // Update the order with cancellation details
        $order->update([
            'status' => 'canceled',
            'reason' => $request->reason,
            'manual_reason' => $request->manual_reason,
        ]);

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
            'order' => $order->load(['orderItems.product', 'user']),
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
            'order' => $order->load(['orderItems.product', 'user']),
        ]);
    }

    public function splitOrder($id, Request $request, OrderService $orderService)
    {
        $originalOrder = Order::with(['orderItems.product', 'user'])->find($id);
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
            'original_order' => $originalOrder->load(['orderItems.product', 'user']),
            'new_order' => $newOrder->load(['orderItems.product', 'user']),
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

    public function getOrdersByStatus(Request $request, $status)
    {
        // Get the latest shift
        $latestShift = Shift::latest()->first();

        if (!$latestShift) {
            return response()->json(['message' => 'No active shifts found'], 404);
        }

        // Get the month and year from the latest shift's start time
        $currentMonth = $latestShift->start_time->month;
        $currentYear = $latestShift->start_time->year;

        // Set end date to the last minute of the provided end_date or current day
        $endDate = $request->has('end_date')
            ? Carbon::parse($request->get('end_date'))->endOfDay()
            : Carbon::now()->endOfDay();

        $query = Order::where('status', $status)
            ->with([
                'orderItems' => fn($query) => $query->select('id', 'order_id', 'product_id', 'quantity'),
                'orderItems.product' => fn($query) => $query->select('id', 'name', 'price')
            ])
            ->select('id', 'code', 'status', 'shift_id', 'total_amount', 'created_at')
            ->whereMonth('created_at', $currentMonth) // Filter by the month of the latest shift
            ->whereYear('created_at', $currentYear) // Filter by the year of the latest shift
            ->where('created_at', '<=', $endDate) // Use endDate here
            ->latest();

        if (!auth()->user()->can('old reciept')) {
            $shift_id = $latestShift->id; // Use the latest shift ID
            $query->where('shift_id', $shift_id);
        }

        return $query->paginate(25);
    }

    public function getCompletedOrdersWithoutShift()
    {
        // Get all shift IDs
        $shiftIds = Shift::pluck('id')->toArray();

        // Retrieve completed orders where the shift_id is not in the shifts table
        $orders = Order::where('status', 'completed')
            ->whereNotIn('shift_id', $shiftIds)
            ->with(['orderItems.product', 'user']) // Include order items and user
            ->get();

        // Calculate the total amount of these orders
        $totalAmount = $orders->sum('total_amount');

        return response()->json([
            'orders' => $orders,
            'total_amount' => $totalAmount,
        ]);
    }
}
