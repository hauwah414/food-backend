<?php

/**
 * Created by Reliese Model.
 * Date: Thu, 10 May 2018 04:28:18 +0000.
 */

namespace Modules\Franchise\Entities;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Modules\Product\Entities\ProductDetail;
use SMartins\PassportMultiauth\HasMultiAuthApiTokens;
use Hash;

/**
 * Class Outlet
 *
 * @property int $id_outlet
 * @property string $outlet_code
 * @property string $outlet_name
 * @property string $outlet_fax
 * @property string $outlet_address
 * @property int $id_city
 * @property string $outlet_postal_code
 * @property string $outlet_phone
 * @property string $outlet_email
 * @property string $outlet_latitude
 * @property string $outlet_longitude
 * @property \Carbon\Carbon $outlet_open_hours
 * @property \Carbon\Carbon $outlet_close_hours
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property \App\Http\Models\City $city
 * @property \Illuminate\Database\Eloquent\Collection $deals
 * @property \Illuminate\Database\Eloquent\Collection $enquiries
 * @property \Illuminate\Database\Eloquent\Collection $holidays
 * @property \Illuminate\Database\Eloquent\Collection $outlet_photos
 * @property \Illuminate\Database\Eloquent\Collection $product_prices
 *
 * @package App\Models
 */
class OutletConnection3 extends Authenticatable
{
    use Notifiable;
    use HasMultiAuthApiTokens;

    public function findForPassport($username)
    {
        return $this->where('outlet_code', $username)->first();
    }

    public function getAuthPassword()
    {
        return $this->outlet_pin;
    }
    protected $connection = 'mysql3';
    protected $table = 'outlets';
    protected $primaryKey = 'id_outlet';

    protected $hidden = ['outlet_pin'];

    protected $casts = [
        'id_city' => 'int',
        'delivery_order' => 'int'
    ];

    // protected $dates = [
    //  'outlet_open_hours' => 'datetime:H:i:s',
    //  'outlet_close_hours' => 'datetime:H:i:s'
    // ];

    protected $fillable = [
        'id_outlet_seed',
        'outlet_code',
        'outlet_pin',
        'outlet_name',
        'outlet_address',
        'id_city',
        'outlet_postal_code',
        'outlet_phone',
        'outlet_email',
        'outlet_latitude',
        'outlet_longitude',
        'outlet_status',
        'deep_link_gojek',
        'deep_link_grab',
        'delivery_order',
        // 'outlet_open_hours',
        // 'outlet_close_hours'
        'status_franchise',
        'outlet_special_status',
        'plastic_used_status',
        'outlet_special_fee',
        'time_zone_utc'
    ];

    protected $appends  = ['call', 'url'];

    public function getCallAttribute()
    {
        $call = preg_replace("/[^0-9]/", "", $this->outlet_phone);
        return $call;
    }

    public function getUrlAttribute()
    {
        return config('url.api_url') . '/api/outlet/webview/' . $this->id_outlet;
    }

    public function brands()
    {
        return $this->belongsToMany(\Modules\Brand\Entities\Brand::class, 'brand_outlet', 'id_outlet', 'id_brand');
    }

    public function city()
    {
        return $this->belongsTo(\App\Http\Models\City::class, 'id_city');
    }

    public function deals()
    {
        return $this->belongsToMany(\App\Http\Models\Deal::class, 'deals_outlets', 'id_outlet', 'id_deals');
    }

    public function enquiries()
    {
        return $this->hasMany(\App\Http\Models\Enquiry::class, 'id_outlet');
    }

    public function holidays()
    {
        return $this->belongsToMany(\App\Http\Models\Holiday::class, 'outlet_holidays', 'id_outlet', 'id_holiday')
                    ->withTimestamps();
    }

    public function photos()
    {
        return $this->hasMany(OutletPhoto::class, 'id_outlet', 'id_outlet')->orderBy('outlet_photo_order', 'ASC');
    }

    public function outlet_photos()
    {
        return $this->hasMany(\App\Http\Models\OutletPhoto::class, 'id_outlet')->orderBy('outlet_photo_order');
    }

    public function product_prices()
    {
        return $this->hasMany(\App\Http\Models\ProductPrice::class, 'id_outlet');
    }

    public function product_special_price()
    {
        return $this->hasMany(\Modules\Product\Entities\ProductSpecialPrice::class, 'id_outlet');
    }

    public function product_detail()
    {
        return $this->hasMany(\Modules\Product\Entities\ProductDetail::class, 'id_outlet');
    }

    public function user_outlets()
    {
        return $this->hasMany(\App\Http\Models\UserOutlet::class, 'id_outlet');
    }

    public function outlet_schedules()
    {
        return $this->hasMany(\App\Http\Models\OutletSchedule::class, 'id_outlet');
    }

    public function today()
    {
        $hari = date("D");

        switch ($hari) {
            case 'Sun':
                $hari_ini = "Minggu";
                break;

            case 'Mon':
                $hari_ini = "Senin";
                break;

            case 'Tue':
                $hari_ini = "Selasa";
                break;

            case 'Wed':
                $hari_ini = "Rabu";
                break;

            case 'Thu':
                $hari_ini = "Kamis";
                break;

            case 'Fri':
                $hari_ini = "Jumat";
                break;

            default:
                $hari_ini = "Sabtu";
                break;
        }

        return $this->belongsTo(\App\Http\Models\OutletSchedule::class, 'id_outlet', 'id_outlet')->where('day', $hari_ini);
    }

    public function payment_method_outlet()
    {
        return $this->hasMany(\App\Http\Models\PaymentMethodOutlet::class, 'id_outlet');
    }

    public function getOutletLatitudeAttribute($value)
    {
        return preg_replace('/[^0-9.-]/', '', $value);
    }

    public function getOutletLongitudeAttribute($value)
    {
        return preg_replace('/[^0-9.-]/', '', $value);
    }
}
