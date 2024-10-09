<?php

/**
 * Created by Reliese Model.
 * Date: Thu, 10 May 2018 04:28:17 +0000.
 */

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Membership
 *
 * @property int $id_membership
 * @property string $membership_name
 * @property int $min_total_value
 * @property int $min_total_count
 * @property int $retain_days
 * @property int $retain_min_total_value
 * @property int $retain_min_total_count
 * @property float $benefit_point_multiplier
 * @property float $benefit_cashback_multiplier
 * @property string $benefit_promo_id
 * @property float $benefit_discount
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property \Illuminate\Database\Eloquent\Collection $users
 *
 * @package App\Models
 */
class Membership extends Model
{
    protected $primaryKey = 'id_membership';

    protected $casts = [
        'min_total_value' => 'int',
        'min_total_count' => 'int',
        'min_total_balance' => 'int',
        'retain_days' => 'int',
        'retain_min_total_value' => 'int',
        'retain_min_total_count' => 'int',
        'retain_min_total_balance' => 'int',
        'benefit_point_multiplier' => 'float',
        'benefit_cashback_multiplier' => 'float',
        'benefit_discount' => 'float',
        'cashback_maximum' => 'int'
    ];

    protected $fillable = [
        'membership_name',
        'membership_type',
        'membership_name_color',
        'membership_image',
        'membership_card',
        'membership_next_image',
        'min_total_value',
        'min_total_count',
        'min_total_balance',
        'min_total_achievement',
        'retain_days',
        'retain_min_total_value',
        'retain_min_total_count',
        'retain_min_total_balance',
        'retain_min_total_achievement',
        'benefit_point_multiplier',
        'benefit_cashback_multiplier',
        'benefit_promo_id',
        'benefit_discount',
        'benefit_text',
        'cashback_maximum'
    ];

    public function users()
    {
        return $this->belongsToMany(\App\Http\Models\User::class, 'users_memberships', 'id_membership', 'id_user')
                    ->withPivot('id_log_membership', 'min_total_value', 'min_total_count', 'retain_date', 'retain_min_total_value', 'retain_min_total_count', 'benefit_point_multiplier', 'benefit_cashback_multiplier', 'benefit_promo_id', 'benefit_discount')
                    ->withTimestamps();
    }

    public function membership_promo_id()
    {
        return $this->hasMany(MembershipPromoId::class, 'id_membership', 'id_membership');
    }
}
