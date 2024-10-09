<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductPriceUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('product_price_users');
        
        Schema::create('product_price_users', function (Blueprint $table) {
            $table->bigIncrements('id_product_price_user');
            $table->unsignedInteger('id_product');
            $table->unsignedInteger('id_user');
            $table->integer('product_price')->default(0);
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
        Schema::dropIfExists('product_price_users');
    }
}
