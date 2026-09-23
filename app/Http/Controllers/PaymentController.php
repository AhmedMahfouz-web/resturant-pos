<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Exceptions\InsufficientStockException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function create(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'amount' => 'required|numeric|min:0',
            'payment_method_id' => 'required|exists:payment_methods,id',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $order = Order::with('orderItems.product')
                    ->lockForUpdate()
                    ->findOrFail($validated['order_id']);

                if ($order->status !== 'live' || Payment::where('order_id', $order->id)->exists()) {
                    return response()->json(['message' => 'This order is already paid or closed.'], 409);
                }

                if ($validated['amount'] < $order->total_amount) {
                    return response()->json([
                        'success' => 'false',
                        'message' => 'Payment is less than the order amount'
                    ], 422);
                }

                $change = $validated['amount'] - $order->total_amount;
                $payment = Payment::create([
                    'order_id' => $order->id,
                    'amount' => $order->total_amount,
                    'payment_method_id' => $validated['payment_method_id'],
                ]);

                $order->update([
                    'status' => 'completed',
                    'close_at' => now()
                ]);

                return response()->json([
                    'message' => 'Payment successful',
                    'order' => $order,
                    'payment' => $payment->load('paymentMethod'),
                    'change' => $change
                ], 201);
            });
        } catch (InsufficientStockException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient stock to complete this order.',
            ], 422);
        }
    }
}
