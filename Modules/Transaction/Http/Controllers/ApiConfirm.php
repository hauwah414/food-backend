<?php

namespace Modules\Transaction\Http\Controllers;

use App\Http\Models\Configs;
use App\Http\Models\TransactionProduct;
use App\Jobs\FraudJob;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Http\Models\User;
use App\Http\Models\LogTopup;
use App\Http\Models\LogTopupMidtrans;
use App\Http\Models\LogTopupManual;
use App\Http\Models\Transaction;
use App\Http\Models\TransactionProductModifier;
use App\Http\Models\ManualPaymentMethod;
use App\Http\Models\OvoReference;
use App\Http\Models\TransactionMultiplePayment;
use App\Http\Models\TransactionPaymentBalance;
use App\Http\Models\TransactionPaymentMidtran;
use App\Http\Models\TransactionPaymentOvo;
use App\Http\Models\LogRequest;
use App\Http\Models\OvoReversal;
use App\Http\Models\TransactionPickup;
use App\Http\Models\Setting;
use DB;
use Modules\IPay88\Lib\IPay88;
use App\Lib\MyHelper;
use App\Lib\Midtrans;
use App\Lib\Ovo;
use Modules\ProductVariant\Entities\TransactionProductVariant;
use Modules\ShopeePay\Entities\TransactionPaymentShopeePay;
use Modules\Transaction\Entities\TransactionBundlingProduct;
use Modules\Transaction\Entities\TransactionGroup;
use Modules\Transaction\Http\Requests\Transaction\ConfirmPayment;
use Modules\Xendit\Entities\TransactionPaymentXendit;

class ApiConfirm extends Controller
{
    public $saveImage = "img/payment/manual/";

    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->notif = "Modules\Transaction\Http\Controllers\ApiNotification";
        $this->trx = "Modules\Transaction\Http\Controllers\ApiOnlineTransaction";
        $this->autocrm  = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        $this->voucher  = "Modules\Deals\Http\Controllers\ApiDealsVoucher";
        $this->promo_campaign   = "Modules\PromoCampaign\Http\Controllers\ApiPromoCampaign";
        $this->subscription  = "Modules\Subscription\Http\Controllers\ApiSubscriptionVoucher";
        $this->shopeepay      = "Modules\ShopeePay\Http\Controllers\ShopeePayController";
    }

    public function confirmTransaction(ConfirmPayment $request)
    {
        $post = $request->json()->all();
        $user = $request->user();

        $check = TransactionGroup::where('id_transaction_group', $post['id'])->first();
        if (empty($check)) {
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Transaction Not Found'],
            ]);
        }

        if ($check['transaction_payment_status'] == 'Completed' && ($check['transaction_payment_type'] == 'Balance' || $check['transaction_grandtotal'] == 0)) {
            $transactionGroup = Transaction::where('id_transaction_group', $check['id_transaction_group'])->get()->toArray();
            foreach ($transactionGroup as $transactions) {
                if ($transactions['trasaction_type'] == 'Delivery') {
                    app('\Modules\Transaction\Http\Controllers\ApiOnlineTransaction')->updateStockProduct($transactions['id_transaction'], 'book');
                }
            }

            return response()->json([
                'status'   => 'success'
            ]);
        }

        if ($check['transaction_payment_status'] != 'Pending') {
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Transaction already ' . $check['transaction_payment_status']],
            ]);
        }

        if (config('payment_method.midtrans_' . strtolower(str_replace(' ', '_', $post['payment_detail'])) . '.payment_gateway') == 'Midtrans') {
            $post['payment_type'] = 'Midtrans';
        }

        if ($post['payment_type'] && $post['payment_type'] != 'Balance') {
            $available_payment = app($this->trx)->availablePayment(new Request())['result'] ?? [];
            if (!in_array($post['payment_type'], array_column($available_payment, 'payment_gateway'))) {
                return [
                    'status' => 'fail',
                    'messages' => 'Metode pembayaran yang dipilih tidak tersedia untuk saat ini'
                ];
            }
        }

        $transactionType = Transaction::where('id_transaction_group', $check['id_transaction_group'])->first()['trasaction_type'] ?? 'trx';
        $transactionType = ($transactionType == 'Delivery' ? 'trx' : $transactionType);
        $payment_id = strtoupper(str_replace(' ', '_', $post['payment_id'] ?? $post['payment_detail'] ?? null));
        $post['payment_id'] = $payment_id;
        $countGrandTotal = $check['transaction_grandtotal'];
        $checkPaymentBalance = TransactionPaymentBalance::where('id_transaction_group', $check['id_transaction_group'])->first();

        if ($checkPaymentBalance) {
            $countGrandTotal = $countGrandTotal - $checkPaymentBalance['balance_nominal'];
        }

        if ($countGrandTotal < 1) {
            return [
                'status' => 'fail',
                'messages' => ['No need to pay']
            ];
        }

        if (!$checkPaymentBalance) {
            TransactionGroup::where('id_transaction_group', $check['id_transaction_group'])->update(['transaction_payment_type' => $post['payment_type']]);
        }

        if ($post['payment_type'] == 'Midtrans') {
            $check->load('transactions.productTransaction.product');

            $productMidtrans   = [];
            $dataDetailProduct = [];
            $checkPayment = TransactionMultiplePayment::where('id_transaction_group', $check['id_transaction_group'])->first();
            foreach ($check['transactions'] as $subtrx) {
                foreach ($subtrx['productTransaction'] as $key => $value) {
                    $dataProductMidtrans = [
                        'id'       => $value['id_product'],
                        'price'    => abs($value['transaction_product_price']),
                        'name'     => substr($value['product']['product_name'], 0, 40), // max 50 char
                        'quantity' => $value['transaction_product_qty'],
                    ];

                    $productMidtrans[] = $dataProductMidtrans;
                    $dataDetailProduct[] = $dataProductMidtrans;
                }
            }

            if ($check['transaction_shipment'] > 0) {
                $dataShip = [
                    'id'       => null,
                    'price'    => abs($check['transaction_shipment']),
                    'name'     => 'Shipping',
                    'quantity' => 1,
                ];
                array_push($dataDetailProduct, $dataShip);
            }

            if ($check['transaction_service'] > 0) {
                $dataShip = [
                    'id'       => null,
                    'price'    => abs($check['transaction_service']),
                    'name'     => 'Service',
                    'quantity' => 1,
                ];
                array_push($dataDetailProduct, $dataShip);
            }

            if ($check['transaction_tax'] > 0) {
                $dataShip = [
                    'id'       => null,
                    'price'    => abs($check['transaction_tax']),
                    'name'     => 'Tax',
                    'quantity' => 1,
                ];
                array_push($dataDetailProduct, $dataShip);
            }

            if (!empty($check['transaction_discount'])) {
                $dataDis = [
                    'id'       => null,
                    'price'    => -abs($check['transaction_discount']),
                    'name'     => 'Discount',
                    'quantity' => 1,
                ];
                array_push($dataDetailProduct, $dataDis);
            }

            if (!empty($checkPayment)) {
                if ($checkPayment['type'] == 'Balance') {
                    if (empty($checkPaymentBalance)) {
                        DB::rollback();
                        return response()->json([
                            'status'   => 'fail',
                            'messages' => ['Transaction is invalid'],
                        ]);
                    }

                    $dataBalance     = [
                        'id'       => null,
                        'price'    => -abs($checkPaymentBalance['balance_nominal']),
                        'name'     => 'Balance',
                        'quantity' => 1,
                    ];

                    array_push($dataDetailProduct, $dataBalance);

                    $detailPayment['balance'] = -$checkPaymentBalance['balance_nominal'];
                }
            }

            if ($check['transaction_type'] == 'Delivery') {
                $dataUser = [
                    'first_name'      => $user['name'],
                    'email'           => $user['email'],
                    'phone'           => $user['phone'],
                    'billing_address' => [
                        'first_name' => $check['transaction_shipments']['destination_name'],
                        'phone'      => $check['transaction_shipments']['destination_phone'],
                        'address'    => $check['transaction_shipments']['destination_address'],
                    ],
                ];

                $dataShipping = [
                    'first_name'  => $check['transaction_shipments']['name'],
                    'phone'       => $check['transaction_shipments']['phone'],
                    'address'     => $check['transaction_shipments']['address'],
                    'postal_code' => $check['transaction_shipments']['postal_code'],
                ];
            } else {
                $dataUser = [
                    'first_name'      => $user['name'],
                    'email'           => $user['email'],
                    'phone'           => $user['phone'],
                    'billing_address' => [
                        'first_name' => $user['name'],
                        'phone'      => $user['phone'],
                    ],
                ];
            }

            $dataNotifMidtrans = [
                'id_transaction_group' => $check['id_transaction_group'],
                'gross_amount'   => $countGrandTotal,
                'order_id'       => $check['transaction_receipt_number']
            ];

            switch ($payment_id) {
                case 'CREDIT_CARD':
                    $dataNotifMidtrans['payment_type'] = 'Credit Card';
                    break;

                case 'GOPAY':
                    $dataNotifMidtrans['payment_type'] = 'Gopay';
                    break;

                case 'SHOPEEPAY':
                    $dataNotifMidtrans['payment_type'] = 'Shopeepay';
                    break;

                case 'BANK_TRANSFER':
                    $dataNotifMidtrans['payment_type'] = 'Bank Transfer';
                    break;
                default:
                    $dataNotifMidtrans['payment_type'] = $payment_id;
                    break;
            }

            $insertNotifMidtrans = TransactionPaymentMidtran::updateOrCreate(['id_transaction_group' => $check['id_transaction_group']], $dataNotifMidtrans);
            if (!$insertNotifMidtrans) {
                return response()->json([
                    'status'   => 'fail',
                    'messages' => [
                        'Failed Create Transaction Payment Midtrans Data',
                    ],
                ]);
            }

            $dataMultiple = [
                'id_transaction_group' => $check['id_transaction_group'],
                'type'           => 'Midtrans',
                'id_payment'     => $insertNotifMidtrans['id_transaction_payment'],
            ];

            $saveMultiple = TransactionMultiplePayment::create($dataMultiple);
            if (!$saveMultiple) {
                return response()->json([
                    'status'   => 'fail',
                    'messages' => ['fail to confirm transaction'],
                ]);
            }

            $transaction_details = array(
                'order_id'     => $check['transaction_receipt_number'],
                'gross_amount' => $countGrandTotal,
            );

            $methodPayment = $payment_id == 'SHOPEEPAY' ? 'charge' : 'token';

            if (\Cache::has('midtrans_confirm_' . $check['id_transaction_group'])) {
                $dataMidtrans = \Cache::get('midtrans_confirm_data_' . $check['id_transaction_group']);
                $connectMidtrans = \Cache::get('midtrans_confirm_' . $check['id_transaction_group']);
            } elseif ($check['transaction_type'] == 'Delivery') {
                $dataMidtrans = array(
                    'transaction_details' => $transaction_details,
                    'customer_details'    => $dataUser,
                    'shipping_address'    => $dataShipping,
                    'expiry_duration'     => (int) MyHelper::setting('shopeepay_validity_period', 'value', 300),
                    'unit'                => 'second',
                );

                $connectMidtrans = Midtrans::{$methodPayment}($check['transaction_receipt_number'], $countGrandTotal, $dataUser, $dataShipping, $dataDetailProduct, $transactionType, $check['transaction_receipt_number'], $post['payment_detail']);
            } else {
                $dataMidtrans = array(
                    'transaction_details' => $transaction_details,
                    'customer_details'    => $dataUser,
                    'expiry_duration'     => (int) MyHelper::setting('shopeepay_validity_period', 'value', 300),
                    'unit'                => 'second',
                );

                $connectMidtrans = Midtrans::{$methodPayment}($check['transaction_receipt_number'], $countGrandTotal, $dataUser, $ship = null, $dataDetailProduct, $transactionType, $check['transaction_receipt_number'], $post['payment_detail']);
            }

            if (empty($connectMidtrans['token']) && $payment_id != 'SHOPEEPAY') {
                DB::rollback();
                return response()->json([
                    'status'   => 'fail',
                    'messages' => [
                        'Midtrans token is empty. Please try again.',
                    ],
                    'error'    => [$connectMidtrans],
                    'data'     => [
                        'trx'         => $transaction_details,
                        'grand_total' => $countGrandTotal,
                        'product'     => $dataDetailProduct,
                        'user'        => $dataUser,
                    ],
                ]);
            } elseif ($payment_id == 'SHOPEEPAY' && !in_array($connectMidtrans['status_code'], [200, 201])) {
                return response()->json([
                    'status'   => 'fail',
                    'messages' => [
                        'Failed create midtrans payment',
                    ],
                    'error'    => [$connectMidtrans],
                    'data'     => [
                        'trx'         => $transaction_details,
                        'grand_total' => $countGrandTotal,
                        'product'     => $dataDetailProduct,
                        'user'        => $dataUser,
                    ],
                ]);
            }
            \Cache::put('midtrans_confirm_data_' . $check['id_transaction_group'], $dataMidtrans, now()->addMinutes(10));
            \Cache::put('midtrans_confirm_' . $check['id_transaction_group'], $connectMidtrans, now()->addMinutes(10));

            $dataMidtrans['items']            = $productMidtrans ?? [];
            $dataMidtrans['payment']          = $detailPayment ?? [];
            $dataMidtrans['midtrans_product'] = $dataDetailProduct ?? [];

            DB::commit();

            if ($payment_id == 'SHOPEEPAY') {
                $response = [
                    'status' => 'success',
                    'result' => [
                        'redirect' => true,
                        'timer_shopeepay'           => (int) MyHelper::setting('shopeepay_validity_period', 'value', 300),
                        'message_timeout_shopeepay' => 'Sorry, your payment has expired',
                        'redirect_url_app'          => $connectMidtrans['actions'][0]['url'],
                    ]
                ];
            } else {
                $dataEncode = [
                    'transaction_receipt_number' => $check['transaction_receipt_number'],
                    'type'                       => 'trx',
                    'trx_success'                => 1,
                ];
                $encode = json_encode($dataEncode);
                $base   = base64_encode($encode);
                $response = [
                    'status'           => 'success',
                    'result'           => [
                        'snap_token'       => $connectMidtrans['token'],
                        'redirect_url'     => $connectMidtrans['redirect_url'],
                        'transaction_data' => $dataMidtrans,
                        'url'              => env('VIEW_URL') . '/transaction/web/view/detail?data=' . $base,
                    ],
                ];
            }
            $transactionGroup = Transaction::where('id_transaction_group', $check['id_transaction_group'])->get()->toArray();
            foreach ($transactionGroup as $transactions) {
                if ($transactions['trasaction_type'] == 'Delivery') {
                    app('\Modules\Transaction\Http\Controllers\ApiOnlineTransaction')->updateStockProduct($transactions['id_transaction'], 'book');
                }

                if (!$checkPaymentBalance) {
                    Transaction::where('id_transaction', $transactions['id_transaction'])->update(['trasaction_payment_type' => $post['payment_type']]);
                }
            }

            if (!empty($connectMidtrans['redirect_url'])) {
                TransactionPaymentMidtran::where('id_transaction_group', $check['id_transaction_group'])->update(['token' => $connectMidtrans['token'], 'redirect_url' => $connectMidtrans['redirect_url']]);
            }

            return response()->json($response);
        } elseif ($post['payment_type'] == 'Ovo') {
            //validasi phone
            $phone = preg_replace("/[^0-9]/", "", $post['phone']);

            if (substr($phone, 0, 2) == '62') {
                $phone = substr($phone, 2);
            } elseif (substr($phone, 0, 3) == '+62') {
                $phone = substr($phone, 3);
            }

            if (substr($phone, 0, 1) != '0') {
                $phone = '0' . $phone;
            }

            $pay = $this->paymentOvo($check, $countGrandTotal, $phone, env('OVO_ENV') ?: 'staging');

            return $pay;
        } elseif ($post['payment_type'] == 'Ipay88') {
            // save multiple payment
            $trx_ipay88 = \Modules\IPay88\Lib\IPay88::create()->insertNewTransaction($check, 'trx', $countGrandTotal, $post);
            if (!$trx_ipay88) {
                DB::rollBack();
                return response()->json([
                    'status'   => 'fail',
                    'messages' => ['Failed create transaction payment'],
                ]);
            }
            $dataMultiple = [
                'id_transaction_group' => $check['id_transaction_group'],
                'type'           => 'IPay88',
                'id_payment'     => $trx_ipay88->id_transaction_payment_ipay88,
            ];
            $saveMultiple = TransactionMultiplePayment::updateOrCreate([
                'id_transaction_group' => $check['id_transaction_group'],
                'type'           => 'IPay88',
            ], $dataMultiple);
            if (!$saveMultiple) {
                DB::rollBack();
                return response()->json([
                    'status'   => 'fail',
                    'messages' => ['Failed create multiple transaction'],
                ]);
            }
            DB::commit();
            return [
                'status'    => 'success',
                'result'    => [
                    'url'  => config('url.api_url') . 'api/ipay88/pay?' . http_build_query([
                            'type' => 'trx',
                            'id_reference' => $check['id_transaction_group'],
                            'payment_id'   => $payment_id,
                        ]),
                ],
            ];
        } elseif ($post['payment_type'] == 'Shopeepay') {
            $paymentShopeepay = TransactionPaymentShopeePay::where('id_transaction_group', $check['id_transaction_group'])->first();
            $trx_shopeepay    = null;
            if (!$paymentShopeepay) {
                $paymentShopeepay                 = new TransactionPaymentShopeePay();
                $paymentShopeepay->id_transaction_group = $check['id_transaction_group'];
                $paymentShopeepay->amount         = $countGrandTotal * 100;
                $paymentShopeepay->save();
                $trx_shopeepay = app($this->shopeepay)->order($paymentShopeepay, 'trx', $errors);
            } elseif (!($paymentShopeepay->redirect_url_app && $paymentShopeepay->redirect_url_http)) {
                $trx_shopeepay = app($this->shopeepay)->order($paymentShopeepay, 'trx', $errors);
            }

            if (!$trx_shopeepay || !(($trx_shopeepay['status_code'] ?? 0) == 200 && ($trx_shopeepay['response']['debug_msg'] ?? '') == 'success' && ($trx_shopeepay['response']['errcode'] ?? 0) == 0)) {
                if ($paymentShopeepay->redirect_url_app && $paymentShopeepay->redirect_url_http) {
                    // already confirmed
                    return [
                        'status' => 'success',
                        'result' => [
                            'redirect'                  => true,
                            'timer_shopeepay'           => (int) MyHelper::setting('shopeepay_validity_period', 'value', 300),
                            'message_timeout_shopeepay' => 'Sorry, your payment has expired',
                            'redirect_url_app'          => $paymentShopeepay->redirect_url_app,
                            'redirect_url_http'         => $paymentShopeepay->redirect_url_http,
                        ],
                    ];
                }
                $dataMultiple = [
                    'id_transaction_group' => $check['id_transaction_group'],
                    'type'           => 'Shopeepay',
                    'id_payment'     => $paymentShopeepay->id_transaction_payment_shopee_pay,
                ];
                // save multiple payment
                $saveMultiple = TransactionMultiplePayment::updateOrCreate([
                    'id_transaction_group' => $check['id_transaction_group'],
                    'type'           => 'Shopeepay',
                ], $dataMultiple);
                if (!$saveMultiple) {
                    DB::rollBack();
                    return response()->json([
                        'status'   => 'fail',
                        'messages' => ['Failed create multiple transaction'],
                    ]);
                }
                $errcode = $trx_shopeepay['response']['errcode'] ?? null;
                $paymentShopeepay->errcode = $errcode;
                $paymentShopeepay->err_reason = app($this->shopeepay)->errcode[$errcode] ?? null;
                $paymentShopeepay->save();
                $trx = $check;
                $trx->cancel();
                DB::commit();
                return [
                    'status' => 'fail',
                    'messages' => [$paymentShopeepay->err_reason]
                ];
            }
            $paymentShopeepay->redirect_url_app  = $trx_shopeepay['response']['redirect_url_app'];
            $paymentShopeepay->redirect_url_http = $trx_shopeepay['response']['redirect_url_http'];
            $paymentShopeepay->save();
            $dataMultiple = [
                'id_transaction_group' => $check['id_transaction_group'],
                'type'           => 'Shopeepay',
                'id_payment'     => $paymentShopeepay->id_transaction_payment_shopee_pay,
            ];
            // save multiple payment
            $saveMultiple = TransactionMultiplePayment::updateOrCreate([
                'id_transaction_group' => $check['id_transaction_group'],
                'type'           => 'Shopeepay',
            ], $dataMultiple);
            if (!$saveMultiple) {
                DB::rollBack();
                return response()->json([
                    'status'   => 'fail',
                    'messages' => ['Failed create multiple transaction'],
                ]);
            }
            DB::commit();
            return [
                'status' => 'success',
                'result' => [
                    'redirect'                  => true,
                    'timer_shopeepay'           => (int) MyHelper::setting('shopeepay_validity_period', 'value', 300),
                    'message_timeout_shopeepay' => 'Sorry, your payment has expired',
                    'redirect_url_app'          => $paymentShopeepay->redirect_url_app,
                    'redirect_url_http'         => $paymentShopeepay->redirect_url_http,
                ],
            ];
        } elseif ($post['payment_type'] == 'Xendit') {
            $post['phone'] = $post['phone'] ?? $user['phone'];
            $payment_id = $request->payment_id ?? $request->payment_detail;
            $paymentXendit = TransactionPaymentXendit::where('id_transaction_group', $check['id_transaction_group'])->first();
            $transactionData = [
                'transaction_details' => [
                    'id_transaction_group' => $check['id_transaction_group'],
                    'order_id' => $check['transaction_receipt_number'],
                ],
            ];
            if (!$paymentXendit) {
                $paymentXendit = new TransactionPaymentXendit([
                    'id_transaction_group' => $check['id_transaction_group'],
                    'xendit_id' => null,
                    'external_id' => $check['transaction_receipt_number'],
                    'business_id' => null,
                    'phone' => $post['phone'],
                    'type' => $payment_id,
                    'amount' => $countGrandTotal,
                    'expiration_date' => null,
                    'failure_code' => null,
                    'status' => null,
                    'callback_authentication_token' => null,
                    'checkout_url' => null,
                ]);
            }

            $dataDetailProduct = [];
            $checkPayment = TransactionMultiplePayment::where('id_transaction_group', $check['id_transaction_group'])->first();
            foreach ($check['transactions'] as $subtrx) {
                foreach ($subtrx['productTransaction'] as $key => $value) {
                    $dataProductMidtrans = [
                        'id'       => $value['id_product'],
                        'price'    => abs($value['transaction_product_price'] - ($value['transaction_product_discount'] / $value['transaction_product_qty'])),
                        'name'     => $value['product']['product_name'],
                        'quantity' => $value['transaction_product_qty'],
                    ];

                    $dataDetailProduct[] = $dataProductMidtrans;
                }
            }

            if ($check['transaction_shipment'] > 0) {
                $dataShip = [
                    'id'       => 'shipment',
                    'price'    => abs($check['transaction_shipment']),
                    'name'     => 'Shipping',
                    'quantity' => 1,
                ];
                array_push($dataDetailProduct, $dataShip);
            }

            if ($check['transaction_service'] > 0) {
                $dataService = [
                    'id'       => 'transaction_service',
                    'price'    => abs($check['transaction_service']),
                    'name'     => 'Service',
                    'quantity' => 1,
                ];
                array_push($dataDetailProduct, $dataService);
            }

            if ($check['transaction_tax'] > 0) {
                $dataTax = [
                    'id'       => 'transaction_tax',
                    'price'    => abs($check['transaction_tax']),
                    'name'     => 'Tax',
                    'quantity' => 1,
                ];
                array_push($dataDetailProduct, $dataTax);
            }

            if (!empty($checkPayment)) {
                if ($checkPayment['type'] == 'Balance') {
                    if (empty($checkPaymentBalance)) {
                        DB::rollback();
                        return response()->json([
                            'status'   => 'fail',
                            'messages' => ['Transaction is invalid'],
                        ]);
                    }

                    $dataBalance     = [
                        'id'       => 'balance',
                        'price'    => abs($checkPaymentBalance['balance_nominal']),
                        'name'     => 'Balance',
                        'quantity' => 1,
                    ];

                    array_push($dataDetailProduct, $dataBalance);

                    $detailPayment['balance'] = -$checkPaymentBalance['balance_nominal'];
                }
            }
            $paymentXendit->items = $dataDetailProduct;

            if ($paymentXendit->pay($errors)) {
                $dataMultiple = [
                    'id_transaction_group' => $paymentXendit->id_transaction_group,
                    'type'           => 'Xendit',
                    'id_payment'     => $paymentXendit->id_transaction_payment_xendit,
                ];
                // save multiple payment
                $saveMultiple = TransactionMultiplePayment::updateOrCreate([
                    'id_transaction_group' => $paymentXendit->id_transaction_group,
                    'type'           => 'Xendit',
                    'payment_detail' => $post['payment_detail']
                ], $dataMultiple);

                $result = [
                    'redirect' => true,
                    'type' => $paymentXendit->type,
                ];
                if ($paymentXendit->type == 'OVO') {
                    $result['timer']  = (int) MyHelper::setting('setting_timer_ovo', 'value', 60);
                    $result['message_timeout'] = 'Sorry, your payment has expired';
                } else {
                    if (!$paymentXendit->checkout_url) {
                        DB::commit();
                        return [
                            'status' => 'fail',
                            'messages' => ['Empty checkout_url']
                        ];
                    }
                    $result['redirect_url'] = $paymentXendit->checkout_url;
                    $result['transaction_data'] = $transactionData;
                }

                $transactionGroup = Transaction::where('id_transaction_group', $check['id_transaction_group'])->get()->toArray();
                foreach ($transactionGroup as $transactions) {
                    if ($transactions['trasaction_type'] == 'Delivery') {
                        app('\Modules\Transaction\Http\Controllers\ApiOnlineTransaction')->updateStockProduct($transactions['id_transaction'], 'book');
                    }

                    if (!$checkPaymentBalance) {
                        Transaction::where('id_transaction', $transactions['id_transaction'])->update(['trasaction_payment_type' => $post['payment_type']]);
                    }
                }

                DB::commit();

                return [
                    'status' => 'success',
                    'result' => $result
                ];
            }

            $dataMultiple = [
                'id_transaction' => $paymentXendit->id_transaction,
                'type'           => 'Xendit',
                'id_payment'     => $paymentXendit->id_transaction_payment_xendit,
            ];
            // save multiple payment
            $saveMultiple = TransactionMultiplePayment::updateOrCreate([
                'id_transaction' => $paymentXendit->id_transaction,
                'type'           => 'Xendit',
            ], $dataMultiple);

            DB::commit();

            return [
                'status' => 'fail',
                'messages' => $errors ?: ['Something went wrong']
            ];
        } elseif ($post['payment_type'] == 'Xendit VA') {
            $post['phone'] = $post['phone'] ?? $user['phone'];
            $payment_id = $request->payment_id ?? $request->payment_detail;
            $paymentXendit = TransactionPaymentXendit::where('id_transaction_group', $check['id_transaction_group'])->first();
            $transactionData = [
                'transaction_details' => [
                    'id_transaction_group' => $check['id_transaction_group'],
                    'order_id' => $check['transaction_receipt_number'],
                ],
            ];
            if (!$paymentXendit) {
                $paymentXendit = new TransactionPaymentXendit([
                    'id_transaction_group' => $check['id_transaction_group'],
                    'xendit_id' => null,
                    'external_id' => $check['transaction_receipt_number'],
                    'business_id' => null,
                    'phone' => $post['phone'],
                    'type' => $payment_id,
                    'amount' => $countGrandTotal,
                    'expiration_date' => null,
                    'failure_code' => null,
                    'status' => null,
                    'callback_authentication_token' => null,
                    'checkout_url' => null,
                ]);
            }

            $dataDetailProduct = [];
            $checkPayment = TransactionMultiplePayment::where('id_transaction_group', $check['id_transaction_group'])->first();
            foreach ($check['transactions'] as $subtrx) {
                foreach ($subtrx['productTransaction'] as $key => $value) {
                    $dataProductMidtrans = [
                        'id'       => $value['id_product'],
                        'price'    => abs($value['transaction_product_price'] - ($value['transaction_product_discount'] / $value['transaction_product_qty'])),
                        'name'     => $value['product']['product_name'],
                        'quantity' => $value['transaction_product_qty'],
                    ];

                    $dataDetailProduct[] = $dataProductMidtrans;
                }
            }

            if ($check['transaction_shipment'] > 0) {
                $dataShip = [
                    'id'       => 'shipment',
                    'price'    => abs($check['transaction_shipment']),
                    'name'     => 'Shipping',
                    'quantity' => 1,
                ];
                array_push($dataDetailProduct, $dataShip);
            }

            if ($check['transaction_service'] > 0) {
                $dataService = [
                    'id'       => 'transaction_service',
                    'price'    => abs($check['transaction_service']),
                    'name'     => 'Service',
                    'quantity' => 1,
                ];
                array_push($dataDetailProduct, $dataService);
            }

            if ($check['transaction_tax'] > 0) {
                $dataTax = [
                    'id'       => 'transaction_tax',
                    'price'    => abs($check['transaction_tax']),
                    'name'     => 'Tax',
                    'quantity' => 1,
                ];
                array_push($dataDetailProduct, $dataTax);
            }

            if (!empty($checkPayment)) {
                if ($checkPayment['type'] == 'Balance') {
                    if (empty($checkPaymentBalance)) {
                        DB::rollback();
                        return response()->json([
                            'status'   => 'fail',
                            'messages' => ['Transaction is invalid'],
                        ]);
                    }

                    $dataBalance     = [
                        'id'       => 'balance',
                        'price'    => abs($checkPaymentBalance['balance_nominal']),
                        'name'     => 'Balance',
                        'quantity' => 1,
                    ];

                    array_push($dataDetailProduct, $dataBalance);

                    $detailPayment['balance'] = -$checkPaymentBalance['balance_nominal'];
                }
            }
            $paymentXendit->items = $dataDetailProduct;

            if ($paymentXendit->payVA($errors)) {
                $dataMultiple = [
                    'id_transaction_group' => $paymentXendit->id_transaction_group,
                    'type'           => 'Xendit',
                    'id_payment'     => $paymentXendit->id_transaction_payment_xendit,
                ];
                // save multiple payment
                $saveMultiple = TransactionMultiplePayment::updateOrCreate([
                    'id_transaction_group' => $paymentXendit->id_transaction_group,
                    'type'           => 'Xendit',
                    'payment_detail' => $post['payment_detail']
                ], $dataMultiple);

                $result = [
                    'redirect' => false,
                    'type' => $paymentXendit->type,
                ];
                $result['transaction_data'] = $transactionData;
                $result['id_transaction_group'] = $transactionData['transaction_details']['id_transaction_group'];
                $result['transaction_receipt_number'] = $transactionData['transaction_details']['order_id'];
                

                $transactionGroup = Transaction::where('id_transaction_group', $check['id_transaction_group'])->get()->toArray();
                foreach ($transactionGroup as $transactions) {
                   
                    if (!$checkPaymentBalance) {
                        Transaction::where('id_transaction', $transactions['id_transaction'])->update(['trasaction_payment_type' => $post['payment_type']]);
                    }
                }

                DB::commit();

                return [
                    'status' => 'success',
                    'result' => $result
                ];
            }

            $dataMultiple = [
                'id_transaction' => $paymentXendit->id_transaction,
                'type'           => 'Xendit VA',
                'id_payment'     => $paymentXendit->id_transaction_payment_xendit,
            ];
            // save multiple payment
            $saveMultiple = TransactionMultiplePayment::updateOrCreate([
                'id_transaction' => $paymentXendit->id_transaction,
                'type'           => 'Xendit VA',
            ], $dataMultiple);

            DB::commit();

            return [
                'status' => 'fail',
                'messages' => $errors ?: ['Something went wrong']
            ];
        } else {
            if (isset($post['id_manual_payment_method'])) {
                $checkPaymentMethod = ManualPaymentMethod::where('id_manual_payment_method', $post['id_manual_payment_method'])->first();
                if (empty($checkPaymentMethod)) {
                    DB::rollback();
                    return response()->json([
                        'status'   => 'fail',
                        'messages' => ['Payment Method Not Found'],
                    ]);
                }
            }

            if (isset($post['payment_receipt_image'])) {
                if (!file_exists($this->saveImage)) {
                    mkdir($this->saveImage, 0777, true);
                }

                $save = MyHelper::uploadPhotoStrict($post['payment_receipt_image'], $this->saveImage, 300, 300);

                if (isset($save['status']) && $save['status'] == "success") {
                    $post['payment_receipt_image'] = $save['path'];
                } else {
                    DB::rollback();
                    return response()->json([
                        'status'   => 'fail',
                        'messages' => ['fail upload image'],
                    ]);
                }
            } else {
                $post['payment_receipt_image'] = null;
            }

            $dataManual = [
                'id_transaction'         => $check['id_transaction'],
                'payment_date'           => $post['payment_date'],
                'id_bank_method'         => $post['id_bank_method'],
                'id_bank'                => $post['id_bank'],
                'id_manual_payment'      => $post['id_manual_payment'],
                'payment_time'           => $post['payment_time'],
                'payment_bank'           => $post['payment_bank'],
                'payment_method'         => $post['payment_method'],
                'payment_account_number' => $post['payment_account_number'],
                'payment_account_name'   => $post['payment_account_name'],
                'payment_nominal'        => $check['transaction_grandtotal'],
                'payment_receipt_image'  => $post['payment_receipt_image'],
                'payment_note'           => $post['payment_note'],
            ];

            $insertPayment = MyHelper::manualPayment($dataManual, 'transaction');
            if (isset($insertPayment) && $insertPayment == 'success') {
                $update = Transaction::where('transaction_receipt_number', $post['id'])->update(['transaction_payment_status' => 'Paid', 'trasaction_payment_type' => $post['payment_type']]);

                if (!$update) {
                    DB::rollback();
                    return response()->json([
                        'status'   => 'fail',
                        'messages' => ['Transaction Failed'],
                    ]);
                }
            } elseif (isset($insertPayment) && $insertPayment == 'fail') {
                DB::rollback();
                return response()->json([
                    'status'   => 'fail',
                    'messages' => ['Transaction Failed'],
                ]);
            } else {
                DB::rollback();
                return response()->json([
                    'status'   => 'fail',
                    'messages' => ['Transaction Failed'],
                ]);
            }

            DB::commit();
            return response()->json([
                'status' => 'success',
                'result' => $check,
            ]);
        }
    }

    //create transaction payment ovo
    public function paymentOvo($trx, $amount, $phone, $type)
    {
        $batchNo   = 1;
        $refnumber = 1;

        $dataPay = TransactionPaymentOvo::where('id_transaction', $trx['id_transaction'])->first();

        if (!$dataPay) {
            //get last ref number
            $lastRef = OvoReference::orderBy('id_ovo_reference', 'DESC')->first();
            if ($lastRef) {
                //cek jika beda tanggal, bacth_no + 1, ref_number reset ke 1
                if ($lastRef['date'] != date('Y-m-d')) {
                    $batchNo = $lastRef['batch_no'] + 1;
                    $refnumber = 1;
                } else {
                //tanggal sama, batch_no tetap, ref_number +1
                    $batchNo = $lastRef['batch_no'];

                    //cek jika ref_number sudah lebih dari 999.999
                    if ($lastRef['reference_number'] >= 999999) {
                        //reset ref_number ke 1 dan batch_no +1
                        $refnumber = 1;
                        $batchNo = $lastRef['batch_no'] + 1;
                    } else {
                        $refnumber = $lastRef['reference_number'] + 1;
                    }
                }
            }

            if ($type == 'production') {
                $is_prod = '1';
            } else {
                $is_prod = '0';
            }

            //update ovo_references
            $updateOvoRef = OvoReference::updateOrCreate(['id_ovo_reference' => 1], [
                'date'             => date('Y-m-d'),
                'batch_no'         => $batchNo,
                'reference_number' => $refnumber,
            ]);

            $insertPayOvo = TransactionPaymentOvo::create([
                'id_transaction'   => $trx['id_transaction'],
                'amount'           => $amount,
                'batch_no'         => $batchNo,
                'reference_number' => $refnumber,
                'phone'            => $phone,
                'reversal'         => 'not yet',
                'is_production'    => $is_prod,
            ]);
            if (!$insertPayOvo) {
                DB::rollback();
                return response()->json([
                    'status'   => 'fail',
                    'messages' => [
                        'Payment Ovo Failed.',
                    ],
                ]);
            }

            $dataMultiple = [
                'id_transaction' => $trx['id_transaction'],
                'type'           => 'Ovo',
                'id_payment'     => $insertPayOvo['id_transaction_payment_ovo'],
                'payment_detail' => 'Ovo',
            ];

            $saveMultiple = TransactionMultiplePayment::create($dataMultiple);
            if (!$saveMultiple) {
                DB::rollback();
                return response()->json([
                    'status'   => 'fail',
                    'messages' => ['fail to confirm transaction'],
                ]);
            }

            DB::commit();
        } else {
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Payment already in progress'],
            ]);
            $insertPayOvo = $dataPay;
        }

        $payOvo = Ovo::PayTransaction($trx, $insertPayOvo, $amount, $type);
        if ($payOvo) {
            //request lagi, krn batch number, ref number & merchat invoice sudah pernah dipakai
            // while(isset($payOvo['response']['responseCode']) && $payOvo['response']['responseCode'] == '40'){
            //     //get last ref number
            //     $lastRef = OvoReference::orderBy('id_ovo_reference', 'DESC')->first();
            //     if($lastRef){
            //         //cek jika beda tanggal, bacth_no + 1, ref_number reset ke 1
            //         if($lastRef['date'] != date('Y-m-d')){
            //             $batchNo = $lastRef['batch_no'] + 1;
            //             $refnumber = 1;
            //         }
            //         //tanggal sama, batch_no tetap, ref_number +1
            //         else{
            //             $batchNo = $lastRef['batch_no'];

            //             //cek jika ref_number sudah lebih dari 999.999
            //             if($lastRef['reference_number'] >= 999999){
            //                 //reset ref_number ke 1 dan batch_no +1
            //                 $refnumber = 1;
            //                 $batchNo = $lastRef['batch_no'] + 1;
            //             }else{
            //                 $refnumber = $lastRef['reference_number'] + 1;
            //             }
            //         }
            //     }

            //     //update ovo_references
            //     $updateOvoRef = OvoReference::updateOrCreate(['id_ovo_reference'=> 1], [
            //         'date' => date('Y-m-d'),
            //         'batch_no' => $batchNo,
            //         'reference_number' => $refnumber
            //     ]);

            //     $updatePayOvo = TransactionPaymentOvo::where('id_transaction', $trx['id_transaction'])->update([
            //         'batch_no' => $batchNo,
            //         'reference_number' => $refnumber,
            //         'reversal' => 'not yet',
            //     ]);

            //     $dataPay = TransactionPaymentOvo::where('id_transaction', $trx['id_transaction'])->first();

            //     $payOvo = Ovo::PayTransaction($trx, $dataPay, $amount, $type);
            // }

            //jika response code 200
            if (isset($payOvo['status_code']) && $payOvo['status_code'] == '200') {
                $response = $payOvo['response'];

                if ($response['responseCode'] == '00') {
                    //update payment
                    if (isset($response['referenceNumber'])) {
                        DB::beginTransaction();

                        $payment = TransactionPaymentOvo::where('id_transaction', $trx['id_transaction'])->first();
                        if ($payment) {
                            $dataUpdate['reversal']             = 'no';
                            $dataUpdate['trace_number']         = $response['traceNumber'];
                            $dataUpdate['approval_code']        = $response['approvalCode'];
                            $dataUpdate['response_code']        = $response['responseCode'];
                            $dataUpdate['response_detail']      = 'Success / Approved';
                            $dataUpdate['response_description'] = 'Success / Approved Transaction';
                            $dataUpdate['ovoid']                = $response['transactionResponseData']['ovoid'];
                            $dataUpdate['cash_used']            = $response['transactionResponseData']['cashUsed'];
                            $dataUpdate['ovo_points_earned']    = $response['transactionResponseData']['ovoPointsEarned'];
                            $dataUpdate['cash_balance']         = $response['transactionResponseData']['cashBalance'];
                            $dataUpdate['full_name']            = $response['transactionResponseData']['fullName'];
                            $dataUpdate['ovo_points_used']      = $response['transactionResponseData']['ovoPointsUsed'];
                            $dataUpdate['ovo_points_balance']   = $response['transactionResponseData']['ovoPointsBalance'];
                            $dataUpdate['payment_type']         = $response['transactionResponseData']['paymentType'];

                            $update = TransactionPaymentOvo::where('id_transaction', $trx['id_transaction'])->update($dataUpdate);
                            if ($update) {
                                $updatePaymentStatus = Transaction::where('id_transaction', $trx['id_transaction'])->update(['transaction_payment_status' => 'Completed', 'completed_at' => date('Y-m-d H:i:s')]);
                                if ($updatePaymentStatus) {
                                    $userData = User::where('id', $trx['id_user'])->first();
                                    $config_fraud_use_queue = Configs::where('config_name', 'fraud use queue')->first()->is_active;

                                    if ($config_fraud_use_queue == 1) {
                                        FraudJob::dispatch($userData, $trx, 'transaction')->onConnection('fraudqueue');
                                    } else {
                                        $checkFraud = app($this->setting_fraud)->checkFraudTrxOnline($userData, $trx);
                                    }

                                    $dataTrx = Transaction::with('user.memberships', 'outlet', 'productTransaction')
                                        ->where('id_transaction', $payment['id_transaction'])->first();

                                    //inset pickup_at when pickup_type = right now
                                    if ($dataTrx['trasaction_type'] == 'Pickup Order') {
                                        $dataPickup = TransactionPickup::where('id_transaction', $dataTrx['id_transaction'])->first();
                                        if (isset($dataPickup['pickup_type']) && $dataPickup['pickup_type'] == 'right now') {
                                            $settingTime = Setting::where('key', 'processing_time')->first();
                                            if ($settingTime && isset($settingTime['value'])) {
                                                $updatePickup = TransactionPickup::where('id_transaction', $dataTrx['id_transaction'])->update(['pickup_at' => date('Y-m-d H:i:s', strtotime('+ ' . $settingTime['value'] . 'minutes'))]);
                                            } else {
                                                $updatePickup = TransactionPickup::where('id_transaction', $dataTrx['id_transaction'])->update(['pickup_at' => date('Y-m-d H:i:s')]);
                                            }
                                        }
                                    }

                                    // // apply cashback to referrer
                                    // \Modules\PromoCampaign\Lib\PromoCampaignTools::applyReferrerCashback($dataTrx);

                                    $mid = [
                                        'order_id'     => $dataTrx['transaction_receipt_number'],
                                        'gross_amount' => $amount,
                                    ];

                                    $notif = app($this->notif)->notification($mid, $dataTrx);
                                    if (!$notif) {
                                        DB::rollBack();
                                        return response()->json([
                                            'status'   => 'fail',
                                            'messages' => ['Transaction Notification failed'],
                                        ]);
                                    }
                                    $sendNotifOutlet = app($this->trx)->outletNotif($dataTrx['id_transaction']);

                                    //create geocode location
                                    if (isset($dataTrx['latitude']) && isset($dataTrx['longitude'])) {
                                        $savelocation = app($this->trx)->saveLocation($dataTrx['latitude'], $dataTrx['longitude'], $dataTrx['id_user'], $dataTrx['id_transaction'], $dataTrx['id_outlet']);
                                    }

                                    //$fraud = app($this->notif)->checkFraud($dataTrx);
                                } else {
                                    DB::rollBack();
                                    return response()->json([
                                        'status'   => 'fail',
                                        'messages' => [' Update Transaction Payment Status Failed'],
                                    ]);
                                }
                            } else {
                                DB::rollBack();
                                return response()->json([
                                    'status'   => 'fail',
                                    'messages' => [' Update Transaction Payment Failed'],
                                ]);
                            }
                        }

                        DB::commit();
                    }

                    //
                }
            } else {
                //response failed

                $response = [];

                if (isset($payOvo['response'])) {
                    $response = $payOvo['response'];
                }

                $payment = TransactionPaymentOvo::where('id_transaction', $trx['id_transaction'])->first();
                if ($payment) {
                    DB::beginTransaction();

                    $dataUpdate = [];

                    if (isset($payOvo['status_code']) && $payOvo['status_code'] != '404') {
                        $dataUpdate['reversal'] = 'no';
                    }

                    if (isset($response['traceNumber'])) {
                        $dataUpdate['trace_number'] = $response['traceNumber'];
                    }
                    if (isset($response['type']) && $response['type'] == '0210') {
                        $dataUpdate['payment_type'] = 'PUSH TO PAY';
                    }
                    if (isset($response['responseCode'])) {
                        $dataUpdate['response_code'] = $response['responseCode'];
                        $dataUpdate = Ovo::detailResponse($dataUpdate);
                    }

                    $update = TransactionPaymentOvo::where('id_transaction', $trx['id_transaction'])->update($dataUpdate);

                    MyHelper::updateFlagTransactionOnline($trx, 'cancel');
                    $updatePaymentStatus = Transaction::where('id_transaction', $trx['id_transaction'])->update(['transaction_payment_status' => 'Cancelled', 'void_date' => date('Y-m-d H:i:s')]);

                    if ($trx->id_promo_campaign_promo_code) {
                        $update_promo_report = app($this->promo_campaign)->deleteReport($trx->id_transaction, $trx->id_promo_campaign_promo_code);
                    }

                    $updateVoucher = app($this->voucher)->returnVoucher($trx['id_transaction']);

                    // return subscription
                    $update_subscription = app($this->subscription)->returnSubscription($trx['id_transaction']);

                    $usere = User::where('id', $trx['id_user'])->first();
                    //return balance
                    $payBalance = TransactionMultiplePayment::where('id_transaction', $trx['id_transaction'])->where('type', 'Balance')->first();
                    if (!empty($payBalance)) {
                        $checkBalance = TransactionPaymentBalance::where('id_transaction_payment_balance', $payBalance['id_payment'])->first();
                        if (!empty($checkBalance)) {
                            $insertDataLogCash = app($this->balance)->addLogBalance($trx['id_user'], $checkBalance['balance_nominal'], $trx['id_transaction'], 'Online Transaction Failed', $trx['transaction_grandtotal']);
                            if (!$insertDataLogCash) {
                                DB::rollback();
                                return response()->json([
                                    'status'   => 'fail',
                                    'messages' => ['Insert Cashback Failed'],
                                ]);
                            }

                            $send = app($this->autocrm)->SendAutoCRM(
                                'Transaction Failed Point Refund',
                                $usere->phone,
                                [
                                    "outlet_name"       => $trx['outlet_name']['outlet_name'] ?? '',
                                    "transaction_date"  => $trx['transaction_date'],
                                    'receipt_number'    => $trx['transaction_receipt_number'],
                                    'id_transaction'    => $trx['id_transaction'],
                                    'received_point'    => (string) $checkBalance['balance_nominal'],
                                    'order_id'          => $trx['order_id'] ?? '',
                                ]
                            );
                            if ($send != true) {
                                DB::rollback();
                                return response()->json([
                                    'status'   => 'fail',
                                    'messages' => ['Failed Send notification to customer'],
                                ]);
                            }
                        }
                    }

                    //send autocrm transaction fail
                    $dataPickup = TransactionPickup::where('id_transaction', $trx['id_transaction'])->first();
                    $send = app($this->autocrm)->SendAutoCRM('Transaction Expired', $usere->phone, [
                        'notif_type' => 'trx',
                        'header_label' => 'Gagal',
                        'id_transaction' => $trx['id_transaction'],
                        'date' => $trx['transaction_date'],
                        'status' => $trx['transaction_payment_status'],
                        'name'  => $usere->name,
                        'id' => $dataPickup['order_id'],
                        'order_id' => $dataPickup['order_id'],
                        'outlet_name' => $trx['outlet_name']['outlet_name'] ?? '',
                        'id_reference' => $dataPickup['order_id'] . ',' . $trx['id_outlet']
                    ]);

                    DB::commit();
                    //request reversal
                    if (!isset($payOvo['status_code']) || $payOvo['status_code'] == '404') {
                        $reversal = Ovo::Reversal($trx, $insertPayOvo, $amount, $type);

                        if (isset($reversal['response'])) {
                            $response   = $reversal['response'];
                            $dataUpdate = [];

                            $dataUpdate['reversal'] = 'yes';

                            if (isset($response['traceNumber'])) {
                                $dataUpdate['trace_number'] = $response['traceNumber'];
                            }
                            if (isset($response['type']) && $response['type'] == '0410') {
                                $dataUpdate['payment_type'] = 'REVERSAL';
                            }
                            if (isset($response['responseCode'])) {
                                $dataUpdate['response_code'] = $response['responseCode'];
                                $dataUpdate                  = Ovo::detailResponse($dataUpdate);
                            }

                            $update = TransactionPaymentOvo::where('id_transaction', $trx['id_transaction'])->update($dataUpdate);
                        }
                    }
                }
            }

            $trx = Transaction::where('id_transaction', $trx['id_transaction'])->first();
            if ($trx) {
                $dataEncode = [
                    'transaction_receipt_number' => $trx['trasaction_receipt_number'],
                    'type'                       => 'trx',
                    'trx_success'                => 1,
                ];
                $button = 'LIHAT NOTA';

                $title = 'Sukses';
                if ($trx['transaction_payment_status'] == 'Pending') {
                    $title = 'Pending';
                }

                if ($trx['transaction_payment_status'] == 'Paid') {
                    $title = 'Terbayar';
                }

                if ($trx['transaction_payment_status'] == 'Completed') {
                    $title = 'Sukses';
                }

                if ($trx['transaction_payment_status'] == 'Cancelled') {
                    $title = 'Gagal';
                }

                $encode = json_encode($dataEncode);
                $base   = base64_encode($encode);

                $send = [
                    'status' => 'success',
                    'result' => [
                        'button'                     => $button,
                        'title'                      => $title,
                        'payment_status'             => $trx['transaction_payment_status'],
                        'transaction_receipt_number' => $trx['transaction_receipt_number'],
                        'transaction_grandtotal'     => $trx['transaction_grandtotal'],
                        'type'                       => 'trx',
                        'url'                        => env('VIEW_URL') . '/transaction/web/view/detail?data=' . $base,
                    ],
                ];
                DB::commit();
                return response()->json($send);
            }
        }

        $updatePaymentStatus = Transaction::where('id_transaction', $payment['id_transaction'])->update(['transaction_payment_status' => 'Cancelled']);

        if ($trx->id_promo_campaign_promo_code) {
            $update_promo_report = app($this->promo_campaign)->deleteReport($trx->id_transaction, $trx->id_promo_campaign_promo_code);
        }

        $updateVoucher = app($this->voucher)->returnVoucher($trx->id_transaction);

        // return subscription
        $update_subscription = app($this->subscription)->returnSubscription($trx->id_transaction);

        //return balance
        $payBalance = TransactionMultiplePayment::where('id_transaction', $trx['id_transaction'])->where('type', 'Balance')->first();
        if (!empty($payBalance)) {
            $checkBalance = TransactionPaymentBalance::where('id_transaction_payment_balance', $payBalance['id_payment'])->first();
            if (!empty($checkBalance)) {
                $insertDataLogCash = app($this->balance)->addLogBalance($trx['id_user'], $checkBalance['balance_nominal'], $trx['id_transaction'], 'Online Transaction Failed', $trx['transaction_grandtotal']);
                if (!$insertDataLogCash) {
                    DB::rollback();
                    return response()->json([
                        'status'   => 'fail',
                        'messages' => ['Insert Cashback Failed'],
                    ]);
                }
                $usere = User::where('id', $trx['id_user'])->first();
                $send  = app($this->autocrm)->SendAutoCRM(
                    'Transaction Failed Point Refund',
                    $usere->phone,
                    [
                        "outlet_name"       => $trx['outlet_name']['outlet_name'] ?? '',
                        "transaction_date"  => $trx['transaction_date'],
                        'id_transaction'    => $trx['id_transaction'],
                        'receipt_number'    => $trx['transaction_receipt_number'],
                        'received_point'    => (string) $checkBalance['balance_nominal'],
                        'order_id'          => $trx['order_id'] ?? '',
                    ]
                );
                if ($send != true) {
                    DB::rollback();
                    return response()->json([
                        'status'   => 'fail',
                        'messages' => ['Failed Send notification to customer'],
                    ]);
                }
            }
        }

        DB::commit();
        return response()->json([
            'status'   => 'fail',
            'messages' => ['Transaction Payment Failed'],
        ]);
    }
}
