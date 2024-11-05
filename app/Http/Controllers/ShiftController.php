<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Shift;
use App\Models\User;
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

    public function closeShift(Request $request, $shiftId)
    {
        $shift = Shift::find($shiftId);

        if (!$shift) {
            return response()->json(['message' => 'Shift not found'], 404);
        }


        $shift->update([
            'end_time' => now()
        ]);

        User::logoutAllUsers();

        return response()->json(['message' => 'Shift closed successfully and users logged out'], 200);
    }

    public function getShiftDetails($shiftId)
    {
        // Retrieve the shift with its orders
        //     $shift = Shift::with('orders.payment.paymentMethod')->find($shiftId);

        //     if (!$shift) {
        //         return response()->json(['message' => 'Shift not found'], 404);
        //     }

        //     // Calculate total payments for each method within the shift
        //     $paymentTotals = Payment::select('payment_method_id', DB::raw('SUM(amount) as total_amount'))
        //         ->whereHas('order', function ($query) use ($shiftId) {
        //             $query->where('shift_id', $shiftId); // Filter by shift
        //         })
        //         ->groupBy('payment_method_id')
        //         ->with('paymentMethod')
        //         ->get();

        //     // Format the total payments by method
        //     $formattedPaymentTotals = $paymentTotals->map(function ($payment) {
        //         return [
        //             'method' => $payment->paymentMethod->name,
        //             'total_amount' => $payment->total_amount
        //         ];
        //     });

        //     // Calculate total shift sales
        //     $totalShiftAmount = $shift->orders->sum('total_amount');

        //     return response()->json([
        //         'shift' => $shift,
        //         'total_sales' => $totalShiftAmount,
        //         'payment_totals' => $formattedPaymentTotals,
        //     ]);
        // }

        $shift = Shift::find($shiftId);

        if (!$shift) {
            return response()->json(['message' => 'Shift not found'], 404);
        }

        $orderSums = $shift->orders()
            ->selectRaw('SUM(total_amount) as total_sales, SUM(tax) as total_tax, SUM(service) as total_services, SUM(discount) as total_discounts')
            ->first();

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
                'user_id' => $shift->user_id,
                'total_sales' => $orderSums->total_sales,
                'total_tax' => $orderSums->total_tax,
                'total_services' => $orderSums->total_services,
                'total_discounts' => $orderSums->total_discounts,
                'payment_totals' => $paymentTotals,
            ],
        ]);
    }
}
