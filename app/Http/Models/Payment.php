<?php

namespace App\Http\Models;

use App\Http\Models\Configs;
use App\Http\Models\LogBalance;
use App\Http\Models\Transaction;
use App\Http\Models\User;
use App\Jobs\FraudJob;
use Illuminate\Database\Eloquent\Model;
use App\Http\Models\PaymentGroup;
use App\Lib\MyHelper;
use DB;
use App\Http\Models\Payment;
use Modules\Transaction\Entities\TransactionGroup;

class Payment extends Model
{
    protected $table = 'payments';

    protected $primaryKey = 'id_payment';

    protected $fillable   = [
        'id_user',
        'transaction_payment_number',
        'transaction_subtotal',
        'transaction_shipment',
        'transaction_service',
        'transaction_tax',
        'transaction_mdr',
        'transaction_grandtotal',
        'transaction_discount',
        'transaction_payment_status',
        'transaction_payment_type',
        'transaction_void_date',
        'transaction_group_date',
        'transaction_completed_at',
        'file_rekap',
        'file_invoice'
    ];
    protected $appends  = ['url_file_rekap','url_file_invoice'];

    public function getUrlFileRekapAttribute()
    {
        return ENV('STORAGE_URL_API') . $this->file_rekap;    
    }
    public function getUrlFileInvoiceAttribute()
    {
        return ENV('STORAGE_URL_API') . $this->file_invoice;    
    }
    public function payments()
    {
        return $this->hasMany(PaymentGroup::class, 'id_payment');
    }
    public function xendits()
    {
        return $this->hasOne(PaymentXendit::class, 'id_payment');
    }

    public function user()
    {
        return $this->belongsTo(\App\Http\Models\User::class, 'id_user');
    }

    public function triggerPaymentCompleted($data = [])
    {
        // check complete allowed
        if ($this->transaction_payment_status != 'Pending') {
            return $this->transaction_payment_status == 'Completed';
        }
        DB::beginTransaction();
        $update = Payment::where('id_payment',$this->id_payment)->update([
            'transaction_payment_status' => 'Completed',
            'transaction_completed_at' => date('Y-m-d H:i:s')
        ]);
        $getTransactions = PaymentGroup::where('id_payment', $this->id_payment)->get();
       foreach ($getTransactions as $transaction) {
           $trx = PaymentGroup::where('id_payment_group', $transaction->id_payment_group)->first();
           if($trx){
              $trxs = TransactionGroup::where('id_transaction_group',$trx->id_transaction_group)->update([
                   'transaction_payment_status' => 'Completed',
                    'transaction_completed_at' => date('Y-m-d H:i:s')
              ]);
           }
        }
        // check fraud
        if ($this->user) {
            $this->user->update([
                'count_transaction_day' => $this->user->count_transaction_day + 1,
                'count_transaction_week' => $this->user->count_transaction_week + 1,
            ]);
        }
        $user = User::where('id',$this->id_user)->first();
        app('Modules\Autocrm\Http\Controllers\ApiAutoCrm')->SendAutoCRM('Payment Status', $user->phone, [
            "date" => MyHelper::dateFormatInd($transaction->transaction_date),
            'receipt_number'   => $this->transaction_payment_number,
            'status'    => 'Pembayaran Berhasil'
        ]);
        DB::commit();
        return true;
    }

    /**
     * Called when payment completed
     * @return [type] [description]
     */
    public function triggerPaymentCancelled($data = [])
    {
         if ($this->transaction_payment_status != 'Pending') {
            return $this->transaction_payment_status == 'Completed';
        }

        DB::beginTransaction();
        $this->update([
            'transaction_payment_status' => 'Cancelled',
            'transaction_void_date' => date('Y-m-d H:i:s')
        ]);

        $getTransactions = PaymentGroup::where('id_payment', $this->id_payment)->get();
        
        foreach ($getTransactions as $transaction) {
            $transaction = TransactionGroup::where('id_transaction_group',$transaction->id_transaction_group)->update([
                'transaction_payment_status' => 'Unpaid',
            ]);
        }
        $user = User::where('id',$this->id_user)->first();
        app('Modules\Autocrm\Http\Controllers\ApiAutoCrm')->SendAutoCRM('Payment Status', $this->user->phone, [
            "date" => MyHelper::dateFormatInd($this->transaction_Date),
            'receipt_number'   => $this->transaction_receipt_number,
            'status'    => 'Pembayaran Dibatalkan'
        ]);

        DB::commit();
        return true;
    }
}
