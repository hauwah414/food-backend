<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCart extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('carts');
        
        Schema::create('carts', function (Blueprint $table) {
            $table->bigIncrements('id_cart');
            $table->unsignedInteger('id_outlet');
            $table->unsignedInteger('id_product');
            $table->unsignedInteger('id_user');
            $table->integer('qty')->default(0);
            $table->tinyInteger('custom')->default(0);
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
        Schema::dropIfExists('carts');
    }
}
