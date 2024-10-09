<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DeleteOutletTimeOpen extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up()
    {
        Schema::dropIfExists('outlet_time_opens');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::create('outlet_time_opens', function (Blueprint $table) {
            $table->increments('id_outlet_time_open');
            $table->unsignedInteger('id_outlet');
            $table->time('open');
            $table->time('close');
            $table->timestamps();
        });
    }
}
