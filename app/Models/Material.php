<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'purchase_price', 'quantity', 'unit'];

    public function recipes()
    {
        return $this->belongsToMany(Recipe::class)->withPivot('material_quantity')->withTimestamps();
    }
}
