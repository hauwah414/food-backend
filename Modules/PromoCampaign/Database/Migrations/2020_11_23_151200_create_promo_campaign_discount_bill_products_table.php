<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePromoCampaignDiscountBillProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('promo_campaign_discount_bill_products', function (Blueprint $table) {
            $table->increments('id_promo_campaign_discount_bill_product');
            $table->unsignedInteger('id_promo_campaign');
            $table->unsignedInteger('id_product')->nullable();
            $table->unsignedInteger('id_product_category')->nullable();
            $table->integer('id_brand')->nullable()->index();
            $table->timestamps();

            $table->foreign('id_promo_campaign', 'fk_promo_campaign_discount_bill_products_promo_campaign')->references('id_promo_campaign')->on('promo_campaigns')->onUpdate('CASCADE')->onDelete('CASCADE');
            $table->foreign('id_product', 'fk_promo_campaign_discount_bill_products_products')->references('id_product')->on('products')->onUpdate('CASCADE')->onDelete('CASCADE')->nullable();
            $table->foreign('id_product_category', 'fk_promo_campaign_discount_bill_products_product_category')->references('id_product_category')->on('product_categories')->onUpdate('CASCADE')->onDelete('CASCADE')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('promo_campaign_discount_bill_products');
    }
}
