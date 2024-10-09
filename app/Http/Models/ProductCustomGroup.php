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

class ProductCustomGroup extends Model
{
    protected $primaryKey = 'id_product_custom_group';
    
    protected $table = 'product_custom_groups';


    protected $fillable = [
        'id_product_parent',
        'id_product',
    ];

}
