<?php

namespace App\Http\Controllers;

use App\Jobs\DecrementMaterials;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function create($request)
    {

        $order = Order::with('orderItems.product');

        if ($request->amount < $order->total_amount) {
            return response()->json([
                'success' => 'false',
                'message' => 'Payment is less than the order amount'
            ], 405);
        }

        $change = $request->amount - $order->amount;

        // Create the payment
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => $order->total_amount,
            'payment_method_id' => $request->payment_method_id,
        ]);



        // Dispatch the job to decrement materials in the background
        DecrementMaterials::dispatch($order);

        // Return response to the frontend
        return response()->json([
            'message' => 'Payment successful',
            'order' => $order,
            'payment' => $payment,
            'change' => $change
        ], 201);
    }
}
