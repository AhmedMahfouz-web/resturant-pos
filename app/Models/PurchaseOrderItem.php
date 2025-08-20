<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'material_id',
        'quantity',
        'unit_cost',
        'total_cost',
        'notes'
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2'
    ];

    // Relationships
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function materialReceipts()
    {
        return $this->hasMany(MaterialReceipt::class);
    }

    // Accessors & Mutators
    public function getReceivedQuantityAttribute()
    {
        return $this->materialReceipts()->sum('quantity_received');
    }

    public function getPendingQuantityAttribute()
    {
        return $this->quantity - $this->received_quantity;
    }

    public function getIsFullyReceivedAttribute()
    {
        return $this->received_quantity >= $this->quantity;
    }

    public function getReceiptPercentageAttribute()
    {
        return $this->quantity > 0 ? ($this->received_quantity / $this->quantity) * 100 : 0;
    }

    // Methods
    public function calculateTotalCost()
    {
        $this->total_cost = $this->quantity * $this->unit_cost;
        $this->save();
    }

    protected static function booted()
    {
        static::saving(function ($item) {
            $item->total_cost = $item->quantity * $item->unit_cost;
        });
    }
}
