<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOutletAttachment extends Migration 
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->string('name_npwp')->nullable();
            $table->string('name_nib')->nullable();
            $table->string('no_nib')->nullable();
            $table->string('no_npwp')->nullable();
            $table->text('npwp_attachment')->nullable();
            $table->text('nib_attachment')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn('name_npwp');
            $table->dropColumn('name_nib');
            $table->dropColumn('no_nib');
            $table->dropColumn('no_npwp');
            $table->dropColumn('npwp_attachment');
            $table->dropColumn('nib_attachment');
        });
    }
}
