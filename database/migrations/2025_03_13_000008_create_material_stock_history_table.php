<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // First, check if the table already exists and drop it if it does
        if (Schema::hasTable('material_stock_history')) {
            Schema::dropIfExists('material_stock_history');
        }
        
        Schema::create('material_stock_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('material_id'); // No foreign key constraint for now
            $table->date('period_date');
            $table->decimal('start_stock', 10, 2);
            $table->decimal('end_stock', 10, 2);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('material_stock_history');
    }
};
