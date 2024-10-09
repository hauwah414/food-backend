<?php

/**
 * Created by Reliese Model.
 * Date: Wed, 04 Mar 2020 16:17:49 +0700.
 */

namespace Modules\Promotion\Entities;

use Reliese\Database\Eloquent\Model as Eloquent;

/**
 * Class DealsPromotionTierDiscountProduct
 *
 * @property int $id_deals_tier_discount_products
 * @property int $id_deals
 * @property string $product_type
 * @property int $id_product
 * @property int $id_product_category
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property \App\Models\DealsPromotionTemplate $deals_promotion_template
 * @property \App\Models\ProductCategory $product_category
 *
 * @package App\Models
 */
class DealsPromotionTierDiscountProduct extends Eloquent
{
    protected $primaryKey = 'id_deals_tier_discount_products';

    protected $appends  = [
        // 'get_product'
    ];

    protected $casts = [
        'id_deals' => 'int',
        'id_product' => 'int',
        'id_product_category' => 'int'
    ];

    protected $fillable = [
        'id_deals',
        'product_type',
        'id_product',
        'id_product_category',
        'id_brand',
        'id_product_variant_group'
    ];

    public function deals_promotion_template()
    {
        return $this->belongsTo(\App\Http\Models\DealsPromotionTemplate::class, 'id_deals');
    }

    public function product_category()
    {
        return $this->belongsTo(\App\Http\Models\ProductCategory::class, 'id_product_category');
    }

    public function product()
    {
        return $this->belongsTo(\App\Http\Models\Product::class, 'id_product');
    }

    public function product_group()
    {
        return $this->hasOne(\Modules\ProductVariant\Entities\ProductGroup::class, 'id_product_group', 'id_product');
    }

    public function getGetProductAttribute()
    {

        if ($this->product_type == 'group') {
            $this->load(['product_group' => function ($q) {
                $q->select('id_product_group', 'product_group_code', 'product_group_name', 'id_product_category');
            }]);
        } else {
            $this->load(['product.product_group' => function ($q) {
                $q->select('id_product_group', 'product_group_code', 'product_group_name', 'id_product_category');
            }]);
        }
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
