<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    /**
     * Display a listing of suppliers.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Supplier::query();

        // Apply filters
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        if ($request->has('min_rating')) {
            $query->byRating($request->input('min_rating'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'name');
        $sortOrder = $request->input('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate results
        $perPage = $request->input('per_page', 15);
        $suppliers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $suppliers,
            'message' => 'Suppliers retrieved successfully'
        ]);
    }

    /**
     * Store a newly created supplier.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:suppliers,name',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:suppliers,email',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'payment_terms' => 'nullable|string|max:100',
            'lead_time_days' => 'nullable|integer|min:0|max:365',
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'rating' => 'nullable|numeric|min:0|max:5',
            'notes' => 'nullable|string'
        ]);

        $supplier = Supplier::create($validated);

        return response()->json([
            'success' => true,
            'data' => $supplier,
            'message' => 'Supplier created successfully'
        ], 201);
    }

    /**
     * Display the specified supplier.
     */
    public function show(Supplier $supplier): JsonResponse
    {
        $supplier->load(['materials', 'materialReceipts']);

        $performanceMetrics = $supplier->calculatePerformanceMetrics();

        return response()->json([
            'success' => true,
            'data' => [
                'supplier' => $supplier,
                'performance_metrics' => $performanceMetrics
            ],
            'message' => 'Supplier retrieved successfully'
        ]);
    }

    /**
     * Update the specified supplier.
     */
    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('suppliers', 'name')->ignore($supplier->id)
            ],
            'contact_person' => 'nullable|string|max:255',
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('suppliers', 'email')->ignore($supplier->id)
            ],
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'payment_terms' => 'nullable|string|max:100',
            'lead_time_days' => 'nullable|integer|min:0|max:365',
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'rating' => 'nullable|numeric|min:0|max:5',
            'notes' => 'nullable|string'
        ]);

        $supplier->update($validated);

        return response()->json([
            'success' => true,
            'data' => $supplier,
            'message' => 'Supplier updated successfully'
        ]);
    }

    /**
     * Remove the specified supplier.
     */
    public function destroy(Supplier $supplier): JsonResponse
    {
        // Check if supplier has associated materials
        if ($supplier->materials()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete supplier with associated materials. Please reassign materials first.'
            ], 422);
        }

        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Supplier deleted successfully'
        ]);
    }

    /**
     * Get supplier performance metrics.
     */
    public function performance(Supplier $supplier): JsonResponse
    {
        $metrics = $supplier->calculatePerformanceMetrics();

        // Add additional performance data
        $metrics['materials_supplied'] = $supplier->materials()->count();
        $metrics['total_receipts'] = $supplier->materialReceipts()->count();
        $metrics['is_reliable'] = $supplier->isReliable();

        return response()->json([
            'success' => true,
            'data' => $metrics,
            'message' => 'Supplier performance metrics retrieved successfully'
        ]);
    }

    /**
     * Toggle supplier active status.
     */
    public function toggleStatus(Supplier $supplier): JsonResponse
    {
        $supplier->update(['is_active' => !$supplier->is_active]);

        $status = $supplier->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'success' => true,
            'data' => $supplier,
            'message' => "Supplier {$status} successfully"
        ]);
    }
}
