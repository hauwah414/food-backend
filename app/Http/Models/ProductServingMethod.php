<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;
use Cache;
use Modules\Merchant\Entities\Merchant;
use Modules\ProductVariant\Entities\ProductVariant;
use Modules\ProductVariant\Entities\ProductVariantGroup;
use App\Lib\MyHelper;
use Modules\Product\Entities\ProductModifierGroup;
use Modules\ProductVariant\Entities\ProductVariantGroupWholesaler;

class ProductServingMethod extends Model
{
    protected $primaryKey = 'id_product_serving_method';
    
    protected $table = 'product_serving_methods';


    protected $fillable = [
        'id_product',
        'serving_name',
        'unit_price',
        'package',
    ];

}
