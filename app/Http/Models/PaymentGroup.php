<?php

namespace App\Http\Models;

use App\Http\Models\Configs;
use App\Http\Models\LogBalance;
use App\Http\Models\Transaction;
use App\Http\Models\User;
use App\Jobs\FraudJob;
use Illuminate\Database\Eloquent\Model;
use Modules\Transaction\Entities\TransactionGroup;
use App\Lib\MyHelper;
use DB;

class PaymentGroup extends Model
{
    protected $table = 'payment_groups';

    protected $primaryKey = 'id_payment_group';

    protected $fillable   = [
        'id_payment',
        'id_transaction_group',
        'transaction_subtotal',
        'transaction_shipment',
        'transaction_service',
        'transaction_tax',
        'transaction_grandtotal',
        'transaction_discount'
    ];
   public function triggerPaymentCompleted($data = [])
    {
        // check complete allowed
       
        if ($this->transaction_payment_status != 'Pending') {
            return $this->transaction_payment_status == 'Completed';
        }
        $transaction = TransactionGroup::where('id_transaction_group',$this->id_transaction_group)->first();
        $transaction->update([
            'transaction_payment_status' => 'Completed',
            'transaction_completed_at' => date('Y-m-d H:i:s')
        ]);
        return true;
    }
     public function triggerPaymentCancelled($data = [])
    {
        DB::beginTransaction();
        // check complete allowed
        if ($this->transaction_payment_status != 'Pending') {
            return $this->transaction_payment_status == 'Completed';
        }

        // update transaction payment cancelled
        $transaction = TransactionGroup::where('id_transaction_group',$this->id_transaction_group)->first();
        $transaction->update([
            'transaction_payment_status' => 'Unpaid',
        ]);
        
        DB::commit();
        return true;
    }
}
