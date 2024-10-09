<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTransactionProductServingMethod extends Migration 
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up()
    {
        Schema::table('transaction_products', function (Blueprint $table) {
            $table->Integer('transaction_serving_method')->default(0);
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
            $table->dropColumn('transaction_serving_method')->default(0);
        });
    }
}
