<?php

/**
 * Created by Reliese Model.
 * Date: Thu, 10 May 2018 04:28:15 +0000.
 */

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $table = 'carts';

    protected $primaryKey = 'id_cart';

    protected $fillable   = [
        'id_outlet',
        'id_product',
        'id_user',
        'qty',
        'custom',
    ];
}
