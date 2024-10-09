<?php

namespace Modules\Subscription\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Lib\MyHelper;
use Modules\Subscription\Entities\Subscription;
use Modules\Subscription\Entities\FeaturedSubscription;
use Modules\Subscription\Entities\SubscriptionOutlet;
use Modules\Subscription\Entities\SubscriptionProduct;
use Modules\Subscription\Entities\SubscriptionContent;
use Modules\Subscription\Entities\SubscriptionContentDetail;
use Modules\Subscription\Entities\SubscriptionUser;
use Modules\Subscription\Entities\SubscriptionUserVoucher;
use Modules\Deals\Entities\DealsContent;
use Modules\Deals\Entities\DealsContentDetail;
use Modules\Promotion\Entities\DealsPromotionContent;
use Modules\Promotion\Entities\DealsPromotionContentDetail;
use App\Http\Models\Setting;
use Modules\Subscription\Http\Requests\ListSubscription;
use Modules\Subscription\Http\Requests\Step1Subscription;
use Modules\Subscription\Http\Requests\Step2Subscription;
use Modules\Subscription\Http\Requests\Step3Subscription;
use Modules\Subscription\Http\Requests\DetailSubscription;
use Modules\Subscription\Http\Requests\DeleteSubscription;
use Modules\Subscription\Http\Requests\UpdateCompleteSubscription;
use DB;

class ApiSubscriptionReport extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->user     = "Modules\Users\Http\Controllers\ApiUser";
    }

    public function transactionReport(Request $request)
    {
        if ($request->report_type == 'claim') {
            $list = $this->getSubscriptionClaimReport($request);
        } else {
            $list = $this->getSubscriptionTrxReport($request);
        }

        $list = $list->paginate(10);

        return MyHelper::checkGet($list);
    }

    public function listStartedSubscription(Request $request)
    {
        $data = Subscription::where('subscription_start', '<', date('Y-m-d H:i:s'))
                ->where('subscription_step_complete', '1')
                ->select('id_subscription', 'subscription_title')->get()->toArray();

        return response()->json(MyHelper::checkGet($data));
    }

    protected function filterReport($query, $request)
    {
        $allowed = [
            'operator'  => ['=', 'like', '<', '>', '<=', '>='],
            'subject'   => ['name', 'phone', 'subscription', 'bought_at', 'subscription_expired_at', 'used_at', 'outlet', 'transaction_receipt_number', 'subscription_price_cash', 'subscription_price_point', 'voucher_code', 'subscription_nominal', 'transaction_grandtotal', 'charged_outlet', 'charged_central'],
            'mainSubject' => ['name', 'phone', 'bought_at', 'subscription_expired_at', 'used_at', 'transaction_receipt_number', 'subscription_price_cash', 'subscription_price_point', 'voucher_code', 'subscription_nominal', 'transaction_grandtotal', 'charged_outlet', 'charged_central']
        ];
        $return     = [];
        $where      = $request['operator'] == 'or' ? 'orWhere' : 'where';
        $whereDate  = $request['operator'] == 'or' ? 'orWhereDate' : 'whereDate';
        $whereHas   = $request['operator'] == 'or' ? 'orWhereHas' : 'whereHas';
        $whereIn    = $request['operator'] == 'or' ? 'orWhereIn' : 'whereIn';
        $rule       = $request['rule'];
        $query->where(function ($queryx) use ($rule, $allowed, $where, $query, $request, $whereDate, $whereHas, $whereIn) {
            $foreign = array();
            $outletCount = 0;
            $userCount = 0;

            foreach ($rule ?? [] as $value) {
                if (is_object($value)) {
                    $value = (array)$value;
                }
                if (!in_array($value['subject'], $allowed['subject'])) {
                    continue;
                }
                if (!(isset($value['operator']) && $value['operator'] && in_array($value['operator'], $allowed['operator']))) {
                    $value['operator'] = '=';
                }
                if ($value['operator'] == 'like') {
                    $value['parameter'] = '%' . $value['parameter'] . '%';
                }

                if ($value['subject'] != 'subscription' && $value['subject'] != 'outlet') {
                    if ($value['subject'] == 'charged_outlet') {
                        $queryx->$where('disburse_outlet_transactions.subscription', $value['operator'], $value['parameter']);
                    } elseif ($value['subject'] == 'charged_central') {
                        $queryx->$where('disburse_outlet_transactions.subscription_central', $value['operator'], $value['parameter']);
                    } elseif ($value['subject'] == 'subscription_price_cash') {
                        $queryx->$where('subscription_users.subscription_price_cash', $value['operator'], $value['parameter']);
                    } elseif ($value['subject'] == 'subscription_price_point') {
                        $queryx->$where('subscription_users.subscription_price_point', $value['operator'], $value['parameter']);
                    } else {
                        $queryx->$where($value['subject'], $value['operator'], $value['parameter']);
                    }
                } else {
                    if ($value['subject'] == 'subscription') {
                        $queryx->$whereIn('subscription_users.id_subscription', $value['parameter']);
                    } elseif ($value['subject'] == 'outlet') {
                        $queryx->$whereIn('transactions.id_outlet', $value['parameter']);
                    }
                }

                $return[] = $value;
            }
        });
        return ['filter' => $return, 'filter_operator' => $request['operator']];
    }

    public function getSubscriptionTrxReport($request)
    {
        $query  = DB::table('subscription_user_vouchers')
                    ->orderBy('subscription_user_vouchers.updated_at', 'Desc')
                    ->select(
                        'subscription_user_vouchers.voucher_code',
                        'subscription_user_vouchers.used_at',
                        'subscription_user_vouchers.created_at',
                        'subscription_users.bought_at',
                        'subscription_users.id_subscription',
                        'subscription_users.subscription_expired_at',
                        'subscription_users.subscription_price_cash',
                        'subscription_users.subscription_price_point',
                        'users.name',
                        'users.phone',
                        DB::raw("CONCAT(users.name,'-',users.phone) as user"),
                        'transactions.id_transaction',
                        'transactions.id_outlet',
                        'transactions.transaction_receipt_number',
                        'transactions.transaction_grandtotal',
                        'outlets.outlet_code',
                        'outlets.outlet_name',
                        DB::raw("CONCAT(outlets.outlet_code,'-',outlets.outlet_name) as outlet"),
                        'subscriptions.subscription_title',
                        'subscriptions.subscription_type',
                        'transaction_payment_subscriptions.subscription_nominal'
                        // 'disburse_outlet_transactions.subscription as charged_outlet',
                        // 'disburse_outlet_transactions.subscription_central as charged_central'
                        // DB::raw("(transaction_payment_subscriptions.subscription_nominal - charged_outlet) as charged_central")
                    )
                    ->join('subscription_users', 'subscription_user_vouchers.id_subscription_user', '=', 'subscription_users.id_subscription_user')
                    ->join('users', 'users.id', '=', 'subscription_users.id_user')
                    ->join('transactions', 'transactions.id_transaction', '=', 'subscription_user_vouchers.id_transaction')
                    ->join('outlets', 'outlets.id_outlet', '=', 'transactions.id_outlet')
                    ->join('subscriptions', 'subscriptions.id_subscription', '=', 'subscription_users.id_subscription')
                    ->join('transaction_payment_subscriptions', 'transaction_payment_subscriptions.id_transaction', '=', 'transactions.id_transaction')
                    // ->leftJoin('disburse_outlet_transactions', 'disburse_outlet_transactions.id_transaction', '=', 'transactions.id_transaction')
                    ->groupBy('subscription_user_vouchers.id_subscription_user_voucher');

        if (isset($request['rule'])) {
             $this->filterReport($query, $request);
        }

        return $query;
    }

    public function getSubscriptionClaimReport($request)
    {
        $query  = DB::table('subscription_user_vouchers')
                    ->orderBy('subscription_users.bought_at', 'Desc')
                    ->select(
                        'subscription_user_vouchers.voucher_code',
                        'subscription_user_vouchers.used_at',
                        'subscription_user_vouchers.created_at',
                        'subscription_users.bought_at',
                        'subscription_users.id_subscription',
                        'subscription_users.subscription_expired_at',
                        'subscription_users.subscription_price_cash',
                        'subscription_users.subscription_price_point',
                        'users.name',
                        'users.phone',
                        DB::raw("CONCAT(users.name,'-',users.phone) as user"),
                        'subscriptions.subscription_title',
                        'subscriptions.subscription_type'
                    )
                    ->join('subscription_users', 'subscription_user_vouchers.id_subscription_user', '=', 'subscription_users.id_subscription_user')
                    ->join('users', 'users.id', '=', 'subscription_users.id_user')
                    ->join('subscriptions', 'subscriptions.id_subscription', '=', 'subscription_users.id_subscription')
                    ->groupBy('subscription_users.id_subscription_user');

        if (isset($request['rule'])) {
             $this->filterReport($query, $request);
        }

        return $query;
    }

    public function exportExcel($filter)
    {
        if (($filter['type'] ?? false) == 'claim') {
            $data = $this->getSubscriptionClaimReport($filter);
        } else {
            $data = $this->getSubscriptionTrxReport($filter);
        }

        foreach ($data->cursor() as $val) {
            $val = (array) $val;
            $bought_at  = ( !empty($val['bought_at']) ? date('d M Y H:i', strtotime($val['bought_at'])) : null );
            $expired_at = ( !empty($val['subscription_expired_at']) ? date('d M Y H:i', strtotime($val['subscription_expired_at'])) : null );

            if (($filter['type'] ?? false) == 'claim') {
                yield [
                    'Subscription Name'         => $val['subscription_title'],
                    'Voucher Code'              => $val['voucher_code'],
                    'User Name'                 => $val['name'],
                    'User Phone'                => $val['phone'],
                    'Subscription Price Cash'   => (float) $val['subscription_price_cash'],
                    'Subscription Price Point'  => (float) $val['subscription_price_point'],
                    'Bought at'                 => $bought_at,
                    'Expired at'                => $expired_at
                ];
            } else {
                $used_at    = ( !empty($val['used_at']) ? date('d M Y H:i', strtotime($val['used_at'])) : null );
                yield [
                    'Subscription Name'         => $val['subscription_title'],
                    'Voucher Code'              => $val['voucher_code'],
                    'User Name'                 => $val['name'],
                    'User Phone'                => $val['phone'],
                    'Subscription Price Cash'   => (float) $val['subscription_price_cash'],
                    'Subscription Price Point'  => (float) $val['subscription_price_point'],
                    'Bought at'                 => $bought_at,
                    'Expired at'                => $expired_at,
                    'Used at'                   => $used_at,
                    'Receipt Number'            => $val['transaction_receipt_number'],
                    'Transaction Grandtotal'    => (float) $val['transaction_grandtotal'],
                    'Outlet Code'               => $val['outlet_code'],
                    'Outlet Name'               => $val['outlet_name'],
                    'Subscription Nominal'      => (float) $val['subscription_nominal']
                    // 'Charged Central'            => (float) $val['charged_outlet'],
                    // 'Charged Outlet'             => (float) $val['charged_central']
                ];
            }
        }
    }
}
