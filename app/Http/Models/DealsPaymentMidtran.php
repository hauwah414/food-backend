<?php

/**
 * Created by Reliese Model.
 * Date: Thu, 10 May 2018 04:28:15 +0000.
 */

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class DealsPaymentMidtran
 *
 * @property int $id_transaction_payment
 * @property int $id_deals
 * @property string $masked_card
 * @property string $approval_code
 * @property string $bank
 * @property string $eci
 * @property string $transaction_time
 * @property string $gross_amount
 * @property string $order_id
 * @property string $payment_type
 * @property string $signature_key
 * @property string $status_code
 * @property string $vt_transaction_id
 * @property string $transaction_status
 * @property string $fraud_status
 * @property string $status_message
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property \App\Http\Models\Deal $deal
 *
 * @package App\Models
 */
class DealsPaymentMidtran extends Model
{
    protected $primaryKey = 'id_transaction_payment';

    protected $casts = [
        'id_deals' => 'int'
    ];

    protected $fillable = [
        'id_deals',
        'id_deals_user',
        'masked_card',
        'approval_code',
        'bank',
        'eci',
        'transaction_time',
        'gross_amount',
        'order_id',
        'payment_type',
        'signature_key',
        'status_code',
        'vt_transaction_id',
        'transaction_status',
        'fraud_status',
        'status_message'
    ];

    public function deal()
    {
        return $this->belongsTo(\App\Http\Models\Deal::class, 'id_deals');
    }
}
