<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_code',
        'material_id',
        'supplier_id',
        'purchase_order_id',
        'purchase_order_item_id',
        'quantity_received',
        'unit',
        'unit_cost',
        'total_cost',
        'source_type',
        'supplier_name',
        'invoice_number',
        'invoice_date',
        'expiry_date',
        'notes',
        'received_by',
        'received_at'
    ];

    protected $casts = [
        'quantity_received' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'invoice_date' => 'date',
        'expiry_date' => 'date',
        'received_at' => 'datetime'
    ];

    protected static function booted()
    {
        static::created(function ($receipt) {
            // Automatically update material quantity when receipt is created
            $material = $receipt->material;

            // Convert received quantity to material's stock unit if different
            $convertedQuantity = $receipt->convertToStockUnit();

            // Update material quantity
            $material->increment('quantity', $convertedQuantity);

            // Create stock batch for FIFO tracking
            StockBatch::createFromReceipt($receipt);

            // Create inventory transaction record
            InventoryTransaction::create([
                'material_id' => $receipt->material_id,
                'type' => 'receipt',
                'quantity' => $convertedQuantity,
                'unit_cost' => $receipt->unit_cost,
                'reference_type' => 'material_receipt',
                'reference_id' => $receipt->id,
                'user_id' => $receipt->received_by,
                'notes' => "Material receipt: {$receipt->receipt_code}"
            ]);
        });
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function stockBatch()
    {
        return $this->hasOne(StockBatch::class);
    }

    public function inventoryTransaction()
    {
        return $this->morphOne(InventoryTransaction::class, 'reference');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    /**
     * Convert received quantity to material's stock unit
     * Since we always receive in the material's stock unit, no conversion needed
     */
    public function convertToStockUnit()
    {
        // We always receive materials in their stock_unit, so no conversion needed
        return $this->quantity_received;
    }

    /**
     * Generate unique receipt code
     */
    public static function generateReceiptCode()
    {
        $date = now()->format('Ymd');
        $lastReceipt = self::whereDate('created_at', now())->orderBy('id', 'desc')->first();

        if ($lastReceipt) {
            $lastNumber = (int) substr($lastReceipt->receipt_code, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "RCP-{$date}-{$newNumber}";
    }

    /**
     * Scope for filtering by source type
     */
    public function scopeBySourceType($query, $sourceType)
    {
        return $query->where('source_type', $sourceType);
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('received_at', [$startDate, $endDate]);
    }
}
