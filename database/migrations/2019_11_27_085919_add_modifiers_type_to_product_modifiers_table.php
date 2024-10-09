<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddModifiersTypeToProductModifiersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_modifiers', function (Blueprint $table) {
            $table->enum('modifier_type',['Global','Specific'])->after('id_product_modifier');
            $table->dropForeign('fk_product_modifiers_products');
            $table->dropColumn('id_product');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_modifiers', function (Blueprint $table) {
            $table->dropColumn('modifier_type');
            $table->unsignedInteger('id_product')->after('id_product_modifier');
            $table->foreign('id_product', 'fk_product_modifiers_products')->references('id_product')->on('products')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }
}
