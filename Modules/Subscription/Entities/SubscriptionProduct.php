<?php

/**
 * Created by Reliese Model.
 * Date: Wed, 18 Mar 2020 15:39:35 +0700.
 */

namespace Modules\Subscription\Entities;

use Reliese\Database\Eloquent\Model as Eloquent;

/**
 * Class SubscriptionProduct
 *
 * @property int $id_subscription_product
 * @property int $id_subscription
 * @property int $id_product
 * @property int $id_product_category
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property \Modules\Subscription\Entities\Product $product
 * @property \Modules\Subscription\Entities\ProductCategory $product_category
 * @property \Modules\Subscription\Entities\Subscription $subscription
 *
 * @package Modules\Subscription\Entities
 */
class SubscriptionProduct extends Eloquent
{
    protected $primaryKey = 'id_subscription_product';

    protected $casts = [
        'id_subscription' => 'int',
        'id_product' => 'int',
        'id_product_category' => 'int'
    ];

    protected $fillable = [
        'id_subscription',
        'id_product',
        'id_product_category',
        'id_brand',
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

    public function subscription()
    {
        return $this->belongsTo(\Modules\Subscription\Entities\Subscription::class, 'id_subscription');
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
