<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure users table engine is InnoDB for foreign key support
        if (Schema::hasTable('users')) {
            try {
                DB::statement('ALTER TABLE users ENGINE = InnoDB');
            } catch (\Throwable $e) {
                // Ignore if engine change fails
            }
        }

        // Add foreign keys to purchase_orders table
        if (Schema::hasTable('purchase_orders') && Schema::hasTable('suppliers') && Schema::hasTable('users')) {
            try {
                Schema::table('purchase_orders', function (Blueprint $table) {
                    $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
                    $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                    $table->foreign('approved_by')->references('id')->on('users')->onDelete('cascade');
                });
            } catch (\Throwable $e) {}
        }

        // Add foreign keys to purchase_order_items table
        if (Schema::hasTable('purchase_order_items') && Schema::hasTable('purchase_orders') && Schema::hasTable('materials')) {
            try {
                Schema::table('purchase_order_items', function (Blueprint $table) {
                    $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('cascade');
                    $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');
                });
            } catch (\Throwable $e) {}
        }

        // Add foreign keys to supplier_performance_metrics table
        if (Schema::hasTable('supplier_performance_metrics') && Schema::hasTable('suppliers')) {
            try {
                Schema::table('supplier_performance_metrics', function (Blueprint $table) {
                    $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
                });
            } catch (\Throwable $e) {}
        }

        // Add foreign keys to supplier_communications table
        if (Schema::hasTable('supplier_communications') && Schema::hasTable('suppliers') && Schema::hasTable('users')) {
            try {
                Schema::table('supplier_communications', function (Blueprint $table) {
                    $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
                    $table->foreign('initiated_by')->references('id')->on('users')->onDelete('cascade');
                });
            } catch (\Throwable $e) {}
        }

        // Add foreign keys to material_receipts table
        if (Schema::hasTable('material_receipts') && Schema::hasTable('materials') && Schema::hasTable('users')) {
            try {
                Schema::table('material_receipts', function (Blueprint $table) {
                    $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');
                    $table->foreign('received_by')->references('id')->on('users')->onDelete('cascade');
                });
            } catch (\Throwable $e) {}
        }

        // Add foreign keys to stock_batches table
        if (Schema::hasTable('stock_batches') && Schema::hasTable('materials')) {
            try {
                Schema::table('stock_batches', function (Blueprint $table) {
                    $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');
                    if (Schema::hasTable('suppliers')) {
                        $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
                    }
                    if (Schema::hasTable('material_receipts')) {
                        $table->foreign('material_receipt_id')->references('id')->on('material_receipts')->onDelete('set null');
                    }
                });
            } catch (\Throwable $e) {}
        }

        // Add foreign keys to stock_alerts table
        if (Schema::hasTable('stock_alerts') && Schema::hasTable('materials')) {
            try {
                Schema::table('stock_alerts', function (Blueprint $table) {
                    $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');
                    if (Schema::hasTable('users')) {
                        $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
                    }
                });
            } catch (\Throwable $e) {}
        }

        // Add foreign keys to recipe_cost_calculations table
        if (Schema::hasTable('recipe_cost_calculations') && Schema::hasTable('recipes')) {
            try {
                Schema::table('recipe_cost_calculations', function (Blueprint $table) {
                    $table->foreign('recipe_id')->references('id')->on('recipes')->onDelete('cascade');
                    if (Schema::hasTable('users')) {
                        $table->foreign('calculated_by')->references('id')->on('users')->onDelete('set null');
                    }
                });
            } catch (\Throwable $e) {}
        }

        // Add foreign keys to materials table (inventory fields)
        if (Schema::hasTable('materials')) {
            try {
                Schema::table('materials', function (Blueprint $table) {
                    if (Schema::hasTable('suppliers')) {
                        $table->foreign('default_supplier_id')->references('id')->on('suppliers')->onDelete('set null');
                    }
                    if (Schema::hasTable('categories')) {
                        $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
                    }
                });
            } catch (\Throwable $e) {}
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign keys from materials table (inventory fields)
        if (Schema::hasTable('materials')) {
            try {
                Schema::table('materials', function (Blueprint $table) {
                    $table->dropForeign(['default_supplier_id']);
                    $table->dropForeign(['category_id']);
                });
            } catch (\Throwable $e) {}
        }

        // Drop foreign keys from recipe_cost_calculations table
        if (Schema::hasTable('recipe_cost_calculations')) {
            try {
                Schema::table('recipe_cost_calculations', function (Blueprint $table) {
                    $table->dropForeign(['recipe_id']);
                    $table->dropForeign(['calculated_by']);
                });
            } catch (\Throwable $e) {}
        }

        // Drop foreign keys from stock_alerts table
        if (Schema::hasTable('stock_alerts')) {
            try {
                Schema::table('stock_alerts', function (Blueprint $table) {
                    $table->dropForeign(['material_id']);
                    $table->dropForeign(['resolved_by']);
                });
            } catch (\Throwable $e) {}
        }

        // Drop foreign keys from stock_batches table
        if (Schema::hasTable('stock_batches')) {
            try {
                Schema::table('stock_batches', function (Blueprint $table) {
                    $table->dropForeign(['material_id']);
                    $table->dropForeign(['supplier_id']);
                    $table->dropForeign(['material_receipt_id']);
                });
            } catch (\Throwable $e) {}
        }

        // Drop foreign keys from material_receipts table
        if (Schema::hasTable('material_receipts')) {
            try {
                Schema::table('material_receipts', function (Blueprint $table) {
                    $table->dropForeign(['material_id']);
                    $table->dropForeign(['received_by']);
                });
            } catch (\Throwable $e) {}
        }

        // Drop foreign keys from supplier_communications table
        if (Schema::hasTable('supplier_communications')) {
            try {
                Schema::table('supplier_communications', function (Blueprint $table) {
                    $table->dropForeign(['supplier_id']);
                    $table->dropForeign(['initiated_by']);
                });
            } catch (\Throwable $e) {}
        }

        // Drop foreign keys from supplier_performance_metrics table
        if (Schema::hasTable('supplier_performance_metrics')) {
            try {
                Schema::table('supplier_performance_metrics', function (Blueprint $table) {
                    $table->dropForeign(['supplier_id']);
                });
            } catch (\Throwable $e) {}
        }

        // Drop foreign keys from purchase_order_items table
        if (Schema::hasTable('purchase_order_items')) {
            try {
                Schema::table('purchase_order_items', function (Blueprint $table) {
                    $table->dropForeign(['purchase_order_id']);
                    $table->dropForeign(['material_id']);
                });
            } catch (\Throwable $e) {}
        }

        // Drop foreign keys from purchase_orders table
        if (Schema::hasTable('purchase_orders')) {
            try {
                Schema::table('purchase_orders', function (Blueprint $table) {
                    $table->dropForeign(['supplier_id']);
                    $table->dropForeign(['created_by']);
                    $table->dropForeign(['approved_by']);
                });
            } catch (\Throwable $e) {}
        }
    }
};
