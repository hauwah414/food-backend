<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductMultiplePhoto extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up()
    {
        Schema::dropIfExists('product_multiple_photos');
        
        Schema::create('product_multiple_photos', function (Blueprint $table) {
            $table->increments('id_product_multiple_photo');
            $table->unsignedInteger('id_product');
            $table->text('photo_image');
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
        Schema::dropIfExists('product_multiple_photos');
    }
}
