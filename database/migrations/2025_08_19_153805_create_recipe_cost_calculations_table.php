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
        Schema::create('recipe_cost_calculations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recipe_id');
            $table->timestamp('calculation_date');
            $table->decimal('total_cost', 10, 2);
            $table->decimal('cost_per_serving', 10, 2);
            $table->enum('calculation_method', ['average', 'fifo', 'lifo'])->default('fifo');
            $table->json('cost_breakdown')->nullable();
            $table->unsignedBigInteger('calculated_by')->nullable();
            $table->timestamps();

            // Foreign keys will be added later
            // $table->foreign('recipe_id')->references('id')->on('recipes')->onDelete('cascade');
            // $table->foreign('calculated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_cost_calculations');
    }
};
