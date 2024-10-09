<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionServingMethod extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up()
    {
        Schema::dropIfExists('transaction_product_serving_methods');
        
        Schema::create('transaction_product_serving_methods', function (Blueprint $table) {
            $table->increments('id_transaction_product_serving_method');
            $table->unsignedInteger('id_transaction_product');
            $table->unsignedInteger('id_product_serving_method');
            $table->string('serving_name')->nullable();
            $table->string('package')->nullable();
            $table->integer('unit_price')->nullable();
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
        Schema::dropIfExists('transaction_product_serving_methods');
    }
}
