<?php

/**
 * Created by Reliese Model.
 * Date: Thu, 10 May 2018 04:28:18 +0000.
 */

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPriceUser extends Model
{
    protected $table = 'product_price_users';

    protected $primaryKey = 'id_product_price_user';

    protected $fillable = [
        'id_product',
        'id_user',
        'product_price',
    ];
}
