<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('material_receipts') && Schema::hasColumn('material_receipts', 'unit')) {
            Schema::table('material_receipts', function (Blueprint $table) {
                $table->dropColumn('unit');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('material_receipts') && !Schema::hasColumn('material_receipts', 'unit')) {
            Schema::table('material_receipts', function (Blueprint $table) {
                $table->string('unit')->after('quantity_received');
            });
        }
    }
};
