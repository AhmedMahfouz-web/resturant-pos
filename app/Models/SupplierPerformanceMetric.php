<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SupplierPerformanceMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'metric_period',
        'total_orders',
        'completed_orders',
        'cancelled_orders',
        'on_time_deliveries',
        'late_deliveries',
        'average_delivery_delay_days',
        'total_order_value',
        'quality_score',
        'communication_score',
        'overall_rating',
        'calculated_at'
    ];

    protected $casts = [
        'metric_period' => 'date',
        'calculated_at' => 'datetime',
        'total_order_value' => 'decimal:2',
        'quality_score' => 'decimal:2',
        'communication_score' => 'decimal:2',
        'overall_rating' => 'decimal:2',
        'average_delivery_delay_days' => 'decimal:1'
    ];

    // Relationships
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    // Accessors & Mutators
    public function getOnTimeDeliveryRateAttribute()
    {
        $totalDeliveries = $this->on_time_deliveries + $this->late_deliveries;
        return $totalDeliveries > 0 ? ($this->on_time_deliveries / $totalDeliveries) * 100 : 0;
    }

    public function getOrderCompletionRateAttribute()
    {
        return $this->total_orders > 0 ? ($this->completed_orders / $this->total_orders) * 100 : 0;
    }

    public function getCancellationRateAttribute()
    {
        return $this->total_orders > 0 ? ($this->cancelled_orders / $this->total_orders) * 100 : 0;
    }

    public function getPerformanceGradeAttribute()
    {
        $rating = $this->overall_rating;

        if ($rating >= 4.5) return 'A+';
        if ($rating >= 4.0) return 'A';
        if ($rating >= 3.5) return 'B+';
        if ($rating >= 3.0) return 'B';
        if ($rating >= 2.5) return 'C+';
        if ($rating >= 2.0) return 'C';
        if ($rating >= 1.5) return 'D';
        return 'F';
    }

    // Static methods
    public static function calculateForSupplier(Supplier $supplier, Carbon $startDate, Carbon $endDate)
    {
        $purchaseOrders = PurchaseOrder::where('supplier_id', $supplier->id)
            ->whereBetween('order_date', [$startDate, $endDate])
            ->get();

        $totalOrders = $purchaseOrders->count();
        $completedOrders = $purchaseOrders->where('status', PurchaseOrder::STATUS_RECEIVED)->count();
        $cancelledOrders = $purchaseOrders->where('status', PurchaseOrder::STATUS_CANCELLED)->count();

        $deliveredOrders = $purchaseOrders->whereNotNull('actual_delivery_date');
        $onTimeDeliveries = $deliveredOrders->where('is_delivered_on_time', true)->count();
        $lateDeliveries = $deliveredOrders->where('is_delivered_on_time', false)->count();

        $averageDelayDays = $deliveredOrders->avg('delivery_delay_days') ?? 0;
        $totalOrderValue = $purchaseOrders->sum('final_amount');

        // Calculate quality score based on receipt matching
        $qualityScore = static::calculateQualityScore($supplier, $startDate, $endDate);

        // Calculate communication score (placeholder - can be enhanced with actual communication tracking)
        $communicationScore = $supplier->rating ?? 3.0;

        // Calculate overall rating
        $onTimeRate = $totalOrders > 0 ? ($onTimeDeliveries / max(1, $onTimeDeliveries + $lateDeliveries)) : 1;
        $completionRate = $totalOrders > 0 ? ($completedOrders / $totalOrders) : 1;
        $qualityRate = $qualityScore / 5;
        $communicationRate = $communicationScore / 5;

        $overallRating = (
            ($onTimeRate * 0.3) +
            ($completionRate * 0.25) +
            ($qualityRate * 0.25) +
            ($communicationRate * 0.2)
        ) * 5;

        return [
            'supplier_id' => $supplier->id,
            'metric_period' => $startDate,
            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'cancelled_orders' => $cancelledOrders,
            'on_time_deliveries' => $onTimeDeliveries,
            'late_deliveries' => $lateDeliveries,
            'average_delivery_delay_days' => round($averageDelayDays, 1),
            'total_order_value' => $totalOrderValue,
            'quality_score' => round($qualityScore, 2),
            'communication_score' => round($communicationScore, 2),
            'overall_rating' => round($overallRating, 2),
            'calculated_at' => now()
        ];
    }

    private static function calculateQualityScore(Supplier $supplier, Carbon $startDate, Carbon $endDate)
    {
        // Calculate quality based on receipt accuracy, returns, etc.
        // This is a simplified calculation - can be enhanced based on actual quality metrics

        $receipts = MaterialReceipt::where('supplier_id', $supplier->id)
            ->whereBetween('received_at', [$startDate, $endDate])
            ->get();

        if ($receipts->isEmpty()) {
            return 3.0; // Default neutral score
        }

        // For now, assume quality is good if receipts match expected quantities
        $totalReceipts = $receipts->count();
        $accurateReceipts = $receipts->filter(function ($receipt) {
            // Assume receipt is accurate if quantity received matches expected
            // This can be enhanced with actual quality tracking
            return true; // Placeholder
        })->count();

        $accuracyRate = $totalReceipts > 0 ? ($accurateReceipts / $totalReceipts) : 1;

        return min(5.0, 2.0 + ($accuracyRate * 3.0)); // Scale from 2.0 to 5.0
    }
}
