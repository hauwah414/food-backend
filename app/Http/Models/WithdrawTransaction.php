<?php

/**
 * Created by Reliese Model.
 * Date: Thu, 10 May 2018 04:28:15 +0000.
 */

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawTransaction extends Model
{
    protected $table = 'withdraw_transactions';

    protected $primaryKey = 'id_withdraw_transaction';

    protected $fillable   = [
        'id_transaction',
        'id_merchant_log_balance',
        'nominal_withdraw',
        'status'
    ];

}
