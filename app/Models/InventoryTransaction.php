<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'quantity',
        'unit_cost',
        'material_id',
        'user_id',
        'remaining_quantity',
        'reference_type',
        'reference_id',
        'notes'
    ];

    protected static function booted()
    {
        static::created(function ($transaction) {
            if ($transaction->type === 'receipt') {
                $transaction->remaining_quantity = $transaction->quantity;
                $transaction->save();
            }
        });

        static::updated(function ($transaction) {
            if ($transaction->type === 'consumption') {
                $receipts = self::where('material_id', $transaction->material_id)
                    ->where('type', 'receipt')
                    ->orderBy('created_at')
                    ->get();

                $remaining = $transaction->quantity;
                foreach ($receipts as $receipt) {
                    $used = min($receipt->remaining_quantity, $remaining);
                    $receipt->remaining_quantity -= $used;
                    $receipt->save();
                    $remaining -= $used;
                    if ($remaining <= 0) break;
                }
            }
        });
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
