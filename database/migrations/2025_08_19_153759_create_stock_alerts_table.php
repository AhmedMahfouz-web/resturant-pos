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
        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('material_id');
            $table->enum('alert_type', ['low_stock', 'out_of_stock', 'expiry_warning', 'expiry_critical', 'overstock']);
            $table->decimal('threshold_value', 10, 3)->nullable();
            $table->decimal('current_value', 10, 3)->nullable();
            $table->text('message');
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamps();

            $table->index(['material_id', 'alert_type'], 'idx_material_type');
            $table->index(['is_resolved', 'created_at'], 'idx_unresolved');

            // Foreign keys will be added later
            // $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');
            // $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_alerts');
    }
};
