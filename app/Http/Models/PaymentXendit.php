<?php

namespace App\Http\Models;

use App\Http\Models\Configs;
use App\Http\Models\LogBalance;
use App\Http\Models\Transaction;
use App\Http\Models\User;
use App\Jobs\FraudJob;
use Illuminate\Database\Eloquent\Model;
use Modules\Transaction\Entities\TransactionGroup;

class PaymentXendit extends Model
{
    protected $table = 'payment_xendits';

    protected $primaryKey = 'id_payment_xendit';

    protected $fillable   = [
        'id_payment',
        'account_number',
        'xendit_id',
        'payment_id',
        'external_id',
        'business_id',
        'phone',
        'type',
        'amount',
        'expiration_date',
        'failure_code',
        'checkout_url',
        'status',
    ];
  public $items = null;
  public function payVA(&$errors = [])
    {
        if ($this->account_number) {
            return true;
        }

        if (\Cache::has('xendit_confirm_' . $this->id_payment)) {
            $create = \Cache::get('xendit_confirm_' . $this->id_payment);
        } else {
            $xenditController = app('Modules\Xendit\Http\Controllers\XenditController');
            $create = $xenditController->createVAGroup($this->type, $this->external_id, $this->amount, [
                'phone' => $this->phone,
                'items' => $this->transaction,
                'type' => $this->type,
                'expiration_date' => $this->expiration_date,
            ], $errors);
        }

        if ($create) {
            \Cache::put('xendit_confirm_' . $this->id_payment, $create, now()->addMinutes(10));
            $this->xendit_id = $create['id'] ?? null;
            $this->business_id = $create['business_id'] ?? null;
            $this->account_number = $create['account_number'] ?? null;
            $this->external_id = $create['external_id'] ?? $this->external_id;
            $this->status = $create['status'] ?? null;
            $this->expiration_date = $create['expiration_date'] ? date('Y-m-d H:i:s', strtotime($create['expiration_date'])) : null;
            $result = true;
        } else {
            $result = false;
        }
        $save = $this->save();
        return $result;
    }

}
