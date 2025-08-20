<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierPerformanceMetric;
use App\Models\Suppl
ier App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SupplierPerformanceController extends Controller
{
    /**
     * Get comprehensive supplier performance metrics
     */
    public function getPerformanceMetrics(Supplier $supplier, Request $request): JsonResponse
    {
        try {
            $days = $request->get('days', 30);
            $includeHistory = $request->boolean('include_history', false);

            // Get current performance metrics
            $currentMetrics = $supplier->calculatePerformanceMetrics($days);

            // Get communication stats
            $communicationStats = $supplier->getCommunicationStats($days);

            // Get recent performance history if requested
            $performanceHistory = [];
            if ($includeHistory) {
                $performanceHistory = $supplier->performanceMetrics()
                    ->orderBy('metric_period', 'desc')
                    ->limit(12)
                    ->get()
                    ->map(function ($metric) {
                        return [
                            'period' => $metric->metric_period->format('Y-m'),
                            'overall_rating' => $metric->overall_rating,
                            'on_time_delivery_rate' => $metric->on_time_delivery_rate,
                            'order_completion_rate' => $metric->order_completion_rate,
                            'quality_score' => $metric->quality_score,
                            'communication_score' => $metric->communication_score,
                            'total_order_value' => $metric->total_order_value
                        ];
                    });
            }

            // Get recent purchase orders
            $recentOrders = $supplier->purchaseOrders()
                ->with(['items.material:id,name'])
                ->orderBy('order_date', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'po_number' => $order->po_number,
                        'status' => $order->status,
                        'order_date' => $order->order_date->toDateString(),
                        'expected_delivery_date' => $order->expected_delivery_date->toDateString(),
                        'actual_delivery_date' => $order->actual_delivery_date?->toDateString(),
                        'final_amount' => $order->final_amount,
                        'is_overdue' => $order->is_overdue,
                        'delivery_delay_days' => $order->delivery_delay_days,
                        'completion_percentage' => $order->completion_percentage,
                        'items_count' => $order->items->count()
                    ];
                });

            // Get recent communications
            $recentCommunications = $supplier->communications()
                ->with('initiatedBy:id,name')
                ->orderBy('communication_date', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($comm) {
                    return [
                        'id' => $comm->id,
                        'type' => $comm->communication_type,
                        'subject' => $comm->subject,
                        'method' => $comm->method,
                        'communication_date' => $comm->communication_date->toISOString(),
                        'response_received' => $comm->response_received,
                        'response_status' => $comm->response_status,
                        'formatted_response_time' => $comm->formatted_response_time,
                        'satisfaction_rating' => $comm->satisfaction_rating,
                        'initiated_by' => $comm->initiatedBy?->name
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'supplier' => [
                        'id' => $supplier->id,
                        'name' => $supplier->name,
                        'is_active' => $supplier->is_active,
                        'rating' => $supplier->rating,
                        'performance_grade' => $supplier->getPerformanceGrade(),
                        'is_reliable' => $supplier->isReliable()
                    ],
                    'current_metrics' => $currentMetrics,
                    'communication_stats' => $communicationStats,
                    'performance_history' => $performanceHistory,
                    'recent_orders' => $recentOrders,
                    'recent_communications' => $recentCommunications,
                    'period_days' => $days,
                    'generated_at' => now()->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch supplier performance metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get supplier performance comparison
     */
    public function getPerformanceComparison(Request $request): JsonResponse
    {
        try {
            $days = $request->get('days', 30);
            $limit = $request->get('limit', 10);

            $suppliers = Supplier::active()
                ->with(['performanceMetrics' => function ($query) use ($days) {
                    $query->where('metric_period', '>=', now()->subDays($days)->startOfMonth())
                          ->orderBy('metric_period', 'desc');
                }])
                ->get()
                ->map(function ($supplier) use ($days) {
                    $metrics = $supplier->calculatePerformanceMetrics($days);
                    return [
                        'id' => $supplier->id,
                        'name' => $supplier->name,
                        'overall_rating' => $metrics['overall_rating'],
                        'on_time_delivery_rate' => $metrics['on_time_delivery_rate'],
                        'completion_rate' => $metrics['completion_rate'],
                        'total_orders' => $metrics['total_orders'],
                        'total_order_value' => $metrics['total_order_value'],
                        'quality_score' => $metrics['quality_score'],
                        'performance_grade' => $supplier->getPerformanceGrade(),
                        'is_reliable' => $supplier->isReliable()
                    ];
                })
                ->sortByDesc('overall_rating')
                ->take($limit)
                ->values();

            // Calculate industry averages
            $industryAverages = [
                'overall_rating' => $suppliers->avg('overall_rating'),
                'on_time_delivery_rate' => $suppliers->avg('on_time_delivery_rate'),
                'completion_rate' => $suppliers->avg('completion_rate'),
                'quality_score' => $suppliers->avg('quality_score')
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'suppliers' => $suppliers,
                    'industry_averages' => $industryAverages,
                    'total_suppliers' => $suppliers->count(),
                    'reliable_suppliers' => $suppliers->where('is_reliable', true)->count(),
                    'period_days' => $days
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch supplier performance comparison',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update supplier performance metrics for a specific period
     */
    public function updatePerformanceMetrics(Supplier $supplier, Request $request): JsonResponse
    {
        try {
            $period = $request->has('period')
                ? Carbon::parse($request->period)->startOfMonth()
                : now()->startOfMonth();

            $metric = $supplier->updatePerformanceMetrics($period);

            return response()->json([
                'success' => true,
                'data' => $metric,
                'message' => 'Performance metrics updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update performance metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update performance metrics for all suppliers
     */
    public function bulkUpdatePerformanceMetrics(Request $request): JsonResponse
    {
        try {
            $period = $request->has('period')
                ? Carbon::parse($request->period)->startOfMonth()
                : now()->startOfMonth();

            $suppliers = Supplier::active()->get();
            $updatedCount = 0;

            foreach ($suppliers as $supplier) {
                $supplier->updatePerformanceMetrics($period);
                $updatedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "Performance metrics updated for {$updatedCount} suppliers",
                'data' => [
                    'updated_suppliers' => $updatedCount,
                    'period' => $period->format('Y-m')
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk update performance metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get supplier delivery performance analysis
     */
    public function getDeliveryPerformance(Supplier $supplier, Request $request): JsonResponse
    {
        try {
            $days = $request->get('days', 90);
            $startDate = now()->subDays($days);

            $orders = $supplier->purchaseOrders()
                ->where('order_date', '>=', $startDate)
                ->whereNotNull('actual_delivery_date')
                ->get();

            $totalOrders = $orders->count();
            $onTimeOrders = $orders->where('is_delivered_on_time', true)->count();
            $lateOrders = $orders->where('is_delivered_on_time', false)->count();

            $deliveryStats = [
                'total_deliveries' => $totalOrders,
                'on_time_deliveries' => $onTimeOrders,
                'late_deliveries' => $lateOrders,
                'on_time_rate' => $totalOrders > 0 ? ($onTimeOrders / $totalOrders) * 100 : 0,
                'average_delay_days' => $orders->avg('delivery_delay_days') ?? 0,
                'max_delay_days' => $orders->max('delivery_delay_days') ?? 0,
                'min_delay_days' => $orders->min('delivery_delay_days') ?? 0
            ];

            // Group by month for trend analysis
            $monthlyTrends = $orders->groupBy(function ($order) {
                return $order->order_date->format('Y-m');
            })->map(function ($monthOrders, $month) {
                $total = $monthOrders->count();
                $onTime = $monthOrders->where('is_delivered_on_time', true)->count();

                return [
                    'month' => $month,
                    'total_orders' => $total,
                    'on_time_orders' => $onTime,
                    'on_time_rate' => $total > 0 ? ($onTime / $total) * 100 : 0,
                    'average_delay' => $monthOrders->avg('delivery_delay_days') ?? 0
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'supplier' => [
                        'id' => $supplier->id,
                        'name' => $supplier->name
                    ],
                    'delivery_stats' => $deliveryStats,
                    'monthly_trends' => $monthlyTrends,
                    'period_days' => $days
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch delivery performance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get supplier communication history and analysis
     */
    public function getCommunicationAnalysis(Supplier $supplier, Request $request): JsonResponse
    {
        try {
            $days = $request->get('days', 90);
            $startDate = now()->subDays($days);

            $communications = $supplier->communications()
                ->where('communication_date', '>=', $startDate)
                ->with('initiatedBy:id,name')
                ->orderBy('communication_date', 'desc')
                ->get();

            $stats = [
                'total_communications' => $communications->count(),
                'by_type' => $communications->groupBy('communication_type')->map->count(),
                'by_method' => $communications->groupBy('method')->map->count(),
                'response_rate' => $communications->count() > 0
                    ? ($communications->where('response_received', true)->count() / $communications->count()) * 100
                    : 0,
                'average_response_time_hours' => $communications->where('response_received', true)->avg('response_time_hours') ?? 0,
                'average_satisfaction' => $communications->whereNotNull('satisfaction_rating')->avg('satisfaction_rating') ?? 0
            ];

            // Monthly communication trends
            $monthlyTrends = $communications->groupBy(function ($comm) {
                return $comm->communication_date->format('Y-m');
            })->map(function ($monthComms, $month) {
                $total = $monthComms->count();
                $responded = $monthComms->where('response_received', true)->count();

                return [
                    'month' => $month,
                    'total_communications' => $total,
                    'responded_communications' => $responded,
                    'response_rate' => $total > 0 ? ($responded / $total) * 100 : 0,
                    'average_response_time' => $monthComms->where('response_received', true)->avg('response_time_hours') ?? 0,
                    'average_satisfaction' => $monthComms->whereNotNull('satisfaction_rating')->avg('satisfaction_rating') ?? 0
                ];
            })->values();

            $communicationList = $communications->map(function ($comm) {
                return [
                    'id' => $comm->id,
                    'type' => $comm->communication_type,
                    'subject' => $comm->subject,
                    'method' => $comm->method,
                    'communication_date' => $comm->communication_date->toISOString(),
                    'response_received' => $comm->response_received,
                    'response_status' => $comm->response_status,
                    'response_time_hours' => $comm->response_time_hours,
                    'satisfaction_rating' => $comm->satisfaction_rating,
                    'initiated_by' => $comm->initiatedBy?->name
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'supplier' => [
                        'id' => $supplier->id,
                        'name' => $supplier->name
                    ],
                    'communication_stats' => $stats,
                    'monthly_trends' => $monthlyTrends,
                    'communications' => $communicationList,
                    'period_days' => $days
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch communication analysis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create supplier communication record
     */
    public function createCommunication(Supplier $supplier, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'communication_type' => 'required|in:' . implode(',', SupplierCommunication::TYPES),
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'method' => 'required|in:' . implode(',', SupplierCommunication::METHODS),
            'communication_date' => 'nullable|date',
            'notes' => 'nullable|string'
        ]);

        try {
            $communication = $supplier->communications()->create([
                ...$validated,
                'communication_date' => $validated['communication_date'] ?? now(),
                'initiated_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $communication,
                'message' => 'Communication record created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create communication record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update communication response
     */
    public function updateCommunicationResponse(SupplierCommunication $communication, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'satisfaction_rating' => 'nullable|numeric|min:1|max:5',
            'notes' => 'nullable|string'
        ]);

        try {
            $communication->markResponseReceived($validated['satisfaction_rating'] ?? null);

            if (isset($validated['notes'])) {
                $communication->update(['notes' => $validated['notes']]);
            }

            return response()->json([
                'success' => true,
                'data' => $communication->fresh(),
                'message' => 'Communication response updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update communication response',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}Communic
