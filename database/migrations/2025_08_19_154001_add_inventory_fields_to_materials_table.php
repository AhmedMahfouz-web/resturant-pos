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
        Schema::table('materials', function (Blueprint $table) {
            $table->decimal('minimum_stock_level', 10, 3)->default(0);
            $table->decimal('maximum_stock_level', 10, 3)->default(0);
            $table->decimal('reorder_point', 10, 3)->default(0);
            $table->decimal('reorder_quantity', 10, 3)->default(0);
            $table->foreignId('default_supplier_id')->nullable()->constrained('suppliers')->onDelete('set null');
            $table->string('storage_location', 100)->nullable();
            $table->integer('shelf_life_days')->nullable();
            $table->boolean('is_perishable')->default(false);
            $table->string('barcode', 100)->nullable();
            $table->string('sku', 100)->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropForeign(['default_supplier_id']);
            $table->dropForeign(['category_id']);
            $table->dropColumn([
                'minimum_stock_level',
                'maximum_stock_level',
                'reorder_point',
                'reorder_quantity',
                'default_supplier_id',
                'storage_location',
                'shelf_life_days',
                'is_perishable',
                'barcode',
                'sku',
                'category_id'
            ]);
        });
    }
};
