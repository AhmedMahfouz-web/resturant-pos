<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class StockBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id',
        'batch_number',
        'quantity',
        'remaining_quantity',
        'unit_cost',
        'received_date',
        'expiry_date',
        'supplier_id',
        'material_receipt_id'
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'remaining_quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'received_date' => 'date',
        'expiry_date' => 'date'
    ];

    // Relationships
    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function materialReceipt()
    {
        return $this->belongsTo(MaterialReceipt::class);
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('remaining_quantity', '>', 0);
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now());
    }

    public function scopeExpiringWithin($query, $days = 7)
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays($days))
            ->where('expiry_date', '>=', now());
    }

    public function scopeForMaterial($query, $materialId)
    {
        return $query->where('material_id', $materialId);
    }

    public function scopeFifoOrder($query)
    {
        return $query->orderBy('received_date', 'asc')
            ->orderBy('created_at', 'asc');
    }

    // Accessors & Mutators
    public function getTotalValueAttribute()
    {
        return $this->remaining_quantity * $this->unit_cost;
    }

    public function getIsExpiredAttribute()
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function getIsExpiringAttribute()
    {
        return $this->expiry_date &&
            $this->expiry_date->isFuture() &&
            $this->expiry_date->diffInDays(now()) <= 7;
    }

    public function getDaysUntilExpiryAttribute()
    {
        if (!$this->expiry_date) {
            return null;
        }

        return $this->expiry_date->diffInDays(now(), false);
    }

    public function getUsagePercentageAttribute()
    {
        if ($this->quantity == 0) {
            return 100;
        }

        return round((($this->quantity - $this->remaining_quantity) / $this->quantity) * 100, 2);
    }

    // Methods
    public function consume($quantity)
    {
        if ($quantity > $this->remaining_quantity) {
            throw new \InvalidArgumentException("Cannot consume {$quantity} units. Only {$this->remaining_quantity} units available.");
        }

        $this->remaining_quantity -= $quantity;
        $this->save();

        return $this;
    }

    public function canConsume($quantity)
    {
        return $quantity <= $this->remaining_quantity;
    }

    public function isFullyConsumed()
    {
        return $this->remaining_quantity <= 0;
    }

    public function isAvailable()
    {
        return $this->remaining_quantity > 0 && !$this->is_expired;
    }

    public function generateBatchNumber()
    {
        $material = $this->material;
        $date = $this->received_date ?? now();

        // Format: MAT001-20250819-001
        $prefix = strtoupper(substr($material->name, 0, 3)) . str_pad($material->id, 3, '0', STR_PAD_LEFT);
        $dateStr = $date->format('Ymd');

        // Find the next sequence number for this material and date
        $lastBatch = static::where('material_id', $this->material_id)
            ->where('received_date', $date)
            ->where('batch_number', 'like', "{$prefix}-{$dateStr}-%")
            ->orderBy('batch_number', 'desc')
            ->first();

        $sequence = 1;
        if ($lastBatch) {
            $lastSequence = (int) substr($lastBatch->batch_number, -3);
            $sequence = $lastSequence + 1;
        }

        return $prefix . '-' . $dateStr . '-' . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    public static function createFromReceipt(MaterialReceipt $receipt)
    {
        $batch = new static([
            'material_id' => $receipt->material_id,
            'quantity' => $receipt->quantity_received,
            'remaining_quantity' => $receipt->quantity_received,
            'unit_cost' => $receipt->unit_cost,
            'received_date' => $receipt->received_at,
            'expiry_date' => $receipt->expiry_date,
            'supplier_id' => $receipt->supplier_id,
            'material_receipt_id' => $receipt->id
        ]);

        $batch->batch_number = $batch->generateBatchNumber();
        $batch->save();

        return $batch;
    }

    public static function consumeForMaterial($materialId, $quantityNeeded)
    {
        $batches = static::forMaterial($materialId)
            ->available()
            ->fifoOrder()
            ->get();

        $totalConsumed = 0;
        $consumedBatches = [];

        foreach ($batches as $batch) {
            if ($totalConsumed >= $quantityNeeded) {
                break;
            }

            $remainingNeeded = $quantityNeeded - $totalConsumed;
            $consumeFromBatch = min($remainingNeeded, $batch->remaining_quantity);

            $batch->consume($consumeFromBatch);

            $consumedBatches[] = [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'quantity_consumed' => $consumeFromBatch,
                'unit_cost' => $batch->unit_cost,
                'total_cost' => $consumeFromBatch * $batch->unit_cost
            ];

            $totalConsumed += $consumeFromBatch;
        }

        if ($totalConsumed < $quantityNeeded) {
            throw new \Exception("Insufficient stock. Needed: {$quantityNeeded}, Available: {$totalConsumed}");
        }

        return [
            'total_consumed' => $totalConsumed,
            'total_cost' => collect($consumedBatches)->sum('total_cost'),
            'batches' => $consumedBatches
        ];
    }

    public static function calculateFifoCost($materialId, $quantity)
    {
        $batches = static::forMaterial($materialId)
            ->available()
            ->fifoOrder()
            ->get();

        $totalCost = 0;
        $remainingQuantity = $quantity;

        foreach ($batches as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $useFromBatch = min($remainingQuantity, $batch->remaining_quantity);
            $totalCost += $useFromBatch * $batch->unit_cost;
            $remainingQuantity -= $useFromBatch;
        }

        if ($remainingQuantity > 0) {
            throw new \Exception("Insufficient stock for FIFO cost calculation. Missing: {$remainingQuantity} units");
        }

        return $totalCost;
    }
}
