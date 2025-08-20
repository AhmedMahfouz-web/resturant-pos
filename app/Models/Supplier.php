<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'contact_person',
        'email',
        'phone',
        'address',
        'payment_terms',
        'lead_time_days',
        'minimum_order_amount',
        'is_active',
        'rating',
        'notes'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'rating' => 'decimal:2',
        'minimum_order_amount' => 'decimal:2',
        'lead_time_days' => 'integer'
    ];

    protected $attributes = [
        'is_active' => true,
        'lead_time_days' => 0,
        'minimum_order_amount' => 0.00
    ];

    // Relationships
    public function materials()
    {
        return $this->hasMany(Material::class, 'default_supplier_id');
    }

    public function materialReceipts()
    {
        return $this->hasMany(MaterialReceipt::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function performanceMetrics()
    {
        return $this->hasMany(SupplierPerformanceMetric::class);
    }

    public function communications()
    {
        return $this->hasMany(SupplierCommunication::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRating($query, $minRating = null)
    {
        if ($minRating) {
            return $query->where('rating', '>=', $minRating);
        }
        return $query;
    }

    // Accessors & Mutators
    public function getFormattedRatingAttribute()
    {
        return $this->rating ? number_format($this->rating, 1) . '/5.0' : 'Not rated';
    }

    public function getFormattedMinimumOrderAttribute()
    {
        return '$' . number_format($this->minimum_order_amount, 2);
    }

    // Methods
    public function calculatePerformanceMetrics($days = 30)
    {
        $startDate = now()->subDays($days);
        $endDate = now();

        $purchaseOrders = $this->purchaseOrders()
            ->whereBetween('order_date', [$startDate, $endDate])
            ->get();

        $totalOrders = $purchaseOrders->count();
        $completedOrders = $purchaseOrders->where('status', PurchaseOrder::STATUS_RECEIVED)->count();

        $deliveredOrders = $purchaseOrders->whereNotNull('actual_delivery_date');
        $onTimeDeliveries = $deliveredOrders->where('is_delivered_on_time', true)->count();
        $totalDeliveries = $deliveredOrders->count();

        $onTimeRate = $totalDeliveries > 0 ? ($onTimeDeliveries / $totalDeliveries) * 100 : 0;
        $averageDeliveryTime = $deliveredOrders->avg('delivery_delay_days') ?? 0;

        $recentMetric = $this->performanceMetrics()
            ->where('metric_period', '>=', $startDate)
            ->orderBy('metric_period', 'desc')
            ->first();

        return [
            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'on_time_delivery_rate' => round($onTimeRate, 1),
            'average_delivery_time' => round($averageDeliveryTime, 1),
            'quality_score' => $recentMetric?->quality_score ?? $this->rating ?? 0,
            'communication_score' => $recentMetric?->communication_score ?? 3.0,
            'overall_rating' => $recentMetric?->overall_rating ?? $this->rating ?? 0,
            'total_order_value' => $purchaseOrders->sum('final_amount'),
            'completion_rate' => $totalOrders > 0 ? ($completedOrders / $totalOrders) * 100 : 0
        ];
    }

    public function getLatestPerformanceMetric()
    {
        return $this->performanceMetrics()
            ->orderBy('metric_period', 'desc')
            ->first();
    }

    public function getCommunicationStats($days = 30)
    {
        $communications = $this->communications()
            ->where('communication_date', '>=', now()->subDays($days))
            ->get();

        $totalCommunications = $communications->count();
        $respondedCommunications = $communications->where('response_received', true)->count();
        $averageResponseTime = $communications->where('response_received', true)->avg('response_time_hours') ?? 0;
        $averageSatisfaction = $communications->whereNotNull('satisfaction_rating')->avg('satisfaction_rating') ?? 0;

        return [
            'total_communications' => $totalCommunications,
            'response_rate' => $totalCommunications > 0 ? ($respondedCommunications / $totalCommunications) * 100 : 0,
            'average_response_time_hours' => round($averageResponseTime, 1),
            'average_satisfaction' => round($averageSatisfaction, 1)
        ];
    }

    public function isReliable()
    {
        $metrics = $this->calculatePerformanceMetrics();

        return $this->is_active &&
            $metrics['overall_rating'] >= 3.5 &&
            $metrics['on_time_delivery_rate'] >= 80;
    }

    public function getPerformanceGrade()
    {
        $metrics = $this->calculatePerformanceMetrics();
        $rating = $metrics['overall_rating'];

        if ($rating >= 4.5) return 'A+';
        if ($rating >= 4.0) return 'A';
        if ($rating >= 3.5) return 'B+';
        if ($rating >= 3.0) return 'B';
        if ($rating >= 2.5) return 'C+';
        if ($rating >= 2.0) return 'C';
        if ($rating >= 1.5) return 'D';
        return 'F';
    }

    public function updatePerformanceMetrics($period = null)
    {
        $period = $period ?? now()->startOfMonth();
        $endDate = $period->copy()->endOfMonth();

        $metricsData = SupplierPerformanceMetric::calculateForSupplier($this, $period, $endDate);

        return $this->performanceMetrics()->updateOrCreate(
            ['metric_period' => $period->toDateString()],
            $metricsData
        );
    }
}
