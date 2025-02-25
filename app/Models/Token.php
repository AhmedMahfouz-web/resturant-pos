<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    protected $fillable = ['user_id', 'token']; // Ensure these fields match your migration
}
