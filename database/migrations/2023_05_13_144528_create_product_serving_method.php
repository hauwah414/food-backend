<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductServingMethod extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('product_serving_methods');
        
        Schema::create('product_serving_methods', function (Blueprint $table) {
            $table->bigIncrements('id_product_serving_method');
            $table->unsignedInteger('id_product');
            $table->string('serving_name')->nullable();
            $table->integer('unit_price')->default(0);
            $table->enum('package',['all','pcs'])->default('all');
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
        Schema::dropIfExists('product_serving_methods');
    }
}
