<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained();
            $table->enum('type', ['receipt', 'consumption', 'adjustment']);
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('remaining_quantity', 10, 2)->default(0); // إضافة الحقل الجديد
            $table->foreignId('user_id')->constrained();
            $table->string('note')->nullable(); // إضافة حقل الملاحظات
            $table->timestamps();
        });
    }
};
