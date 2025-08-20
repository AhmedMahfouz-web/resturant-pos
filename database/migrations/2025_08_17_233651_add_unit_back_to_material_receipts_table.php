<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('material_receipts', function (Blueprint $table) {
            $table->string('unit')->after('quantity_received');
        });
    }

    public function down()
    {
        Schema::table('material_receipts', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }
};
