<?php

/**
 * Created by Reliese Model.
 * Date: Thu, 10 May 2018 04:28:19 +0000.
 */

namespace App\Http\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Modules\UserFeedback\Entities\UserFeedbackLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use App\Traits\HasInfobipToken;

class User extends Authenticatable
{
    use HasApiTokens;
    use Notifiable;
    use HasInfobipToken;

    protected $connection = 'mysql';

    public function findForPassport($username)
    {
        if (!strpos($username, '@')) {
            if (substr($username, 0, 2) == '62') {
             $username = substr($username, 2);
            } elseif (substr($username, 0, 3) == '+62') {
                $username = substr($username, 3);
            }

            if (substr($username, 0, 1) != '0') {
                $username = '0' . $username;
            }
        }
        $user = $this->where('phone', $username)->orwhere('email',$username)->first();
        return $user;
    }

    public function validateForPassportPasswordGrant($password)
    {
        try {
            if (Crypt::decryptString($password) == true) {
                return true;
            }
        } catch (DecryptException $e) {
            return Hash::check($password, $this->password);
        }
    }

    protected $primaryKey = "id";
    protected $casts = [
        'id_membership' => 'int',
        'id_city' => 'int',
        'points' => 'int',
        'count_transaction_day' => 'int',
        'count_transaction_week' => 'int'
    ];

    protected $dates = [
        'birthday'
    ];

    protected $hidden = [
        'password',
        'remember_token'
    ];

    protected $fillable = [
        'name',
        'phone',
        'id_membership',
        'email',
        'password',
        'temporary_password',
        'photo',
        'id_card_image',
        'id_city',
        'id_subdistrict',
        'gender',
        'provider',
        'birthday',
        'relationship',
        'phone_verified',
        'email_verified',
        'email_verified_valid_time',
        'level',
        'points',
        'balance',
        'count_complete_profile',
        'last_complete_profile',
        'complete_profile',
        'complete_profile_date',
        'android_device',
        'ios_device',
        'ios_apps_version',
        'android_apps_version',
        'is_suspended',
        'is_deleted',
        'remember_token',
        'count_transaction_day',
        'count_transaction_week',
        'subtotal_transaction',
        'count_transaction',
        'count_login_failed',
        'new_login',
        'pin_changed',
        'first_pin_change',
        'celebrate',
        'job',
        'address',
        'address_postal_code',
        'email_verify_request_status',
        'otp_forgot',
        'otp_request_status',
        'otp_valid_time',
        'otp_available_time_request',
        'otp_increment',
        'transaction_online',
        'transaction_online_status',
        'note_suspend',
        'id_department'
    ];

    protected $appends = [
        'url_photo',
        'call'
    ];
     public function getCallAttribute()
    {
      if (substr($this->phone, 0, 2) == '62') {
            $this->phone = substr($this->phone, 2);
        } elseif (substr($this->phone, 0, 3) == '+62') {
            $this->phone = substr($this->phone, 3);
        }elseif (substr($this->phone, 0, 1) == '0') {
            $this->phone = substr($this->phone, 1);
        }

        if (substr($this->phone, 0, 1) != '0') {
            $this->phone = '62' . $this->phone;
        }
        return env('URL_WA').'/'.$this->phone;
    }
    public function city()
    {
        return $this->belongsTo(\App\Http\Models\City::class, 'id_city');
    }

    public function autocrm_email_logs()
    {
        return $this->hasMany(\App\Http\Models\AutocrmEmailLog::class, 'id', 'id_user');
    }

    public function user_outlets()
    {
        return $this->hasOne(\App\Http\Models\UserOutlet::class, 'id_user', 'id');
    }

    public function autocrm_push_logs()
    {
        return $this->hasMany(\App\Http\Models\AutocrmPushLog::class, 'id', 'id_user');
    }

    public function autocrm_sms_logs()
    {
        return $this->hasMany(\App\Http\Models\AutocrmSmsLog::class, 'id', 'id_user');
    }

    public function campaigns()
    {
        return $this->hasMany(\App\Http\Models\Campaign::class, 'id', 'id_user');
    }

    public function deals_payment_manuals()
    {
        return $this->hasMany(\App\Http\Models\DealsPaymentManual::class, 'id_user_confirming');
    }

    public function transaction_payment_manuals()
    {
        return $this->hasMany(\App\Http\Models\TransactionPaymentManual::class, 'id_user_confirming');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'id_user', 'id')->orderBy('created_at', 'DESC');
    }

    public function history_transactions()
    {
        return $this->hasMany(Transaction::class, 'id_user', 'id')->select('id_user', 'id_transaction', 'id_outlet', 'transaction_receipt_number', 'trasaction_type', 'transaction_grandtotal', 'transaction_payment_status', 'transaction_date')->orderBy('transaction_date', 'DESC');
    }

    public function addresses()
    {
        return $this->hasMany(UserAddress::class, 'id', 'id_user');
    }

    public function user_devices()
    {
        return $this->hasMany(\App\Http\Models\UserDevice::class, 'id', 'id_user');
    }

    public function features()
    {
        return $this->belongsToMany(\App\Http\Models\Feature::class, 'user_features', 'id_user', 'id_feature');
    }

    public function user_inboxes()
    {
        return $this->hasMany(\App\Http\Models\UserInbox::class, 'id', 'id_user');
    }

    public function user_membership()
    {
        return $this->belongsTo(\App\Http\Models\Membership::class, 'id_membership')->select('id_membership', 'membership_name');
    }

    public function memberships()
    {
        return $this->belongsToMany(\App\Http\Models\Membership::class, 'users_memberships', 'id_user', 'id_membership')
                    ->withPivot('id_log_membership', 'min_total_value', 'min_total_count', 'retain_date', 'retain_min_total_value', 'retain_min_total_count', 'benefit_point_multiplier', 'benefit_cashback_multiplier', 'benefit_promo_id', 'benefit_discount')
                    ->withTimestamps()->orderBy('id_log_membership', 'DESC');
    }

    public function point()
    {
        return $this->hasMany(LogPoint::class, 'id_user', 'id')->orderBy('created_at', 'DESC');
    }

    public function log_balance()
    {
        return $this->hasMany(LogBalance::class, 'id_user', 'id')->orderBy('created_at', 'DESC');
    }

    public function history_balance()
    {
        return $this->hasMany(LogBalance::class, 'id_user', 'id')->orderBy('created_at', 'DESC');
    }

    public function pointTransaction()
    {
        return $this->hasMany(LogPoint::class, 'id_user', 'id')->orderBy('created_at', 'DESC')->where('source', '=', 'transaction');
    }

    public function pointVoucher()
    {
        return $this->hasMany(LogPoint::class, 'id_user', 'id')->orderBy('created_at', 'DESC')->where('source', '=', 'voucher');
    }

    public function promotion_queue()
    {
        return $this->hasMany(PromotionQueue::class, 'id_user', 'id');
    }

    public function promotionSents()
    {
        return $this->hasMany(PromotionSent::class, 'id_user', 'id')->orderBy('series_no', 'ASC');
    }

    public function favorites()
    {
        return $this->hasMany(\Modules\Favorite\Entities\Favorite::class, 'id_user');
    }

    public function log_popup()
    {
        return $this->hasOne(UserFeedbackLog::class, 'id_user');
    }

    public function referred_user()
    {
        return $this->belongsToMany(User::class, 'promo_campaign_referral_transactions', 'id_referrer', 'id_user');
    }

    public function referred_transaction()
    {
        return $this->hasMany(\Modules\PromoCampaign\Entities\PromoCampaignReferralTransaction::class, 'id_referrer', 'id');
    }

    public function getChallengeKeyAttribute()
    {
        $password = md5($this->password);
        return $password . '15F1AB77951B5JAO';
    }

    public function quest_user_redemption()
    {
        return $this->hasMany(\Modules\Quest\Entities\QuestUserRedemption::class, 'id_user', 'id');
    }

    public function devices()
    {
        return $this->hasMany(UserDevice::class, 'id_user');
    }

    public function log_popup_user_rating()
    {
        return $this->hasMany(\Modules\UserRating\Entities\UserRatingLog::class, 'id_user')->orderBy('last_popup')->orderBy('id_user_rating_log');
    }

    public function getUrlPhotoAttribute()
    {
        if (!empty($this->attributes['photo'])) {
            $url_photo = env('STORAGE_URL_API') . $this->attributes['photo'];
        } else {
            $url_photo = null;
        }

        return $url_photo;
    }
}
