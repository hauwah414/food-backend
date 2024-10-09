<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DeleteQueueTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('campaign_whatsapp_queues');
        Schema::dropIfExists('campaign_email_queues');
        Schema::dropIfExists('campaign_push_queues');
        Schema::dropIfExists('campaign_sms_queues');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::create('campaign_whatsapp_queues', function (Blueprint $table) {
            $table->increments('id_campaign_whatsapp_queue');
            $table->unsignedInteger('id_campaign');
            $table->string('whatsapp_queue_to');
            $table->dateTime('whatsapp_queue_send_at')->nullable();
            $table->timestamps();
            
            $table->foreign('id_campaign', 'fk_campaign_whatsapp_queues_campaigns')
              ->references('id_campaign')->on('campaigns')
              ->onUpdate('cascade')
              ->onDelete('cascade');
        });
        Schema::create('campaign_email_queues', function(Blueprint $table)
        {
            $table->increments('id_campaign_email_queue');
            $table->integer('id_campaign')->unsigned()->index('fk_campaign_email_queues_campaigns');
            $table->string('email_queue_to');
            $table->string('email_queue_subject');
            $table->text('email_queue_content', 16777215);
            $table->dateTime('email_queue_send_at')->nullable();
            $table->timestamps();
            
            $table->foreign('id_campaign', 'fk_campaign_email_queues_campaigns')->references('id_campaign')->on('campaigns')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
        Schema::create('campaign_push_queues', function(Blueprint $table)
        {
          $table->increments('id_campaign_push_queue');
          $table->integer('id_campaign')->unsigned()->index('fk_campaign_push_queues_campaigns');
          $table->string('push_queue_to');
          $table->string('push_queue_subject');
          $table->text('push_queue_content', 16777215)->nullable();
          $table->dateTime('push_queue_send_at')->nullable();
          $table->timestamps();

          $table->foreign('id_campaign', 'fk_campaign_push_queues_campaigns')->references('id_campaign')->on('campaigns')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
        Schema::create('campaign_sms_queues', function(Blueprint $table)
        {
          $table->increments('id_campaign_sms_queue');
          $table->integer('id_campaign')->unsigned()->index('fk_campaign_sms_queues_campaigns');
          $table->string('sms_queue_to', 18);
          $table->text('sms_queue_content', 65535);
          $table->dateTime('sms_queue_send_at')->nullable();
          $table->timestamps();

          $table->foreign('id_campaign', 'fk_campaign_sms_queues_campaigns')->references('id_campaign')->on('campaigns')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }
}
