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
        Schema::create('supplier_performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->date('metric_period'); // Start date of the period being measured

            // Order metrics
            $table->integer('total_orders')->default(0);
            $table->integer('completed_orders')->default(0);
            $table->integer('cancelled_orders')->default(0);

            // Delivery metrics
            $table->integer('on_time_deliveries')->default(0);
            $table->integer('late_deliveries')->default(0);
            $table->decimal('average_delivery_delay_days', 5, 1)->default(0);

            // Financial metrics
            $table->decimal('total_order_value', 12, 2)->default(0);

            // Quality and communication scores
            $table->decimal('quality_score', 3, 2)->default(0); // 0-5 scale
            $table->decimal('communication_score', 3, 2)->default(0); // 0-5 scale
            $table->decimal('overall_rating', 3, 2)->default(0); // 0-5 scale

            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['supplier_id', 'metric_period']);
            $table->index(['supplier_id', 'overall_rating']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_performance_metrics');
    }
};
