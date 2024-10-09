<?php

/**
 * Created by Reliese Model.
 * Date: Thu, 10 May 2018 04:28:15 +0000.
 */

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class CartServingMethod extends Model
{
    protected $table = 'cart_serving_methods';

    protected $primaryKey = 'id_cart_serving_method';

    protected $fillable   = [
        'id_cart',
        'id_product_serving_method',
    ];
}
