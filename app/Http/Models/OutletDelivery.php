<?php

/**
 * Created by Reliese Model.
 * Date: Thu, 10 May 2018 04:28:18 +0000.
 */

namespace App\Http\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Modules\Merchant\Entities\Merchant;
use Modules\Product\Entities\ProductDetail;
use SMartins\PassportMultiauth\HasMultiAuthApiTokens;
use Hash;

class OutletDelivery extends Authenticatable
{
    

     protected $table = 'outlet_deliveries';

    protected $primaryKey = 'id_outlet_delivery';

    protected $fillable   = [
        'id_outlet',
        'total_price',
        'price_delivery',
        'flat'
    ];

   
}
