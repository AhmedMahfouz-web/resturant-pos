<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function endShift()
    {
        $shift = Shift::where('end_time', null)->first();
        if ($shift) {
            $shift->end_time = now();
            $shift->save();
        }
    }

    public function getShiftDetails($shiftId)
    {
        // Retrieve the shift with its orders
        $shift = Shift::with('orders.payment.paymentMethod')->find($shiftId);

        if (!$shift) {
            return response()->json(['message' => 'Shift not found'], 404);
        }

        // Calculate total payments for each method within the shift
        $paymentTotals = Payment::select('payment_method_id', DB::raw('SUM(amount) as total_amount'))
            ->whereHas('order', function ($query) use ($shiftId) {
                $query->where('shift_id', $shiftId); // Filter by shift
            })
            ->groupBy('payment_method_id')
            ->with('paymentMethod')
            ->get();

        // Format the total payments by method
        $formattedPaymentTotals = $paymentTotals->map(function ($payment) {
            return [
                'method' => $payment->paymentMethod->name,
                'total_amount' => $payment->total_amount
            ];
        });

        // Calculate total shift sales
        $totalShiftAmount = $shift->orders->sum('total_amount');

        return response()->json([
            'shift' => $shift,
            'total_sales' => $totalShiftAmount,
            'payment_totals' => $formattedPaymentTotals,
        ]);
    }
}
