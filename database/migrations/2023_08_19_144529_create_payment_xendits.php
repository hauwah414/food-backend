<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePaymentXendits extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up()
    {
        Schema::dropIfExists('payment_xendits');
        
        Schema::create('payment_xendits', function (Blueprint $table) {
            $table->increments('id_payment_xendit');
            $table->unsignedInteger('id_payment');
            $table->string('account_number')->nullable();
            $table->string('xendit_id')->nullable();
            $table->string('payment_id')->nullable();
            $table->string('external_id')->nullable();
            $table->string('business_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('type')->nullable();
            $table->integer('amount')->default(0);
            $table->dateTime('expiration_date')->nullable();
            $table->string('failure_code')->nullable();
            $table->string('checkout_url')->nullable();
            $table->enum('status',['PENDING','ACTIVE','INACTIVE','COMPLETED'])->default('PENDING');
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
        Schema::dropIfExists('payment_xendits');
    }
}
