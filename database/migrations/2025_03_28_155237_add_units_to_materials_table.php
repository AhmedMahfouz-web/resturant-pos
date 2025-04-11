<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->string('stock_unit');
            $table->string('recipe_unit');
            $table->decimal('conversion_rate', 10, 4);
        });
    }

    public function down()
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['stock_unit', 'recipe_unit', 'conversion_rate']);
        });
    }
};
