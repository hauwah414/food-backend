<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTransactionCogs extends Migration 
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up()
    {
        Schema::table('transaction_products', function (Blueprint $table) {
            $table->Integer('transaction_product_cogs')->default(0);
            $table->Integer('transaction_product_fee')->default(0);
        });
        Schema::table('transaction_product_boxs', function (Blueprint $table) {
            $table->Integer('cogs')->default(0);
            $table->Integer('fee')->default(0);
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->Integer('transaction_cogs')->default(0);
            $table->Integer('transaction_outlet_fee')->default(0);
        });
       
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transaction_products', function (Blueprint $table) {
            $table->dropColumn('transaction_product_cogs')->default(0);
            $table->dropColumn('transaction_product_fee')->default(0);
        });
        Schema::table('transaction_product_boxs', function (Blueprint $table) {
            $table->dropColumn('cogs')->default(0);
            $table->dropColumn('fee')->default(0);
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('transaction_cogs')->default(0);
            $table->dropColumn('transaction_outlet_fee')->default(0);
        });
    }
}
