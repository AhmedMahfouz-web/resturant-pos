<?php

namespace App\Http\Controllers;

use App\Events\UserLoggedOutEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\User;
use App\Models\TokenBlacklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Token;

class ShiftController extends Controller
{
    public function startShift($user)
    {
        $shift = Shift::where('end_time', null)->first();
        if ($shift) {
            return $shift->id;
        } else {

            if ($user->can('start shift')) {
                $shift = Shift::create([
                    'user_id' => $user->id,
                    'start_time' => now(),
                ]);
                return $shift->id;
            } else {
                return null;
            }
        }
    }

    public function closeShift(Request $request, $shiftId)
    {
        $shift = Shift::find($shiftId);

        if (!$shift) {
            return response()->json(['message' => 'Shift not found'], 404);
        }

        $order = Order::where(['shift_id' => $shiftId, 'status' => 'live'])->first();
        if (!empty($order)) {
            return response()->json(['message' => 'Close all orders first'], 403);
        }

        // Get all tokens from the database
        $tokens = Token::all();

        // Add all tokens to the blacklist
        foreach ($tokens as $token) {
            TokenBlacklist::create(['token' => $token->token]);
        }

        // Delete all tokens from the database
        Token::truncate();

        // Fire the event to notify the frontend
        event(new UserLoggedOutEvent());

        $shift->update([
            'end_time' => now()
        ]);

        return response()->json(['message' => 'Shift closed successfully and users logged out'], 200);
    }

    public function getShiftDetails($shiftId)
    {
        $shift = Shift::find($shiftId);

        if (!$shift) {
            return response()->json(['message' => 'Shift not found'], 404);
        }

        // Calculate total sales and other metrics for completed orders
        $orderSums = $shift->orders()
            ->where('status', 'completed')
            ->selectRaw('SUM(total_amount) as total_sales, SUM(tax) as total_tax, SUM(service) as total_services, SUM(discount) as total_discounts')
            ->first();

        // Calculate total number of completed orders
        $totalCompletedOrders = $shift->orders()->where('status', 'completed')->count();

        // Calculate total number of items sold
        $totalItemsSold = $shift->orders()
            ->where('status', 'completed')
            ->with('orderItems') // Eager load order items
            ->get()
            ->flatMap(function ($order) {
                return $order->orderItems;
            })->sum('quantity');

        // Calculate canceled orders
        $canceledOrders = $shift->orders()->where('status', 'canceled')->get();
        $totalCanceledOrders = $canceledOrders->count();
        $totalCanceledValue = $canceledOrders->sum('total_amount');

        // Calculate total payments for each method within the shift
        $paymentTotals = Payment::with('paymentMethod:id,name') // Only load payment method name
            ->select('payment_method_id', DB::raw('SUM(amount) as total_amount'))
            ->whereIn('order_id', function ($query) use ($shiftId) {
                $query->select('id')
                    ->from('orders')
                    ->where('shift_id', $shiftId);
            })
            ->groupBy('payment_method_id')
            ->get()
            ->map(function ($payment) {
                return [
                    'method' => $payment->paymentMethod->name,
                    'total_amount' => $payment->total_amount,
                ];
            });

        return response()->json([
            'shift' => [
                'id' => $shift->id,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'user' => $shift->user->first_name . ' ' . $shift->user->last_name,
                'total_sales' => $orderSums->total_sales,
                'total_tax' => $orderSums->total_tax,
                'total_services' => $orderSums->total_services,
                'total_discounts' => $orderSums->total_discounts,
                'total_completed_orders' => $totalCompletedOrders,
                'total_items_sold' => $totalItemsSold,
                'total_canceled_orders' => $totalCanceledOrders,
                'total_canceled_value' => $totalCanceledValue,
                'payment_totals' => $paymentTotals,
            ],
        ]);
    }
}
