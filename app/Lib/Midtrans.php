<?php

namespace App\Lib;

use Image;
use File;
use DB;
use App\Http\Models\Notification;
use App\Http\Models\Store;
use App\Http\Models\User;
use App\Http\Models\Transaction;
use App\Http\Models\ProductVariant;
use App\Http\Models\LogPoint;
use App\Http\Models\DealsUser;
use App\Http\Models\SubscriptionUser;
use App\Http\Requests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use Guzzle\Http\EntityBody;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\Response;
use Guzzle\Http\Exception\ServerErrorResponseException;
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;
use FCM;
use App\Lib\MyHelper;
use App\Http\Models\LogMidtrans;
use Modules\Transaction\Entities\TransactionGroup;

class Midtrans
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    public static function bearer()
    {
        // return 'Basic ' . base64_encode(env('MIDTRANS_PRO_BEARER'));
        return 'Basic ' . base64_encode(env('MIDTRANS_SANDBOX_BEARER'));
    }

    public static function bearerPro()
    {
        return 'Basic ' . base64_encode(env('MIDTRANS_PRO_BEARER'));
        // return 'Basic ' . base64_encode(env('MIDTRANS_SANDBOX_BEARER'));
    }

    public static function token($receipt, $grandTotal, $user = null, $shipping = null, $product = null, $type = null, $id = null, $payment_detail = null)
    {
        // $url    = env('MIDTRANS_PRO');
        $url    = env('MIDTRANS_SANDBOX');

        $transaction_details = array(
            'order_id'      => $receipt,
            'gross_amount'  => $grandTotal
        );

        $payment_detail = str_replace(' ', '-', (strtolower($payment_detail)));
        $dataMidtrans = array(
            'transaction_details' => $transaction_details,
        );

        if ($payment_detail) {
            $payment_detail = str_replace('-', '_', $payment_detail);
            $dataMidtrans['enabled_payments'] = [$payment_detail];
        }

        if (!is_null($user)) {
            $dataMidtrans['customer_details'] = $user;
        }

        if (!is_null($shipping)) {
            $dataMidtrans['shipping_address'] = $shipping;
        }

        if (!is_null($product)) {
            $dataMidtrans['item_details'] = $product;
        }

        $dataMidtrans['credit_card'] = [
            'secure' => true,
        ];

        $tab = ($type == 'trx' ? 'history/order' : 'page/consultation');
        $baseCallback = env('MIDTRANS_CALLBACK') . $tab . '?type=' . $type . '&order_id=' . urlencode($id);
        if (!$payment_detail || strtolower($payment_detail) == 'gopay') {
            $dataMidtrans['gopay'] = [
                'enable_callback' => true,
                'callback_url' => $baseCallback . '&result=success',
            ];
        }
        $dataMidtrans['callbacks'] = [
            'finish' => $baseCallback . '&result=success',
            'unfinish' => $baseCallback . '&result=fail',
            'error' => $baseCallback . '&result=fail'
        ];

        $token = MyHelper::post($url, self::bearer(), $dataMidtrans);

        try {
            LogMidtrans::create([
                'type'                 => 'request_token',
                'id_reference'         => $receipt,
                'request'              => json_encode($dataMidtrans),
                'request_url'          => $url,
                'request_header'       => json_encode(['Authorization' => self::bearer()]),
                'response'             => json_encode($token),
                'response_status_code' => $token['status_code'] ?? null,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed write log to LogMidtrans: ' . $e->getMessage());
        }

        return $token;
    }

    public static function tokenPro($receipt, $grandTotal, $user = null, $shipping = null, $product = null)
    {
        $url    = env('MIDTRANS_PRO');
        // $url    = env('MIDTRANS_SANDBOX');

        $transaction_details = array(
            'order_id'      => $receipt,
            'gross_amount'  => $grandTotal
        );

        $dataMidtrans = array(
            'transaction_details' => $transaction_details,
        );

        if (!is_null($user)) {
            $dataMidtrans['customer_details'] = $user;
        }

        if (!is_null($shipping)) {
            $dataMidtrans['shipping_address'] = $shipping;
        }

        if (!is_null($product)) {
            $dataMidtrans['item_details'] = $product;
        }

        $dataMidtrans['credit_card'] = [
            'secure' => true,
        ];

        $token = MyHelper::post($url, self::bearerPro(), $dataMidtrans);

        return $token;
    }

    public static function expire($order_id)
    {
        // $url    = env('BASE_MIDTRANS_PRO').'/v2/'.$order_id.'/expire';
        $url    = env('BASE_MIDTRANS_SANDBOX') . '/v2/' . $order_id . '/expire';
        $status = MyHelper::post($url, self::bearer(), ['data' => 'expired']);

        return $status;
    }

    public static function expire2($order_id)
    {
        $url    = env('BASE_MIDTRANS_PRO') . '/v2/' . $order_id . '/expire';
        // $url    = env('BASE_MIDTRANS_SANDBOX').'/v2/'.$order_id.'/expire';
        $status = MyHelper::post($url, self::bearerPro(), ['data' => 'expired']);

        return $status;
    }
    public static function refund($order_id, $param = null, $transaction_status = null)
    {
        if (!$transaction_status) {
            // $url    = env('BASE_MIDTRANS_PRO').'/v2/'.$order_id.'/expire';
            $trx = Transaction::join('transaction_payment_midtrans', 'transaction_payment_midtrans.id_transaction_group', '=', 'transactions.id_transaction_group')->where('vt_transaction_id', $order_id)->orWhere('transaction_receipt_number', $order_id)->first();
            if (!$trx) {
                return ['status' => 'fail','messages' => 'Midtrans payment not found'];
            }
            $url    = env('BASE_MIDTRANS_SANDBOX') . '/v2/' . $trx->vt_transaction_id . '/refund/online/direct';
            if ($trx->transaction_status == 'capture') {
                $url = env('BASE_MIDTRANS_SANDBOX') . '/v2/' . $trx->vt_transaction_id . '/cancel';
            } else {
                $param['reason'] = 'Pengembalian dana';
            }
            if (!$param) {
                $param = [];
            }
            $id_reference = $trx->id_transaction;
        } else {
            $url    = env('BASE_MIDTRANS_SANDBOX') . '/v2/' . $order_id . '/refund/online/direct';
            if ($transaction_status == 'capture') {
                $url = env('BASE_MIDTRANS_SANDBOX') . '/v2/' . $order_id . '/cancel';
            } else {
                $param['reason'] = 'Pengembalian dana';
            }
            if (!$param) {
                $param = [];
            }
            $id_reference = $order_id;
        }
        $status = MyHelper::post($url, self::bearer(), $param);
        try {
            LogMidtrans::create([
                'type'                 => 'refund',
                'id_reference'         => $id_reference,
                'request'              => json_encode($param),
                'request_url'          => $url,
                'request_header'       => json_encode(['Authorization' => self::bearer()]),
                'response'             => json_encode($status),
                'response_status_code' => $status['status_code'] ?? null,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed write log to LogMidtrans: ' . $e->getMessage());
        }
        if (($status['status_code'] ?? false) != 200) {
            // check status saa seperti ipay88
            $midtransStatus = static::status($order_id);
            if (($midtransStatus['transaction_status'] ?? ($midtransStatus['response']['transaction_status'] ?? false)) == 'refund') {
                return [
                    'status' => 'success',
                    'messages' => [
                        'Refund already processed'
                    ]
                ];
            }
        }
        return [
            'status' => ($status['status_code'] ?? false) == 200 ? 'success' : 'fail',
            'messages' => [$status['status_message'] ?? 'Something went wrong','Refund failed']
        ];
    }

    public static function refundPartial($order_id, $param = null)
    {
        $trx = Transaction::join('transaction_payment_midtrans', 'transaction_payment_midtrans.id_transaction_group', '=', 'transactions.id_transaction_group')->where('vt_transaction_id', $order_id)->orWhere('transaction_receipt_number', $order_id)->first();
        if (!$trx) {
            return ['status' => 'fail','messages' => 'Midtrans payment not found'];
        }

        $url    = env('BASE_MIDTRANS_SANDBOX') . '/v2/' . $trx->vt_transaction_id . '/refund';
        if ($trx->transaction_status == 'capture') {
            $url = env('BASE_MIDTRANS_SANDBOX') . '/v2/' . $trx->vt_transaction_id . '/cancel';
        } else {
            $param['reason'] = 'Pengembalian dana';
        }
        if (!$param) {
            $param = [];
        }
        $id_reference = $trx->id_transaction;

        $status = MyHelper::post($url, self::bearer(), $param);
        try {
            LogMidtrans::create([
                'type'                 => 'refund',
                'id_reference'         => $id_reference,
                'request'              => json_encode($param),
                'request_url'          => $url,
                'request_header'       => json_encode(['Authorization' => self::bearer()]),
                'response'             => json_encode($status),
                'response_status_code' => $status['status_code'] ?? null,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed write log to LogMidtrans: ' . $e->getMessage());
        }
        if (($status['status_code'] ?? false) != 200) {
            // check status saa seperti ipay88
            $midtransStatus = static::status($order_id);
            if (($midtransStatus['transaction_status'] ?? ($midtransStatus['response']['transaction_status'] ?? false)) == 'refund') {
                return [
                    'status' => 'success',
                    'messages' => [
                        'Refund already processed'
                    ]
                ];
            }
        }
        return [
            'status' => ($status['status_code'] ?? false) == 200 ? 'success' : 'fail',
            'messages' => [$status['status_message'] ?? 'Something went wrong','Refund failed']
        ];
    }


    // static function checkStatus($orderId) {
    //     $url = 'https://api.sandbox.midtrans.com/v2/'.$orderId.'/status';

    //     $status = MyHelper::get($url, Self::bearer());

    //     return $status;
    // }

    /**
     * Get status payment midtrans
     * @param  integer $id_transaction Transaction id
     * @return Array           array response
     */
    public static function status($order_id, $type = 'trx')
    {
        if (is_numeric($order_id)) {
            switch ($type) {
                case 'deals':
                    $trx = DealsUser::join('deals_payment_midtrans', 'deals_payment_midtrans.id_deals_user', '=', 'deals_users.id_deals_user')->where('deals_users.id_deals_user', $order_id)->orWhere('order_id', $order_id)->first();
                    if (!$trx) {
                        return ['status' => 'fail','messages' => ['Deals payment not found']];
                    }

                    $transaction_id = $trx->order_id;
                    break;

                case 'subscription':
                    $trx = SubscriptionUser::join('subscription_payment_midtrans', 'subscription_payment_midtrans.id_subscription_user', '=', 'subscription_users.id_subscription_user')->where('subscription_users.id_subscription_user', $order_id)->first();
                    $transaction_id = $trx->order_id;

                    if (!$trx) {
                        return ['status' => 'fail','messages' => ['Subscription payment not found']];
                    }

                    break;

                default:
                    $trx = TransactionGroup::join('transaction_payment_midtrans', 'transaction_payment_midtrans.id_transaction_group', '=', 'transaction_groups.id_transaction_group')->where('transaction_groups.id_transaction_group', $order_id)->first();

                    if (!$trx) {
                        // jika edit messages error ini, pastikan edit juga di ApiCronTrxController@cron
                        return ['status' => 'fail','messages' => ['Midtrans payment not found']];
                    }
                    $transaction_id = $trx->transaction_receipt_number;
                    break;
            }
        } else {
            $transaction_id = $order_id;
        }

        $url    = env('BASE_MIDTRANS_SANDBOX') . '/v2/' . $transaction_id . '/status';
        $result = MyHelper::get($url, self::bearer());
        try {
            LogMidtrans::create([
                'type'                 => 'check_status',
                'id_reference'         => $transaction_id,
                'request'              => null,
                'request_url'          => $url,
                'request_header'       => json_encode(['Authorization' => self::bearer()]),
                'response'             => json_encode($result),
                'response_status_code' => $result['status_code'] ?? null,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed write log to LogMidtrans: ' . $e->getMessage());
        }
        return $result;
    }
}
