<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOutletDefaultOngkosKirim extends Migration 
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up()
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->Integer('default_ongkos_kirim')->default(0);
        });
        DB::statement("ALTER TABLE outlets MODIFY COLUMN flat ENUM('default','flat','dinamis') default 'default';");
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->tinyInteger('flat')->default(0);
            $table->dropColumn('default_ongkos_kirim')->default(0);
        });
    }
}
