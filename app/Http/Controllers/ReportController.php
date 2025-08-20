<?php

namespace App\Http\Controllers;

use App\Services\ReportingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    protected $reportingService;

    public function __construct(ReportingService $reportingService)
    {
        $this->reportingService = $reportingService;
    }

    /**
     * Get stock valuation report
     */
    public function stockValuation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'as_of_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $report = $this->reportingService->generateStockValuationReport(
                $request->input('as_of_date')
            );

            return response()->json([
                'success' => true,
                'data' => $report,
                'message' => 'Stock valuation report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating stock valuation report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inventory movement report
     */
    public function inventoryMovement(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'material_id' => 'nullable|exists:materials,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $report = $this->reportingService->generateInventoryMovementReport(
                $request->input('start_date'),
                $request->input('end_date'),
                $request->input('material_id')
            );

            return response()->json([
                'success' => true,
                'data' => $report,
                'message' => 'Inventory movement report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating inventory movement report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stock aging report
     */
    public function stockAging(): JsonResponse
    {
        try {
            $report = $this->reportingService->generateStockAgingReport();

            return response()->json([
                'success' => true,
                'data' => $report,
                'message' => 'Stock aging report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating stock aging report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get waste tracking report
     */
    public function wasteReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $report = $this->reportingService->generateWasteReport(
                $request->input('start_date'),
                $request->input('end_date')
            );

            return response()->json([
                'success' => true,
                'data' => $report,
                'message' => 'Waste report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating waste report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cost analysis report
     */
    public function costAnalysis(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $report = $this->reportingService->generateCostAnalysisReport(
                $request->input('start_date'),
                $request->input('end_date')
            );

            return response()->json([
                'success' => true,
                'data' => $report,
                'message' => 'Cost analysis report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating cost analysis report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get profitability report
     */
    public function profitability(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $report = $this->reportingService->generateProfitabilityReport(
                $request->input('start_date'),
                $request->input('end_date')
            );

            return response()->json([
                'success' => true,
                'data' => $report,
                'message' => 'Profitability report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating profitability report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard summary
     */
    public function dashboard(): JsonResponse
    {
        try {
            $summary = $this->reportingService->generateDashboardSummary();

            return response()->json([
                'success' => true,
                'data' => $summary,
                'message' => 'Dashboard summary generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating dashboard summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export report data (placeholder for future implementation)
     */
    public function exportReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'report_type' => 'required|in:stock_valuation,inventory_movement,stock_aging,waste,cost_analysis,profitability',
            'format' => 'required|in:excel,pdf,csv',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'material_id' => 'nullable|exists:materials,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // This is a placeholder for future export functionality
        // You would implement actual export logic here using libraries like
        // Laravel Excel, DomPDF, etc.

        return response()->json([
            'success' => true,
            'message' => 'Export functionality will be implemented in a future update',
            'data' => [
                'report_type' => $request->input('report_type'),
                'format' => $request->input('format'),
                'parameters' => $request->only(['start_date', 'end_date', 'material_id'])
            ]
        ]);
    }

    /**
     * Get available report types and their descriptions
     */
    public function reportTypes(): JsonResponse
    {
        $reportTypes = [
            'stock_valuation' => [
                'name' => 'Stock Valuation Report',
                'description' => 'Current inventory value using FIFO methodology',
                'parameters' => ['as_of_date' => 'optional']
            ],
            'inventory_movement' => [
                'name' => 'Inventory Movement Report',
                'description' => 'Detailed inventory transactions and movements',
                'parameters' => ['start_date' => 'required', 'end_date' => 'required', 'material_id' => 'optional']
            ],
            'stock_aging' => [
                'name' => 'Stock Aging Report',
                'description' => 'Inventory aging analysis by receipt date',
                'parameters' => []
            ],
            'waste' => [
                'name' => 'Waste Tracking Report',
                'description' => 'Expired items and waste adjustments',
                'parameters' => ['start_date' => 'required', 'end_date' => 'required']
            ],
            'cost_analysis' => [
                'name' => 'Cost Analysis Report',
                'description' => 'Recipe cost calculations and trends',
                'parameters' => ['start_date' => 'required', 'end_date' => 'required']
            ],
            'profitability' => [
                'name' => 'Profitability Report',
                'description' => 'Product profitability analysis',
                'parameters' => ['start_date' => 'required', 'end_date' => 'required']
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $reportTypes,
            'message' => 'Available report types retrieved successfully'
        ]);
    }
}
