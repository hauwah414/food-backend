<?php

namespace Modules\Favorite\Entities;

use App\Http\Models\Outlet;
use App\Http\Models\Product;
use App\Http\Models\ProductModifier;
use App\Http\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Product\Entities\ProductGlobalPrice;
use Modules\Product\Entities\ProductSpecialPrice;
use Modules\ProductVariant\Entities\ProductVariant;

class Favorite extends Model
{
    protected $primaryKey = 'id_favorite';

    protected $fillable = [
        'id_outlet',
        'id_brand',
        'id_product',
        'id_product_variant_group',
        'id_user',
        'notes'
    ];

    protected $appends = ['product'];

    public function modifiers()
    {
        return $this->belongsToMany(ProductModifier::class, 'favorite_modifiers', 'id_favorite', 'id_product_modifier');
    }

    public function variants()
    {
        return $this->belongsToMany(ProductVariant::class, 'product_variant_pivot', 'id_product_variant_group', 'id_product_variant', 'id_product_variant_group');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product', 'id_product');
    }

    protected function getProductPrice($id_outlet, $id_product)
    {
        $different_price = $this->outlet_different_price;
        if ($different_price) {
            return ProductSpecialPrice::where([
                'id_outlet' => $id_outlet,
                'id_product' => $id_product
            ])->pluck('product_special_price')->first();
        } else {
            return ProductGlobalPrice::select('product_global_price')->where('id_product', $id_product)->pluck('product_global_price')->first() ?: 0;
        }
    }

    public function getProductAttribute()
    {
        $id_outlet = $this->id_outlet;
        $id_product = $this->id_product;
        $product_qty = $this->product_qty;
        $product = Product::select('id_product', 'product_name', 'product_code', 'product_description')->where([
            'id_product' => $id_product
        ])->with([
            'photos' => function ($query) {
                $query->select('id_product', 'product_photo')->limit(1);
            }
        ])->first();
        if ($product) {
            return [
                'product_name' => $product->product_name,
                'product_code' => $product->product_code,
                'product_description' => $product->product_description,
                'url_product_photo' => optional($product->photos[0] ?? null)->url_product_photo ?: config('url.storage_url_api') . 'img/product/item/default.png',
                'price' => $this->getProductPrice($id_outlet, $id_product)
            ];
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id');
    }

    public function favorite_modifiers()
    {
        return $this->hasMany(FavoriteModifier::class, 'id_favorite', 'id_favorite');
    }
}
