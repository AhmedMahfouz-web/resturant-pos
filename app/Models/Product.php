<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'category_id',
        'image',
        'discount_type',
        'discount',
        'tax'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_items')
            ->withPivot('quantity', 'price');
    }

    public function recipe()
    {
        return $this->belongsToMany(Recipe::class, 'recipe_product', 'product_id', 'recipe_id')->limit(1);
        // return $this->hasOne(Recipe::class, 'id', 'recipe_id')
        //     ->join('recipe_product', 'recipes.id', '=', 'recipe_product.recipe_id')
        //     ->where('recipe_product.product_id', '=', $this->id);
    }

    // public function recipe()
    // {
    //     return $this->hasOne(Recipe::class);
    // }
}
