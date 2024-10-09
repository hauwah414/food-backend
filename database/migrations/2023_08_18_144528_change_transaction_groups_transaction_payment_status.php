<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeTransactionGroupsTransactionPaymentStatus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE transaction_groups MODIFY COLUMN transaction_payment_status ENUM('Pending','Unpaid','Paid','Completed','Cancelled')DEFAULT 'Pending'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE transaction_groups MODIFY COLUMN transaction_payment_status ENUM('Pending','Paid','Completed','Cancelled')DEFAULT 'Pending'");
    }
}
