<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddShipment extends Migration 
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_shipments', function (Blueprint $table) {
            $table->string('destination_latitude')->nullable();
            $table->string('destination_longitude')->nullable();
            $table->string('destination_postal_code')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transaction_shipments', function (Blueprint $table) {
           $table->dropColumn('destination_latitude')->nullable();
            $table->dropColumn('destination_longitude')->nullable();
            $table->dropColumn('destination_postal_code')->nullable();
        });
    }
}
