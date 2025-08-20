<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('material_id');
            $table->string('batch_number', 100);
            $table->decimal('quantity', 10, 3);
            $table->decimal('remaining_quantity', 10, 3);
            $table->decimal('unit_cost', 10, 2);
            $table->date('received_date');
            $table->date('expiry_date')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('material_receipt_id')->nullable();
            $table->timestamps();

            $table->index(['material_id', 'received_date'], 'idx_material_received');
            $table->index('expiry_date', 'idx_expiry_date');

            // Foreign keys will be added later
            // $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');
            // $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
            // $table->foreign('material_receipt_id')->references('id')->on('material_receipts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
    }
};
