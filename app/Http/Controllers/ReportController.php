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
    /**
     * Generate a sales report for a specified date range.
     *
     * This method retrieves all completed orders within the specified date range,
     * calculates total revenue, total tax, and total service fees, and provides
     * a breakdown of payment methods used.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function salesReport(Request $request)
    {
        $startDate = Carbon::parse($request->get('start_date'));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        $orders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
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

    /**
     * Generate an inventory report showing the quantity of materials used and remaining.
     *
     * This method retrieves all materials and calculates the used quantity based on
     * the recipes associated with each material. It returns the remaining quantity
     * and the used quantity for each material.
     *
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * Generate a user activity report showing sales per user within a date range.
     *
     * This method retrieves orders within the specified date range, groups them by
     * user ID, and calculates total sales and total orders for each user. It returns
     * the aggregated data for user activity analysis.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * Generate a monthly report showing daily sales data for a specified month.
     *
     * This method retrieves orders for a specified month and year, calculates daily
     * totals for revenue, services, and taxes, and provides a breakdown of payment
     * methods used for each day. It returns a structured report for the month.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function monthlyReport(Request $request)
    {
        if (!$request->has('month')) {
            $month = Carbon::now()->month;
            $year = Carbon::now()->year;
        } else {
            $month = $request->get('month', now()->format('m'));
            $year = $request->get('year', now()->format('Y'));
        }

        $startDate = Carbon::createFromFormat('Y-m-d', "{$year}-{$month}-01");
        $endDate = $startDate->copy()->endOfMonth();

        $orders = Order::whereBetween('created_at', [$startDate, $endDate])->get();

        $dailyReport = [];

        foreach (range(1, $startDate->daysInMonth) as $day) {
            $currentDate = $startDate->copy()->day($day);
            $dailyOrders = $orders->filter(function ($order) use ($currentDate) {
                return $order->created_at->isSameDay($currentDate);
            });

            $totalSubTotal = $dailyOrders->sum('total_amount');
            $totalServices = $dailyOrders->sum('service');
            $totalTax = $dailyOrders->sum('tax');
            $totalRevenue = $totalSubTotal + $totalServices + $totalTax;

            $paymentBreakdown = Payment::select('payment_method_id', DB::raw('SUM(amount) as total_amount'))
                ->whereIn('order_id', $dailyOrders->pluck('id'))
                ->groupBy('payment_method_id')
                ->get()
                ->keyBy('payment_method_id');

            $dailyReport[$currentDate->toDateString()] = [
                'total_sub_total' => $totalSubTotal,
                'total_services' => $totalServices,
                'total_tax' => $totalTax,
                'total_revenue' => $totalRevenue,
                'total_payment_method_1' => $paymentBreakdown->get(1)->total_amount ?? 0,
                'total_payment_method_2' => $paymentBreakdown->get(2)->total_amount ?? 0,
            ];
        }

        return response()->json($dailyReport);
    }

    /**
     * Get the top-selling products within a specified date range.
     *
     * This method retrieves orders within the specified date range, aggregates the
     * sales data for each product, and returns the top products based on total revenue.
     * It helps in identifying which products are performing well.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function topSellingProducts(Request $request)
    {
        $startDate = Carbon::parse($request->get('start_date'));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        $topProducts = Order::with('orderItems.product')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->get()
            ->flatMap(function ($order) {
                return $order->orderItems;
            })
            ->groupBy('product_id')
            ->map(function ($group) {
                return [
                    'product_id' => $group->first()->product_id,
                    'product_name' => $group->first()->product->name,
                    'total_quantity' => $group->sum('quantity'),
                    'total_revenue' => $group->sum('total_amount'),
                ];
            })
            ->sortByDesc('total_quantity');
        // ->take(10); // Get top 10 products

        return response()->json($topProducts);
    }

    /**
     * Get customer purchase history report.
     *
     * This method retrieves all orders made by a specific customer within a specified
     * date range, calculates total spent, average order value, and identifies the
     * most frequently purchased products. It provides insights into customer behavior.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function customerPurchaseHistory(Request $request)
    {
        $customerId = $request->get('customer_id');
        $startDate = Carbon::parse($request->get('start_date', Carbon::now()->subMonths(6)));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        $orders = Order::where('customer_id', $customerId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('orderItems.product', 'payments')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalSpent = $orders->sum('total_amount');
        $orderCount = $orders->count();
        $averageOrderValue = $orderCount > 0 ? $totalSpent / $orderCount : 0;

        $frequentProducts = $orders->flatMap(function ($order) {
            return $order->orderItems;
        })
            ->groupBy('product_id')
            ->map(function ($group) {
                return [
                    'product_id' => $group->first()->product_id,
                    'product_name' => $group->first()->product->name,
                    'quantity' => $group->sum('quantity'),
                ];
            })
            ->sortByDesc('quantity')
            ->take(5)
            ->values();

        return response()->json([
            'customer_id' => $customerId,
            'total_spent' => $totalSpent,
            'order_count' => $orderCount,
            'average_order_value' => $averageOrderValue,
            'orders' => $orders,
            'frequent_products' => $frequentProducts,
        ]);
    }

    /**
     * Get sales by category report.
     *
     * This method retrieves orders within a specified date range, groups the sales data
     * by product category, and returns total quantities and revenues for each category.
     * It helps in understanding category performance.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function salesByCategory(Request $request)
    {
        $startDate = Carbon::parse($request->get('start_date'));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        $categorySales = Order::whereBetween('created_at', [$startDate, $endDate])
            ->with('orderItems.product.category')
            ->get()
            ->flatMap(function ($order) {
                return $order->orderItems;
            })
            ->groupBy(function ($item) {
                return $item->product->category->name;
            })
            ->map(function ($group) {
                return [
                    'category' => $group->first()->product->category->name,
                    'total_quantity' => $group->sum('quantity'),
                    'total_revenue' => $group->sum('total_amount'),
                    'order_count' => $group->count(),
                ];
            })
            ->sortByDesc('total_revenue')
            ->values();

        return response()->json([
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'category_sales' => $categorySales,
        ]);
    }

    /**
     * Get refunds and returns report.
     *
     * This method retrieves orders marked as refunded within a specified date range,
     * aggregates refund amounts, and provides a breakdown of refunds by reason and
     * the most refunded products. It helps in analyzing return trends.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refundsAndReturns(Request $request)
    {
        $startDate = Carbon::parse($request->get('start_date'));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        $refunds = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'refunded')
            ->with('orderItems.product', 'user')
            ->get();

        $totalRefundAmount = $refunds->sum('total_amount');
        $refundCount = $refunds->count();

        $refundsByReason = $refunds->groupBy('refund_reason')
            ->map(function ($group, $reason) {
                return [
                    'reason' => $reason ?: 'Not specified',
                    'count' => $group->count(),
                    'total_amount' => $group->sum('total_amount'),
                ];
            })
            ->values();

        $mostRefundedProducts = $refunds->flatMap(function ($order) {
            return $order->orderItems;
        })
            ->groupBy('product_id')
            ->map(function ($group) {
                return [
                    'product_id' => $group->first()->product_id,
                    'product_name' => $group->first()->product->name,
                    'refund_count' => $group->count(),
                    'total_amount' => $group->sum('total_amount'),
                ];
            })
            ->sortByDesc('refund_count')
            ->take(10)
            ->values();

        return response()->json([
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'total_refund_amount' => $totalRefundAmount,
            'refund_count' => $refundCount,
            'refunds_by_reason' => $refundsByReason,
            'most_refunded_products' => $mostRefundedProducts,
        ]);
    }

    /**
     * Get monthly sales growth report.
     *
     * This method calculates sales growth over a specified number of months,
     * providing insights into sales trends and performance over time. It returns
     * monthly sales data along with growth percentages compared to the previous month.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function monthlySalesGrowth(Request $request)
    {
        $months = $request->get('months', 12);
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));
        $startDate = $endDate->copy()->subMonths($months - 1)->startOfMonth();

        $monthlySales = [];
        $previousMonthSales = 0;

        for ($i = 0; $i < $months; $i++) {
            $currentMonth = $startDate->copy()->addMonths($i);
            $monthStart = $currentMonth->copy()->startOfMonth();
            $monthEnd = $currentMonth->copy()->endOfMonth();

            $sales = Order::whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('total_amount');

            $growth = $previousMonthSales > 0
                ? (($sales - $previousMonthSales) / $previousMonthSales) * 100
                : 0;

            $monthlySales[] = [
                'month' => $monthStart->format('Y-m'),
                'sales' => $sales,
                'growth_percentage' => round($growth, 2),
                'previous_month_sales' => $previousMonthSales,
            ];

            $previousMonthSales = $sales;
        }

        return response()->json([
            'monthly_sales' => $monthlySales,
        ]);
    }

    /**
     * Get user engagement report.
     *
     * This method analyzes user engagement metrics such as active users, total orders,
     * and average order value within a specified date range. It helps in understanding
     * user behavior and engagement levels.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function userEngagement(Request $request)
    {
        $startDate = Carbon::parse($request->get('start_date'));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        $users = Order::whereBetween('created_at', [$startDate, $endDate])
            ->select('customer_id')
            ->distinct()
            ->count();

        $orders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $totalRevenue = Order::whereBetween('created_at', [$startDate, $endDate])
            ->sum('total_amount');

        $averageOrderValue = $orders > 0 ? $totalRevenue / $orders : 0;

        $ordersPerUser = $users > 0 ? $orders / $users : 0;

        $userFrequency = Order::whereBetween('created_at', [$startDate, $endDate])
            ->select('customer_id', DB::raw('COUNT(*) as order_count'), DB::raw('SUM(total_amount) as total_spent'))
            ->groupBy('customer_id')
            ->orderBy('order_count', 'desc')
            ->take(10)
            ->get();

        return response()->json([
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'active_users' => $users,
            'total_orders' => $orders,
            'total_revenue' => $totalRevenue,
            'average_order_value' => $averageOrderValue,
            'orders_per_user' => $ordersPerUser,
            'top_users_by_frequency' => $userFrequency,
        ]);
    }

    /**
     * Get inventory turnover report.
     *
     * This method calculates the turnover rate of materials based on their usage
     * in orders within a specified date range. It provides insights into inventory
     * management and helps in understanding how quickly materials are being used.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function inventoryTurnover(Request $request)
    {
        $startDate = Carbon::parse($request->get('start_date'));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        $materials = Material::with(['recipes.product.orderItems' => function ($query) use ($startDate, $endDate) {
            $query->whereHas('order', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            });
        }])
            ->get()
            ->map(function ($material) use ($startDate, $endDate) {
                $usedQuantity = $material->recipes->sum(function ($recipe) {
                    return $recipe->product->orderItems->sum('quantity') * $recipe->pivot->quantity;
                });

                // Calculate average inventory
                $startInventory = $material->quantity + $usedQuantity; // Estimate starting inventory
                $endInventory = $material->quantity;
                $averageInventory = ($startInventory + $endInventory) / 2;

                // Calculate turnover rate
                $turnoverRate = $averageInventory > 0 ? $usedQuantity / $averageInventory : 0;

                // Calculate days to sell inventory
                $daysBetween = $startDate->diffInDays($endDate) ?: 1;
                $daysToSell = $turnoverRate > 0 ? $daysBetween / $turnoverRate : 0;

                return [
                    'material_id' => $material->id,
                    'material_name' => $material->name,
                    'start_inventory' => $startInventory,
                    'end_inventory' => $endInventory,
                    'used_quantity' => $usedQuantity,
                    'average_inventory' => $averageInventory,
                    'turnover_rate' => round($turnoverRate, 2),
                    'days_to_sell' => round($daysToSell, 2),
                ];
            });

        return response()->json([
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'materials' => $materials,
        ]);
    }

    /**
     * Get payment method performance report.
     *
     * This method analyzes the performance of different payment methods used in orders
     * within a specified date range. It provides insights into transaction counts,
     * total amounts, and average amounts for each payment method.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function paymentMethodPerformance(Request $request)
    {
        $startDate = Carbon::parse($request->get('start_date'));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        $paymentMethods = Payment::whereBetween('created_at', [$startDate, $endDate])
            ->select(
                'payment_method_id',
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('AVG(amount) as average_amount')
            )
            ->with('paymentMethod')
            ->groupBy('payment_method_id')
            ->get()
            ->map(function ($payment) {
                return [
                    'payment_method_id' => $payment->payment_method_id,
                    'payment_method_name' => $payment->paymentMethod->name,
                    'transaction_count' => $payment->transaction_count,
                    'total_amount' => $payment->total_amount,
                    'average_amount' => round($payment->average_amount, 2),
                    'percentage' => 0, // Will be calculated below
                ];
            });

        $totalAmount = $paymentMethods->sum('total_amount');

        // Calculate percentage for each payment method
        $paymentMethods = $paymentMethods->map(function ($method) use ($totalAmount) {
            $method['percentage'] = $totalAmount > 0
                ? round(($method['total_amount'] / $totalAmount) * 100, 2)
                : 0;
            return $method;
        });

        // Get daily breakdown
        $dailyBreakdown = Payment::whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(created_at) as date'),
                'payment_method_id',
                DB::raw('SUM(amount) as total_amount')
            )
            ->groupBy('date', 'payment_method_id')
            ->get()
            ->groupBy('date');

        return response()->json([
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'payment_methods' => $paymentMethods,
            'total_amount' => $totalAmount,
            'daily_breakdown' => $dailyBreakdown,
        ]);
    }
}
