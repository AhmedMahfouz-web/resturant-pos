<?php

namespace App\Http\Controllers;

use App\Jobs\DecrementMaterials;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function create(Request $request)
    {

        $order = Order::where('id', $request->order_id)->with('orderItems.product')->first();
        if ($request->amount < $order->total_amount) {
            return response()->json([
                'success' => 'false',
                'message' => 'Payment is less than the order amount'
            ], 405);
        }

        $change = $request->amount - $order->amount;

        $payment = Payment::where('order_id', $request->order_id)->first();
        if (!empty($payment)) {
            return response()->json([
                'message' => 'this order is paid already',
            ], 405);
        }
        // Create the payment
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => $order->total_amount,
            'payment_method_id' => $request->payment_method_id,
        ]);

        $order->update([
            'status' => 'completed',
            'close_at' => now()
        ]);

        // DecrementMaterials::dispatch($order);

        // Return response to the frontend
        return response()->json([
            'message' => 'Payment successful',
            'order' => $order,
            'payment' => $payment->load('paymentMethod'),
            'change' => $change
        ], 201);
    }
}
