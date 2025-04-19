<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_id',
        'user_id',
        'shift_id',
        'status',
        'code',
        'tax',
        'service',
        'discount_value', // Calculated discount value
        'discount_type', // {percentage, cash, saved}
        'discount_id', // Discount id form Discount model
        'discount', // Discount value without orderItems's discounts
        'sub_total',
        'total_amount',
        'close_at',
        'type',
        'reason',
        'manual_reason',
    ];

    protected static function booted()
    {
        static::created(function ($order) {
            foreach ($order->orderItems as $item) {
                if ($item->product->materials) {
                    foreach ($item->product->materials as $material) {
                        $consumedQty = $material->pivot->quantity * $item->quantity;

                        InventoryTransaction::create([
                            'material_id' => $material->id,
                            'type' => 'consumption',
                            'quantity' => $consumedQty,
                            'unit_cost' => $material->unit_cost,
                            'user_id' => auth()->id()
                        ]);
                    }
                }
            }
        });
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function totalQuantity()
    {
        return $this->items()->sum('quantity');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'order_items')
            ->withPivot('quantity', 'price');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function discountSaved()
    {
        return $this->hasOne(Discount::class);
    }
}
