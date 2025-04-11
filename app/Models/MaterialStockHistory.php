<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialStockHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id',
        'period_date',
        'start_stock',
        'end_stock'
    ];
}
