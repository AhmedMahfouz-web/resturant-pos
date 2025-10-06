<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\StockBatch;
use App\Models\StockAlert;
use App\Models\InventoryTransaction;
use App\Services\InventoryService;
use App\Services\InventoryBroadcastService;
use App\Http\Requests\StockAdjustmentRequest;
use App\Http\Requests\InventoryFilterRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class EnhancedInventoryController extends Controller
{
    protected $inventoryService;
    protected $broadcastService;

    public function __construct(
        InventoryService $inventoryService,
        InventoryBroadcastService $broadcastService
    ) {
        $this->inventoryService = $inventoryService;
        $this->broadcastService = $broadcastService;
    }

    /**
     * Get inventory dashboard overview
     */
    public function dashboard(): JsonResponse
    {
        try {
            $totalMaterials = Material::count();
            $lowStockCount = Material::whereRaw('quantity <= minimum_stock_level')->count();
            $outOfStockCount = Material::where('quantity', '<=', 0)->count();
            $activeAlertsCount = StockAlert::unresolved()->count();

            $totalStockValue = Material::with('availableStockBatches')
                ->get()
                ->sum(function ($material) {
                    retuurrentStockValue();
                });

            $expiringBatchesCount = StockBatch::expiringWithin(7)->available()->count();

            $recentMovements = InventoryTransaction::with('material:id,name,stock_unit')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'material_name' => $transaction->material->name,
                        'type' => $transaction->type,
                        'quantity' => $transaction->quantity,
                        'stock_unit' => $transaction->material->stock_unit,
                        'created_at' => $transaction->created_at->toISOString()
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_materials' => $totalMaterials,
                        'low_stock_count' => $lowStockCount,
                        'out_of_stock_count' => $outOfStockCount,
                        'active_alerts_count' => $activeAlertsCount,
                        'total_stock_value' => round($totalStockValue, 2),
                        'expiring_batches_count' => $expiringBatchesCount
                    ],
                    'recent_movements' => $recentMovements,
                    'timestamp' => now()->toISOString()
                ]
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
     * Get materials with enhanced stock information
     */
    public function materials(InventoryFilterRequest $request): JsonResponse
    {
        try {
            $query = Material::with([
                'supplier:id,name',
                'category:id,name',
                'stockBatches' => function ($q) {
                    $q->available()->fifoOrder()->limit(5);
                },
                'stockAlerts' => function ($q) {
                    $q->unresolved()->orderBy('created_at', 'desc')->limit(3);
                }
            ]);

            // Apply filters
            if ($request->has('low_stock') && $request->low_stock) {
                $query->whereRaw('quantity <= minimum_stock_level');
            }

            if ($request->has('out_of_stock') && $request->out_of_stock) {
                $query->where('quantity', '<=', 0);
            }

            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('supplier_id')) {
                $query->where('default_supplier_id', $request->supplier_id);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            }

            $materials = $query->paginate($request->get('per_page', 20));

            $materials->getCollection()->transform(function ($material) {
                return [
                    'id' => $material->id,
                    'name' => $material->name,
                    'sku' => $material->sku,
                    'barcode' => $material->barcode,
                    'quantity' => $material->quantity,
                    'stock_unit' => $material->stock_unit,
                    'recipe_unit' => $material->recipe_unit,
                    'conversion_rate' => $material->conversion_rate,
                    'purchase_price' => $material->purchase_price,
                    'minimum_stock_level' => $material->minimum_stock_level,
                    'maximum_stock_level' => $material->maximum_stock_level,
                    'reorder_point' => $material->reorder_point,
                    'reorder_quantity' => $material->reorder_quantity,
                    'storage_location' => $material->storage_location,
                    'is_perishable' => $material->is_perishable,
                    'shelf_life_days' => $material->shelf_life_days,
                    'supplier' => $material->supplier,
                    'category' => $material->category,
                    'current_stock_value' => $material->getCurrentStockValue(),
                    'is_low_stock' => $material->isBelowMinimumStock(),
                    'is_at_reorder_point' => $material->isAtReorderPoint(),
                    'is_overstock' => $material->isAboveMaximumStock(),
                    'stock_batches' => $material->stockBatches->map(function ($batch) {
                        return [
                            'id' => $batch->id,
                            'batch_number' => $batch->batch_number,
                            'remaining_quantity' => $batch->remaining_quantity,
                            'unit_cost' => $batch->unit_cost,
                            'expiry_date' => $batch->expiry_date?->toDateString(),
                            'days_until_expiry' => $batch->days_until_expiry,
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
                    'updated_at' => $material->updated_at->toISOString()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $materials
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch materials',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create stock adjustment with audit trail
     */
    public function adjustStock(StockAdjustmentRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Set default adjustment type to 'increase' if null
            $adjustmentType = $request->adjustment_type ?? 'increase';

            $results = [];
            $totalTransactions = 0;

            foreach ($request->materials as $materialData) {
                $material = Material::findOrFail($materialData['material_id']);
                $oldQuantity = $material->quantity;
                $adjustmentQuantity = $materialData['quantity'];
                $unitCost = $materialData['unit_cost'] ?? $material->purchase_price;

                // Calculate new quantity based on adjustment type
                switch ($adjustmentType) {
                    case 'increase':
                        $newQuantity = $oldQuantity + $adjustmentQuantity;
                        $transactionQuantity = $adjustmentQuantity;
                        break;
                    case 'decrease':
                        $newQuantity = max(0, $oldQuantity - $adjustmentQuantity);
                        $transactionQuantity = -min($adjustmentQuantity, $oldQuantity);
                        break;
                    case 'set':
                        $newQuantity = $adjustmentQuantity;
                        $transactionQuantity = $newQuantity - $oldQuantity;
                        break;
                }

                // Update material quantity
                $material->quantity = $newQuantity;
                if (isset($materialData['unit_cost'])) {
                    $material->purchase_price = $materialData['unit_cost'];
                }
                $material->save();

                // Create inventory transaction
                $transaction = InventoryTransaction::create([
                    'material_id' => $material->id,
                    'type' => 'adjustment',
                    'quantity' => $transactionQuantity,
                    'unit_cost' => $unitCost,
                    'user_id' => auth()->id(),
                    'notes' => $request->reason . ($request->notes ? ' - ' . $request->notes : '')
                ]);

                // If it's an increase, create a stock batch
                if ($adjustmentType === 'increase' && $adjustmentQuantity > 0) {
                    StockBatch::create([
                        'material_id' => $material->id,
                        'batch_number' => 'ADJ-' . now()->format('Ymd-His') . '-' . $material->id,
                        'quantity' => $adjustmentQuantity,
                        'remaining_quantity' => $adjustmentQuantity,
                        'unit_cost' => $unitCost,
                        'received_date' => now(),
                        'supplier_id' => $material->default_supplier_id
                    ]);
                }

                $results[] = [
                    'transaction_id' => $transaction->id,
                    'material_id' => $material->id,
                    'material_name' => $material->name,
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => $newQuantity,
                    'adjustment_quantity' => $transactionQuantity
                ];

                $totalTransactions++;

                // Broadcast the adjustment
                // $this->broadcastService->broadcastStockAdjustment($material, [
                //     'quantity' => $transactionQuantity,
                //     'reason' => $request->reason,
                //     'user_id' => auth()->id(),
                //     'old_quantity' => $oldQuantity,
                //     'new_quantity' => $newQuantity
                // ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Stock adjustment completed successfully for {$totalTransactions} material(s)",
                'data' => [
                    'adjustment_type' => $adjustmentType,
                    'total_materials_adjusted' => $totalTransactions,
                    'materials' => $results
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to adjust stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inventory valuation
     */
    public function valuation(Request $request): JsonResponse
    {
        try {
            $materials = Material::with('availableStockBatches')->get();

            $valuationData = $materials->map(function ($material) {
                $stockValue = $material->getCurrentStockValue();
                $averageCost = $material->quantity > 0 ? $stockValue / $material->quantity : 0;

                return [
                    'material_id' => $material->id,
                    'material_name' => $material->name,
                    'quantity' => $material->quantity,
                    'stock_unit' => $material->stock_unit,
                    'average_cost' => round($averageCost, 4),
                    'total_value' => round($stockValue, 2),
                    'purchase_price' => $material->purchase_price,
                    'batches_count' => $material->availableStockBatches->count()
                ];
            });

            $totalValue = $valuationData->sum('total_value');
            $totalQuantity = $valuationData->sum('quantity');

            return response()->json([
                'success' => true,
                'data' => [
                    'materials' => $valuationData,
                    'summary' => [
                        'total_materials' => $materials->count(),
                        'total_value' => round($totalValue, 2),
                        'average_value_per_material' => $materials->count() > 0 ? round($totalValue / $materials->count(), 2) : 0
                    ],
                    'generated_at' => now()->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate valuation report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inventory movement history
     */
    public function movements(Request $request): JsonResponse
    {
        try {
            $query = InventoryTransaction::with('material:id,name,stock_unit');

            // Apply filters
            if ($request->has('material_id')) {
                $query->where('material_id', $request->material_id);
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            $movements = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 50));

            $movements->getCollection()->transform(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'material_id' => $transaction->material_id,
                    'material_name' => $transaction->material->name,
                    'type' => $transaction->type,
                    'quantity' => $transaction->quantity,
                    'stock_unit' => $transaction->material->stock_unit,
                    'unit_cost' => $transaction->unit_cost,
                    'total_cost' => $transaction->quantity * $transaction->unit_cost,
                    'user_id' => $transaction->user_id,
                    'notes' => $transaction->notes,
                    'created_at' => $transaction->created_at->toISOString()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $movements
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch movement history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stock batches with filtering
     */
    public function batches(Request $request): JsonResponse
    {
        try {
            $query = StockBatch::with('material:id,name,stock_unit', 'supplier:id,name');

            // Apply filters
            if ($request->has('material_id')) {
                $query->where('material_id', $request->material_id);
            }

            if ($request->has('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }

            if ($request->has('expiring_within_days')) {
                $days = (int) $request->expiring_within_days;
                $query->expiringWithin($days);
            }

            if ($request->has('expired') && $request->expired) {
                $query->expired();
            }

            if ($request->has('available_only') && $request->available_only) {
                $query->available();
            }

            $batches = $query->orderBy('received_date', 'desc')
                ->paginate($request->get('per_page', 50));

            $batches->getCollection()->transform(function ($batch) {
                return [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'material_id' => $batch->material_id,
                    'material_name' => $batch->material->name,
                    'supplier_name' => $batch->supplier?->name,
                    'quantity' => $batch->quantity,
                    'remaining_quantity' => $batch->remaining_quantity,
                    'stock_unit' => $batch->material->stock_unit,
                    'unit_cost' => $batch->unit_cost,
                    'total_value' => $batch->total_value,
                    'usage_percentage' => $batch->usage_percentage,
                    'received_date' => $batch->received_date->toDateString(),
                    'expiry_date' => $batch->expiry_date?->toDateString(),
                    'days_until_expiry' => $batch->days_until_expiry,
                    'is_expired' => $batch->is_expired,
                    'is_expiring' => $batch->is_expiring,
                    'is_available' => $batch->isAvailable()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $batches
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stock batches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get batches for specific material
     */
    public function materialBatches($materialId): JsonResponse
    {
        try {
            $material = Material::findOrFail($materialId);

            $batches = $material->stockBatches()
                ->with('supplier:id,name')
                ->fifoOrder()
                ->get()
                ->map(function ($batch) {
                    return [
                        'id' => $batch->id,
                        'batch_number' => $batch->batch_number,
                        'supplier_name' => $batch->supplier?->name,
                        'quantity' => $batch->quantity,
                        'remaining_quantity' => $batch->remaining_quantity,
                        'unit_cost' => $batch->unit_cost,
                        'total_value' => $batch->total_value,
                        'usage_percentage' => $batch->usage_percentage,
                        'received_date' => $batch->received_date->toDateString(),
                        'expiry_date' => $batch->expiry_date?->toDateString(),
                        'days_until_expiry' => $batch->days_until_expiry,
                        'is_expired' => $batch->is_expired,
                        'is_expiring' => $batch->is_expiring,
                        'is_available' => $batch->isAvailable()
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'material' => [
                        'id' => $material->id,
                        'name' => $material->name,
                        'current_quantity' => $material->quantity,
                        'stock_unit' => $material->stock_unit
                    ],
                    'batches' => $batches,
                    'summary' => [
                        'total_batches' => $batches->count(),
                        'available_batches' => $batches->where('is_available', true)->count(),
                        'expired_batches' => $batches->where('is_expired', true)->count(),
                        'expiring_batches' => $batches->where('is_expiring', true)->count(),
                        'total_value' => $batches->sum('total_value')
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch material batches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get expiry tracking information
     */
    public function expiryTracking(Request $request): JsonResponse
    {
        try {
            $days = $request->get('days', 30);

            $expiringBatches = StockBatch::with('material:id,name,stock_unit', 'supplier:id,name')
                ->expiringWithin($days)
                ->available()
                ->orderBy('expiry_date', 'asc')
                ->get();

            $expiredBatches = StockBatch::with('material:id,name,stock_unit', 'supplier:id,name')
                ->expired()
                ->available()
                ->orderBy('expiry_date', 'desc')
                ->limit(50)
                ->get();

            $groupedByDays = $expiringBatches->groupBy(function ($batch) {
                $days = $batch->days_until_expiry;
                if ($days <= 0) return 'expired';
                if ($days <= 2) return '0-2_days';
                if ($days <= 7) return '3-7_days';
                if ($days <= 14) return '8-14_days';
                return '15+_days';
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'expiring_batches' => $expiringBatches->map(function ($batch) {
                        return [
                            'id' => $batch->id,
                            'batch_number' => $batch->batch_number,
                            'material_name' => $batch->material->name,
                            'supplier_name' => $batch->supplier?->name,
                            'remaining_quantity' => $batch->remaining_quantity,
                            'stock_unit' => $batch->material->stock_unit,
                            'total_value' => $batch->total_value,
                            'expiry_date' => $batch->expiry_date->toDateString(),
                            'days_until_expiry' => $batch->days_until_expiry,
                            'urgency_level' => $batch->days_until_expiry <= 2 ? 'critical' : ($batch->days_until_expiry <= 7 ? 'high' : 'medium')
                        ];
                    }),
                    'expired_batches' => $expiredBatches->map(function ($batch) {
                        return [
                            'id' => $batch->id,
                            'batch_number' => $batch->batch_number,
                            'material_name' => $batch->material->name,
                            'remaining_quantity' => $batch->remaining_quantity,
                            'stock_unit' => $batch->material->stock_unit,
                            'total_value' => $batch->total_value,
                            'expiry_date' => $batch->expiry_date->toDateString(),
                            'days_expired' => abs($batch->days_until_expiry)
                        ];
                    }),
                    'summary' => [
                        'total_expiring' => $expiringBatches->count(),
                        'total_expired' => $expiredBatches->count(),
                        'value_at_risk' => $expiringBatches->sum('total_value'),
                        'expired_value' => $expiredBatches->sum('total_value'),
                        'by_urgency' => [
                            'critical' => $groupedByDays->get('0-2_days', collect())->count(),
                            'high' => $groupedByDays->get('3-7_days', collect())->count(),
                            'medium' => $groupedByDays->get('8-14_days', collect())->count(),
                            'low' => $groupedByDays->get('15+_days', collect())->count()
                        ]
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch expiry tracking data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
