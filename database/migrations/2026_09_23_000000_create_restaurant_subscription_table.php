<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_subscription', function (Blueprint $table) {
            $table->id();
            $table->date('expires_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_subscription');
    }
};
