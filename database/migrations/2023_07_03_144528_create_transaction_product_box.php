<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionProductBox extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('transaction_product_boxs');
        
        Schema::create('transaction_product_boxs', function (Blueprint $table) {
            $table->increments('id_transaction_product_box');
            $table->unsignedInteger('id_transaction_product');
            $table->unsignedInteger('id_product');
            $table->string('name_product')->nullable();
            $table->integer('product_price')->nullable();
            $table->integer('base_price')->nullable();
            $table->integer('service')->nullable();
            $table->integer('tax')->nullable();
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
        Schema::dropIfExists('transaction_product_boxs');
    }
}
