<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('notifications');
        
        Schema::create('notifications', function (Blueprint $table) {
            $table->bigIncrements('id_notification');
            $table->unsignedInteger('id_user');
            $table->enum('type_notif',['transaction','info']);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->morphs('customerable');
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
        Schema::dropIfExists('product_price_users');
    }
}
