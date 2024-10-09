<?php

namespace Modules\Product\Entities;

use Illuminate\Database\Eloquent\Model;

class ProductPromoCategory extends Model
{
    public $primaryKey = 'id_product_promo_category';
    protected $fillable = [
        'product_promo_category_order',
        'product_promo_category_name',
        'product_promo_category_description',
        'product_promo_category_photo'
    ];
    public function products()
    {
        return $this->belongsToMany(\App\Http\Models\Product::class, 'product_product_promo_categories', 'id_product_promo_category', 'id_product');
    }
}
