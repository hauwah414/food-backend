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

class ProductMultiplePhoto extends Model
{
    protected $primaryKey = 'id_product_multiple_photo';
    
    protected $table = 'product_multiple_photos';


    protected $fillable = [
        'photo_image',
        'id_product',
    ];
    protected $appends = [
        'url_photo_image',
    ];

     public function getUrlPhotoImageAttribute()
    {
       
        return config('url.storage_url_api') . $this->photo_image;
       
    }
}
