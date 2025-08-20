<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'status',
        'order_date',
        'expected_delivery_date',
        'actual_delivery_date',
        'total_amount',
        'discount_amount',
        'tax_amount',
        'final_amount',
        'payment_terms',
        'delivery_address',
        'notes',
        'created_by',
        'approved_by',
        'approved_at'
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'approved_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'final_amount' => 'decimal:2'
    ];

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_SENT = 'sent';
    const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    const STATUS_RECEIVED = 'received';
    const STATUS_CANCELLED = 'cancelled';

    const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_SENT,
        self::STATUS_PARTIALLY_RECEIVED,
        self::STATUS_RECEIVED,
        self::STATUS_CANCELLED
    ];

    // Relationships
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function materialReceipts()
    {
        return $this->hasMany(MaterialReceipt::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOverdue($query)
    {
        return $query->where('expected_delivery_date', '<', now())
            ->whereNotIn('status', [self::STATUS_RECEIVED, self::STATUS_CANCELLED]);
    }

    public function scopeDeliveredOnTime($query)
    {
        return $query->whereNotNull('actual_delivery_date')
            ->whereColumn('actual_delivery_date', '<=', 'expected_delivery_date');
    }

    public function scopeDeliveredLate($query)
    {
        return $query->whereNotNull('actual_delivery_date')
            ->whereColumn('actual_delivery_date', '>', 'expected_delivery_date');
    }

    // Accessors & Mutators
    public function getIsOverdueAttribute()
    {
        return $this->expected_delivery_date < now() &&
            !in_array($this->status, [self::STATUS_RECEIVED, self::STATUS_CANCELLED]);
    }

    public function getDeliveryDelayDaysAttribute()
    {
        if (!$this->actual_delivery_date || !$this->expected_delivery_date) {
            return null;
        }

        return $this->actual_delivery_date->diffInDays($this->expected_delivery_date, false);
    }

    public function getIsDeliveredOnTimeAttribute()
    {
        return $this->actual_delivery_date &&
            $this->actual_delivery_date <= $this->expected_delivery_date;
    }

    public function getCompletionPercentageAttribute()
    {
        $totalItems = $this->items()->sum('quantity');
        if ($totalItems == 0) return 0;

        $receivedItems = $this->materialReceipts()
            ->join('purchase_order_items', 'material_receipts.purchase_order_item_id', '=', 'purchase_order_items.id')
            ->sum('material_receipts.quantity_received');

        return min(100, ($receivedItems / $totalItems) * 100);
    }

    // Methods
    public function generatePONumber()
    {
        $year = now()->year;
        $month = now()->format('m');

        $lastPO = static::whereYear('created_at', $year)
            ->whereMonth('created_at', now()->month)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastPO ? (int) substr($lastPO->po_number, -4) + 1 : 1;

        return "PO-{$year}{$month}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function calculateTotals()
    {
        $subtotal = $this->items()->sum(\DB::raw('quantity * unit_cost'));
        $this->total_amount = $subtotal;
        $this->final_amount = $subtotal - $this->discount_amount + $this->tax_amount;
        $this->save();
    }

    public function markAsReceived()
    {
        $this->update([
            'status' => self::STATUS_RECEIVED,
            'actual_delivery_date' => now()
        ]);
    }

    public function canBeModified()
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING]);
    }

    public function canBeCancelled()
    {
        return !in_array($this->status, [self::STATUS_RECEIVED, self::STATUS_CANCELLED]);
    }
}
