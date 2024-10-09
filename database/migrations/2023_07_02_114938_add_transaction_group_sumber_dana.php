<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTransactionGroupSumberDana extends Migration 
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_groups', function (Blueprint $table) {
            $table->text('sumber_dana')->nullable();
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
            $table->dropColumn('sumber_dana')->nullable();
        });
    }
}
