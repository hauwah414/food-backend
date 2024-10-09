<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOutletDelivery extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('outlet_deliveries');
        
        Schema::create('outlet_deliveries', function (Blueprint $table) {
            $table->bigIncrements('id_outlet_delivery');
            $table->unsignedInteger('id_outlet');
            $table->integer('total_price')->nullable();
            $table->integer('price_delivery')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('outlet_deliveries');
    }
}
