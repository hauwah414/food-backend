<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOutletDeliveryType extends Migration 
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('outlet_deliveries', function (Blueprint $table) {
            $table->tinyInteger('flat')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('outlet_deliveries', function (Blueprint $table) {
            $table->dropColumn('flat');
        });
    }
}
