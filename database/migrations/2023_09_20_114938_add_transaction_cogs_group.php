<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTransactionCogsGroup extends Migration 
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up()
    {
        Schema::table('transaction_groups', function (Blueprint $table) {
            $table->integer('transaction_cogs')->default(0);
        });
       
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transaction_groups', function (Blueprint $table) {
            $table->dropColumn('transaction_cogs')->default(0);
        });
     
    }
}
