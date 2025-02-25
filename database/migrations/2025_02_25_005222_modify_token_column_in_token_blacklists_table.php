<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyTokenColumnInTokenBlacklistsTable extends Migration
{
    public function up()
    {
        Schema::table('token_blacklists', function (Blueprint $table) {
            $table->string('token', 512)->change(); // Increase length to 512
        });
    }

    public function down()
    {
        Schema::table('token_blacklists', function (Blueprint $table) {
            $table->string('token')->change(); // Revert to default length if needed
        });
    }
}
