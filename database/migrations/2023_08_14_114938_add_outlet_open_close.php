<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOutletOpenClose extends Migration 
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->time('open')->default('09:00');
            $table->time('close')->default('19:00');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn('open');
            $table->dropColumn('close');
        });
    }
}
