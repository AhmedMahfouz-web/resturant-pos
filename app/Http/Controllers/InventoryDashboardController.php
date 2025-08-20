<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\StockAlert;
use App\Models\StockBatch;
use App\Services\InventoryBroadcastService;
use App\Services\ReportingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class InventoryDashboardController extends Controller
{
    protected $broadcastService;
    protected $reportingService;

    public function __construct(
        InventoryBroadcastService $broadcastService,
        ReportingService $reportingService
    ) {
        $this->broadcastService = $broadcastService;
        $this->reportingService = $reportingService;
    }

    /**
     * Get real-time dashboard data
     */
    public function getDashboardData(): JsonResponse
    {
        try {
            $data = $this->broadcastService->getDashboardData();

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get real-time inventory status
     */
    public function getInventoryStatus(): JsonResponse
    {
        try {
            $materials = Material::with(['stockBatches' => function ($query) {
                $query->available()->fifoOrder();
            }])
                ->select([
                    'id',
                    'name',
                    'quantity',
                    'stock_unit',
                    'reorder_point',
                    'minimum_stock_level',
                    'maximum_stock_level',
                    'updated_at'
                ])
                ->get()
                ->map(function ($material) {
                    $stockValue = $material->getCurrentStockValue();
                    $expiringBatches = $material->stockBatches()
                        ->expiringWithin(7)
                        ->count();

                    return [
                        'id' => $material->id,
                        'name' => $material->name,
                        'quantity' => $material->quantity,
                        'stock_unit' => $material->stock_unit,
                        'reorder_point' => $material->reorder_point,
                        'minimum_stock_level' => $material->minimum_stock_level,
                        'maximum_stock_level' => $material->maximum_stock_level,
                        'stock_value' => $stockValue,
                        'is_low_stock' => $material->isBelowMinimumStock(),
                        'is_at_reorder_point' => $material->isAtReorderPoint(),
                        'is_overstock' => $material->isAboveMaximumStock(),
                        'expiring_batches_count' => $expiringBatches,
                        'last_updated' => $material->updated_at->toISOString()
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'materials' => $materials,
                    'summary' => [
                        'total_materials' => $materials->count(),
                        'low_stock_count' => $materials->where('is_low_stock', true)->count(),
                        'reorder_needed_count' => $materials->where('is_at_reorder_point', true)->count(),
                        'overstock_count' => $materials->where('is_overstock', true)->count(),
                        'total_stock_value' => $materials->sum('stock_value')
                    ],
                    'timestamp' => now()->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch inventory status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active stock alerts
     */
    public function getActiveAlerts(): JsonResponse
    {
        try {
            $alerts = StockAlert::with('material:id,name,stock_unit')
                ->unresolved()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($alert) {
                    return [
                        'id' => $alert->id,
                        'material_id' => $alert->material_id,
                        'material_name' => $alert->material->name,
                        'alert_type' => $alert->alert_type,
                        'priority' => $alert->priority,
                        'is_critical' => $alert->is_critical,
                        'threshold_value' => $alert->threshold_value,
                        'current_value' => $alert->current_value,
                        'message' => $alert->message,
                        'age_in_hours' => $alert->age_in_hours,
                        'created_at' => $alert->created_at->toISOString()
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'alerts' => $alerts,
                    'summary' => [
                        'total_alerts' => $alerts->count(),
                        'critical_alerts' => $alerts->where('is_critical', true)->count(),
                        'low_stock_alerts' => $alerts->where('alert_type', 'low_stock')->count(),
                        'expiry_alerts' => $alerts->whereIn('alert_type', ['expiry_warning', 'expiry_critical'])->count()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stock alerts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get expiring batches
     */
    public function getExpiringBatches(Request $request): JsonResponse
    {
        try {
            $days = $request->get('days', 7);

            $expiringBatches = StockBatch::with('material:id,name,stock_unit')
                ->expiringWithin($days)
                ->available()
                ->orderBy('expiry_date', 'asc')
                ->get()
                ->map(function ($batch) {
                    return [
                        'id' => $batch->id,
                        'batch_number' => $batch->batch_number,
                        'material_id' => $batch->material_id,
                        'material_name' => $batch->material->name,
                        'remaining_quantity' => $batch->remaining_quantity,
                        'stock_unit' => $batch->material->stock_unit,
                        'unit_cost' => $batch->unit_cost,
                        'total_value' => $batch->total_value,
                        'expiry_date' => $batch->expiry_date->toDateString(),
                        'days_until_expiry' => $batch->days_until_expiry,
                        'is_expired' => $batch->is_expired,
                        'is_expiring' => $batch->is_expiring
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'batches' => $expiringBatches,
                    'summary' => [
                        'total_batches' => $expiringBatches->count(),
                        'expired_batches' => $expiringBatches->where('is_expired', true)->count(),
                        'expiring_soon' => $expiringBatches->where('is_expiring', true)->count(),
                        'total_value_at_risk' => $expiringBatches->sum('total_value')
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch expiring batches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent inventory movements
     */
    public function getRecentMovements(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 20);

            $movements = \App\Models\InventoryTransaction::with('material:id,name,stock_unit')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'material_id' => $transaction->material_id,
                        'material_name' => $transaction->material->name,
                        'type' => $transaction->type,
                        'quantity' => $transaction->quantity,
                        'stock_unit' => $transaction->material->stock_unit,
                        'unit_cost' => $transaction->unit_cost,
                        'total_cost' => $transaction->quantity * $transaction->unit_cost,
                        'notes' => $transaction->notes,
                        'created_at' => $transaction->created_at->toISOString()
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'movements' => $movements,
                    'summary' => [
                        'total_movements' => $movements->count(),
                        'receipts' => $movements->where('type', 'receipt')->count(),
                        'consumptions' => $movements->where('type', 'consumption')->count(),
                        'adjustments' => $movements->where('type', 'adjustment')->count()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent movements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Trigger manual dashboard update broadcast
     */
    public function broadcastDashboardUpdate(): JsonResponse
    {
        try {
            $this->broadcastService->broadcastDashboardUpdate();

            return response()->json([
                'success' => true,
                'message' => 'Dashboard update broadcasted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to broadcast dashboard update',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get material-specific real-time data
     */
    public function getMaterialData(Request $request, $materialId): JsonResponse
    {
        try {
            $material = Material::with([
                'stockBatches' => function ($query) {
                    $query->available()->fifoOrder();
                },
                'stockAlerts' => function ($query) {
                    $query->unresolved()->orderBy('created_at', 'desc');
                }
            ])->findOrFail($materialId);

            $recentTransactions = \App\Models\InventoryTransaction::where('material_id', $materialId)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'material' => [
                        'id' => $material->id,
                        'name' => $material->name,
                        'quantity' => $material->quantity,
                        'stock_unit' => $material->stock_unit,
                        'reorder_point' => $material->reorder_point,
                        'minimum_stock_level' => $material->minimum_stock_level,
                        'maximum_stock_level' => $material->maximum_stock_level,
                        'current_stock_value' => $material->getCurrentStockValue(),
                        'is_low_stock' => $material->isBelowMinimumStock(),
                        'is_at_reorder_point' => $material->isAtReorderPoint(),
                        'is_overstock' => $material->isAboveMaximumStock()
                    ],
                    'stock_batches' => $material->stockBatches->map(function ($batch) {
                        return [
                            'id' => $batch->id,
                            'batch_number' => $batch->batch_number,
                            'remaining_quantity' => $batch->remaining_quantity,
                            'unit_cost' => $batch->unit_cost,
                            'total_value' => $batch->total_value,
                            'received_date' => $batch->received_date->toDateString(),
                            'expiry_date' => $batch->expiry_date?->toDateString(),
                            'days_until_expiry' => $batch->days_until_expiry,
                            'is_expired' => $batch->is_expired,
                            'is_expiring' => $batch->is_expiring
                        ];
                    }),
                    'active_alerts' => $material->stockAlerts->map(function ($alert) {
                        return [
                            'id' => $alert->id,
                            'alert_type' => $alert->alert_type,
                            'priority' => $alert->priority,
                            'message' => $alert->message,
                            'created_at' => $alert->created_at->toISOString()
                        ];
                    }),
                    'recent_transactions' => $recentTransactions->map(function ($transaction) {
                        return [
                            'id' => $transaction->id,
                            'type' => $transaction->type,
                            'quantity' => $transaction->quantity,
                            'unit_cost' => $transaction->unit_cost,
                            'notes' => $transaction->notes,
                            'created_at' => $transaction->created_at->toISOString()
                        ];
                    })
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch material data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
