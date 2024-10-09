<?php

namespace Modules\IPay88\Lib;

use App\Http\Models\Configs;
use App\Jobs\DisburseJob;
use App\Jobs\FraudJob;
use Illuminate\Support\Facades\Log;
use DB;
use App\Http\Models\Transaction;
use App\Http\Models\TransactionPickup;
use App\Http\Models\TransactionMultiplePayment;
use App\Http\Models\TransactionPaymentBalance;
use App\Http\Models\DealsUser;
use App\Http\Models\Deal;
use App\Http\Models\DealsVoucher;
use Modules\Subscription\Entities\SubscriptionUser;
use Modules\Subscription\Entities\Subscription;
use App\Http\Models\User;
use App\Http\Models\Setting;
use Modules\IPay88\Entities\LogIpay88;
use Modules\IPay88\Entities\TransactionPaymentIpay88;
use Modules\IPay88\Entities\DealsPaymentIpay88;
use Modules\IPay88\Entities\SubscriptionPaymentIpay88;
use App\Lib\MyHelper;

/**
 * IPay88 Payment Integration Class
 */
class IPay88
{
    public static $obj = null;
    public function __construct()
    {
        $this->notif = "Modules\Transaction\Http\Controllers\ApiNotification";
        $this->autocrm = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        $this->promo_campaign = "Modules\PromoCampaign\Http\Controllers\ApiPromoCampaign";
        $this->voucher  = "Modules\Deals\Http\Controllers\ApiDealsVoucher";
        $this->trx = "Modules\Transaction\Http\Controllers\ApiOnlineTransaction";
        $this->setting_fraud = "Modules\SettingFraud\Http\Controllers\ApiFraud";
        $this->deals_claim   = "Modules\Deals\Http\Controllers\ApiDealsClaim";
        $this->subscription  = "Modules\Subscription\Http\Controllers\ApiSubscriptionVoucher";

        $this->posting_url = ENV('IPAY88_POSTING_URL');
        $this->requery_url = ENV('IPAY88_REQUERY_URL');
        $this->void_url = ENV('IPAY88_VOID_URL');
        $this->merchant_code = ENV('IPAY88_MERCHANT_CODE');
        $this->merchant_key = ENV('IPAY88_MERCHANT_KEY');
        $cc_id = strpos(ENV('IPAY88_POSTING_URL'), 'sandbox') !== false ? 1 : 35;
        $this->payment_id = [
            'Credit_Card' => $cc_id,
            'Credit Card' => $cc_id,
            'CREDIT_CARD' => $cc_id,
            'CREDIT_CARD_BCA' => 52,
            'CREDIT_CARD_BRI' => 35,
            'CREDIT_CARD_CIMB' => 42,
            'CREDIT_CARD_CIMB_AUTHORIZATION' => 56,
            'CREDIT_CARD_CIMB IPG)' => 34,
            'CREDIT_CARD_DANAMON' => 45,
            'CREDIT_CARD_MANDIRI' => 53,
            'CREDIT_CARD_MAYBANK' => 43,
            'CREDIT_CARD_UNIONPAY' => 54,
            'CREDIT_CARD_UOB' => 46,
            'MAYBANK_VA' => 9,
            'MANDIRI_ATM' => 17,
            'BCA_VA' => 25,
            'BNI_VA' => 26,
            'PERMATA_VA' => 31,
            'LinkAja' => 13,
            'OVO' => 63,
            'PAYPAL' => 6,
            'KREDIVO' => 55,
            'ALFAMART' => 60,
            'INDOMARET' => 65
        ];
        $this->currency = ENV('IPAY88_CURRENCY', 'IDR');
    }
    /**
     * Create object from static function
     * @return IPay88 IPay88 Instance
     */
    public static function create()
    {
        if (!self::$obj) {
            self::$obj = new self();
        }
        return self::$obj;
    }

    /**
     * Signing data (add signature parameter)
     * @param  array $data array of full unsigned data
     * @return array       array of signed data
     */
    public function sign($data)
    {
        $string = $this->merchant_key . $data['MerchantCode'] . $data['RefNo'] . $data['Amount'] . $data['Currency'] . $data['xfield1'];
        $hex2bin = function ($hexSource) {
            $bin = '';
            for ($i = 0; $i < strlen($hexSource); $i = $i + 2) {
                $bin .= chr(hexdec(substr($hexSource, $i, 2)));
            }
            return $bin;
        };
        $signature = base64_encode($hex2bin(sha1($string)));
        $data['Signature'] = $signature;
        return $data;
    }
    /**
     * generate formdata to be send to IPay88
     * @param  Integer $reference id_transaction/id_deals_user
     * @param  string $type type of transaction ('trx'/'deals')
     * @return Array       array formdata
     */
    public function generateData($reference, $type = 'trx', $payment_method = null)
    {
        $data = [
            'MerchantCode' => $this->merchant_code,
            'PaymentId' => $this->getPaymentId($payment_method),
            'Currency' => $this->currency,
            'Lang' => 'UTF-8'
        ];
        if ($type == 'trx') {
            $trx = Transaction::with('user')
            ->join('transaction_payment_ipay88s', 'transaction_payment_ipay88s.id_transaction', '=', 'transactions.id_transaction')
            ->where('transactions.id_transaction', $reference)->first();
            $payment_ipay = TransactionPaymentIpay88::where('id_transaction', $trx->id_transaction)->first();
            if (!($trx && $payment_ipay)) {
                return false;
            }
            $data += [
                'RefNo' => $trx->transaction_receipt_number,
                'Amount' => $trx->amount,
                'ProdDesc' => Setting::select('value_text')->where('key', 'ipay88_product_desc')->pluck('value_text')->first() ?: $trx->transaction_receipt_number,
                'UserName' => $trx->user->name,
                'UserEmail' => $trx->user->email,
                'UserContact' => $payment_ipay->user_contact ?: $trx->user->phone,
                'Remark' => '',
                'ResponseURL' => config('url.api_url') . 'api/ipay88/detail/trx',
                'BackendURL' => config('url.api_url') . 'api/ipay88/notif/trx',
                'xfield1' => ''
            ];
        } elseif ($type == 'deals') {
            $deals_user = DealsUser::with('user')
            ->where([
                'deals_users.id_deals_user' => $reference,
                'deals_users.payment_method' => 'Ipay88'
            ])
            ->join('deals_payment_ipay88s', 'deals_payment_ipay88s.id_deals_user', '=', 'deals_users.id_deals_user')
            ->join('deals', 'deals.id_deals', '=', 'deals_payment_ipay88s.id_deals')
            ->first();
            $payment_ipay = DealsPaymentIpay88::where('id_deals_user', $deals_user->id_deals_user)->first();
            if (!($deals_user && $payment_ipay)) {
                return false;
            }
            $data += [
                'RefNo' => $deals_user->order_id,
                'Amount' => $deals_user->amount,
                'ProdDesc' => 'Voucher ' . $deals_user->deals_title,
                'UserName' => $deals_user->user->name,
                'UserEmail' => $deals_user->user->email,
                'UserContact' => $payment_ipay->user_contact ?: $deals_user->user->phone,
                'Remark' => '',
                'ResponseURL' => config('url.api_url') . 'api/ipay88/detail/deals',
                'BackendURL' => config('url.api_url') . 'api/ipay88/notif/deals',
                'xfield1' => ''
            ];
        } elseif ($type == 'subscription') {
            $deals_user = SubscriptionUser::with('user')
            ->where([
                'subscription_users.id_subscription_user' => $reference,
                'subscription_users.payment_method' => 'Ipay88'
            ])
            ->join('subscription_payment_ipay88s', 'subscription_payment_ipay88s.id_subscription_user', '=', 'subscription_users.id_subscription_user')
            ->join('subscriptions', 'subscriptions.id_subscription', '=', 'subscription_payment_ipay88s.id_subscription')
            ->first();
            $payment_ipay = SubscriptionPaymentIpay88::where('id_subscription_user', $deals_user->id_subscription_user)->first();
            if (!($deals_user && $payment_ipay)) {
                return false;
            }
            $data += [
                'RefNo' => $deals_user->order_id,
                'Amount' => $deals_user->amount,
                'ProdDesc' => 'Voucher ' . $deals_user->deals_title,
                'UserName' => $deals_user->user->name,
                'UserEmail' => $deals_user->user->email,
                'UserContact' => $payment_ipay->user_contact ?: $deals_user->user->phone,
                'Remark' => '',
                'ResponseURL' => url('api/ipay88/detail/subscription'),
                'BackendURL' => url('api/ipay88/notif/subscription'),
                'xfield1' => ''
            ];
        } else {
            return false;
        }
        $signed = $this->sign($data);
        return [
            'action_url' => $this->posting_url,
            'data' => $signed
        ];
    }
    /**
     * function to Re-query received data (for security reason) and validate status
     * @param  Array $data received data
     * @param  String $status received payment status
     * @return String reply from IPay88
     */
    public function reQuery($data, $status)
    {
        $submitted = [
            'MerchantCode' => $data['MerchantCode'],
            'RefNo' => $data['RefNo'],
            'Amount' => $data['Amount']
        ];
        $url = $this->requery_url . '?' . http_build_query($submitted);
        $response = MyHelper::postWithTimeout($url, null, $submitted, 0);
        $toLog = [
            'type' => $data['type'] . '_requery',
            'triggers' => $data['triggers'],
            'id_reference' => $data['RefNo'],
            'request' => json_encode($submitted),
            'request_header' => '',
            'request_url' => $url,
            'response' => json_encode($response['response']),
            'response_status_code' => json_encode($response['status_code'])
        ];
        try {
            LogIpay88::create($toLog);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
        $is_valid = false;
        if (
            ($status == '1' && $response['response'] == '00') ||
            ($status == '0' && $response['response'] == 'Payment fail') ||
            ($status == '6' && $response['response'] == 'Payment Pending') ||
            ($status == 'void' && $response['response'] == 'Success / Approved')
        ) {
            $is_valid = true;
        }
        $response['valid'] = $is_valid;
        return $response;
    }
    /**
     * Insert new transaction payment to database or return existing
     * @param  Array $data      Array version of Transaction / DealsUser Model
     * @param  String $type     trx/deals
     * @return Object           TransactionPaymentIpay88 / DealsPaymentIpay88
     */
    public function insertNewTransaction($data, $type = 'trx', $grandtotal = 0, $post = null)
    {
        $result = TransactionPaymentIpay88::where('id_transaction', $data['id_transaction'])->first();
        if ($result) {
            return $result;
        }
        if ($type == 'trx') {
            $toInsert = [
                'id_transaction' => $data['id_transaction'],
                'amount' => $grandtotal * 100,
                'payment_id' => $this->getPaymentId($post['payment_id'] ?? ''),
                'payment_method' => $this->getPaymentMethod($post['payment_id'] ?? ''),
                'user_contact' => $post['phone'] ?? null,
                'merchant_code' => $this->merchant_code,
                'ref_no' => $data['transaction_receipt_number'] ?? '',
            ];

            $result = TransactionPaymentIpay88::create($toInsert);
        } elseif ($type == 'deals') {
            $toInsert = [
                'id_deal_users' => $data['id_deal_users'],
                'amount' => $grandtotal * 100
            ];

            $result = DealsPaymentIpay88::create($toInsert);
        } elseif ($type == 'subscription') {
            $toInsert = [
                'id_subscription_user' => $data['id_subscription_user'],
                'amount' => $grandtotal * 100
            ];

            $result = SubscriptionPaymentIpay88::create($toInsert);
        }
        return $result;
    }
    /**
     * Update transaction ipay table
     * @param  Model $model     [Transaction/Deals]Ipay88 Object or Integer => id_transaction
     * @param  Array $data      update data (request data from ipay)
     * @param  Boolean $response_only  Save response requery only, ignore other
     * @param  Boolean $saveToLog  Save update data to log or not
     */
    public function update($model, $data, $response_only = false, $saveToLog = true)
    {
        if ($response_only) {
            return $model->update(['requery_response' => $data['requery_response']]);
        }
        DB::beginTransaction();
        switch ($data['type']) {
            case 'trx':
                $amount = 0;
                if (is_numeric($model)) {
                    $id_transaction = $model;
                } else {
                    $id_transaction = $model->id_transaction;
                    $amount = $model->amount / 100;
                }
                $trx = Transaction::with('user', 'outlet')->where('id_transaction', $id_transaction)->first();
                if ($trx->transaction_payment_status == 'Cancelled' && $data['Status'] == '1') { // kalau di kita cancel dari ipay success, void
                    $this->void($model);
                    return 1;
                } elseif ($trx->transaction_payment_status != 'Pending') {
                    return 1;
                }
                if (!$amount) {
                    $amount = $trx->transaction_grandtotal;
                }
                $mid = [
                    'order_id' => $trx['transaction_receipt_number'],
                    'gross_amount' => $amount
                ];
                $detailTrx = TransactionPickup::where('id_transaction', $id_transaction)->first();
                switch ($data['Status']) {
                    case '1':
                        if ($trx->transaction_payment_status == 'Completed') {
                            break;
                        }
                        $update = $trx->update(['transaction_payment_status' => 'Completed', 'completed_at' => date('Y-m-d H:i:s')]);
                        if (!$update) {
                            DB::rollBack();
                            return [
                                'status' => 'fail',
                                'messages' => ['Failed update payment status']
                            ];
                        }
                        DisburseJob::dispatch(['id_transaction' => $id_transaction])->onConnection('disbursequeue');

                        //inset pickup_at when pickup_type = right now
                        if ($trx['trasaction_type'] == 'Pickup Order') {
                            if ($detailTrx['pickup_type'] == 'right now') {
                                $settingTime = Setting::where('key', 'processing_time')->first();
                                if ($settingTime && isset($settingTime['value'])) {
                                    $updatePickup = TransactionPickup::where('id_transaction', $detailTrx['id_transaction'])->update(['pickup_at' => date('Y-m-d H:i:s', strtotime('+ ' . $settingTime['value'] . 'minutes'))]);
                                } else {
                                    $updatePickup = TransactionPickup::where('id_transaction', $detailTrx['id_transaction'])->update(['pickup_at' => date('Y-m-d H:i:s')]);
                                }
                            }
                        }

                        $trx->load('outlet');
                        $trx->load('productTransaction');

                        $userData = User::where('id', $trx['id_user'])->first();

                        $config_fraud_use_queue = Configs::where('config_name', 'fraud use queue')->first()->is_active;

                        if ($config_fraud_use_queue == 1) {
                            FraudJob::dispatch($userData, $trx, 'transaction')->onConnection('fraudqueue');
                        } else {
                            $checkFraud = app($this->setting_fraud)->checkFraudTrxOnline($userData, $trx);
                        }

                        //send notif to outlet
                        $sendNotifOutlet = app($this->trx)->outletNotif($trx['id_transaction']);
                        $send = app($this->notif)->notification($mid, $trx);
                        break;

                    case '6':
                        $update = $trx->update(['transaction_payment_status' => 'Pending']);
                        if (!$update) {
                            DB::rollBack();
                            return [
                                'status' => 'fail',
                                'messages' => ['Failed update payment status']
                            ];
                        }
                        break;

                    case '0':
                        if ($trx->transaction_payment_status == 'Cancelled') {
                            break;
                        }
                        MyHelper::updateFlagTransactionOnline($trx, 'cancel', $trx->user);
                        $update = $trx->update(['transaction_payment_status' => 'Cancelled','void_date' => date('Y-m-d H:i:s')]);
                        $trx->load('outlet_name');
                        // $send = app($this->notif)->notificationDenied($mid, $trx);

                        //return balance
                        $payBalance = TransactionMultiplePayment::where('id_transaction', $trx->id_transaction)->where('type', 'Balance')->first();
                        if (!empty($payBalance)) {
                            $checkBalance = TransactionPaymentBalance::where('id_transaction_payment_balance', $payBalance['id_payment'])->first();
                            if (!empty($checkBalance)) {
                                $insertDataLogCash = app("Modules\Balance\Http\Controllers\BalanceController")->addLogBalance($trx['id_user'], $checkBalance['balance_nominal'], $trx['id_transaction'], 'Transaction Failed', $trx['transaction_grandtotal']);
                                if (!$insertDataLogCash) {
                                    DB::rollBack();
                                    return response()->json([
                                        'status'    => 'fail',
                                        'messages'  => ['Insert Cashback Failed']
                                    ]);
                                }
                                $usere = User::where('id', $trx['id_user'])->first();
                                $send = app($this->autocrm)->SendAutoCRM(
                                    'Transaction Failed Point Refund',
                                    $usere->phone,
                                    [
                                        "outlet_name"       => $trx['outlet_name']['outlet_name'] ?? '',
                                        "transaction_date"  => $trx['transaction_date'],
                                        'id_transaction'    => $trx['id_transaction'],
                                        'receipt_number'    => $trx['transaction_receipt_number'],
                                        'received_point'    => (string) $checkBalance['balance_nominal'],
                                        'order_id'          => $detailTrx->order_id,
                                    ]
                                );
                                if ($send != true) {
                                    DB::rollBack();
                                    return response()->json([
                                            'status' => 'fail',
                                            'messages' => ['Failed Send notification to customer']
                                        ]);
                                }
                            }
                        }
                        // delete promo campaign report
                        if ($trx->id_promo_campaign_promo_code) {
                            $update_promo_report = app($this->promo_campaign)->deleteReport($trx->id_transaction, $trx->id_promo_campaign_promo_code);
                        }

                        // return voucher
                        $update_voucher = app($this->voucher)->returnVoucher($trx->id_transaction);

                        // return subscription
                        $update_subscription = app($this->subscription)->returnSubscription($trx->id_transaction);

                        if (!$update) {
                            DB::rollBack();
                            return [
                                'status' => 'fail',
                                'messages' => ['Failed update payment status']
                            ];
                        }
                        break;

                    default:
                        # code...
                        break;
                }
                break;

            case 'deals':
                $amount = 0;
                if (is_numeric($model)) {
                    $id_deals_user = $model;
                } else {
                    $id_deals_user = $model->id_deals_user;
                    $amount = $model->amount / 100;
                }
                $deals_user = DealsUser::join('deals_vouchers', 'deals_vouchers.id_deals_voucher', '=', 'deals_users.id_deals_voucher')->with('userMid')->where('id_deals_user', $id_deals_user)->first();
                $deals = Deal::where('id_deals', $deals_user->id_deals)->first();
                if ($deals_user->paid_status == 'Cancelled' && $data['Status'] == '1') { // kalau di kita cancel dari ipay success, void
                    $this->void($model, 'deals');
                    return 1;
                } elseif ($deals_user->paid_status != 'Pending') {
                    return 1;
                }
                switch ($data['Status']) {
                    case '1':
                        if ($deals_user->paid_status == 'Completed') {
                            break;
                        }
                        $update = $deals_user->update(['paid_status' => 'Completed']);
                        if (!$update) {
                            DB::rollBack();
                            return [
                                'status' => 'fail',
                                'messages' => ['Failed update payment status']
                            ];
                        }
                        $send = app($this->autocrm)->SendAutoCRM(
                            'Payment Deals Success',
                            $deals_user['userMid']['phone'],
                            [
                                'deals_title'       => $deals->title,
                                'id_deals_user'     => $model->id_deals_user
                            ]
                        );
                        break;

                    case '6':
                        $update = $deals_user->update(['paid_status' => 'Pending']);
                        if (!$update) {
                            DB::rollBack();
                            return [
                                'status' => 'fail',
                                'messages' => ['Failed update payment status']
                            ];
                        }
                        break;

                    case '0':
                        if ($deals_user->paid_status == 'Cancelled') {
                            break;
                        }
                        if ($deals_user->balance_nominal) {
                            $insertDataLogCash = app("Modules\Balance\Http\Controllers\BalanceController")->addLogBalance($deals_user->id_user, $deals_user->balance_nominal, $deals_user->id_deals_user, 'Claim Deals Failed');
                            if (!$insertDataLogCash) {
                                DB::rollBack();
                                return response()->json([
                                    'status'    => 'fail',
                                    'messages'  => ['Insert Cashback Failed']
                                ]);
                            }
                        }
                        $update = $deals_user->update(['paid_status' => 'Cancelled']);
                        // revert back deals data
                        if ($deals) {
                            $up1 = $deals->update(['deals_total_claimed' => $deals->deals_total_claimed - 1]);
                            if (!$up1) {
                                DB::rollBack();
                                return [
                                    'status' => 'fail',
                                    'messages' => ['Failed update total claimed']
                                ];
                            }
                        }
                        $up2 = DealsVoucher::where('id_deals_voucher', $deals_user->id_deals_voucher)->update(['deals_voucher_status' => 'Available']);
                        if (!$up2) {
                            DB::rollBack();
                            return [
                                'status' => 'fail',
                                'messages' => ['Failed update voucher status']
                            ];
                        }
                        // $del = app($this->deals_claim)->checkUserClaimed($user, $deals_user->id_deals, true);
                        if (!$update) {
                            DB::rollBack();
                            return [
                                'status' => 'fail',
                                'messages' => ['Failed update payment status']
                            ];
                        }
                        break;

                    default:
                        # code...
                        break;
                }
                break;

            case 'subscription':
                $subscription_user = SubscriptionUser::with('user')->where('id_subscription_user', $model->id_subscription_user)->first();
                $subscription = Subscription::where('id_subscription', $model->id_subscription)->first();
                if ($subscription_user->paid_status == 'Cancelled' && $data['Status'] == '1') { // kalau di kita cancel dari ipay success, void
                    $this->void($model, 'subscription');
                    return 1;
                } elseif ($subscription_user->paid_status != 'Pending') {
                    return 1;
                }
                switch ($data['Status']) {
                    case '1':
                        if ($subscription_user->paid_status == 'Completed') {
                            break;
                        }
                        $update = $subscription_user->update(['paid_status' => 'Completed']);
                        if (!$update) {
                            DB::rollBack();
                            return [
                                'status' => 'fail',
                                'messages' => ['Failed update payment status']
                            ];
                        }
                        $send = app($this->autocrm)->SendAutoCRM(
                            'Buy Paid Subscription Success',
                            $subscription_user['user']['phone'],
                            [
                                'subscription_title'       => $subscription->subscription_title,
                                'id_subscription_users'     => $model->id_subscription_user
                            ]
                        );
                        break;

                    case '6':
                        $update = $subscription_user->update(['paid_status' => 'Pending']);
                        if (!$update) {
                            DB::rollBack();
                            return [
                                'status' => 'fail',
                                'messages' => ['Failed update payment status']
                            ];
                        }
                        break;

                    case '0':
                        if ($subscription_user->paid_status == 'Cancelled') {
                            break;
                        }
                        if ($subscription_user->balance_nominal) {
                            $insertDataLogCash = app("Modules\Balance\Http\Controllers\BalanceController")->addLogBalance($subscription_user->id_user, $subscription_user->balance_nominal, $subscription_user->id_subscription_user, 'Claim Subscription Failed');
                            if (!$insertDataLogCash) {
                                DB::rollBack();
                                return response()->json([
                                    'status'    => 'fail',
                                    'messages'  => ['Insert Cashback Failed']
                                ]);
                            }
                            $send = app($this->autocrm)->SendAutoCRM(
                                'Buy Subscription Failed Point Refund',
                                $subscription_user['user']['phone'],
                                [
                                    'subscription_title'       => $subscription->title,
                                    'id_subscription_users'     => $model->id_subscription_user
                                ]
                            );
                        }
                        $update = $subscription_user->update(['paid_status' => 'Cancelled', 'void_date' => date('Y-m-d H:i:s')]);
                        // revert back deals data
                        if ($subscription) {
                            $up1 = $subscription->update(['subscription_bought' => $subscription->subscription_bought - 1]);
                            if (!$up1) {
                                DB::rollBack();
                                return [
                                    'status' => 'fail',
                                    'messages' => ['Failed update subscription bought']
                                ];
                            }
                        }
                        // $up2 = SubscriptionUserVoucher::where('id_subscription_user_voucher', $singleTrx->id_subscription_user_voucher)->delete();
                        // if (!$up2) {
                        //     DB::rollBack();
                        //     continue;
                        // }
                        if (!$update) {
                            DB::rollBack();
                            return [
                                'status' => 'fail',
                                'messages' => ['Failed update payment status']
                            ];
                        }
                        break;

                    default:
                        # code...
                        break;
                }
                break;

            default:
                # code...
                break;
        }
        if (is_numeric($model)) {
            DB::commit();
            return 1;
        }
        if (!$saveToLog) {
            $up = $model->update([
                'status' => $data['Status'],
                'requery_response' => $data['requery_response'] ?? ''
            ]);
            DB::commit();
            return 1;
        }
        $payment_method = $this->getPaymentMethod($data['PaymentId']);
        $forUpdate = [
            'from_user' => $data['from_user'] ?? 0,
            'from_backend' => $data['from_backend'] ?? 0,
            'merchant_code' => $data['MerchantCode'] ?? '',
            'payment_id' => $data['PaymentId'] ?? null,
            'payment_method' => $payment_method,
            'ref_no' => $data['RefNo'],
            'amount' => $data['Amount'],
            'currency' => $data['Currency'],
            'remark' => $data['Remark'] ?? null,
            'trans_id' => $data['TransId'] ?? null,
            'auth_code' => $data['AuthCode'] ?? null,
            'status' => $data['Status'] ?? '0',
            'err_desc' => $data['ErrDesc'] ?? null,
            'signature' => $data['Signature'] ?? '',
            'xfield1' => $data['xfield1'] ?? null,
            'requery_response' => $data['requery_response'] ?? ''
        ];
        $up = $model->update($forUpdate);
        DB::commit();
        return $up;
    }
    public function getHtml($trx, $item, $name, $phone, $date, $outlet, $receipt)
    {
        $setting = Setting::where('key', 'transaction_grand_total_order')->first();
        $order = $setting['value'];

        $exp   = explode(',', $order);
        $manna = [];

        for ($i = 0; $i < count($exp); $i++) {
            if (substr($exp[$i], 0, 5) == 'empty') {
                unset($exp[$i]);
                continue;
            }

            if ($exp[$i] == 'subtotal') {
                $manna[$exp[$i]] = $trx['transaction_subtotal'];
            }

            if ($exp[$i] == 'tax') {
                $manna[$exp[$i]] = $trx['transaction_tax'];
            }

            if ($exp[$i] == 'discount') {
                $manna[$exp[$i]] = $trx['transaction_discount'];
            }

            if ($exp[$i] == 'service') {
                $manna[$exp[$i]] = $trx['transaction_service'];
            }

            if ($exp[$i] == 'shipping') {
                $manna[$exp[$i]] = $trx['transaction_shipment'];
            }
        }

        $data = [
            'trx' => $trx,
            'item' => $item,
            'name' => $name,
            'phone' => $phone,
            'date' => $date,
            'outlet' => $outlet,
            'receipt' => $receipt,
            'manna' => $manna
        ];

        return view('ipay88::components.detail_transaction', $data)->render();
    }
    public function getPaymentId($payment_method)
    {
        return $this->payment_id[$payment_method] ?? null;
    }
    public function getPaymentMethod($payment_id, $pretty = true)
    {
        if (is_numeric($payment_id)) {
            $payment_method = array_flip($this->payment_id)[$payment_id] ?? null;
        } else {
            $payment_method = $payment_id;
        }
        if ($pretty) {
            $payment_method = $payment_method ? str_replace('_', ' ', $payment_method) : null;
        }
        return $payment_method;
    }

    /**
     * Check if current cancel action is allowed
     * @param  string $last_url current webview url
     * @return boolean          allowed (true) / not allowed (false)
     */
    public function checkCancellable($last_url)
    {
        if (!$last_url) {
            return true;
        }
        // last_url = https://sandbox.ipay88.co.id:8462/PG/PaymentResponse/BacktoMerchant?
        $last_url = str_replace(['http://', 'https://'], '', $last_url); // sandbox.ipay88.co.id:8462/PG/PaymentResponse/BacktoMerchant?
        $to_check = explode('/', $last_url); // ['sandbox.ipay88.co.id:8462', 'PG', 'PaymentResponse', 'BacktoMerchant?']
        $to_check2 = $to_check;
        array_shift($to_check2); // ['PG', 'PaymentResponse', 'BacktoMerchant?']
        $to_match = implode('/', $to_check2); // PG/PaymentResponse/BacktoMerchant?
        /**
         * Allowed:
         * https://sapi.jiwa.app/api/ipay88/pay?type=trx&id_reference=623&payment_id=CREDIT_CARD
         * https://sandbox.ipay88.co.id/epayment/entry.asp
         * https://sandbox.ipay88.co.id/PG/F46D26EF401F85BB8DFD8481BFA1350D569BBD591EF4C22634AC1130FFAF9E48
         */

        /**
         * Not Allowed:
         * https://sandbox.ipay88.co.id/PG/CreditCardRoute/Processing
         * https://sandbox.ipay88.co.id/ePayment/sandprocess/ccV2.asp
         * https://sandbox.ipay88.co.id/ePayment/sandprocess/ccV2.asp
         * https://sandbox.ipay88.co.id:8462/PG/PaymentResponse/BacktoMerchant?EID=f46d26ef401f85bb8dfd8481bfa1350d569bbd591ef4c22634ac1130ffaf9e48
         * https://sapi.jiwa.app/api/ipay88/detail/trx#trxPaid*623*success*
         */

        if (
            (preg_match('/^api\/ipay88\/pay/', $to_match)) // https://sapi.jiwa.app/api/ipay88/pay?type=trx&id_reference=623&payment_id=CREDIT_CARD
            || (($to_check2[0] ?? false) == 'epayment' && ($to_check2[1] ?? false) == 'entry.asp') // https://sandbox.ipay88.co.id/epayment/entry.asp
            || (($to_check2[0] ?? false) == 'PG' && count($to_check2) == 2) // https://sandbox.ipay88.co.id/PG/F46D26EF401F85BB8DFD8481BFA1350D569BBD591EF4C22634AC1130FFAF9E48
            || (preg_match('/^api[\/]+webview\/default/', $to_match)) // https://project/api//webview/default
        ) {
            return true;
        }

        return false;
    }
    /**
     * Cancel trx or deals
     * @param  String $type  'trx'/'deals'
     * @param  Model $model Transaction/DealsUser Model
     * @param  Array $errors Error message, if any
     * @return [type]        [description]
     */
    public function cancel($type, $model, &$errors = null, $last_url = null)
    {
        $errors = ['Payment in progress'];
        if (!$this->checkCancellable($last_url ?? '')) {
            return false;
        }

        switch ($type) {
            case 'trx':
                $model->load('transaction_payment_ipay88');
                if (!$model->transaction_payment_ipay88) {
                    return false;
                }
                $submitted = [
                    'MerchantCode' => $model->transaction_payment_ipay88->merchant_code ?: $this->merchant_code,
                    'RefNo' => $model->transaction_receipt_number,
                    'Amount' => $model->transaction_payment_ipay88->amount,
                    'type' => 'cancel',
                    'triggers' => 'user'
                ];

                // $requery = $this->reQuery($submitted,'0');
                // if(in_array($requery['response'],['Record not found','Payment fail'])){
                $update = $this->update($model->transaction_payment_ipay88, [
                    'type' => 'trx',
                    'Status' => '0',
                    'requery_response' => $requery['response'] ?? ''
                ], false, false);
                if (!$update) {
                    $errors = ['Failed update transaction'];
                    return false;
                }
                return true;
                // }
                break;
            case 'deals':
                $model->load('deals_payment_ipay88');
                if (!$model->deals_payment_ipay88) {
                    return false;
                }
                $submitted = [
                    'MerchantCode' => $model->deals_payment_ipay88->merchant_code ?: $this->merchant_code,
                    'RefNo' => $model->deals_payment_ipay88->order_id,
                    'Amount' => $model->deals_payment_ipay88->amount,
                    'type' => 'cancel',
                    'triggers' => 'user'
                ];

                // $requery = $this->reQuery($submitted,'0');
                // if(in_array($requery['response'],['Record not found','Payment fail'])){
                $update = $this->update($model->deals_payment_ipay88, [
                    'type' => 'deals',
                    'Status' => '0',
                    'requery_response' => $requery['response'] ?? ''
                ], false, false);
                if (!$update) {
                    $errors = ['Failed update voucher'];
                    return false;
                }
                return true;
                // }
                break;
            case 'subscription':
                $model->load('subscription_payment_ipay88');
                if (!$model->subscription_payment_ipay88) {
                    return false;
                }
                $submitted = [
                    'MerchantCode' => $model->subscription_payment_ipay88->merchant_code ?: $this->merchant_code,
                    'RefNo' => $model->subscription_payment_ipay88->order_id,
                    'Amount' => $model->subscription_payment_ipay88->amount,
                    'type' => 'cancel',
                    'triggers' => 'user'
                ];

                // $requery = $this->reQuery($submitted,'0');
                // if(in_array($requery['response'],['Record not found','Payment fail'])){
                $update = $this->update($model->subscription_payment_ipay88, [
                    'type' => 'subscription',
                    'Status' => '0',
                    'requery_response' => $requery['response'] ?? ''
                ], false, false);
                if (!$update) {
                    $errors = ['Failed update subscription'];
                    return false;
                }
                return true;
                // }
                break;
        }
        return false;
    }

    /**
     * Signing data (add signature parameter) for void request
     * @param  array $data array of full unsigned data
     * @return array       array of signed data
     */
    public function signVoid($data)
    {
        $string = '||' . $this->merchant_key . '||' . $data['MerchantCode'] . '||' . $data['RefNo'] . '||' . $data['Amount'] . '||';
        $signature = hash('sha256', $string);
        $data['Signature'] = $signature;
        return $data;
    }
    /**
     * Void ipay88 payment
     * @param  Model $model Transaction/DealsUser Model
     * @return [type]         [description]
     */
    public function void($model, $type = 'trx', $triggers = 'user', &$message = null)
    {
        if (is_numeric($model)) {
            switch ($type) {
                case 'trx':
                    $model = TransactionPaymentIpay88::where('id_transaction', $model)->first();
                    break;

                case 'deals':
                    $model = DealsPaymentIpay88::where('id_deals_user', $model)->first();
                    break;

                case 'trx':
                    $model = SubscriptionPaymentIpay88::where('id_subscription_user', $model)->first();
                    break;

                default:
                    return false;
            }
        }

        if (!$model) {
            return true;
        }

        $submitted = $this->signVoid([
            'MerchantCode' => $model['merchant_code'] ?: $this->merchant_code,
            'RefNo' => $model['ref_no'],
            'Reason' => 'Pengembalian dana',
            'Amount' => $model['amount'] / 100
        ]);

        $url = $this->void_url . '?' . http_build_query($submitted);
        $response = MyHelper::postWithTimeout($url, null, $submitted, 0);

        $toLog = [
            'type' => $type . '_void',
            'triggers' => $triggers,
            'id_reference' => $model['ref_no'],
            'request' => json_encode($submitted),
            'request_header' => '',
            'request_url' => $url,
            'response' => json_encode($response['response']),
            'response_status_code' => json_encode($response['status_code'])
        ];
        try {
            LogIpay88::create($toLog);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }

        if (!($response['response']['Code'] ?? '0')) {
            $submitted = [
                'MerchantCode' => $model['merchant_code'] ?: $this->merchant_code,
                'RefNo' => $model['ref_no'],
                'Amount' => $model['amount'],
                'type' => 'failed_void',
                'triggers' => 'user'
            ];

            $requery = $this->reQuery($submitted, 'void');
            if ($requery['valid'] ?? false) {
                return $requery;
            }
        }

        $message = $response['response']['Message'] ?? '';
        return $response['response']['Code'] ?? '0';
    }
}
