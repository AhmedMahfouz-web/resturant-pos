<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->string('reference_type')->nullable()->after('user_id');
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            $table->text('notes')->nullable()->after('reference_id');

            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down()
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropIndex(['reference_type', 'reference_id']);
            $table->dropColumn(['reference_type', 'reference_id', 'notes']);
        });
    }
};
