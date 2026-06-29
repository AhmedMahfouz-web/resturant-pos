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
        if (Schema::hasTable('recipe_cost_calculations')) {
            if (Schema::hasColumn('recipe_cost_calculations', 'calculation_method')) {
                Schema::table('recipe_cost_calculations', function (Blueprint $table) {
                    $table->dropColumn('calculation_method');
                });
            }

            Schema::table('recipe_cost_calculations', function (Blueprint $table) {
                $table->enum('calculation_method', ['purchase_price', 'fifo', 'average_cost'])->default('fifo')->after('cost_per_serving');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('recipe_cost_calculations')) {
            if (Schema::hasColumn('recipe_cost_calculations', 'calculation_method')) {
                Schema::table('recipe_cost_calculations', function (Blueprint $table) {
                    $table->dropColumn('calculation_method');
                });
            }

            Schema::table('recipe_cost_calculations', function (Blueprint $table) {
                $table->enum('calculation_method', ['average', 'fifo', 'lifo'])->default('fifo')->after('cost_per_serving');
            });
        }
    }
};
