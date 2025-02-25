<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TokenBlacklist extends Model
{
    protected $fillable = ['token'];
}
