<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            // Add new columns for transaction details
            $table->decimal('old_quantity', 10, 2)->nullable()->after('unit_cost');
            $table->decimal('new_quantity', 10, 2)->nullable()->after('old_quantity');
            $table->decimal('adjustment_quantity', 10, 2)->nullable()->after('new_quantity');
        });

        // Update the enum type to include new adjustment types
        DB::statement("ALTER TABLE inventory_transactions MODIFY COLUMN type ENUM('receipt', 'consumption', 'adjustment', 'increase', 'decrease', 'set')");
        
        // Rename 'note' column to 'notes' if it exists
        if (Schema::hasColumn('inventory_transactions', 'note')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                $table->renameColumn('note', 'notes');
            });
        }
    }

    public function down()
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            // Remove the added columns
            $table->dropColumn(['old_quantity', 'new_quantity', 'adjustment_quantity']);
        });

        // Revert enum type back to original
        DB::statement("ALTER TABLE inventory_transactions MODIFY COLUMN type ENUM('receipt', 'consumption', 'adjustment')");
        
        // Rename 'notes' back to 'note' if needed
        if (Schema::hasColumn('inventory_transactions', 'notes')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                $table->renameColumn('notes', 'note');
            });
        }
    }
};
