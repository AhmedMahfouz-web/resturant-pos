<?php

namespace App\Http\Controllers;

use App\Services\InventoryBroadcastService;
use App\Services\ReportingService;
use App\Models\Material;
use App\Models\StockAlert;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WebSocketController extends Controller
{
    protected $broadcastService;
    protected $reportingService;

    public function __construct(InventoryBroadcastService $broadcastService, ReportingService $reportingService)
    {
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
                'data' => $data,
                'message' => 'Dashboard data retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving dashboard data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Force broadcast dashboard update
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
                'message' => 'Error broadcasting dashboard update: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get real-time inventory status for specific materials
     */
    public function getMaterialStatus(Request $request): JsonResponse
    {
        $request->validate([
            'material_ids' => 'required|array',
            'material_ids.*' => 'exists:materials,id'
        ]);

        try {
            $materials = Material::with(['stockBatches' => function ($query) {
                $query->where('remaining_quantity', '>', 0)
                    ->orderBy('received_date', 'asc');
            }])
                ->whereIn('id', $request->input('material_ids'))
                ->get();

            $materialStatus = $materials->map(function ($material) {
                $totalValue = $material->stockBatches->sum(function ($batch) {
                    return $batch->remaining_quantity * $batch->unit_cost;
                });

                $expiringBatches = $material->stockBatches->filter(function ($batch) {
                    return $batch->expiry_date && $batch->expiry_date->diffInDays(now()) <= 7;
                });

                return [
                    'material_id' => $material->id,
                    'material_name' => $material->name,
                    'current_quantity' => $material->quantity,
                    'stock_unit' => $material->stock_unit,
                    'reorder_point' => $material->reorder_point,
                    'is_low_stock' => $material->quantity <= $material->reorder_point,
                    'total_value' => $totalValue,
                    'batch_count' => $material->stockBatches->count(),
                    'expiring_batches_count' => $expiringBatches->count(),
                    'expiring_quantity' => $expiringBatches->sum('remaining_quantity'),
                    'last_updated' => $material->updated_at->toISOString()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $materialStatus,
                'message' => 'Material status retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving material status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active stock alerts
     */
    public function getActiveAlerts(): JsonResponse
    {
        try {
            $alerts = StockAlert::with('material')
                ->where('is_resolved', false)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($alert) {
                    return [
                        'id' => $alert->id,
                        'material_id' => $alert->material_id,
                        'material_name' => $alert->material->name,
                        'alert_type' => $alert->alert_type,
                        'severity' => $alert->severity,
                        'current_quantity' => $alert->current_quantity,
                        'threshold_quantity' => $alert->threshold_quantity,
                        'message' => $alert->message,
                        'created_at' => $alert->created_at->toISOString(),
                        'age_hours' => $alert->created_at->diffInHours(now())
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $alerts,
                'count' => $alerts->count(),
                'message' => 'Active alerts retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving active alerts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test WebSocket connection by broadcasting a test message
     */
    public function testBroadcast(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:255'
        ]);

        try {
            broadcast(new \Illuminate\Broadcasting\BroadcastEvent([
                'channel' => 'test-channel',
                'event' => 'test.message',
                'data' => [
                    'message' => $request->input('message'),
                    'timestamp' => now()->toISOString(),
                    'user_id' => auth()->id()
                ]
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Test broadcast sent successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error sending test broadcast: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get WebSocket connection info and available channels
     */
    public function getConnectionInfo(): JsonResponse
    {
        $channels = [
            'inventory' => [
                'description' => 'General inventory updates',
                'events' => ['inventory.updated', 'stock-alert.triggered']
            ],
            'inventory.material.{id}' => [
                'description' => 'Updates for specific material',
                'events' => ['inventory.updated']
            ],
            'stock-alerts' => [
                'description' => 'Stock alert notifications',
                'events' => ['stock-alert.triggered']
            ],
            'recipe-costs' => [
                'description' => 'Recipe cost updates',
                'events' => ['recipe-cost.updated']
            ],
            'recipe-costs.recipe.{id}' => [
                'description' => 'Updates for specific recipe costs',
                'events' => ['recipe-cost.updated']
            ],
            'orders' => [
                'description' => 'Order processing updates',
                'events' => ['order.inventory-processed']
            ],
            'order.{id}' => [
                'description' => 'Updates for specific order',
                'events' => ['order.inventory-processed']
            ],
            'inventory-dashboard' => [
                'description' => 'Dashboard summary updates',
                'events' => ['dashboard.updated']
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'websocket_enabled' => config('broadcasting.default') !== 'null',
                'broadcast_driver' => config('broadcasting.default'),
                'available_channels' => $channels,
                'connection_url' => config('broadcasting.connections.pusher.options.host', 'ws://localhost:6001'),
                'app_key' => config('broadcasting.connections.pusher.key')
            ],
            'message' => 'WebSocket connection info retrieved successfully'
        ]);
    }
}
