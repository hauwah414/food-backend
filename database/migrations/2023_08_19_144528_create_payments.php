<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePayments extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up()
    {
        Schema::dropIfExists('payments');
        
        Schema::create('payments', function (Blueprint $table) {
            $table->increments('id_payment');
            $table->unsignedInteger('id_user');
            $table->string('transaction_payment_number')->nullable();
            $table->integer('transaction_subtotal')->default(0);
            $table->integer('transaction_shipment')->default(0);
            $table->integer('transaction_tax')->default(0);
            $table->integer('transaction_service')->default(0);
            $table->integer('transaction_mdr')->default(0);
            $table->integer('transaction_grandtotal')->default(0);
            $table->integer('transaction_discount')->default(0);
            $table->enum('transaction_payment_status',['Pending','Completed','Cancelled'])->default('Pending');
            $table->enum('transaction_payment_type',['Xendit VA','Xendit'])->default('Xendit VA');
            $table->dateTime('transaction_void_date')->nullable();
            $table->dateTime('transaction_group_date')->nullable();
            $table->dateTime('transaction_completed_at')->nullable();
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
        Schema::dropIfExists('payments');
    }
}
