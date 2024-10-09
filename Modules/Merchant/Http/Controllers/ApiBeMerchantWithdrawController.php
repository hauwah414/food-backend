<?php

namespace Modules\Merchant\Http\Controllers;

use App\Http\Models\InboxGlobal;
use App\Http\Models\InboxGlobalRead;
use App\Http\Models\MonthlyReportTrx;
use App\Http\Models\Outlet;
use App\Http\Models\Product;
use App\Http\Models\ProductPhoto;
use App\Http\Models\Subdistricts;
use App\Http\Models\TransactionProduct;
use App\Http\Models\User;
use App\Http\Models\UserInbox;
use App\Jobs\DisburseJob;
use App\Lib\Shipper;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Http\Models\Setting;
use App\Lib\MyHelper;
use Modules\Brand\Entities\Brand;
use Modules\Brand\Entities\BrandOutlet;
use Modules\Brand\Entities\BrandProduct;
use Modules\Disburse\Entities\BankAccount;
use Modules\Disburse\Entities\BankAccountOutlet;
use Modules\Disburse\Entities\BankName;
use Modules\Disburse\Entities\Disburse;
use Modules\InboxGlobal\Http\Requests\MarkedInbox;
use Modules\Merchant\Entities\Merchant;
use Modules\Merchant\Entities\MerchantInbox;
use Modules\Merchant\Entities\MerchantLogBalance;
use Modules\Merchant\Http\Requests\MerchantCreateStep1;
use Modules\Merchant\Http\Requests\MerchantCreateStep2;
use Modules\Outlet\Entities\DeliveryOutlet;
use DB;
use App\Http\Models\Transaction;
use Modules\Merchant\Entities\MerchantGrading;
use App\Http\Models\WithdrawTransaction;

class ApiBeMerchantWithdrawController  extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->autocrm          = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        $this->product_variant_group = "Modules\ProductVariant\Http\Controllers\ApiProductVariantGroupController";
        $this->online_trx = "Modules\Transaction\Http\Controllers\ApiOnlineTransaction";
    }
    public function balanceDetail(Request $request)
    {
        $post = $request->json()->all();
        $idUser = $request->user()->id;
        $checkMerchant = Merchant::where('id_user', $idUser)->first();
        if (empty($checkMerchant)) {
            return response()->json(['status' => 'fail', 'messages' => ['Data merchant tidak ditemukan']]);
        }

        $currentBalance = MerchantLogBalance::where('id_merchant', $checkMerchant['id_merchant'])->sum('merchant_balance');
        $history = MerchantLogBalance::where('id_merchant', $checkMerchant['id_merchant'])->orderBy('created_at', 'desc');

        if (!empty($post['history_date_start']) && !empty($post['history_date_end'])) {
            $history = $history->whereDate('created_at', '>=', date('Y-m-d', strtotime($post['history_date_start'])))
                ->whereDate('created_at', '<=', date('Y-m-d', strtotime($post['history_date_end'])));
        }

        $history = $history->paginate($post['pagination_total_row'] ?? 15)->toArray();

        foreach ($history['data'] ?? [] as $key => $dt) {
            if ($dt['merchant_balance'] < 0) {
                $title = 'Penarikan Saldo';
                $des = 'Total saldo terpakai';
                $nominal = '-Rp ' . number_format(abs($dt['merchant_balance']), 0, ",", ".");
            } else {
                $title = 'Saldo Masuk';
                $des = 'Total saldo didapat';
                $nominal = 'Rp ' . number_format($dt['merchant_balance'], 0, ",", ".");
            }
            $history['data'][$key] = [
                'date' => MyHelper::dateFormatInd($dt['created_at'], false),
                'title' => $title,
                'description' => $des,
                'nominal' => $dt['merchant_balance'],
                'nominal_text' => $nominal,
            ];
        }

        $res = [
            'current_balance' => (int)$currentBalance,
            'current_balance_text' => 'Rp ' . number_format($currentBalance, 0, ",", "."),
            'history' => $history
        ];

        return response()->json(MyHelper::checkGet($res));
    }

    public function balanceWithdrawalFee(Request $request)
    {
        return $post = $request->json()->all();
        $idUser = $request->user()->id;
        $checkMerchant = Merchant::where('id_user', $idUser)->first();
        if (empty($checkMerchant)) {
            return response()->json(['status' => 'fail', 'messages' => ['Data merchant tidak ditemukan']]);
        }

        if (empty($post['id_bank_account']) || empty($post['id_transaction'])) {
            return response()->json(['status' => 'fail', 'messages' => ['Imcompleted data']]);
        }
        $post['amount_withdrawal'] = 0;
        foreach($post['id_transaction'] as $key){
           $list = MerchantLogBalance::join('transactions', 'transactions.id_transaction', 'merchant_log_balances.merchant_balance_id_reference')
                    ->where('merchant_balance_source', 'Transaction Completed')
                    ->where('merchant_balance_status', 'Pending')
                    ->where('merchant_log_balances.merchant_balance_id_reference',$key)
                    ->where('id_merchant', $checkMerchant['id_merchant'])
                    ->select('merchant_log_balances.*')->first()['merchant_balance']??0;
           $post['amount_withdrawal'] = $post['amount_withdrawal']+$list;
        }
        if ($post['id_transaction'] < 10000) {
            return response()->json(['status' => 'fail', 'messages' => ['Jumlah penarikan minumum ' . number_format(10000, 0, ",", ".")]]);
        }

        if ($post['amount_withdrawal'] <= 0) {
            return response()->json(['status' => 'fail', 'messages' => ['Jumlah penarikan tidak valid']]);
        }

        $currentBalance = MerchantLogBalance::where('id_merchant', $checkMerchant['id_merchant'])->sum('merchant_balance');

        if ($post['amount_withdrawal'] > $currentBalance) {
            return response()->json(['status' => 'fail', 'messages' => ['Jumlah saldo saat ini tidak mencukupi']]);
        }

        $checkBankAccount = BankAccount::join('bank_account_outlets', 'bank_account_outlets.id_bank_account', 'bank_accounts.id_bank_account')
            ->join('bank_name', 'bank_name.id_bank_name', 'bank_accounts.id_bank_name')
            ->where('bank_accounts.id_bank_account', $post['id_bank_account'])
            ->where('id_outlet', $checkMerchant['id_outlet'])
            ->first();

        if (empty($checkBankAccount)) {
            return response()->json(['status' => 'fail', 'messages' => ['Bank account tidak ditemukan']]);
        }

        $amount = $post['amount_withdrawal'];
        //calculate withdrawal fee
        if (empty($checkBankAccount['withdrawal_fee_formula'])) {
            $formula = Setting::where('key', 'withdrawal_fee_global')->first()['value'] ?? null;
        } else {
            $formula = $checkBankAccount['withdrawal_fee_formula'];
        }

        $fee = (!empty($formula) ? MyHelper::calculator($formula, ['amount' => $amount]) : 0);

        if ($amount > $currentBalance) {
            return response()->json(['status' => 'fail', 'messages' => ['Jumlah saldo saat ini tidak mencukupi']]);
        }

        $totalTransfer = $amount - $fee;
        if ($totalTransfer < 0) {
            return response()->json(['status' => 'fail', 'messages' => ['Jumlah saldo yang Anda tarik tidak mencukupi dikarenakan dikenakan fee sebesar ' . number_format($fee, 0, ",", ".")]]);
        }

        $result = [
            'ammount' => 'Rp ' . number_format($amount, 0, ",", "."),
            'fee' => 'Rp ' . number_format($fee, 0, ",", "."),
            'total_withdrawal' => 'Rp ' . number_format($totalTransfer, 0, ",", ".")
        ];

        return response()->json(['status' => 'success', 'result' => $result]);
    }

    public function balanceWithdrawal(Request $request)
    {
        $post = $request->json()->all();
        $idUser = $request->user()->id;
        $checkMerchant = Merchant::where('id_user', $idUser)->first();
        if (empty($checkMerchant)) {
            return response()->json(['status' => 'fail', 'messages' => ['Data merchant tidak ditemukan']]);
        }

         if (empty($post['id_bank_account']) || empty($post['id_transaction'])) {
            return response()->json(['status' => 'fail', 'messages' => ['Imcompleted data']]);
        }
        $post['amount_withdrawal'] = 0;
        foreach($post['id_transaction'] as $key){
           $list = MerchantLogBalance::join('transactions', 'transactions.id_transaction', 'merchant_log_balances.merchant_balance_id_reference')
                    ->where('merchant_balance_source', 'Transaction Completed')
                    ->where('merchant_balance_status', 'Pending')
                    ->where('merchant_log_balances.merchant_balance_id_reference',$key)
                    ->where('id_merchant', $checkMerchant['id_merchant'])
                    ->select('merchant_log_balances.*')->first()['merchant_balance']??0;
           $post['amount_withdrawal'] = $post['amount_withdrawal']+$list;
        }

        if ($post['amount_withdrawal'] < 10000) {
            return response()->json(['status' => 'fail', 'messages' => ['Jumlah penarikan minumum ' . number_format(10000, 0, ",", ".")]]);
        }

        if ($post['amount_withdrawal'] <= 0) {
            return response()->json(['status' => 'fail', 'messages' => ['Jumlah penarikan tidak valid']]);
        }

        $currentBalance = MerchantLogBalance::where('id_merchant', $checkMerchant['id_merchant'])->sum('merchant_balance');

        if ($post['amount_withdrawal'] > $currentBalance) {
            return response()->json(['status' => 'fail', 'messages' => ['Jumlah saldo saat ini tidak mencukupi']]);
        }

        $checkBankAccount = BankAccount::join('bank_account_outlets', 'bank_account_outlets.id_bank_account', 'bank_accounts.id_bank_account')
                            ->join('bank_name', 'bank_name.id_bank_name', 'bank_accounts.id_bank_name')
                            ->where('bank_accounts.id_bank_account', $post['id_bank_account'])
                            ->where('id_outlet', $checkMerchant['id_outlet'])
                            ->first();

        if (empty($checkBankAccount)) {
            return response()->json(['status' => 'fail', 'messages' => ['Bank account tidak ditemukan']]);
        }

        $amount = $post['amount_withdrawal'];
        //calculate withdrawal fee
        if (empty($checkBankAccount['withdrawal_fee_formula'])) {
            $formula = Setting::where('key', 'withdrawal_fee_global')->first()['value'] ?? null;
        } else {
            $formula = $checkBankAccount['withdrawal_fee_formula'];
        }

        $fee = (!empty($formula) ? MyHelper::calculator($formula, ['amount' => $amount]) : 0);

        if ($amount > $currentBalance) {
            return response()->json(['status' => 'fail', 'messages' => ['Jumlah saldo saat ini tidak mencukupi']]);
        }

        $totalTransfer = $amount - $fee;
        if ($totalTransfer < 0) {
            return response()->json(['status' => 'fail', 'messages' => ['Jumlah saldo yang Anda tarik tidak mencukupi dikarenakan dikenakan fee sebesar ' . number_format($fee, 0, ",", ".")]]);
        }
        
        DB::beginTransaction();
        $amount = $totalTransfer;
        $dt = [
            'id_merchant' => $checkMerchant['id_merchant'],
            'balance_nominal' => -$amount,
            'id_transaction' => $post['id_bank_account'],
            'source' => 'Withdrawal',
            'merchant_balance_status'=>"Pending"
        ];
        $saveBalanceMerchant = app('Modules\Merchant\Http\Controllers\ApiMerchantTransactionController')->insertBalanceMerchant($dt);
        
        $date = date('d M Y H:i');
        if (!empty($saveBalanceMerchant['id_merchant_log_balance'])) {
            if ($fee > 0) {
                foreach($post['id_transaction'] as $key){
                    $list = MerchantLogBalance::join('transactions', 'transactions.id_transaction', 'merchant_log_balances.merchant_balance_id_reference')
                             ->where('merchant_balance_source', 'Transaction Completed')
                             ->where('merchant_balance_status', 'Pending')
                             ->where('merchant_log_balances.merchant_balance_id_reference',$key)
                             ->where('id_merchant', $checkMerchant['id_merchant'])
                             ->select('merchant_log_balances.*')->first();
                    $dtC = [
                     'nominal_withdraw'=>$list['merchant_balance'],
                     'id_transaction' => $key,
                     'id_merchant_log_balance'=>$saveBalanceMerchant['id_merchant_log_balance'],
                     'status'=>1
                    ];
                    $create = WithdrawTransaction::create($dtC);
                    $lists = MerchantLogBalance::join('transactions', 'transactions.id_transaction', 'merchant_log_balances.merchant_balance_id_reference')
                             ->where('merchant_balance_source', 'Transaction Completed')
                             ->where('merchant_balance_status', 'Pending')
                             ->where('merchant_log_balances.merchant_balance_id_reference',$key)
                             ->where('id_merchant', $checkMerchant['id_merchant'])
                             ->select('merchant_log_balances.*')->update([
                                 'merchant_balance_status'=>"On Progress"
                             ]);
                 }
                $dtFee = [
                    'id_merchant' => $checkMerchant['id_merchant'],
                    'balance_nominal' => -$fee,
                    'id_transaction' => $saveBalanceMerchant['id_merchant_log_balance'],
                    'source' => 'Withdrawal Fee',
                    'merchant_balance_status'=>"Pending"
                ];
                $saveBalanceMerchant = app('Modules\Merchant\Http\Controllers\ApiMerchantTransactionController')->insertBalanceMerchant($dtFee);
            }
        }
        
        DB::commit();
        if ($saveBalanceMerchant) {
            return response()->json(MyHelper::checkGet([
                'amount' => $post['amount_withdrawal'],
                'fee' => $fee,
                'bank_account_number' => $checkBankAccount['beneficiary_account'],
                'bank_account_name' => $checkBankAccount['bank_name'],
                'bank_image' => (empty($checkBankAccount['bank_image']) ? config('url.storage_url_api') . 'img/default.jpg' : config('url.storage_url_api') . $checkBankAccount['bank_image']),
                'date' => MyHelper::dateFormatInd($date)
            ]));
        } else {
            return response()->json(MyHelper::checkUpdate($saveBalanceMerchant));
        }
    }

    public function balanceList(Request $request)
    {
        $post = $request->json()->all();
        $idUser = $request->user()->id;
        $checkMerchant = Merchant::where('id_user', $idUser)->first();
       
        if (empty($checkMerchant)) {
            return response()->json(['status' => 'fail', 'messages' => ['Data merchant tidak ditemukan']]);
        }

        $list1 = MerchantLogBalance::join('transactions', 'transactions.id_transaction', 'merchant_log_balances.merchant_balance_id_reference')
                    ->where('merchant_balance_source', 'Transaction Completed')
                    ->where('merchant_balance_status', 'Pending')
                    ->where('id_merchant', $checkMerchant['id_merchant'])->select('merchant_log_balances.*','transactions.id_transaction','transactions.transaction_receipt_number');
       
        $list = $list1->orderBy('created_at', 'desc')->get()->toArray();
        $currentBalance = 0;
        foreach ($list as $key => $dt) {
           $currentBalance = $currentBalance+$dt['merchant_balance'];
            $list[$key] = [
                'date' => $dt['created_at'],
                'nominal' => $dt['merchant_balance'],
                'nominal_text' => 'Rp ' . number_format($dt['merchant_balance'], 0, ",", "."),
                'id_transaction' => $dt['id_transaction'],
                'transaction_receipt_number' => $dt['transaction_receipt_number'],
            ];
        }
        $res = [
            'current_balance' => (int)$currentBalance,
            'current_balance_text' => 'Rp ' . number_format($currentBalance, 0, ",", "."),
            'list' => $list
        ];
        return response()->json(MyHelper::checkGet($res));
    }
    public function balancePending(Request $request)
    {
        $post = $request->json()->all();
        $idUser = $request->user()->id;
        $checkMerchant = Merchant::where('id_user', $idUser)->first();
       
        if (empty($checkMerchant)) {
            return response()->json(['status' => 'fail', 'messages' => ['Data merchant tidak ditemukan']]);
        }
       
        $list2 = MerchantLogBalance::join('bank_accounts', 'bank_accounts.id_bank_account', 'merchant_log_balances.merchant_balance_id_reference')
                ->whereIn('merchant_balance_source', ['Withdrawal', 'Withdrawal Fee'])
                ->where('merchant_balance_status', 'Pending')
                ->where('id_merchant', $checkMerchant['id_merchant'])->select('merchant_log_balances.*');

        if (!empty($post['search_key'])) {
        
            $list2 = $list2->where(function ($q) use ($post) {
                $q->where('beneficiary_name', 'like', '%' . $post['search_key'] . '%')
                    ->orWhere('beneficiary_account', 'like', '%' . $post['search_key'] . '%')
                    ->orWhere('merchant_balance_source', 'like', '%' . $post['search_key'] . '%');
            });
        }

        $list = $list2->orderBy('created_at', 'desc')->get();

        foreach ($list ?? [] as $key => $dt) {
            $transaction = [];
            $bankAccount = [];
            if ($dt['merchant_balance_source'] == 'Transaction Completed' || $dt['merchant_balance_source'] == 'Transaction Consultation Completed') {
                $transaction = Transaction::where('id_transaction', $dt['merchant_balance_id_reference'])->first();
            } elseif ($dt['merchant_balance_source'] == 'Withdrawal') {
                $bankAccount =  BankAccount::join('bank_name', 'bank_name.id_bank_name', 'bank_accounts.id_bank_name')
                    ->where('bank_accounts.id_bank_account', $dt['merchant_balance_id_reference'])
                    ->first();
            }

            $list[$key] = [
                'date' => $dt['created_at'],
                'source' => $dt['merchant_balance_source'],
                'nominal' => $dt['merchant_balance'],
                'data_transaction' => $transaction,
                'data_bank_account' => $bankAccount
            ];
        }

        return response()->json(MyHelper::checkGet($list));
    }

}
