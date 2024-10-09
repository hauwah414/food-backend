<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeTransactionsTransactionPaymentType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE transactions MODIFY COLUMN trasaction_payment_type ENUM('Xendit VA','Xendit','Paylater') DEFAULT 'Paylater'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE transactions MODIFY COLUMN trasaction_payment_type ENUM('Manual','Midtrans','Offline','Balance','Ovo','Cimb','Ipay88','Shopeepay','Xendit VA','Xendit')");
    }
}
