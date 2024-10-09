<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBannerWebsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('banner_webs', function (Blueprint $table) {
            $table->increments('id_banner_web');
            $table->string('image');
            $table->integer('id_reference')->nullable();
            $table->string('url')->nullable();
            $table->tinyInteger('position');
            $table->string('type')->nullable();
            $table->dateTime('banner_start')->nullable();
            $table->dateTime('banner_end')->nullable();
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();
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
        Schema::dropIfExists('banner_webs');
    }
}
