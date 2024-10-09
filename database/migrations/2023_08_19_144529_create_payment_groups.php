<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePaymentGroups extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up()
    {
        Schema::dropIfExists('payment_groups');
        
        Schema::create('payment_groups', function (Blueprint $table) {
            $table->increments('id_payment_group');
            $table->unsignedInteger('id_payment');
            $table->unsignedInteger('id_transaction_group');
            $table->integer('transaction_subtotal')->default(0);
            $table->integer('transaction_shipment')->default(0);
            $table->integer('transaction_tax')->default(0);
            $table->integer('transaction_service')->default(0);
            $table->integer('transaction_grandtotal')->default(0);
            $table->integer('transaction_discount')->default(0);
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
        Schema::dropIfExists('payment_groups');
    }
}
