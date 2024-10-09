<?php

namespace Modules\Subscription\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Lib\MyHelper;
use App\Lib\Midtrans;
use Modules\Subscription\Entities\Subscription;
use Modules\Subscription\Entities\SubscriptionPaymentMidtran;
use Modules\Subscription\Entities\FeaturedSubscription;
use Modules\Subscription\Entities\SubscriptionOutlet;
use Modules\Subscription\Entities\SubscriptionUser;
use Modules\Subscription\Entities\SubscriptionUserVoucher;
use Modules\IPay88\Entities\SubscriptionPaymentIpay88;
use Modules\ShopeePay\Entities\SubscriptionPaymentShopeePay;
use App\Http\Models\Outlet;
use App\Http\Models\LogPoint;
use App\Http\Models\LogBalance;
use App\Http\Models\User;
use App\Http\Models\Setting;
use Modules\Subscription\Http\Requests\CreateSubscriptionVoucher;
use Modules\Subscription\Http\Requests\Paid;
use Modules\Subscription\Http\Requests\PayNow;
use Illuminate\Support\Facades\Schema;
use DB;

class ApiSubscriptionClaimPay extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');

        $this->deals   = "Modules\Deals\Http\Controllers\ApiDeals";
        $this->subscription   = "Modules\Subscription\Http\Controllers\ApiSubscription";
        $this->voucher = "Modules\Subscription\Http\Controllers\ApiSubscriptionVoucher";
        $this->setting = "Modules\Transaction\Http\Controllers\ApiOnlineTransaction";
        $this->balance = "Modules\Balance\Http\Controllers\BalanceController";
        $this->claim   = "Modules\Subscription\Http\Controllers\ApiSubscriptionClaim";
        if (\Module::collections()->has('Autocrm')) {
            $this->autocrm  = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        }
        $this->shopeepay      = "Modules\ShopeePay\Http\Controllers\ShopeePayController";
    }

    public $saveImage = "img/receipt_deals/";

    public function cancel(Request $request)
    {
        $id_subscription_user = $request->id_subscription_user;
        $subscription_user = SubscriptionUser::where(['id_subscription_user' => $id_subscription_user, 'id_user' => $request->user()->id])->first();
        if (!$subscription_user || $subscription_user->paid_status != 'Pending') {
            return MyHelper::checkGet([], 'Subscription cannot be canceled');
        }
        $payment_type = $subscription_user->payment_method;
        switch (strtolower($payment_type)) {
            case 'ipay88':
                $errors = '';
                $cancel = \Modules\IPay88\Lib\IPay88::create()->cancel('subscription', $subscription_user, $errors, $request->last_url);
                if ($cancel) {
                    return ['status' => 'success'];
                }
                return [
                    'status' => 'fail',
                    'messages' => $errors ?: ['Something went wrong']
                ];
            case 'midtrans':
                $trx_mid = SubscriptionPaymentMidtran::where('id_subscription_user', $subscription_user->id_subscription_user)->first();
                if (!$trx_mid) {
                    return ['status' => 'fail', 'messages' => ['Payment not found']];
                }
                $connectMidtrans = Midtrans::expire($trx_mid->order_id);
                $singleTrx = $subscription_user;
                DB::beginTransaction();

                $singleTrx->paid_status = 'Cancelled';
                $singleTrx->void_date   = date('Y-m-d H:i:s');
                $singleTrx->save();

                // revert back subscription data
                $subscription = Subscription::where('id_subscription', $singleTrx->id_subscription)->first();
                if ($subscription) {
                    $up1 = $subscription->update(['subscription_bought' => $subscription->subscription_bought - 1]);
                    if (!$up1) {
                        DB::rollBack();
                        continue;
                    }
                }
                // $up2 = SubscriptionUserVoucher::where('id_subscription_user_voucher', $singleTrx->id_subscription_user_voucher)->delete();
                // if (!$up2) {
                //     DB::rollBack();
                //     continue;
                // }
                //reversal balance
                $logBalance = LogBalance::where('id_reference', $singleTrx->id_subscription_user)->where('source', 'Subscription Balance')->where('balance', '<', 0)->get();
                foreach ($logBalance as $logB) {
                    $reversal = app($this->balance)->addLogBalance($singleTrx->id_user, abs($logB['balance']), $singleTrx->id_subscription_user, 'Subscription Reversal', $singleTrx->subscription_price_point ?: $singleTrx->subscription_price_cash);
                    if (!$reversal) {
                        DB::rollBack();
                        continue;
                    }
                    // $usere= User::where('id',$singleTrx->id_user)->first();
                    // $send = app($this->autocrm)->SendAutoCRM('Transaction Failed Point Refund', $usere->phone,
                    //     [
                    //         "outlet_name"       => $singleTrx->outlet_name->outlet_name,
                    //         "transaction_date"  => $singleTrx->transaction_date,
                    //         'id_transaction'    => $singleTrx->id_transaction,
                    //         'receipt_number'    => $singleTrx->transaction_receipt_number,
                    //         'received_point'    => (string) abs($logB['balance'])
                    //     ]
                    // );
                }

                DB::commit();
                return ['status' => 'success'];
        }
        return ['status' => 'fail', 'messages' => ["Cancel $payment_type transaction is not supported yet"]];
    }

    /* CLAIM SUBSCRIPTION */
    public function claim(Paid $request)
    {

        try {
            $post     = $request->json()->all();
            if (isset($post['pin']) && strtolower($post['payment_method']) == 'balance') {
                if (!password_verify($post['pin'], $request->user()->password)) {
                    return [
                        'status' => 'fail',
                        'messages' => ['Incorrect PIN']
                    ];
                }
            }
            $dataSubs = app($this->claim)->checkSubsData($request->json('id_subscription'));
            $id_user  = $request->user()->id;
            $dataSubsUser = app($this->claim)->checkSubsUser($id_user, $dataSubs);

            if (empty($dataSubs)) {
                return response()->json([
                    'status'   => 'fail',
                    'messages' => ['Subscription tidak ditemukan']
                ]);
            } else {
                // CEK VALID DATE
                if (app($this->claim)->checkValidDate($dataSubs)) {
                    //Check if paid type subscription
                    if (!empty($dataSubs->subscription_price_cash) || !empty($dataSubs->subscription_price_point)) {
                        if (($dataSubsUser[0]['paid_status'] ?? false) == 'Pending') {
                            DB::rollback();
                            return response()->json([
                                'status'   => 'fail',
                                'messages' => ['Your subscription is pending, Please complete your subscription payment.']
                            ]);
                        }
                        // CEK LIMIT USER
                        if (app($this->claim)->checkUserLimit($dataSubs, $dataSubsUser)) {
                            // CEK IF USER SUBSCRIPTION IS EXPIRED OR NULL
                            if (app($this->claim)->checkSubsUserExpired($dataSubs, $dataSubsUser)) {
                                //check if type is point
                                if (!empty($dataSubs->subscription_price_point)) {
                                    if (!app($this->claim)->checkSubsPoint($dataSubs, $request->user()->id)) {
                                        DB::rollback();
                                        return response()->json([
                                            'status'   => 'fail',
                                            'messages' => ['Your point not enough.']
                                        ]);
                                    }
                                }
                                // elseif ( $post['payment_method'] == 'balance' ) {

                                //     DB::rollback();
                                //     return response()->json([
                                //         'status'   => 'fail',
                                //         'messages' => ['You can\'t buy this subscription with point.']
                                //     ]);
                                // }

                                //CEK IF BALANCE O
                                if (isset($post['balance']) && $post['balance'] == true) {
                                    if (app($this->claim)->getPoint($request->user()->id) <= 0) {
                                        DB::rollback();
                                        return response()->json([
                                            'status'   => 'fail',
                                            'messages' => ['Your need more point.']
                                        ]);
                                    }
                                }

                                $id_subscription = $dataSubs->id_subscription;

                                // count claimed subscription by id_subscription (how many times subscription are claimed)
                                $subsClaimed = 0;
                                if ($dataSubs->subscription_total != null) {
                                    $subsVoucher = SubscriptionUser::where('id_subscription', '=', $id_subscription)->where('paid_status', '=', 'Completed')->count();
                                    if ($subsVoucher > 0) {
                                        $subsClaimed = $subsVoucher;
                                        if (is_float($subsClaimed)) { // if miss calculate use deals_total_claimed
                                            $subsClaimed = $dataSubs->subscription_bought;
                                        }
                                    }
                                }

                                // check available voucher
                                DB::beginTransaction();
                                if ($dataSubs->subscription_total > $subsClaimed || $dataSubs->subscription_total == null) {
                                    // create subscription user and subscription voucher x times
                                    $user_voucher_array = [];

                                    // create user Subscription
                                    $user_subs = app($this->claim)->createSubscriptionUser($id_user, $dataSubs);
                                    $voucher = $user_subs;

                                    if (!$user_subs) {
                                        DB::rollback();
                                        return response()->json([
                                            'status'   => 'fail',
                                            'messages' => ['Failed to save data.']
                                        ]);
                                    }

                                    $subs_receipt = 'SUBS-' . time() . sprintf("%05d", $voucher->id_subscription_user);
                                    $updateSubs = SubscriptionUser::where('id_subscription_user', '=', $voucher->id_subscription_user)->update(['subscription_user_receipt_number' => $subs_receipt]);

                                    if (!$updateSubs) {
                                        DB::rollback();
                                        return response()->json([
                                            'status'   => 'fail',
                                            'messages' => ['Failed to update data.']
                                        ]);
                                    }

                                    $voucher['subscription_user_receipt_number'] = $subs_receipt;

                                    for ($i = 1; $i <= $dataSubs->subscription_voucher_total; $i++) {
                                        // generate voucher code
                                        do {
                                            $code = app($this->voucher)->generateCode($id_subscription);
                                            $voucherCode = SubscriptionUserVoucher::where('id_subscription_user', '=', $voucher->id_subscription_user)
                                                         ->where('voucher_code', $code)
                                                         ->first();
                                        } while (!empty($voucherCode));

                                        // create user voucher
                                        $subs_voucher = SubscriptionUserVoucher::create([
                                            'id_subscription_user' => $voucher->id_subscription_user,
                                            'voucher_code'         => strtoupper($code)
                                        ]);

                                        if ($subs_voucher) {
                                            $subs_voucher_data = SubscriptionUser::with(['user', 'subscription_user_vouchers'])->where('id_subscription_user', $voucher->id_subscription_user)->first();

                                            // add notif mobile
                                            // $addNotif = MyHelper::addUserNotification($create->id_user,'voucher');
                                        }

                                        if (!$subs_voucher) {
                                            DB::rollback();
                                            return response()->json([
                                                'status'   => 'fail',
                                                'messages' => ['Failed to save data.']
                                            ]);
                                        }

                                        // keep user voucher in order to return in response
                                        array_push($user_voucher_array, $subs_voucher_data);
                                    }   // end of for
                                } else {
                                    DB::rollback();
                                    return response()->json([
                                        'status'   => 'fail',
                                        'messages' => ['Voucer telah habis']
                                    ]);
                                }

                                if ($voucher) {
                                    if (!empty($dataSubs->subscription_price_point)) {
                                        $req['payment_method'] = 'balance';
                                    } else {
                                        $req['payment_method'] = $post['payment_method'];
                                    }
                                    $req['id_subscription_user'] =  $voucher['id_subscription_user'];
                                    $payNow = new PayNow($req);

                                    DB::commit();
                                    // update subscription total bought
                                    $updateSubs = app($this->claim)->updateSubs($dataSubs);
                                    return $this->bayarSekarang($payNow);
                                } else {
                                    DB::rollback();
                                    return response()->json([
                                        'status'   => 'fail',
                                        'messages' => ['Transaction is failed.']
                                    ]);
                                }
                                return response()->json(MyHelper::checkCreate($voucher));
                                DB::commit();
                            } else {
                                switch ($dataSubs->new_purchase_after) {
                                    case 'Empty':
                                        $msg = 'Gagal mengambil Subscription karena masih ada paket yang belum digunakan';
                                        break;
                                    case 'Empty Expired':
                                        $msg = 'Gagal mengambil Subscription karena masih ada paket yang sedang berjalan';
                                        break;
                                    default:
                                        $msg = 'Gagal mengambil Subscription karena masih ada paket yang sedang berjalan';
                                        break;
                                }
                                DB::rollback();
                                return response()->json([
                                    'status'   => 'fail',
                                    'messages' => [$msg]
                                ]);
                            }
                        } else {
                            DB::rollback();
                            return response()->json([
                                'status'   => 'fail',
                                'messages' => ['Subscription telah mencapai limit penggunaan']
                            ]);
                        }
                    } else {
                        DB::rollback();
                        return response()->json([
                            'status' => 'fail',
                            'messages' => ['This is a free subscription.']
                        ]);
                    }
                } else {
                    DB::rollback();
                    return response()->json([
                        'status' => 'fail',
                        'messages' => ['Subscription berlaku pada ' . MyHelper::dateFormatInd($dataSubs->subscription_start, true, false) . ' sampai ' . MyHelper::dateFormatInd($dataSubs->subscription_end, true, false)]
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error($e);
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Proses pembelian subscription gagal, silakan mencoba kembali']
            ]);
        }
    }

    /* BAYAR SEKARANG */
    /* KARENA TADI NGGAK BAYAR MAKANYA SEKARANG KUDU BAYAR*/
    public function bayarSekarang(PayNow $request)
    {
        DB::beginTransaction();
        $post      = $request->json()->all();
        $dataSubs  = $this->subs($request->get('id_subscription_user'));

        if ($dataSubs) {
            $voucher = SubscriptionUser::with(['user', 'subscription_user_vouchers'])->where('id_subscription_user', '=', $request->get('id_subscription_user'))->first();
            $id_user = $voucher['id_user'];
            $id_subscription = $voucher['id_subscription'];

            if ($voucher) {
                $pay = $this->paymentMethod($dataSubs, $voucher, $request);
                if (($pay['payment'] ?? false) == 'ipay88') {
                    DB::commit();
                    return [
                        'status'    => 'success',
                        'result'    => [
                            'url'  => config('url.api_url') . 'api/ipay88/pay?' . http_build_query([
                                'type' => 'subscription',
                                'id_reference' => $voucher->id_subscription_user,
                                'payment_id' => $request->json('payment_id') ?: ''
                            ]),
                            'redirect' => true,
                            'id_subscription_user' => $voucher->id_subscription_user,
                            'cancel_message' => 'Are you sure you want to cancel this transaction?'
                        ]
                    ];
                } elseif (($pay['payment'] ?? false) == 'shopeepay') {
                    DB::commit();
                    $pay['message_timeout_shopeepay'] = "Sorry, your payment has expired";
                    $pay['timer_shopeepay'] = (int) MyHelper::setting('shopeepay_validity_period', 'value', 300);
                    return [
                        'status'    => 'success',
                        'result'    => $pay
                    ];
                }
            }

            if ($pay) {
                DB::commit();
                $pay['cancel_message'] = 'Are you sure you want to cancel this transaction?';
                $return = MyHelper::checkCreate($pay);
                if (isset($return['status']) && $return['status'] == 'success') {
                    if (\Module::collections()->has('Autocrm')) {
                        $phone = User::where('id', $voucher->id_user)->pluck('phone')->first();
                        $voucher->load('subscription');

                        if (($pay['voucher']['payment_method'] ?? false) == 'Balance') {
                            $autocrm = app($this->autocrm)->SendAutoCRM(
                                'Buy Point Subscription Success',
                                $phone,
                                [
                                    'bought_at'                        => $voucher->bought_at,
                                    'subscription_title'               => $voucher->subscription->subscription_title,
                                    'id_subscription_user'             => $return['result']['voucher']['id_subscription_user'],
                                    'subscription_price_point'         => (string) $voucher->subscription_price_point,
                                    'id_subscription'                  => $voucher->id_subscription
                                ]
                            );
                        }
                    }
                    $result = [
                        'id_subscription_user' => $return['result']['voucher']['id_subscription_user'],
                        'id_subscription' => $return['result']['voucher']['id_subscription'],
                        'paid_status' => $return['result']['voucher']['paid_status'],
                    ];
                    if (isset($return['result']['midtrans'])) {
                        $result['redirect'] = true;
                        $result['midtrans'] = $return['result']['midtrans'];
                    } else {
                        $result['redirect'] = false;
                    }

                    if ($result['paid_status'] == 'Pending') {
                        $result['title'] = 'Pending';
                    } elseif ($result['paid_status'] == 'Completed') {
                        $result['title'] = 'Success';
                    }
                    $result['webview_success'] = config('url.api_url') . 'api/webview/subscription/success/' . $return['result']['voucher']['id_subscription_user'];
                    unset($return['result']);
                    $result['cancel_message'] = 'Are you sure you want to cancel this transaction?';
                    $return['result'] = $result;
                }
                return response()->json($return);
            }
        }

        DB::rollback();
        return response()->json([
            'status' => 'fail',
            'messages' => ['Pembayaran gagal. Silakan coba kembali']
        ]);
    }

    /* DEALS */
    public function subs($id_subscription_user)
    {
        $subs = Subscription::leftjoin('subscription_users', 'subscription_users.id_subscription', '=', 'subscriptions.id_subscription')->leftjoin('subscription_user_vouchers', 'subscription_user_vouchers.id_subscription_user', '=', 'subscription_users.id_subscription_user')->select('subscriptions.*')->where('subscription_users.id_subscription_user', $id_subscription_user)->first();
        return $subs;
    }

    /* PAYMENT */
    public function paymentMethod($dataSubs, $voucher, $request)
    {
        //IF USING BALANCE
        if ($request->json('balance') && $request->json('balance') == true) {
            /* BALANCE */
            $pay = $this->balance($dataSubs, $voucher, $request->json('payment_method'), $request->json()->all());
        } else {
            /* BALANCE */
            if ($request->json('payment_method') && $request->json('payment_method') == "balance") {
                $pay = $this->balance($dataSubs, $voucher);
            }
           /* MIDTRANS */
            if ($request->json('payment_method') && $request->json('payment_method') == "midtrans") {
                $pay = $this->midtrans($dataSubs, $voucher);
            }
           /* IPay88 */
            if ($request->json('payment_method') && strtolower($request->json('payment_method')) == "ipay88") {
                $pay = $this->ipay88($dataSubs, $voucher, null, $request->json()->all());
                $ipay88 = [
                    'MERCHANT_TRANID'   => $pay['order_id'],
                    'AMOUNT'            => $pay['amount'],
                    'payment'           => 'ipay88'
                ];
                return $ipay88;
            }

           /* ShopeePay */
            if ($request->json('payment_method') && $request->json('payment_method') == "shopeepay") {
                $pay = $this->shopeepay($dataSubs, $voucher, null);
            }
        }

        if (!isset($pay)) {
            $pay = $this->midtrans($dataSubs, $voucher);
        }


        return $pay;
    }

    /* MIDTRANS */
    public function midtrans($subs, $voucher, $grossAmount = null)
    {
        // simpan dulu di deals payment midtrans
        $data = [
            'id_subscription'      => $subs->id_subscription,
            'id_subscription_user' => $voucher->id_subscription_user,
            'gross_amount'  => $voucher->subscription_price_cash,
            'order_id'      => $voucher->subscription_user_receipt_number
        ];

        if (is_null($grossAmount)) {
            if (!$this->updateInfoDealUsers($voucher->id_subscription_user, ['payment_method' => 'Midtrans'])) {
                 return false;
            }
        } else {
            $data['gross_amount'] = $grossAmount;
        }
        $tembakMitrans = Midtrans::token($data['order_id'], $data['gross_amount'], null, null, null, 'subscription', $voucher->id_subscription_user);
        $tembakMitrans['order_id'] = $data['order_id'];
        $tembakMitrans['gross_amount'] = $data['gross_amount'];

        if (isset($tembakMitrans['token'])) {
            if (SubscriptionPaymentMidtran::create($data)) {
                return [
                    'midtrans'      => $tembakMitrans,
                    'voucher'       => SubscriptionUser::with(['user', 'subscription_user_vouchers'])->where('id_subscription_user', '=', $voucher->id_subscription_user)->first(),
                    'data'          => $data,
                    'subscription'  => $subs
                ];
            }
        }

        return false;
    }

    /* IPay88 */
    public function ipay88($subs, $voucher, $grossAmount = null, $post = null)
    {
        $ipay = \Modules\IPay88\Lib\IPay88::create();
        $payment_id = $post['payment_id'] ?? ''; // ex. CREDIT_CARD, OVO, MANDIRI_ATM
        // simpan dulu di deals payment ipay88
        $data = [
            'id_subscription'      => $subs->id_subscription,
            'id_subscription_user' => $voucher->id_subscription_user,
            'amount'               => $voucher->subscription_price_cash * 100,
            'order_id'             => $voucher->subscription_user_receipt_number,
            'payment_id'           => $ipay->getPaymentId($payment_id ?? ''), // ex. 1,2,3,7,19
            'payment_method'       => $ipay->getPaymentMethod($payment_id), // ex CREDIT CARD, BRI VA, MANDIRI ATM
            'user_contact'         => $post['phone'] ?? null,
            'merchant_code'        => $ipay->merchant_code,
            'ref_no'               => $voucher->subscription_user_receipt_number,
        ];
        if (is_null($grossAmount)) {
            if (!$this->updateInfoDealUsers($voucher->id_subscription_user, ['payment_method' => 'Ipay88'])) {
                 return false;
            }
        } else {
            $data['amount'] = $grossAmount * 100;
        }
        $create = SubscriptionPaymentIpay88::create($data);
        return $create;
    }

    /* ShopeePay */
    public function shopeepay($subscription, $voucher, $grossAmount = null)
    {
        $paymentShopeepay = SubscriptionPaymentShopeePay::where('id_subscription_user', $voucher['id_subscription_user'])->first();
        $trx_shopeepay    = null;
        if (is_null($grossAmount)) {
            if (!$this->updateInfoDealUsers($voucher->id_subscription_user, ['payment_method' => 'Shopeepay'])) {
                 return false;
            }
        }
        $grossAmount = $grossAmount ?? ($voucher->subscription_price_cash);
        if (!$paymentShopeepay) {
            $paymentShopeepay                       = new SubscriptionPaymentShopeePay();
            $paymentShopeepay->id_subscription_user        = $voucher['id_subscription_user'];
            $paymentShopeepay->id_subscription             = $subscription['id_subscription'];
            $paymentShopeepay->amount               = $grossAmount * 100;
            $paymentShopeepay->order_id = $voucher->subscription_user_receipt_number;
            $paymentShopeepay->save();
            $trx_shopeepay = app($this->shopeepay)->order($paymentShopeepay, 'subscription', $errors);
        } elseif (!($paymentShopeepay->redirect_url_app && $paymentShopeepay->redirect_url_http)) {
            $trx_shopeepay = app($this->shopeepay)->order($paymentShopeepay, 'subscription', $errors);
        }
        if (!$trx_shopeepay || !(($trx_shopeepay['status_code'] ?? 0) == 200 && ($trx_shopeepay['response']['debug_msg'] ?? '') == 'success' && ($trx_shopeepay['response']['errcode'] ?? 0) == 0)) {
            if ($paymentShopeepay->redirect_url_app && $paymentShopeepay->redirect_url_http) {
                return [
                    'redirect_url_app'  => $paymentShopeepay->redirect_url_app,
                    'redirect_url_http' => $paymentShopeepay->redirect_url_http,
                ];
            }
            return false;
        }
        $paymentShopeepay->redirect_url_app  = $trx_shopeepay['response']['redirect_url_app'];
        $paymentShopeepay->redirect_url_http = $trx_shopeepay['response']['redirect_url_http'];
        $paymentShopeepay->save();
        return [
            'redirect' => 'true',
            'payment' => 'shopeepay',
            'id_subscription_user' => $voucher->id_subscription_user,
            'redirect_url_app'  => $paymentShopeepay->redirect_url_app,
            'redirect_url_http' => $paymentShopeepay->redirect_url_http
        ];
    }

    /* BALANCE */
    public function balance($subs, $voucher, $paymentMethod = null, $post = null)
    {
        $myBalance   = app($this->balance)->balanceNow($voucher->id_user);
        $kurangBayar = $myBalance - $voucher->subscription_price_cash;

        if ($paymentMethod == null) {
            $paymentMethod = 'balance';
        }

        // jika kurang bayar
        if ($kurangBayar < 0) {
            $dataSubsUserUpdate = [
                'payment_method'  => $paymentMethod,
                'balance_nominal' => $myBalance,
            ];

            if ($this->updateLogPoint(- $myBalance, $voucher)) {
                if ($this->updateInfoDealUsers($voucher->id_subscription_user, $dataSubsUserUpdate)) {
                    if ($paymentMethod == 'midtrans') {
                        return $this->midtrans($subs, $voucher, -$kurangBayar);
                    }
                    if (strtolower($paymentMethod) == 'ipay88') {
                        $pay = $this->ipay88($subs, $voucher, -$kurangBayar, $post);
                        $ipay88 = [
                            'MERCHANT_TRANID'   => $pay['order_id'],
                            'AMOUNT'            => $pay['amount'],
                            'payment'           => 'ipay88'
                        ];
                        return $ipay88;
                    }
                    if ($paymentMethod == 'shopeepay') {
                        return $this->shopeepay($subs, $voucher, -$kurangBayar);
                    }
                }
            }
        } else {
            // update log balance
            $price = 0;
            if (!empty($voucher->subscription_price_cash)) {
                $price = $voucher->subscription_price_cash;
            }
            if (!empty($voucher->subscription_price_point)) {
                $price = $voucher->subscription_price_point;
            }
            if ($this->updateLogPoint(- $price, $voucher)) {
                $dataSubsUserUpdate = [
                    'payment_method'  => 'Balance',
                    'balance_nominal' => $voucher->subscription_price_cash,
                    'paid_status'     => 'Completed'
                ];

                // update deals user
                if ($this->updateInfoDealUsers($voucher->id_subscription_user, $dataSubsUserUpdate)) {
                    return $result = [
                        'voucher'  => SubscriptionUser::with(['user', 'subscription_user_vouchers'])->where('id_subscription_user', '=', $voucher->id_subscription_user)->first(),
                        'data'     => $dataSubsUserUpdate,
                        'subs'     => $subs
                    ];
                }
            }
        }

        return false;
    }

    /* UPDATE BALANCE */
    public function updateLogPoint($balance_nominal, $voucher)
    {
        $user = User::with('memberships')->where('id', $voucher->id_user)->first();

        // $balance_nominal = -$voucher->voucher_price_cash;
        $grand_total = 0;
        if (!empty($voucher->subscription_price_cash)) {
            $grand_total = $voucher->subscription_price_cash;
        }
        if (!empty($voucher->subscription_price_point)) {
            $grand_total = $voucher->subscription_price_point;
        }
        $id_reference = $voucher->id_subscription_user;

        // add log balance (with balance hash check) & update user balance
        $addLogBalance = app($this->balance)->addLogBalance($user->id, $balance_nominal, $id_reference, "Subscription Balance", $grand_total);
        return $addLogBalance;
    }

    /* UPDATE HARGA BALANCE */
    public function updateInfoDealUsers($id_subscription_user, $data)
    {
        $update = SubscriptionUser::where('id_subscription_user', '=', $id_subscription_user)->update($data);

        return $update;
    }

    /* CEK STATUS */
    public function status(Request $request)
    {
        $voucher = SubscriptionUser::select('id_subscription_user', 'paid_status')->where('id_subscription_user', $request->json('id_subscription_user'))->first()->toArray();
        if ($voucher['paid_status'] == 'Completed') {
            $voucher['message'] = Setting::where('key', 'payment_success_messages')->pluck('value_text')->first() ?? 'Apakah kamu ingin menggunakan Voucher sekarang?';
        } elseif ($voucher['paid_status'] == 'Cancelled') {
            $voucher['message'] = Setting::where('key', 'payment_ovo_fail_messages')->pluck('value_text')->first() ?? 'Transaksi Gagal';
        }

        return MyHelper::checkGet($voucher);
    }
}
