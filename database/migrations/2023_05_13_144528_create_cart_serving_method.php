<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCartServingMethod extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('cart_serving_methods');
        
        Schema::create('cart_serving_methods', function (Blueprint $table) {
            $table->bigIncrements('id_cart_serving_method');
            $table->unsignedInteger('id_cart');
            $table->unsignedInteger('id_product_serving_method');
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
        Schema::dropIfExists('cart_serving_methods');
    }
}
