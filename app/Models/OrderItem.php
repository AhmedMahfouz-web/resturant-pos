<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
        'discount_value', // Calculated discount value
        'discount_type', // {percentage, cash, saved}
        'discount_id', // Discount id form Discount model
        'discount', // Discount value per one
        'sub_total',
        'total_amount',
        'tax',
        'service',
        'notes',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function discountSaved()
    {
        return $this->hasOne(Discount::class);
    }
}
