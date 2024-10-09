<?php

namespace Modules\Product\Entities;

use App\Http\Models\ProductModifier;
use Illuminate\Database\Eloquent\Model;
use Modules\OutletApp\Entities\ProductModifierGroupInventoryBrand;

class ProductModifierGroup extends Model
{
    public $primaryKey = 'id_product_modifier_group';
    protected $fillable = [
        'product_modifier_group_name',
        'product_modifier_group_order',
        'created_at',
        'updated_at'
    ];

    public function product_modifier_group_pivots()
    {
        return $this->hasMany(ProductModifierGroupPivot::class, 'id_product_modifier_group')
            ->leftJoin('products', 'products.id_product', 'product_modifier_group_pivots.id_product')
            ->leftJoin('product_variants', 'product_variants.id_product_variant', 'product_modifier_group_pivots.id_product_variant');
    }

    public function product_modifier()
    {
        return $this->hasMany(ProductModifier::class, 'id_product_modifier_group')->orderBy('product_modifier_order', 'asc');
    }

    public function inventory_brand()
    {
        return $this->hasMany(ProductModifierGroupInventoryBrand::class, 'id_product_modifier_group');
    }
}
