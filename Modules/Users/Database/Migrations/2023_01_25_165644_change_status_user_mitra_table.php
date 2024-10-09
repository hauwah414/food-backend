<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeStatusUserMitraTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function __construct() {
        DB::getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
    }
    public function up()
    {
         DB::statement('ALTER TABLE `users` CHANGE `level` `level` ENUM("Super Admin","Admin","Mitra","Customer") default "Customer";');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
         DB::statement('ALTER TABLE `users` CHANGE `level` `level` ENUM("Super Admin","Admin","Admin Outlet","Customer") default "Customer";');
    }
}
