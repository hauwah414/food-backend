<?php

namespace Modules\ShopeePay\Entities;

use Illuminate\Database\Eloquent\Model;

class TransactionPaymentShopeePay extends Model
{
    public $primaryKey  = 'id_transaction_payment_shopee_pay';
    protected $fillable = [
        'id_transaction',
        'id_transaction_group',
        'request_id',
        'payment_reference_id',
        'merchant_ext_id',
        'store_ext_id',
        'amount',
        'currency',
        'return_url',
        'point_of_initiation',
        'validity_period',
        'additional_info',
        'transaction_sn',
        'payment_status',
        'user_id_hash',
        'terminal_id',
        'redirect_url_app',
        'redirect_url_http',
        'refund_reference_id',
        'void_reference_id',
        'manual_refund'
    ];
}
