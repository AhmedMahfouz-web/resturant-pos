<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function salesReport(Request $request)
    {
        $startDate = Carbon::parse($request->get('start_date'));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        $orders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->with('orderItems')
            ->get();

        $totalRevenue = $orders->sum('total_amount');
        $totalTax = $orders->sum('tax');
        $totalService = $orders->sum('service');

        $paymentBreakdown = Payment::select('payment_method_id', DB::raw('SUM(amount) as total_amount'))
            ->whereIn('order_id', $orders->pluck('id'))
            ->groupBy('payment_method_id')
            ->get();

        return response()->json([
            'total_revenue' => $totalRevenue,
            'total_tax' => $totalTax,
            'total_service' => $totalService,
            'payment_breakdown' => $paymentBreakdown
        ]);
    }

    public function inventoryReport()
    {
        $materials = Material::with('recipes.product.orderItems')
            ->get()
            ->map(function ($material) {
                $usedQuantity = $material->recipes->sum(function ($recipe) {
                    return $recipe->product->orderItems->sum('quantity') * $recipe->pivot->quantity;
                });
                return [
                    'material' => $material->name,
                    'remaining_quantity' => $material->quantity,
                    'used_quantity' => $usedQuantity,
                ];
            });

        return response()->json(['materials' => $materials]);
    }

    public function userActivityReport(Request $request)
    {
        $startDate = Carbon::parse($request->get('start_date'));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        $userSales = Order::whereBetween('created_at', [$startDate, $endDate])
            ->select('user_id', DB::raw('SUM(total_amount) as total_sales'), DB::raw('COUNT(id) as total_orders'))
            ->groupBy('user_id')
            ->with('user')
            ->get();

        return response()->json(['user_sales' => $userSales]);
    }

    public function monthlyReport(Request $request)
    {
        if (!$request->has('month')) {
            $month = Carbon::now()->month;
            $month = Carbon::now()->year;
        } else {
            $month = $request->get('month', now()->format('m'));
            $year = $request->get('year', now()->format('Y'));
        }


        $startDate = Carbon::createFromFormat('Y-m-d', "{$year}-{$month}-01");
        $endDate = $startDate->copy()->endOfMonth();


        $orders = Order::whereBetween('created_at', [$startDate, $endDate])->get();


        $dailyReport = [];


        foreach ($startDate->daysInMonth as $day) {
            $currentDate = $startDate->copy()->day($day);
            $dailyOrders = $orders->filter(function ($order) use ($currentDate) {
                return $order->created_at->isSameDay($currentDate);
            });


            $totalSubTotal = $dailyOrders->sum('total_amount'); // Adjust if you have a separate column for subtotal
            $totalServices = $dailyOrders->sum('service');
            $totalTax = $dailyOrders->sum('tax');
            $totalRevenue = $totalSubTotal + $totalServices + $totalTax; // Adjust as necessary


            $paymentBreakdown = Payment::select('payment_method_id', \DB::raw('SUM(amount) as total_amount'))
                ->whereIn('order_id', $dailyOrders->pluck('id'))
                ->groupBy('payment_method_id')
                ->get()
                ->keyBy('payment_method_id');


            $dailyReport[$currentDate->toDateString()] = [
                'total_sub_total' => $totalSubTotal,
                'total_services' => $totalServices,
                'total_tax' => $totalTax,
                'total_revenue' => $totalRevenue,
                'total_payment_method_1' => $paymentBreakdown->get(1)->total_amount ?? 0, // Replace 1 with actual method ID
                'total_payment_method_2' => $paymentBreakdown->get(2)->total_amount ?? 0, // Replace 2 with actual method ID
                // Add more payment methods as needed
            ];
        }

        // Step 2.9: Return the monthly report as a JSON response
        return response()->json($dailyReport);
    }

    
}
