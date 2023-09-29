<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('origin_maps', function (Blueprint $table) {
            $table->dropColumn('metrics');
        });
    }

    public function down()
    {
        Schema::table('origin_maps', function (Blueprint $table) {
            $table->json('metrics')->nullable();
        });
    }
};
