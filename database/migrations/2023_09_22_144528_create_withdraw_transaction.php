<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWithdrawTransaction extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up()
    {
        Schema::dropIfExists('withdraw_transactions');
        
        Schema::create('withdraw_transactions', function (Blueprint $table) {
            $table->increments('id_withdraw_transaction');
            $table->unsignedInteger('id_transaction');
            $table->unsignedInteger('id_merchant_log_balance');
            $table->integer('nominal_withdraw')->default(0);
            $table->tinyInteger('status')->default(0);
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
        Schema::dropIfExists('withdraw_transactions');
    }
}
