<?php

namespace App\Http\Controllers;

use App\Models\StockAlert;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StockAlertController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Display a listing of stock alerts.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockAlert::with(['material', 'resolvedBy'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('resolved')) {
            if ($request->boolean('resolved')) {
                $query->resolved();
            } else {
                $query->unresolved();
            }
        }

        if ($request->has('type')) {
            $query->byType($request->input('type'));
        }

        if ($request->has('material_id')) {
            $query->byMaterial($request->input('material_id'));
        }

        if ($request->has('critical')) {
            if ($request->boolean('critical')) {
                $query->critical();
            }
        }

        if ($request->has('recent')) {
            $days = $request->input('recent', 7);
            $query->recent($days);
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        if ($sortBy === 'priority') {
            // Custom sorting by priority
            $query->orderByRaw("
                CASE alert_type
                    WHEN 'out_of_stock' THEN 5
                    WHEN 'expiry_critical' THEN 4
                    WHEN 'low_stock' THEN 3
                    WHEN 'expiry_warning' THEN 2
                    WHEN 'overstock' THEN 1
                    ELSE 0
                END {$sortOrder}
            ");
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Paginate results
        $perPage = $request->input('per_page', 15);
        $alerts = $query->paginate($perPage);

        // Add computed properties
        $alerts->getCollection()->transform(function ($alert) {
            $alert->is_critical = $alert->is_critical;
            $alert->priority = $alert->priority;
            $alert->age_in_hours = $alert->age_in_hours;
            return $alert;
        });

        return response()->json([
            'success' => true,
            'data' => $alerts,
            'message' => 'Stock alerts retrieved successfully'
        ]);
    }

    /**
     * Display the specified stock alert.
     */
    public function show(StockAlert $stockAlert): JsonResponse
    {
        $stockAlert->load(['material', 'resolvedBy']);

        // Add computed properties
        $stockAlert->is_critical = $stockAlert->is_critical;
        $stockAlert->priority = $stockAlert->priority;
        $stockAlert->age_in_hours = $stockAlert->age_in_hours;

        return response()->json([
            'success' => true,
            'data' => $stockAlert,
            'message' => 'Stock alert retrieved successfully'
        ]);
    }

    /**
     * Resolve a stock alert.
     */
    public function resolve(StockAlert $stockAlert): JsonResponse
    {
        if ($stockAlert->is_resolved) {
            return response()->json([
                'success' => false,
                'message' => 'Alert is already resolved'
            ], 422);
        }

        $stockAlert->resolve(auth()->id());

        return response()->json([
            'success' => true,
            'data' => $stockAlert->fresh(['material', 'resolvedBy']),
            'message' => 'Stock alert resolved successfully'
        ]);
    }

    /**
     * Unresolve a stock alert.
     */
    public function unresolve(StockAlert $stockAlert): JsonResponse
    {
        if (!$stockAlert->is_resolved) {
            return response()->json([
                'success' => false,
                'message' => 'Alert is not resolved'
            ], 422);
        }

        $stockAlert->unresolve();

        return response()->json([
            'success' => true,
            'data' => $stockAlert->fresh(['material', 'resolvedBy']),
            'message' => 'Stock alert unresolved successfully'
        ]);
    }

    /**
     * Bulk resolve multiple stock alerts.
     */
    public function bulkResolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'alert_ids' => 'required|array',
            'alert_ids.*' => 'exists:stock_alerts,id'
        ]);

        $alerts = StockAlert::whereIn('id', $validated['alert_ids'])
            ->unresolved()
            ->get();

        $resolvedCount = 0;
        foreach ($alerts as $alert) {
            $alert->resolve(auth()->id());
            $resolvedCount++;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'resolved_count' => $resolvedCount,
                'total_requested' => count($validated['alert_ids'])
            ],
            'message' => "{$resolvedCount} alerts resolved successfully"
        ]);
    }

    /**
     * Generate new stock alerts by checking all materials.
     */
    public function generate(): JsonResponse
    {
        $alerts = $this->inventoryService->generateStockAlerts();

        return response()->json([
            'success' => true,
            'data' => [
                'generated_alerts' => $alerts->count(),
                'alerts' => $alerts
            ],
            'message' => 'Stock alerts generated successfully'
        ]);
    }

    /**
     * Get stock alert statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_alerts' => StockAlert::count(),
            'unresolved_alerts' => StockAlert::unresolved()->count(),
            'critical_alerts' => StockAlert::unresolved()->critical()->count(),
            'alerts_by_type' => StockAlert::unresolved()
                ->selectRaw('alert_type, COUNT(*) as count')
                ->groupBy('alert_type')
                ->pluck('count', 'alert_type'),
            'recent_alerts' => StockAlert::recent(7)->count(),
            'resolved_today' => StockAlert::resolved()
                ->whereDate('resolved_at', today())
                ->count(),
            'average_resolution_time' => StockAlert::resolved()
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours')
                ->value('avg_hours')
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'Stock alert statistics retrieved successfully'
        ]);
    }

    /**
     * Delete resolved stock alerts older than specified days.
     */
    public function cleanup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'integer|min:1|max:365'
        ]);

        $days = $validated['days'] ?? 30;
        $cutoffDate = now()->subDays($days);

        $deletedCount = StockAlert::resolved()
            ->where('resolved_at', '<', $cutoffDate)
            ->delete();

        return response()->json([
            'success' => true,
            'data' => [
                'deleted_count' => $deletedCount,
                'cutoff_date' => $cutoffDate->toDateString()
            ],
            'message' => "Cleaned up {$deletedCount} resolved alerts older than {$days} days"
        ]);
    }
}
