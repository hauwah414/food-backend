<?php

/**
 * Created by Reliese Model.
 * Date: Mon, 16 Dec 2019 16:39:25 +0700.
 */

namespace Modules\PromoCampaign\Entities;

use Reliese\Database\Eloquent\Model as Eloquent;

/**
 * Class PromoCampaignBuyxgetyProductRequirement
 *
 * @property int $id_promo_campaign_buyxgety_product
 * @property int $id_promo_campaign
 * @property int $id_product
 * @property int $id_product_category
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property \Modules\PromoCampaign\Entities\Product $product
 * @property \Modules\PromoCampaign\Entities\ProductCategory $product_category
 * @property \Modules\PromoCampaign\Entities\PromoCampaign $promo_campaign
 *
 * @package Modules\PromoCampaign\Entities
 */
class PromoCampaignBuyxgetyProductRequirement extends Eloquent
{
    protected $primaryKey = 'id_promo_campaign_buyxgety_product';

    protected $casts = [
        'id_promo_campaign' => 'int',
        'id_product' => 'int',
        'id_product_category' => 'int'
    ];

    protected $fillable = [
        'id_promo_campaign',
        'id_product',
        'id_brand',
        'id_product_category',
        'id_product_variant_group'
    ];

    public function product()
    {
        return $this->belongsTo(\App\Http\Models\Product::class, 'id_product');
    }

    public function product_category()
    {
        return $this->belongsTo(\App\Http\Models\ProductCategory::class, 'id_product_category');
    }

    public function promo_campaign()
    {
        return $this->belongsTo(\Modules\PromoCampaign\Entities\PromoCampaign::class, 'id_promo_campaign');
    }

    public function brand()
    {
        return $this->belongsTo(\Modules\Brand\Entities\Brand::class, 'id_brand');
    }

    public function product_variant_pivot()
    {
        return $this->hasMany(\Modules\ProductVariant\Entities\ProductVariantPivot::class, 'id_product_variant_group', 'id_product_variant_group');
    }
}
