<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCartCustom extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('cart_customs');
        
        Schema::create('cart_customs', function (Blueprint $table) {
            $table->bigIncrements('id_cart_custom');
            $table->unsignedInteger('id_product');
            $table->unsignedInteger('id_cart');
            $table->integer('qty')->default(0);
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
        Schema::dropIfExists('cart_customs');
    }
}
