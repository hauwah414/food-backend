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
class Outlet extends Authenticatable
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

    protected $primaryKey = 'id_outlet';

    protected $hidden = ['outlet_pin'];

    protected $casts = [
        'id_city' => 'int',
        'delivery_order' => 'int'
    ];

    protected $fillable = [
        'id_outlet_seed',
        'outlet_code',
        'outlet_pin',
        'outlet_name',
        'outlet_description',
        'outlet_license_number',
        'outlet_address',
        'id_city',
        'id_subdistrict',
        'outlet_postal_code',
        'outlet_phone',
        'outlet_email',
        'outlet_latitude',
        'outlet_longitude',
        'outlet_status',
        'outlet_is_closed',
        'outlet_image_cover',
        'outlet_image_logo_portrait',
        'outlet_image_logo_landscape',
        'deep_link_gojek',
        'deep_link_grab',
        'delivery_order',
        // 'outlet_open_hours',
        // 'outlet_close_hours'
        'status_franchise',
        'outlet_special_status',
        'plastic_used_status',
        'outlet_special_fee',
        'outlet_referral_code',
        'outlet_total_rating',
        'time_zone_utc',
        'open',
        'close',
        'flat',
        'fee',
        'default_ongkos_kirim',
        'first_order',
        'last_order',
        'name_npwp',
        'name_nib',
        'no_nib',
        'no_npwp',
        'npwp_attachment',
        'nib_attachment',
    ];

    protected $appends  = ['outlet_time','call', 'url','url_npwp_attachment','url_nib_attachment', 'url_outlet_image_cover', 'url_outlet_image_logo_portrait', 'url_outlet_image_logo_landscape', 'outlet_full_address'];

    public function getOutletTimeAttribute()
    {
        return date("H:s",strtotime($this->first_order)).' - '.date("H:s",strtotime($this->last_order));
    }
    public function getCallAttribute()
    {
         if (substr($this->outlet_phone, 0, 2) == '62') {
            $this->outlet_phone = substr($this->outlet_phone, 2);
        } elseif (substr($this->outlet_phone, 0, 3) == '+62') {
            $this->outlet_phone = substr($this->outlet_phone, 3);
        }elseif (substr($this->outlet_phone, 0, 1) == '0') {
            $this->outlet_phone = substr($this->outlet_phone, 1);
        }

        if (substr($this->outlet_phone, 0, 1) != '0') {
            $this->outlet_phone = '62' . $this->outlet_phone;
        }
        $call = preg_replace("/[^0-9]/", "", $this->outlet_phone);
        return env('URL_WA').'/'.$call;
    }

    public function getUrlNpwpAttachmentAttribute()
    {
        return ENV('STORAGE_URL_API') . $this->npwp_attachment;    
    }
    public function getUrlNibAttachmentAttribute()
    {
       
        return ENV('STORAGE_URL_API'). $this->nib_attachment;
    }
    public function getUrlAttribute()
    {
        return config('url.api_url') . '/api/outlet/webview/' . $this->id_outlet;
    }

    public function getUrlOutletImageCoverAttribute()
    {
        if (empty($this->outlet_image_cover)) {
            return config('url.storage_url_api') . 'default_image/outlet_cover.png';
        } else {
            return config('url.storage_url_api') . $this->outlet_image_cover;
        }
    }

    public function getUrlOutletImageLogoPortraitAttribute()
    {
        if (empty($this->outlet_image_logo_portrait)) {
            return config('url.storage_url_api') . 'default_image/outlet_logo_portrait.png';
        } else {
            return config('url.storage_url_api') . $this->outlet_image_logo_portrait;
        }
    }

    public function getUrlOutletImageLogoLandscapeAttribute()
    {
        if (empty($this->outlet_image_logo_landscape)) {
            return config('url.storage_url_api') . 'default_image/outlet_logo_lanscape.png';
        } else {
            return config('url.storage_url_api') . $this->outlet_image_logo_landscape;
        }
    }

    public function brands()
    {
        return $this->belongsToMany(\Modules\Brand\Entities\Brand::class, 'brand_outlet', 'id_outlet', 'id_brand')->orderBy('brands.order_brand');
    }

    public function brand_outlets()
    {
        return $this->hasMany(\Modules\Brand\Entities\BrandOutlet::class, 'id_outlet');
    }
    public function city()
    {
        return $this->belongsTo(\App\Http\Models\City::class, 'id_city');
    }

    public function subdistrict()
    {
        return $this->belongsTo(\App\Http\Models\Subdistricts::class, 'id_subdistrict');
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

        return $this->belongsTo(OutletSchedule::class, 'id_outlet', 'id_outlet')->where('day', $hari_ini);
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

    public function delivery_outlet()
    {
        return $this->hasMany(\Modules\Outlet\Entities\DeliveryOutlet::class, 'id_outlet');
    }

    public function merchant()
    {
        return $this->hasOne(Merchant::class, 'id_outlet');
    }

    public function doctors()
    {
        return $this->hasMany(\Modules\Doctor\Entities\Doctor::class, 'id_outlet');
    }

    public function getOutletFullAddressAttribute()
    {
        $outletFullAddress = [];
        if (!empty($this->outlet_address)) {
            $outletFullAddress[] = $this->outlet_address;
        }

        if (!empty($this->id_subdistrict)) {
            $outletFullAddress[] = $this->subdistrict->subdistrict_name;
        }

        if (!empty($this->id_city)) {
            $outletFullAddress[] = $this->city->city_name;
        }

        if (!empty($this->outlet_postal_code)) {
            $outletFullAddress[] = $this->outlet_postal_code;
        }

        // dd($outletFullAddress);

        $outletFullAddress = implode(", ", $outletFullAddress);

        return $outletFullAddress;
    }
}
