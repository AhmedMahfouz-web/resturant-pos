<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\MaterialReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MaterialReceiptController extends Controller
{
    /**
     * Display a listing of material receipts
     */
    public function index(Request $request)
    {
        $query = MaterialReceipt::with([
            'material:id,name,stock_unit,recipe_unit,conversion_rate',
            'supplier:id,name,contact_person',
            'receivedBy:id,first_name,last_name',
            'stockBatch:id,batch_number,remaining_quantity,expiry_date'
        ])
            ->orderBy('received_at', 'desc');

        // Filter by material if provided
        if ($request->has('material_id')) {
            $query->where('material_id', $request->material_id);
        }

        // Filter by source type if provided
        if ($request->has('source_type')) {
            $query->where('source_type', $request->source_type);
        }

        // Filter by date range if provided (supports both start_date/end_date and from/to)
        $startDate = $request->get('start_date') ?? $request->get('from');
        $endDate = $request->get('end_date') ?? $request->get('to');

        if ($startDate && $endDate) {
            $query->whereBetween('received_at', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->where('received_at', '>=', $startDate);
        } elseif ($endDate) {
            $query->where('received_at', '<=', $endDate);
        }

        $receipts = $query->paginate(20);

        return response()->json([
            'success' => true,
            'receipts' => $receipts,
            'summary' => [
                'total_receipts' => $receipts->total(),
                'total_cost' => MaterialReceipt::sum('total_cost')
            ]
        ]);
    }

    /**
     * Show the form for creating a new material receipt
     */
    public function create()
    {
        $materials = Material::select('id', 'name', 'stock_unit', 'recipe_unit', 'conversion_rate')->get();
        $suppliers = \App\Models\Supplier::active()->select('id', 'name', 'contact_person')->get();

        return response()->json([
            'success' => true,
            'materials' => $materials,
            'suppliers' => $suppliers,
            'source_types' => [
                'company_purchase' => 'Company Purchase',
                'company_transfer' => 'Company Transfer',
                'external_supplier' => 'External Supplier'
            ]
        ]);
    }

    /**
     * Store a newly created material receipt
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'material_id' => 'required|exists:materials,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'quantity_received' => 'required|numeric|min:0.001',
            'unit_cost' => 'required|numeric|min:0',
            'source_type' => 'required|in:company_purchase,company_transfer,external_supplier',
            'supplier_name' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:100',
            'invoice_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:today',
            'notes' => 'nullable|string|max:1000',
            'received_at' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Get the material to fetch its stock_unit
            $material = Material::findOrFail($request->material_id);

            // Calculate total cost
            $totalCost = $request->quantity_received * $request->unit_cost;

            // Create the receipt
            $receipt = MaterialReceipt::create([
                'receipt_code' => MaterialReceipt::generateReceiptCode(),
                'material_id' => $request->material_id,
                'supplier_id' => $request->supplier_id,
                'quantity_received' => $request->quantity_received,
                'unit' => $material->stock_unit, // Automatically get unit from material
                'unit_cost' => $request->unit_cost,
                'total_cost' => $totalCost,
                'source_type' => $request->source_type,
                'supplier_name' => $request->supplier_name,
                'invoice_number' => $request->invoice_number,
                'invoice_date' => $request->invoice_date,
                'expiry_date' => $request->expiry_date,
                'notes' => $request->notes,
                'received_by' => auth()->id(),
                'received_at' => $request->received_at ?? now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Material receipt created successfully',
                'receipt' => $receipt->load([
                    'material:id,name,stock_unit,recipe_unit,conversion_rate',
                    'supplier:id,name,contact_person,email',
                    'receivedBy:id,first_name,last_name',
                    'stockBatch:id,batch_number,quantity,remaining_quantity,expiry_date'
                ])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error creating material receipt: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified material receipt
     */
    public function show($id)
    {
        $receipt = MaterialReceipt::with([
            'material:id,name,stock_unit,recipe_unit,conversion_rate',
            'supplier:id,name,contact_person,email,phone',
            'receivedBy:id,first_name,last_name',
            'stockBatch:id,batch_number,quantity,remaining_quantity,expiry_date',
            'inventoryTransaction'
        ])->find($id);

        if (!$receipt) {
            return response()->json([
                'success' => false,
                'message' => 'Material receipt not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'receipt' => $receipt
        ]);
    }

    /**
     * Update the specified material receipt
     */
    public function update(Request $request, $id)
    {
        $receipt = MaterialReceipt::find($id);

        if (!$receipt) {
            return response()->json([
                'success' => false,
                'message' => 'Material receipt not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'supplier_name' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:100',
            'invoice_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:today',
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Only allow updating non-critical fields
        $receipt->update($request->only([
            'supplier_name',
            'invoice_number',
            'invoice_date',
            'expiry_date',
            'notes'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Material receipt updated successfully',
            'receipt' => $receipt->load(['material:id,name,stock_unit', 'receivedBy:id,first_name,last_name'])
        ]);
    }

    /**
     * Remove the specified material receipt
     */
    public function destroy($id)
    {
        $receipt = MaterialReceipt::with(['stockBatch', 'inventoryTransaction'])->find($id);

        if (!$receipt) {
            return response()->json([
                'success' => false,
                'message' => 'Material receipt not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Reverse the inventory impact
            $material = $receipt->material;
            $convertedQuantity = $receipt->convertToStockUnit();

            // Check if material has enough stock to reverse
            if ($material->quantity < $convertedQuantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete receipt: insufficient stock to reverse the transaction'
                ], 400);
            }

            // Decrease material quantity
            $material->decrement('quantity', $convertedQuantity);

            // Delete related stock batch
            if ($receipt->stockBatch) {
                // Check if the batch has been consumed
                if ($receipt->stockBatch->remaining_quantity < $receipt->stockBatch->quantity) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot delete receipt: stock batch has been partially consumed'
                    ], 400);
                }
                $receipt->stockBatch->delete();
            }

            // Delete related inventory transaction
            if ($receipt->inventoryTransaction) {
                $receipt->inventoryTransaction->delete();
            }

            // Delete the receipt
            $receipt->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Material receipt deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error deleting material receipt: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get receipt statistics
     */
    public function statistics(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        $stats = MaterialReceipt::whereBetween('received_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_receipts,
                SUM(total_cost) as total_cost,
                SUM(quantity_received) as total_quantity,
                source_type,
                COUNT(*) as count_by_source
            ')
            ->groupBy('source_type')
            ->get();

        $totalStats = MaterialReceipt::whereBetween('received_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_receipts,
                SUM(total_cost) as total_cost,
                AVG(unit_cost) as average_unit_cost
            ')
            ->first();

        return response()->json([
            'success' => true,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'overall_stats' => $totalStats,
            'by_source_type' => $stats
        ]);
    }

    /**
     * Get batch information for a receipt
     */
    public function getBatch($id)
    {
        $receipt = MaterialReceipt::with([
            'stockBatch' => function ($query) {
                $query->with(['material:id,name']);
            }
        ])->find($id);

        if (!$receipt) {
            return response()->json([
                'success' => false,
                'message' => 'Material receipt not found'
            ], 404);
        }

        if (!$receipt->stockBatch) {
            return response()->json([
                'success' => false,
                'message' => 'No batch found for this receipt'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'batch' => $receipt->stockBatch
        ]);
    }
}
