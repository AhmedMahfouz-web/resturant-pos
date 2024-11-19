<?php

namespace App\Http\Controllers;

use App\Jobs\DecrementMaterials;
use App\Models\Discount;
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
        $totalTax = 0;
        $totalService = 0;
        $totalDiscount = 0;
        $grandTotal = 0;

        $orderData = [
            'user_id' => auth()->user()->id,
            'code' => $newOrderId,
            'guest' => $request->guest,
            'type' => $request->type,
            'shift_id' => $request->shift_id,
            'status' => 'live',
            'tax' => $totalTax,
            'discount' => $totalDiscount,
            'service' => $totalService,
            'sub_total' => $totalAmount,
            'total_amount' => $grandTotal,
        ];

        if (!empty($request->table_id)) {
            $orderData['table_id'] = $request->table_id;
        }

        $order = Order::create($orderData);

        // Collect order items data for batch insertion
        $orderItemsData = [];

        foreach ($validated['items'] as $item) {
            $product = Product::find($item['product_id']);

            // Prepare order item data including the product for calculations
            $orderItemData = [
                'order_id' => $order->id,
                'product' => $product,
                'product_id' => $product->id,
                'price' => $product->price,
                'quantity' => $item['quantity'],
                'sub_total' => $product->price * $item['quantity'],
                'total_amount' => $product->price * $item['quantity'],
            ];

            $calculated = calculate_total_amount_for_order_item($request->type, (object)$orderItemData);

            // Add calculated values to the order item data
            $orderItemData['tax'] = $calculated['tax'];
            $orderItemData['service'] = $calculated['service'];
            $orderItemData['discount'] = $calculated['discount'];

            // Create a new array for insertion excluding the 'product' key
            $orderItemForInsert = [
                'order_id' => $orderItemData['order_id'],
                'product_id' => $orderItemData['product_id'],
                'price' => $orderItemData['price'],
                'quantity' => $orderItemData['quantity'],
                'sub_total' => $orderItemData['sub_total'],
                'total_amount' => $orderItemData['total_amount'],
                'tax' => $orderItemData['tax'],
                'service' => $orderItemData['service'],
                'discount' => $orderItemData['discount'],
            ];

            // Add to the array for batch insertion
            $orderItemsData[] = $orderItemForInsert;

            // Update totals
            $totalAmount += $calculated['base_amount'];
            $totalTax += $calculated['tax'];
            $totalService += $calculated['service'];
            $totalDiscount += $calculated['discount'];
            $grandTotal += $calculated['total_amount'];
        }

        // Perform batch insert
        OrderItem::insert($orderItemsData);

        // Update the order with the calculated totals
        $order->update([
            'tax' => $totalTax,
            'discount' => $totalDiscount,
            'service' => $totalService,
            'sub_total' => $totalAmount,
            'total_amount' => $grandTotal,
        ]);

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
        $currentItemDiscounts = $order->orderItems->sum('discount');

        // Apply whole-order discount based on type
        if ($validated['type'] == 'cash') {
            $orderDiscountValue = $validated['amount'];
        } else {
            $orderDiscountValue = ($order->sub_total * $validated['amount']) / 100;
        }

        // Ensure whole-order discount + item-level discounts do not exceed sub-total
        if ($orderDiscountValue + $currentItemDiscounts > $order->sub_total) {
            return response()->json(['message' => 'Total discount exceeds the sub-total amount.'], 400);
        }

        $service_tax = calculate_tax_service($order->sub_total - $orderDiscountValue);

        // Update the order with the whole-order discount
        $order->discount = $orderDiscountValue;
        $order->total_amount = $order->sub_total + $service_tax['service'] + $service_tax['tax'] - $order->discount;
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
