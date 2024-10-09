<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductCustomGroups extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('product_custom_groups');
        
        Schema::create('product_custom_groups', function (Blueprint $table) {
            $table->bigIncrements('id_product_custom_group');
            $table->unsignedInteger('id_product_parent');
            $table->unsignedInteger('id_product');
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
        Schema::dropIfExists('product_custom_groups');
    }
}
