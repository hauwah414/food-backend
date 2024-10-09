<?php

/**
 * Created by Reliese Model.
 * Date: Thu, 10 May 2018 04:28:15 +0000.
 */

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class CartCustom extends Model
{
    protected $table = 'cart_customs';

    protected $primaryKey = 'id_cart_custom';

    protected $fillable   = [
        'id_cart',
        'id_product',
        'qty',
    ];
}
