<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMaterialStockHistoryTable extends Migration
{
    public function up()
    {
        Schema::create('material_stock_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained();
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
}
