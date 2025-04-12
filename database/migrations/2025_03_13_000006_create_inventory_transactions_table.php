<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // First, check if the table already exists and drop it if it does
        if (Schema::hasTable('inventory_transactions')) {
            Schema::dropIfExists('inventory_transactions');
        }

        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('material_id'); // No foreign key constraint for now
            $table->enum('type', ['receipt', 'consumption', 'adjustment']);
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('remaining_quantity', 10, 2)->default(0); // إضافة الحقل الجديد
            $table->unsignedBigInteger('user_id'); // No foreign key constraint for now
            $table->string('note')->nullable(); // إضافة حقل الملاحظات
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
