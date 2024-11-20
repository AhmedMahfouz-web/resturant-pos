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
    ];

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
