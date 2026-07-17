<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shift;
use App\Services\ReportingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    protected $reportingService;

    public function __construct(ReportingService $reportingService)
    {
        $this->reportingService = $reportingService;
    }

    public function stockValuation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'as_of_date' => 'nullable|date',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->reportingService->generateStockValuationReport($validated['as_of_date'] ?? null),
            'message' => 'Stock valuation report generated successfully',
        ]);
    }

    public function inventoryMovement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'material_id' => 'nullable|exists:materials,id',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->reportingService->generateInventoryMovementReport(
                $validated['start_date'],
                $validated['end_date'],
                $validated['material_id'] ?? null,
            ),
            'message' => 'Inventory movement report generated successfully',
        ]);
    }

    public function stockAging(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->reportingService->generateStockAgingReport(),
            'message' => 'Stock aging report generated successfully',
        ]);
    }

    public function wasteReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->reportingService->generateWasteReport($validated['start_date'], $validated['end_date']),
            'message' => 'Waste report generated successfully',
        ]);
    }

    public function costAnalysis(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->reportingService->generateCostAnalysisReport($validated['start_date'], $validated['end_date']),
            'message' => 'Cost analysis report generated successfully',
        ]);
    }

    public function profitability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->reportingService->generateProfitabilityReport($validated['start_date'], $validated['end_date']),
            'message' => 'Profitability report generated successfully',
        ]);
    }

    public function dashboard(): JsonResponse
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();
        $orders = Order::whereBetween('created_at', [$start, $end])
            ->with(['orderItems.product', 'payment'])
            ->get();
        $completedOrders = $orders->where('status', 'completed');
        $uniqueCustomers = $completedOrders->pluck('user_id')->unique()->count();
        $topProducts = $completedOrders
            ->flatMap->orderItems
            ->groupBy('product_id')
            ->map(fn ($items) => [
                'product_id' => $items->first()->product_id,
                'product_name' => $items->first()->product->name,
                'total_quantity' => $items->sum('quantity'),
                'total_revenue' => $items->sum('total_amount'),
            ])
            ->sortByDesc('total_revenue')
            ->take(10)
            ->values();

        return response()->json([
            'success' => true,
            'data' => array_merge($this->reportingService->generateDashboardSummary(), [
                'sales_metrics' => [
                    'total_sales' => $completedOrders->sum('total_amount'),
                    'total_orders' => $completedOrders->count(),
                    'total_canceled_orders' => $orders->where('status', 'canceled')->count(),
                    'average_order_value' => $completedOrders->avg('total_amount') ?? 0,
                    'unique_customers' => $uniqueCustomers,
                    'active_users' => $uniqueCustomers,
                ],
                'top_selling_products' => $topProducts,
                'daily_sales_trend' => $completedOrders
                    ->groupBy(fn ($order) => $order->created_at->toDateString())
                    ->map(fn ($dailyOrders, $date) => ['date' => $date, 'total_sales' => $dailyOrders->sum('total_amount')])
                    ->values(),
                'payment_method_breakdown' => $completedOrders
                    ->pluck('payment')
                    ->filter()
                    ->groupBy('payment_method_id')
                    ->map(fn ($payments) => [
                        'payment_method_id' => $payments->first()->payment_method_id,
                        'total_amount' => $payments->sum('amount'),
                    ])
                    ->values(),
                'inventory_levels' => Material::whereColumn('quantity', '<=', 'reorder_point')
                    ->get(['id', 'name', 'quantity', 'reorder_point']),
            ]),
            'message' => 'Dashboard summary generated successfully',
        ]);
    }

    public function reportTypes(): JsonResponse
    {
        $reportTypes = [
            'stock_valuation' => ['method' => 'stockValuation', 'name' => 'Stock Valuation Report', 'description' => 'Current inventory value using FIFO methodology', 'parameters' => ['as_of_date' => 'optional']],
            'inventory_movement' => ['method' => 'inventoryMovement', 'name' => 'Inventory Movement Report', 'description' => 'Detailed inventory transactions and movements', 'parameters' => ['start_date' => 'required', 'end_date' => 'required', 'material_id' => 'optional']],
            'stock_aging' => ['method' => 'stockAging', 'name' => 'Stock Aging Report', 'description' => 'Inventory aging analysis by receipt date', 'parameters' => []],
            'waste' => ['method' => 'wasteReport', 'name' => 'Waste Tracking Report', 'description' => 'Expired items and waste adjustments', 'parameters' => ['start_date' => 'required', 'end_date' => 'required']],
            'cost_analysis' => ['method' => 'costAnalysis', 'name' => 'Cost Analysis Report', 'description' => 'Recipe cost calculations and trends', 'parameters' => ['start_date' => 'required', 'end_date' => 'required']],
            'profitability' => ['method' => 'profitability', 'name' => 'Profitability Report', 'description' => 'Product profitability analysis', 'parameters' => ['start_date' => 'required', 'end_date' => 'required']],
            'dashboard_summary' => ['method' => 'dashboard', 'name' => 'Dashboard Summary', 'description' => 'Inventory and costing summary for the reporting dashboard', 'parameters' => []],
            'sales' => ['method' => 'salesReport', 'name' => 'Sales Report', 'description' => 'Completed-order revenue, tax, service, and payment totals', 'parameters' => ['start_date' => 'required', 'end_date' => 'optional']],
            'inventory' => ['method' => 'inventoryReport', 'name' => 'Inventory Report', 'description' => 'Material quantities used and remaining', 'parameters' => []],
            'user_activity' => ['method' => 'userActivityReport', 'name' => 'User Activity Report', 'description' => 'Sales and order totals grouped by user', 'parameters' => ['start_date' => 'required', 'end_date' => 'optional']],
            'monthly' => ['method' => 'monthlyReport', 'name' => 'Monthly Sales Report', 'description' => 'Daily sales and payment totals for a month', 'parameters' => ['month' => 'optional', 'year' => 'optional']],
            'top_selling_products' => ['method' => 'sellingProducts', 'name' => 'Top-Selling Products Report', 'description' => 'Product quantity and revenue rankings for a date range', 'parameters' => ['start_date' => 'required', 'end_date' => 'optional']],
            'customer_purchase_history' => ['method' => 'customerPurchaseHistory', 'name' => 'Customer Purchase History', 'description' => 'Customer orders, spend, averages, and frequent products', 'parameters' => ['customer_id' => 'required', 'start_date' => 'optional', 'end_date' => 'optional']],
            'sales_by_category' => ['method' => 'salesByCategory', 'name' => 'Sales by Category Report', 'description' => 'Sales quantities and revenue grouped by product category', 'parameters' => ['start_date' => 'required', 'end_date' => 'optional']],
            'refunds_and_returns' => ['method' => 'refundsAndReturns', 'name' => 'Refunds and Returns Report', 'description' => 'Refund totals, reasons, and most-refunded products', 'parameters' => ['start_date' => 'required', 'end_date' => 'optional']],
            'monthly_sales_growth' => ['method' => 'monthlySalesGrowth', 'name' => 'Monthly Sales Growth Report', 'description' => 'Monthly sales totals and month-over-month growth', 'parameters' => ['months' => 'optional', 'end_date' => 'optional']],
            'user_engagement' => ['method' => 'userEngagement', 'name' => 'User Engagement Report', 'description' => 'Customer activity, order frequency, and order values', 'parameters' => ['start_date' => 'required', 'end_date' => 'optional']],
            'inventory_turnover' => ['method' => 'inventoryTurnover', 'name' => 'Inventory Turnover Report', 'description' => 'Material usage, turnover rates, and days to sell', 'parameters' => ['start_date' => 'required', 'end_date' => 'optional']],
            'payment_method_performance' => ['method' => 'paymentMethodPerformance', 'name' => 'Payment Method Performance Report', 'description' => 'Payment transaction counts, totals, averages, and percentages', 'parameters' => ['start_date' => 'required', 'end_date' => 'optional']],
            'shifts' => ['method' => 'getShifts', 'name' => 'Shift Revenue Report', 'description' => 'Completed-order revenue grouped by shift', 'parameters' => ['start_date' => 'required', 'end_date' => 'required']],
            'monthly_cost' => ['method' => 'monthlyCost', 'name' => 'Monthly Cost Report', 'description' => 'FIFO material consumption cost for a month', 'parameters' => ['month' => 'optional']],
            'product_cost_comparison' => ['method' => 'productCostComparison', 'name' => 'Product Cost Comparison', 'description' => 'Current and previous month product costs', 'parameters' => ['product_id' => 'required']],
        ];

        return response()->json([
            'success' => true,
            'data' => $reportTypes,
            'message' => 'Available report types retrieved successfully',
        ]);
    }

    public function salesReport(Request $request)
    {
        $startDate = Carbon::parse($request->get('start_date'))->startOfDay();

        $endDate = $request->has('end_date')
            ? Carbon::parse($request->get('end_date'))->endOfDay()
            : Carbon::now()->endOfDay();

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
            'payment_breakdown' => $paymentBreakdown,
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
            ->where('status', 'completed')
            ->select('user_id', DB::raw('SUM(total_amount) as total_sales'), DB::raw('COUNT(id) as total_orders'))
            ->groupBy('user_id')
            ->with('user')
            ->get();

        return response()->json(['user_sales' => $userSales]);
    }

    public function monthlyReport(Request $request)
    {
        $validated = $request->validate([
            'month' => 'nullable|integer|between:1,12',
            'year' => 'nullable|integer|between:2000,2100',
        ]);
        $month = $validated['month'] ?? now()->month;
        $year = $validated['year'] ?? now()->year;

        $startDate = Carbon::createFromFormat('Y-m-d', "{$year}-{$month}-01")->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();
        $orders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->get();
        $ordersByDate = $orders->groupBy(fn ($order) => $order->created_at->toDateString());
        $paymentsByOrder = Payment::whereIn('order_id', $orders->pluck('id'))->get()->groupBy('order_id');

        $dailyReport = collect(range(1, $startDate->daysInMonth))->mapWithKeys(function ($day) use ($startDate, $ordersByDate, $paymentsByOrder) {
            $currentDate = $startDate->copy()->day($day);
            $dailyOrders = $ordersByDate->get($currentDate->toDateString(), collect());
            $paymentBreakdown = $dailyOrders
                ->flatMap(fn ($order) => $paymentsByOrder->get($order->id, collect()))
                ->groupBy('payment_method_id')
                ->map->sum('amount');

            return [$currentDate->toDateString() => [
                'total_sub_total' => $dailyOrders->sum('sub_total'),
                'total_services' => $dailyOrders->sum('service'),
                'total_tax' => $dailyOrders->sum('tax'),
                'total_revenue' => $dailyOrders->sum('total_amount'),
                'total_payment_method_1' => $paymentBreakdown->get(1, 0),
                'total_payment_method_2' => $paymentBreakdown->get(2, 0),
            ]];
        });

        return response()->json($dailyReport);
    }

    public function sellingProducts(Request $request)
    {
        $startDate = Carbon::parse($request->get('start_date'))->startOfDay();
        $endDate = $request->has('end_date')
            ? Carbon::parse($request->get('end_date'))->endOfDay()
            : Carbon::now()->endOfDay();

        $topProducts = Order::with('orderItems.product.category')
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
                    'category' => $group->first()->product->category->name,
                    'total_quantity' => $group->sum('quantity'),
                    'total_revenue' => $group->sum('total_amount'),
                ];
            })
            ->sortByDesc('total_quantity');

        return response()->json($topProducts);
    }

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

    public function monthlySalesGrowth(Request $request)
    {
        $validated = $request->validate([
            'months' => 'nullable|integer|between:1,120',
            'end_date' => 'nullable|date',
        ]);
        $months = $validated['months'] ?? 12;
        $endDate = Carbon::parse($validated['end_date'] ?? now())->endOfMonth();
        $startDate = $endDate->copy()->subMonths($months - 1)->startOfMonth();
        $salesByMonth = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_amount) as sales")
            ->groupBy('month')
            ->pluck('sales', 'month');
        $previousMonthSales = 0;

        $monthlySales = collect(range(0, $months - 1))->map(function ($offset) use ($startDate, $salesByMonth, &$previousMonthSales) {
            $month = $startDate->copy()->addMonths($offset)->format('Y-m');
            $sales = (float) $salesByMonth->get($month, 0);
            $report = [
                'month' => $month,
                'sales' => $sales,
                'growth_percentage' => $previousMonthSales > 0
                    ? round((($sales - $previousMonthSales) / $previousMonthSales) * 100, 2)
                    : 0,
                'previous_month_sales' => $previousMonthSales,
            ];
            $previousMonthSales = $sales;

            return $report;
        });

        return response()->json([
            'monthly_sales' => $monthlySales,
        ]);
    }

    public function userEngagement(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date'] ?? now());
        $metrics = Order::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('COUNT(*) as total_orders, COUNT(DISTINCT user_id) as active_users, COALESCE(SUM(total_amount), 0) as total_revenue, COALESCE(AVG(total_amount), 0) as average_order_value')
            ->first();

        $userFrequency = Order::whereBetween('created_at', [$startDate, $endDate])
            ->select('user_id', DB::raw('COUNT(*) as order_count'), DB::raw('SUM(total_amount) as total_spent'))
            ->groupBy('user_id')
            ->orderBy('order_count', 'desc')
            ->take(10)
            ->get();

        return response()->json([
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'active_users' => $metrics->active_users,
            'total_orders' => $metrics->total_orders,
            'total_revenue' => $metrics->total_revenue,
            'average_order_value' => $metrics->average_order_value,
            'orders_per_user' => $metrics->active_users > 0 ? $metrics->total_orders / $metrics->active_users : 0,
            'top_users_by_frequency' => $userFrequency,
        ]);
    }

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

                $startInventory = $material->quantity + $usedQuantity;
                $endInventory = $material->quantity;
                $averageInventory = ($startInventory + $endInventory) / 2;

                $turnoverRate = $averageInventory > 0 ? $usedQuantity / $averageInventory : 0;

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
                    'percentage' => 0,
                ];
            });

        $totalAmount = $paymentMethods->sum('total_amount');

        $paymentMethods = $paymentMethods->map(function ($method) use ($totalAmount) {
            $method['percentage'] = $totalAmount > 0
                ? round(($method['total_amount'] / $totalAmount) * 100, 2)
                : 0;

            return $method;
        });

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

    public function getShifts(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $shifts = Shift::whereBetween('start_time', [$request->start_date, $request->end_date])
            ->with(['orders' => function ($query) {
                $query->where('status', 'completed');
            }])
            ->get();

        return response()->json(
            $shifts->map(fn ($shift) => [
                'shift_id' => $shift->id,
                'user_id' => $shift->user_id,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'total_revenue' => $shift->orders->sum('total_amount'),
            ])
        );
    }

    public function monthlyCost(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));

        $materials = Material::with(['transactions' => function ($query) use ($month) {
            $query->whereYear('created_at', substr($month, 0, 4))
                ->whereMonth('created_at', substr($month, 5, 2));
        }])->get();

        $totalCost = 0;
        foreach ($materials as $material) {
            $consumption = $material->transactions
                ->where('type', 'consumption')
                ->sum('quantity');

            $totalCost += $material->calculateFIFOCost($consumption);
        }

        return response()->json([
            'total_cost' => $totalCost,
        ]);
    }

    public function productCostComparison(Request $request, $productId)
    {
        $product = Product::with('materials')->findOrFail($productId);

        $currentMonth = now()->format('Y-m');
        $lastMonth = now()->subMonth()->format('Y-m');

        $current = $product->monthlyCostComparison($currentMonth);
        $last = $product->monthlyCostComparison($lastMonth);

        return response()->json([
            'product' => $product->name,
            'current_month' => $current,
            'last_month' => $last,
            'comparison' => [
                'unit_cost_diff' => $current['fifo_cost'] - $last['fifo_cost'],
                'total_cost_diff' => $current['total_cost'] - $last['total_cost'],
            ],
        ]);
    }
}
