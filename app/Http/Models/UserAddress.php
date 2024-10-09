<?php

/**
 * Created by Reliese Model.
 * Date: Thu, 10 May 2018 04:28:18 +0000.
 */

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class UserAddress
 *
 * @property int $id_user_address
 * @property string $name
 * @property string $phone
 * @property int $id_user
 * @property int $id_city
 * @property string $address
 * @property string $postal_code
 * @property string $description
 * @property string $primary
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property \App\Http\Models\City $city
 * @property \App\Http\Models\User $user
 *
 * @package App\Models
 */
class UserAddress extends Model
{
    protected $primaryKey = 'id_user_address';

    protected $casts = [
        'latitude'  => 'float',
        'longitude' => 'float'
    ];

    protected $fillable = [
        'name',
        'id_user',
        'short_address',
        'address',
        'postal_code',
        'description',
        'latitude',
        'longitude',
        'favorite',
        'type',
        'main_address',
        'id_city',
        'id_subdistrict',
        'receiver_name',
        'receiver_phone',
        'receiver_email'
    ];
    protected $appends  = ['user_full_address'];

    public function city()
       {
           return $this->belongsTo(\App\Http\Models\City::class, 'id_city');
       }

    public function subdistrict()
    {
        return $this->belongsTo(\App\Http\Models\Subdistricts::class, 'id_subdistrict');
    }


    public function user()
    {
        return $this->belongsTo(\App\Http\Models\User::class, 'id_user');
    }

    public function getTypeAttribute($value)
    {
        return $value ?: '';
    }

    public function getDescriptionAttribute($value)
    {
        return $value ?: '';
    }
    public function getUserFullAddressAttribute()
    {
        $outletFullAddress = [];
        if (!empty($this->address)) {
            $outletFullAddress[] = $this->address;
        }

        if (!empty($this->id_subdistrict)) {
            $outletFullAddress[] = $this->subdistrict->subdistrict_name;
        }

        if (!empty($this->id_city)) {
            $outletFullAddress[] = $this->city->city_name;
        }

        if (!empty($this->postal_code)) {
            $outletFullAddress[] = $this->postal_code;
        }

        // dd($outletFullAddress);

        $outletFullAddress = implode(", ", $outletFullAddress);

        return $outletFullAddress;
    }
}
