<?php

namespace Modules\Report\Http\Controllers;

use App\Http\Models\DealsPaymentMidtran;
use App\Http\Models\User;
use App\Http\Models\Transaction;
use App\Http\Models\TransactionProduct;
use App\Http\Models\Outlet;
use App\Http\Models\Product;
use App\Http\Models\MonthlyReportTrx;
use App\Http\Models\DailyReportTrx;
use App\Http\Models\MonthlyReportTrxMenu;
use App\Http\Models\DailyReportTrxMenu;
use App\Http\Models\DailyMembershipReport;
use App\Http\Models\MonthlyMembershipReport;
use App\Http\Models\TransactionPaymentMidtran;
use App\Http\Models\TransactionPaymentBalance;
use Modules\IPay88\Entities\DealsPaymentIpay88;
use Modules\IPay88\Entities\SubscriptionPaymentIpay88;
use Modules\IPay88\Entities\TransactionPaymentIpay88;
use App\Http\Models\TransactionPaymentOvo;
use App\Http\Models\TransactionPaymentOffline;
use App\Http\Models\GlobalMonthlyReportTrx;
use App\Http\Models\GlobalDailyReportTrx;
use App\Http\Models\GlobalMonthlyReportTrxMenu;
use App\Http\Models\GlobalDailyReportTrxMenu;
use Modules\Report\Entities\DailyReportPayment;
use Modules\Report\Entities\DailyReportPaymentDeals;
use Modules\Report\Entities\DailyReportPaymentSubcription;
use Modules\Report\Entities\GlobalDailyReportPayment;
use Modules\Report\Entities\MonthlyReportPayment;
use Modules\Report\Entities\GlobalMonthlyReportPayment;
use Modules\Report\Entities\DailyReportTrxModifier;
use Modules\Report\Entities\GlobalDailyReportTrxModifier;
use Modules\Report\Entities\MonthlyReportTrxModifier;
use Modules\Report\Entities\GlobalMonthlyReportTrxModifier;
use App\Http\Models\DailyCustomerReportRegistration;
use App\Http\Models\MonthlyCustomerReportRegistration;
use Modules\ShopeePay\Entities\DealsPaymentShopeePay;
use Modules\ShopeePay\Entities\SubscriptionPaymentShopeePay;
use Modules\ShopeePay\Entities\TransactionPaymentShopeePay;
use Modules\Subscription\Entities\TransactionPaymentSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Report\Http\Requests\DetailReport;
use App\Lib\MyHelper;
use Modules\Subscription\Entities\SubscriptionPaymentMidtran;
use Modules\Xendit\Entities\TransactionPaymentXendit;
use Validator;
use DateTime;
use Hash;
use DB;
use Mail;

class ApiCronReport extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->month = '';
        $this->year = '';
    }

    /* CRON */
    public function transactionCron(Request $request)
    {
        $log = MyHelper::logCron('Daily Transaction Report');
        try {
            DB::beginTransaction();
            $dateStart = $this->firstTrx();

            if ($dateStart) {
                // UP TO YESTERDAY
                while (strtotime($dateStart) < strtotime(date('Y-m-d'))) {
                    // CALCULATION
                    $calculation = $this->calculation($dateStart);

                    if (!$calculation) {
                        DB::rollback();
                        $log->fail('Failed update data');
                        return response()->json([
                            'status'   => 'fail',
                            'messages' => 'Failed to update data.'
                        ]);
                    }

                    // INCREMENT
                    $dateStart = date('Y-m-d', strtotime("+1 days", strtotime($dateStart)));
                }
            } else {
                DB::rollback();
                $log->success('Data transaction is empty');
                return response()->json([
                    'status'   => 'fail',
                    'messages' => 'Data transaction is empty.'
                ]);
            }

            DB::commit();
            // RETURN SUCCESS
            $log->success('success');
            return response()->json([
                'status' => 'success'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $log->fail($e->getMessage());
        }
    }

    /* CHECK TABLES */
    public function checkReportTable()
    {
        $table = DailyReportTrx::count();

        if ($table > 1) {
            return true;
        }

        return false;
    }

    /* FIRST TRX */
    public function firstTrx()
    {
        // CEK TABEL REPORT
        if ($this->checkReportTable()) {
            $lastDate = DailyReportTrx::orderBy('trx_date', 'DESC')->first();

            if ($lastDate) {
                return $lastDate->trx_date;
            }
        } else {
            $firstTrx = Transaction::orderBy('transaction_date', 'ASC')->first();

            if (!empty($firstTrx)) {
                return $firstTrx->transaction_date;
            }
        }

        return false;
    }

    /* CALCULATION */
    public function calculation($date)
    {
        // TRANSACTION & PRODUCT DAILY
        $daily = $this->newDailyReport($date);

        if (!$daily) {
            return false;
        }
        if (!is_bool($daily)) {
            $daily = array_column($daily, 'id_outlet');

            // PRODUCT
            if (!$this->dailyReportProduct($daily, $date)) {
                return false;
            }
            // PAYMENT
            if (!$this->dailyReportPayment($date)) {
                return false;
            }
            // MODIFIER
            if (!$this->dailyReportModifier($daily, $date)) {
                return false;
            }
        }

        // TRANSACTION & PRODUCT MONTHLY
        $monthly = $this->newMonthlyReport($date);
        if (!$monthly) {
            return false;
        }
        if (!is_bool($monthly)) {
            $monthly = array_column($monthly, 'id_outlet');
            // PRODUCT
            if (!$this->monthlyReportProduct($monthly, $date)) {
                return false;
            }

            $month = date('m', strtotime($date));
            $year = date('Y', strtotime($date));
            if ($month != $this->month || $year != $this->year) {
                $this->month = $month;
                $this->year = $year;
                if (!$this->monthlyReportPayment($month, $year)) {
                    return false;
                }
            }

            // MODIFIER
            if (!$this->monthlyReportModifier($monthly, $date)) {
                return false;
            }
        }

        // MEMBERSHIP REGISTRATION DAILY
        $daily = $this->newCustomerRegistrationDailyReport($date);
        if (!$daily) {
            return false;
        }

        // MEMBERSHIP REGISTRATION MONTHLY
        $monthly = $this->newCustomerRegistrationMonthlyReport($date);
        if (!$monthly) {
            return false;
        }

        // MEMBERSHIP LEVEL DAILY
        $daily = $this->customerLevelDailyReport($date);
        if (!$daily) {
            return false;
        }

        // MEMBERSHIP LEVEL MONTHLY
        $monthly = $this->customerLevelMonthlyReport($date);
        if (!$monthly) {
            return false;
        }

        return true;
    }

    /* MAX TRX */
    public function maxTrans($date, $id_outlet)
    {
        $dateStart = date('Y-m-d 00:00:00', strtotime($date));
        $dateEnd   = date('Y-m-d 23:59:59', strtotime($date));

        $trans = Transaction::whereBetween('transaction_date', [$dateStart, $dateEnd])
                ->where('transaction_payment_status', 'Completed')
                ->where('id_outlet', $id_outlet)
                ->orderBy('transaction_grandtotal', 'DESC')
                ->first();

        if ($trans) {
            return json_encode($trans);
        }

        return null;
    }

    /* TRX TIME */
    public function trxTime($time1, $time2, $time_type = null)
    {
        $str_time1 = strtotime($time1);
        $str_time2 = strtotime($time2);

        if ($time_type == 'first') {
            if ($str_time1 < $str_time2) {
                $time = $str_time1;
            } else {
                $time = $str_time2;
            }
        } elseif ($time_type == 'last') {
            if ($str_time1 > $str_time2) {
                $time = $str_time1;
            } else {
                $time = $str_time2;
            }
        } else {
            $time = $str_time1;
        }

        return date('H:i:s', $time);
    }

    /* CUSTOMER LEVEL DAILY REPORT */
    public function customerLevelDailyReport($date)
    {
        // $date = date('Y-m-d', strtotime("-7 days", strtotime($date)));

        $now = time(); // or your date as well
        $your_date = strtotime($date);
        $datediff = $now - $your_date;

        $diff = round($datediff / (60 * 60 * 24));

        for ($x = 0; $x <= $diff; $x++) {
            $start = date('Y-m-d', strtotime("+ " . $x . " days", strtotime($date)));

            $trans = DB::select(DB::raw('
					SELECT COUNT(id), id_membership,
					(select COUNT(users.id)) as cust_total,
					(select SUM(Case When users.gender = \'Male\' Then 1 Else 0 End)) as cust_male, 
					(select SUM(Case When users.gender = \'Female\' Then 1 Else 0 End)) as cust_female, 
					(select SUM(Case When users.android_device is not null Then 1 Else 0 End)) as cust_android, 
					(select SUM(Case When users.ios_device is not null Then 1 Else 0 End)) as cust_ios, 
					(select SUM(Case When users.provider = \'Telkomsel\' Then 1 Else 0 End)) as cust_telkomsel, 
					(select SUM(Case When users.provider = \'XL\' Then 1 Else 0 End)) as cust_xl, 
					(select SUM(Case When users.provider = \'Indosat\' Then 1 Else 0 End)) as cust_indosat, 
					(select SUM(Case When users.provider = \'Tri\' Then 1 Else 0 End)) as cust_tri, 
					(select SUM(Case When users.provider = \'Axis\' Then 1 Else 0 End)) as cust_axis, 
					(select SUM(Case When users.provider = \'Smart\' Then 1 Else 0 End)) as cust_smart, 
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 11 && floor(datediff (now(), users.birthday)/365) <= 17 Then 1 Else 0 End)) as cust_teens, 
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 18 && floor(datediff (now(), users.birthday)/365) <= 24 Then 1 Else 0 End)) as cust_young_adult, 
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 25 && floor(datediff (now(), users.birthday)/365) <= 34 Then 1 Else 0 End)) as cust_adult,
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 35 && floor(datediff (now(), users.birthday)/365) <= 100 Then 1 Else 0 End)) as cust_old,
					(select DATE(created_at)) as mem_date
					FROM users 
					WHERE users.created_at BETWEEN "' . $start . ' 00:00:00" AND "' . $start . ' 23:59:59"
					GROUP BY users.id_membership
				'));
            $trans = json_decode(json_encode($trans), true);

            if (!empty($trans[0]['cust_total'])) {
                foreach ($trans as $key => $value) {
                    $save = DailyMembershipReport::updateOrCreate([
                            'mem_date'  => $start,
                            'id_membership' => $value['id_membership']
                        ], $value);

                    if (!$save) {
                        return false;
                    }
                }
                return $trans;
            }
        }
        return true;
    }

    /* CUSTOMER LEVEL MONTHLY REPORT */
    public function customerLevelMonthlyReport($date)
    {
        $start = date('Y-m-1', strtotime("+0  month", strtotime($date)));
        $end = date('Y-m-t', strtotime("+0 month", strtotime($date)));

        $trans = DB::select(DB::raw('
					SELECT COUNT(id), id_membership,
					(select COUNT(users.id)) as cust_total,
					(select SUM(Case When users.gender = \'Male\' Then 1 Else 0 End)) as cust_male, 
					(select SUM(Case When users.gender = \'Female\' Then 1 Else 0 End)) as cust_female, 
					(select SUM(Case When users.android_device is not null Then 1 Else 0 End)) as cust_android, 
					(select SUM(Case When users.ios_device is not null Then 1 Else 0 End)) as cust_ios, 
					(select SUM(Case When users.provider = \'Telkomsel\' Then 1 Else 0 End)) as cust_telkomsel, 
					(select SUM(Case When users.provider = \'XL\' Then 1 Else 0 End)) as cust_xl, 
					(select SUM(Case When users.provider = \'Indosat\' Then 1 Else 0 End)) as cust_indosat, 
					(select SUM(Case When users.provider = \'Tri\' Then 1 Else 0 End)) as cust_tri, 
					(select SUM(Case When users.provider = \'Axis\' Then 1 Else 0 End)) as cust_axis, 
					(select SUM(Case When users.provider = \'Smart\' Then 1 Else 0 End)) as cust_smart, 
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 11 && floor(datediff (now(), users.birthday)/365) <= 17 Then 1 Else 0 End)) as cust_teens, 
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 18 && floor(datediff (now(), users.birthday)/365) <= 24 Then 1 Else 0 End)) as cust_young_adult, 
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 25 && floor(datediff (now(), users.birthday)/365) <= 34 Then 1 Else 0 End)) as cust_adult,
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 35 && floor(datediff (now(), users.birthday)/365) <= 100 Then 1 Else 0 End)) as cust_old,
					(select DATE(created_at)) as mem_date
					FROM users 
					WHERE users.created_at BETWEEN "' . $start . ' 00:00:00" AND "' . $end . ' 23:59:59"
					GROUP BY users.id_membership
				'));
        $trans = json_decode(json_encode($trans), true);

        if (!empty($trans[0]['cust_total'])) {
            foreach ($trans as $key => $value) {
                $value['mem_month'] = date('n', strtotime($end));
                $value['mem_year'] = date('Y', strtotime($end));
                $save = MonthlyMembershipReport::updateOrCreate([
                    'mem_month'  => date('n', strtotime($end)),
                    'mem_year'  => date('Y', strtotime($end)),
                    'id_membership' => $value['id_membership']
                ], $value);

                if (!$save) {
                    return false;
                }
            }
            return $trans;
        }

        return true;
    }

    /* NEW CUSTOMER REGISTRATION DAILY REPORT */
    public function newCustomerRegistrationDailyReport($date)
    {
        $date = date('Y-m-d', strtotime("-7 days", strtotime($date)));

        $now = time(); // or your date as well
        $your_date = strtotime($date);
        $datediff = $now - $your_date;

        $diff = round($datediff / (60 * 60 * 24));

        for ($x = 0; $x <= $diff; $x++) {
            $start = date('Y-m-d', strtotime("+ " . $x . " days", strtotime($date)));

            $trans = DB::select(DB::raw('
					SELECT (select COUNT(users.id)) as total,
					(select SUM(Case When users.gender = \'Male\' Then 1 Else 0 End)) as cust_male, 
					(select SUM(Case When users.gender = \'Female\' Then 1 Else 0 End)) as cust_female, 
					(select SUM(Case When users.android_device is not null Then 1 Else 0 End)) as cust_android, 
					(select SUM(Case When users.ios_device is not null Then 1 Else 0 End)) as cust_ios, 
					(select SUM(Case When users.provider = \'Telkomsel\' Then 1 Else 0 End)) as cust_telkomsel, 
					(select SUM(Case When users.provider = \'XL\' Then 1 Else 0 End)) as cust_xl, 
					(select SUM(Case When users.provider = \'Indosat\' Then 1 Else 0 End)) as cust_indosat, 
					(select SUM(Case When users.provider = \'Tri\' Then 1 Else 0 End)) as cust_tri, 
					(select SUM(Case When users.provider = \'Axis\' Then 1 Else 0 End)) as cust_axis, 
					(select SUM(Case When users.provider = \'Smart\' Then 1 Else 0 End)) as cust_smart, 
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 11 && floor(datediff (now(), users.birthday)/365) <= 17 Then 1 Else 0 End)) as cust_teens, 
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 18 && floor(datediff (now(), users.birthday)/365) <= 24 Then 1 Else 0 End)) as cust_young_adult, 
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 25 && floor(datediff (now(), users.birthday)/365) <= 34 Then 1 Else 0 End)) as cust_adult,
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 35 && floor(datediff (now(), users.birthday)/365) <= 100 Then 1 Else 0 End)) as cust_old,
					(select DATE(created_at)) as reg_date
					FROM users
					WHERE created_at BETWEEN "' . $start . ' 00:00:00" 
					AND "' . $start . ' 23:59:59"
				'));
            $trans = json_decode(json_encode($trans), true);
            if (!empty($trans[0]['reg_date'])) {
                foreach ($trans as $key => $value) {
                    $save = DailyCustomerReportRegistration::updateOrCreate([
                            'reg_date'  => $start
                        ], $value);

                    if (!$save) {
                        return false;
                    }
                }
                return $trans;
            }
        }
        return true;
    }

    /* NEW CUSTOMER REGISTRATION MONTHLY REPORT */
    public function newCustomerRegistrationMonthlyReport($date)
    {
        $start = date('Y-m-1', strtotime("+0  month", strtotime($date)));
        $end = date('Y-m-t', strtotime("+0 month", strtotime($date)));

        $trans = DB::select(DB::raw('
					SELECT (select COUNT(users.id)) as total,
					(select SUM(Case When users.gender = \'Male\' Then 1 Else 0 End)) as cust_male, 
					(select SUM(Case When users.gender = \'Female\' Then 1 Else 0 End)) as cust_female, 
					(select SUM(Case When users.android_device is not null Then 1 Else 0 End)) as cust_android, 
					(select SUM(Case When users.ios_device is not null Then 1 Else 0 End)) as cust_ios, 
					(select SUM(Case When users.provider = \'Telkomsel\' Then 1 Else 0 End)) as cust_telkomsel, 
					(select SUM(Case When users.provider = \'XL\' Then 1 Else 0 End)) as cust_xl, 
					(select SUM(Case When users.provider = \'Indosat\' Then 1 Else 0 End)) as cust_indosat, 
					(select SUM(Case When users.provider = \'Tri\' Then 1 Else 0 End)) as cust_tri, 
					(select SUM(Case When users.provider = \'Axis\' Then 1 Else 0 End)) as cust_axis, 
					(select SUM(Case When users.provider = \'Smart\' Then 1 Else 0 End)) as cust_smart, 
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 11 && floor(datediff (now(), users.birthday)/365) <= 17 Then 1 Else 0 End)) as cust_teens, 
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 18 && floor(datediff (now(), users.birthday)/365) <= 24 Then 1 Else 0 End)) as cust_young_adult, 
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 25 && floor(datediff (now(), users.birthday)/365) <= 34 Then 1 Else 0 End)) as cust_adult,
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 35 && floor(datediff (now(), users.birthday)/365) <= 100 Then 1 Else 0 End)) as cust_old
					FROM users
					WHERE created_at BETWEEN "' . $start . ' 00:00:00" 
					AND "' . $end . ' 23:59:59"
					
				'));
        $trans = json_decode(json_encode($trans), true);

        if (!empty($trans[0]['total'])) {
            foreach ($trans as $key => $value) {
                $value['reg_month'] = date('n', strtotime($end));
                $value['reg_year'] = date('Y', strtotime($end));

                $save = MonthlyCustomerReportRegistration::updateOrCreate([
                    'reg_month'  => date('n', strtotime($end)),
                    'reg_year'  => date('Y', strtotime($end))
                ], $value);

                if (!$save) {
                    return false;
                }
            }
            return $trans;
        }

        return true;
    }

    /* NEW DAILY REPORT */
    public function newDailyReport($date)
    {
        $trans = DB::select(DB::raw('
                    SELECT transactions.id_outlet,
				    (CASE WHEN trasaction_type = \'Offline\' THEN CASE WHEN transactions.id_user IS NOT NULL THEN \'Offline Member\' ELSE \'Offline Non Member\' END ELSE \'Online\' END) AS trx_type,
					(select SUM(Case When users.gender = \'Male\' Then 1 Else 0 End)) as cust_male, 
					(select SUM(Case When users.gender = \'Female\' Then 1 Else 0 End)) as cust_female, 
					(select SUM(Case When users.android_device is not null Then 1 Else 0 End)) as cust_android, 
					(select SUM(Case When users.ios_device is not null Then 1 Else 0 End)) as cust_ios, 
					(select SUM(Case When users.provider = \'Telkomsel\' Then 1 Else 0 End)) as cust_telkomsel, 
					(select SUM(Case When users.provider = \'XL\' Then 1 Else 0 End)) as cust_xl, 
					(select SUM(Case When users.provider = \'Indosat\' Then 1 Else 0 End)) as cust_indosat, 
					(select SUM(Case When users.provider = \'Tri\' Then 1 Else 0 End)) as cust_tri, 
					(select SUM(Case When users.provider = \'Axis\' Then 1 Else 0 End)) as cust_axis, 
					(select SUM(Case When users.provider = \'Smart\' Then 1 Else 0 End)) as cust_smart, 
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 11 && floor(datediff (now(), users.birthday)/365) <= 17 Then 1 Else 0 End)) as cust_teens, 
					(select SUM( Case When floor(datediff (now(), users.birthday)/365) >= 18 && floor(datediff (now(), users.birthday)/365) <= 24 Then 1 Else 0 End)) as cust_young_adult, 
					(select SUM( Case When floor(datediff (now(), users.birthday)/365) >= 25 && floor(datediff (now(), users.birthday)/365) <= 34 Then 1 Else 0 End)) as cust_adult,
					(select SUM( Case When floor(datediff (now(), users.birthday)/365) >= 35 && floor(datediff (now(), users.birthday)/365) <= 100 Then 1 Else 0 End)) as cust_old,
                    (select SUM(transaction_subtotal)) as trx_subtotal, 
                    (select SUM(transaction_tax)) as trx_tax, 
                    (select SUM(transaction_shipment)) as trx_shipment, 
                    (select SUM(transaction_service)) as trx_service, 
                    (select SUM(transaction_discount)) as trx_discount, 
                    (select SUM(transaction_grandtotal)) as trx_grand, 
                    (select SUM(transaction_point_earned)) as trx_point_earned, 
                    (select SUM(transaction_cashback_earned)) as trx_cashback_earned, 
                    (select TIME(MIN(transaction_date))) as first_trx_time, 
                    (select TIME(MAX(transaction_date))) as last_trx_time, 
                    (select count(transactions.id_transaction)) as trx_count, 
                    (select AVG(transaction_grandtotal)) as trx_average, 
                    (select SUM(trans_p.trx_total_item)) as trx_total_item,
                    (select DATE(transaction_date)) as trx_date,
                    (select SUM(transaction_grandtotal)) as trx_net_sale,
                    (select SUM(transactions.transaction_shipment)) as trx_shipment_go_send
                    FROM transactions 
                    LEFT JOIN users ON users.id = transactions.id_user  
                    LEFT JOIN (
                    	select 
	                    	transaction_products.id_transaction, SUM(transaction_products.transaction_product_qty) trx_total_item
	                    	FROM transaction_products 
	                    	GROUP BY transaction_products.id_transaction
	                ) trans_p
                    	ON (transactions.id_transaction = trans_p.id_transaction) 
                    WHERE transaction_date BETWEEN "' . date('Y-m-d', strtotime($date)) . ' 00:00:00" 
                    AND "' . date('Y-m-d', strtotime($date)) . ' 23:59:59"
                    AND transaction_payment_status = "Completed"
                    GROUP BY transactions.id_outlet,trx_type
                '));

        if ($trans) {
            $trans = json_decode(json_encode($trans), true);
            $sum = array();
            $sum['trx_date'] = $date;
            $sum['first_trx_time'] = date('H:i:s', strtotime($date));
            $sum['last_trx_time'] = date('H:i:s', strtotime($date));
            $sum['trx_type'] = $trans[0]['trx_type'];
            $sum['trx_subtotal'] = 0;
            $sum['trx_tax'] = 0;
            $sum['trx_shipment'] = 0;
            $sum['trx_service'] = 0;
            $sum['trx_discount'] = 0;
            $sum['trx_grand'] = 0;
            $sum['trx_point_earned'] = 0;
            $sum['trx_cashback_earned'] = 0;
            $sum['trx_count'] = 0;
            $sum['trx_total_item'] = 0;
            $sum['trx_average'] = 0;
            $sum['trx_net_sale'] = 0;
            $sum['trx_shipment_go_send'] = 0;
            $sum['cust_male'] = 0;
            $sum['cust_female'] = 0;
            $sum['cust_android'] = 0;
            $sum['cust_ios'] = 0;
            $sum['cust_telkomsel'] = 0;
            $sum['cust_xl'] = 0;
            $sum['cust_indosat'] = 0;
            $sum['cust_tri'] = 0;
            $sum['cust_axis'] = 0;
            $sum['cust_smart'] = 0;
            $sum['cust_teens'] = 0;
            $sum['cust_young_adult'] = 0;
            $sum['cust_adult'] = 0;
            $sum['cust_old'] = 0;
            $st_time = date('H:i:s', strtotime($date));
            $ed_time = date('H:i:s', strtotime($date));
            foreach ($trans as $key => $value) {
                $trans[$key]['trx_max'] = $this->maxTrans($value['trx_date'], $value['id_outlet']);
                $sum['first_trx_time'] = $this->trxTime($st_time, $value['first_trx_time'], 'first');
                $st_time = $sum['first_trx_time'];
                $sum['last_trx_time'] = $this->trxTime($ed_time, $value['last_trx_time'], 'last');
                $ed_time = $sum['last_trx_time'];
                $sum['trx_subtotal'] += $value['trx_subtotal'];
                $sum['trx_tax'] += $value['trx_tax'];
                $sum['trx_shipment'] += $value['trx_shipment'];
                $sum['trx_service'] += $value['trx_service'];
                $sum['trx_discount'] += $value['trx_discount'];
                $sum['trx_grand'] += $value['trx_grand'];
                $sum['trx_point_earned'] += $value['trx_point_earned'];
                $sum['trx_cashback_earned'] += $value['trx_cashback_earned'];
                $sum['trx_count'] += $value['trx_count'];
                $sum['trx_total_item'] += $value['trx_total_item'];
                $sum['trx_average'] += $value['trx_average'];
                $sum['cust_male'] += $value['cust_male'];
                $sum['cust_female'] += $value['cust_female'];
                $sum['cust_android'] += $value['cust_android'];
                $sum['cust_ios'] += $value['cust_ios'];
                $sum['cust_telkomsel'] += $value['cust_telkomsel'];
                $sum['cust_xl'] += $value['cust_xl'];
                $sum['cust_indosat'] += $value['cust_indosat'];
                $sum['cust_tri'] += $value['cust_tri'];
                $sum['cust_axis'] += $value['cust_axis'];
                $sum['cust_smart'] += $value['cust_smart'];
                $sum['cust_teens'] += $value['cust_teens'];
                $sum['cust_young_adult'] += $value['cust_young_adult'];
                $sum['cust_adult'] += $value['cust_adult'];
                $sum['cust_old'] += $value['cust_old'];
                $sum['trx_net_sale'] += $value['trx_net_sale'];
                $gosend = 0;
                if (!empty($value['trx_shipment_go_send'])) {
                    $gosend = $value['trx_shipment_go_send'];
                }
                $sum['trx_shipment_go_send'] += $gosend;

                $save = DailyReportTrx::updateOrCreate([
                    'trx_date'  => date('Y-m-d', strtotime($value['trx_date'])),
                    'id_outlet' => $value['id_outlet']
                ], $trans[$key]);

                if (!$save) {
                    return false;
                }
            }

            $saveGlobal = GlobalDailyReportTrx::updateOrCreate([
                    'trx_date'  => date('Y-m-d', strtotime($date))
                ], $sum);

            return $trans;
        }

        return true;
    }

    public function generate(Request $request, $method)
    {
        $id_outlets = $request->id_outlets;
        if ($request->id_outlets && !is_array($request->id_outlets)) {
            return [
                'status' => 'fail',
                'messages' => 'id_outlets should be array of integer (id_outlet)'
            ];
        } elseif ($request->id_outlets) {
            $id_outlets = $request->id_outlets;
            if ($request->clear_old_data) {
                DailyReportTrxMenu::where('trx_date', date('Y-m-d', strtotime($request->trx_date)))
                    ->whereIn('id_outlet', $request->id_outlets)
                    ->delete();
            }
        } else {
            $id_outlets = Outlet::pluck('id_outlet');
        }

        if (method_exists($this, $method)) {
            if ($method == 'dailyReportProduct' && $request->clear_old_data) {
                DailyReportTrxMenu::whereBetween('trx_date', [date('Y-m-d 00:00:00', strtotime($request->trx_date_start)),date('Y-m-d 23:59:59', strtotime($request->trx_date_end))])
                    ->whereIn('id_outlet', $id_outlets)
                    ->delete();
            }
            $result = $this->$method($id_outlets, [$request->trx_date_start, $request->trx_date_end]);
            return [
                'status' => $result ? 'success' : 'fail',
                'result' => $result
            ];
        }
        return [
            'status' => 'fail',
            'messages' => ['Unknown method']
        ];
    }

    /* REPORT PRODUCT */
    public function dailyReportProduct($outletAll, $date)
    {
        foreach ($outletAll as $outlet) {
             $product = DB::select(DB::raw('
                        SELECT transaction_products.id_product, transaction_products.id_product_variant_group, products.id_product_category, transaction_products.type, transaction_products.id_brand, transactions.id_outlet, 
                        (select SUM(transaction_products.transaction_product_qty)) as total_qty, 
                        (select SUM(transaction_products.transaction_product_subtotal)) as total_nominal, 
                        (select SUM(transaction_products.transaction_product_discount)) as total_product_discount, 
                        (select count(transaction_products.id_product)) as total_rec, 
                        (select DATE(transactions.transaction_date)) as trx_date,
						(select SUM(Case When users.gender = \'Male\' Then 1 Else 0 End)) as cust_male, 
						(select SUM(Case When users.gender = \'Female\' Then 1 Else 0 End)) as cust_female, 
						(select SUM(Case When users.android_device is not null Then 1 Else 0 End)) as cust_android, 
						(select SUM(Case When users.ios_device is not null Then 1 Else 0 End)) as cust_ios, 
						(select SUM(Case When users.provider = \'Telkomsel\' Then 1 Else 0 End)) as cust_telkomsel, 
						(select SUM(Case When users.provider = \'XL\' Then 1 Else 0 End)) as cust_xl, 
						(select SUM(Case When users.provider = \'Indosat\' Then 1 Else 0 End)) as cust_indosat, 
						(select SUM(Case When users.provider = \'Tri\' Then 1 Else 0 End)) as cust_tri, 
						(select SUM(Case When users.provider = \'Axis\' Then 1 Else 0 End)) as cust_axis, 
						(select SUM(Case When users.provider = \'Smart\' Then 1 Else 0 End)) as cust_smart, 
						(select products.product_name) as product_name,
						(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 11 && floor(datediff (now(), users.birthday)/365) <= 17 Then 1 Else 0 End)) as cust_teens, 
						(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 18 && floor(datediff (now(), users.birthday)/365) <= 24 Then 1 Else 0 End)) as cust_young_adult, 
						(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 25 && floor(datediff (now(), users.birthday)/365) <= 34 Then 1 Else 0 End)) as cust_adult,
						(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 35 && floor(datediff (now(), users.birthday)/365) <= 100 Then 1 Else 0 End)) as cust_old
                        FROM transaction_products 
                        INNER JOIN transactions ON transaction_products.id_transaction = transactions.id_transaction 
						LEFT JOIN users ON users.id = transactions.id_user
						LEFT JOIN products ON transaction_products.id_product = products.id_product
						WHERE transactions.transaction_date BETWEEN "' . date('Y-m-d', strtotime(is_array($date) ? $date[0] : $date)) . ' 00:00:00" 
                        AND "' . date('Y-m-d', strtotime(is_array($date) ? $date[1] : $date)) . ' 23:59:59"
                        AND transactions.id_outlet = "' . $outlet . '"
                        AND transaction_payment_status = "Completed"
                        GROUP BY trx_date, transaction_products.id_product, products.id_product_category, transaction_products.id_product_variant_group, transaction_products.id_brand
                        ORDER BY trx_date asc, transaction_products.id_product ASC
                    '));

            if (!empty($product)) {
                $product = json_decode(json_encode($product), true);
                foreach ($product as $key => $value) {
                    // $sum = array();
                    $sum[$value['id_product']]['trx_date'] = $value['trx_date'];
                    $sum[$value['id_product']]['id_product'] = $value['id_product'];
                    $sum[$value['id_product']]['id_product_variant_group'] = $value['id_product_variant_group'];
                    $sum[$value['id_product']]['id_product_category'] = $value['id_product_category'];
                    $sum[$value['id_product']]['type'] = $value['type'];
                    $sum[$value['id_product']]['product_name'] = $value['product_name'];
                    $sum[$value['id_product']]['total_qty'] = ($sum[$value['id_product']]['total_qty'] ?? 0) + $value['total_qty'];
                    $sum[$value['id_product']]['total_nominal'] = ($sum[$value['id_product']]['total_nominal'] ?? 0) + $value['total_nominal'];
                    $sum[$value['id_product']]['total_product_discount'] = ($sum[$value['id_product']]['total_product_discount'] ?? 0) + $value['total_product_discount'];
                    $sum[$value['id_product']]['total_rec'] = ($sum[$value['id_product']]['total_rec'] ?? 0) + $value['total_rec'];
                    $sum[$value['id_product']]['cust_male'] = $value['cust_male'];
                    $sum[$value['id_product']]['cust_female'] = $value['cust_female'];
                    $sum[$value['id_product']]['cust_android'] = $value['cust_android'];
                    $sum[$value['id_product']]['cust_ios'] = $value['cust_ios'];
                    $sum[$value['id_product']]['cust_telkomsel'] = $value['cust_telkomsel'];
                    $sum[$value['id_product']]['cust_xl'] = $value['cust_xl'];
                    $sum[$value['id_product']]['cust_indosat'] = $value['cust_indosat'];
                    $sum[$value['id_product']]['cust_tri'] = $value['cust_tri'];
                    $sum[$value['id_product']]['cust_axis'] = $value['cust_axis'];
                    $sum[$value['id_product']]['cust_smart'] = $value['cust_smart'];
                    $sum[$value['id_product']]['cust_teens'] = $value['cust_teens'];
                    $sum[$value['id_product']]['cust_young_adult'] = $value['cust_young_adult'];
                    $sum[$value['id_product']]['cust_adult'] = $value['cust_adult'];
                    $sum[$value['id_product']]['cust_old'] = $value['cust_old'];

                    $save = DailyReportTrxMenu::updateOrCreate([
                        'trx_date'                  => date('Y-m-d', strtotime($value['trx_date'])),
                        'id_product'                => $value['id_product'],
                        'id_product_variant_group'  => $value['id_product_variant_group'],
                        'id_outlet'                 => $value['id_outlet'],
                        'id_brand'                  => $value['id_brand']
                    ], $value);

                    $saveGlobal = GlobalDailyReportTrxMenu::updateOrCreate([
                        'trx_date'   => date('Y-m-d', strtotime($value['trx_date'])),
                        'id_product' => $value['id_product']
                    ], $sum[$value['id_product']]);

                    if (!$save) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public function dailyReportPayment($date)
    {
        $date = date('Y-m-d', strtotime($date));

        //delete report if there is already a report for the date
        $delete = DailyReportPayment::where('trx_date', $date)->delete();
        $delete = GlobalDailyReportPayment::where('trx_date', $date)->delete();

        //midtrans
            $dataPayment = TransactionPaymentMidtran::join('transaction_groups', 'transaction_groups.id_transaction_group', 'transaction_payment_midtrans.id_transaction_group')
            ->join('transactions', 'transactions.id_transaction_group', 'transaction_groups.id_transaction_group')
            ->leftJoin('transaction_payment_balances', 'transactions.id_transaction', 'transaction_payment_balances.id_transaction')
            ->select(
                'transactions.id_outlet',
                DB::raw('DATE(transactions.transaction_date) as trx_date'),
                DB::raw('0 as refund_with_point'),
                DB::raw('"Midtrans" as payment_type'),
                DB::raw('COUNT(transactions.id_transaction) as trx_payment_count'),
                DB::raw('SUM(transactions.transaction_grandtotal-ifnull(transaction_payment_balances.balance_nominal, 0)) as trx_payment_nominal'),
                DB::raw("CONCAT_WS(' ', transaction_payment_midtrans.payment_type, transaction_payment_midtrans.bank) AS trx_payment"),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as created_at'),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as updated_at')
            )
            ->whereDate('transactions.transaction_date', $date)
            ->where('transactions.transaction_payment_status', 'Completed')
            ->where('transactions.transaction_grandtotal', '>', 0)
            ->groupBy('transactions.id_outlet', 'trx_payment')
            ->get()->toArray();

        if ($dataPayment) {
            //insert daily
            $insertDaily = DailyReportPayment::insert($dataPayment);
        }

            $dataPaymentGlobal = TransactionPaymentMidtran::join('transaction_groups', 'transaction_groups.id_transaction_group', 'transaction_payment_midtrans.id_transaction_group')
                ->join('transactions', 'transactions.id_transaction_group', 'transaction_groups.id_transaction_group')
                ->leftJoin('transaction_payment_balances', 'transactions.id_transaction', 'transaction_payment_balances.id_transaction')
            ->select(
                DB::raw('DATE(transactions.transaction_date) as trx_date'),
                DB::raw('COUNT(transactions.id_transaction) as trx_payment_count'),
                DB::raw('SUM(transactions.transaction_grandtotal-ifnull(transaction_payment_balances.balance_nominal, 0)) as trx_payment_nominal'),
                DB::raw("CONCAT_WS(' ', transaction_payment_midtrans.payment_type, transaction_payment_midtrans.bank) AS trx_payment"),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as created_at'),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as updated_at')
            )
            ->whereDate('transactions.transaction_date', $date)
            ->where('transactions.transaction_payment_status', 'Completed')
            ->where('transactions.transaction_grandtotal', '>', 0)
            ->groupBy('trx_payment')
            ->get()->toArray();

        if ($dataPaymentGlobal) {
            //insert global
            $insertGlobal = GlobalDailyReportPayment::insert($dataPaymentGlobal);
        }
        //end midtrans

        //Xendit
        $dataPayment = TransactionPaymentXendit::join('transaction_groups', 'transaction_groups.id_transaction_group', 'transaction_payment_xendits.id_transaction_group')
            ->join('transactions', 'transactions.id_transaction_group', 'transaction_groups.id_transaction_group')
            ->leftJoin('transaction_payment_balances', 'transactions.id_transaction', 'transaction_payment_balances.id_transaction')
            ->select(
                'transactions.id_outlet',
                DB::raw('DATE(transactions.transaction_date) as trx_date'),
                DB::raw('0 as refund_with_point'),
                DB::raw('"Xendit" as payment_type'),
                DB::raw('COUNT(transactions.id_transaction) as trx_payment_count'),
                DB::raw('SUM(transactions.transaction_grandtotal-ifnull(transaction_payment_balances.balance_nominal, 0)) as trx_payment_nominal'),
                DB::raw("transaction_payment_xendits.type AS trx_payment"),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as created_at'),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as updated_at')
            )
            ->whereDate('transactions.transaction_date', $date)
            ->where('transactions.transaction_payment_status', 'Completed')
            ->where('transactions.transaction_grandtotal', '>', 0)
            ->groupBy('transactions.id_outlet', 'trx_payment')
            ->get()->toArray();

        if ($dataPayment) {
            //insert daily
            $insertDaily = DailyReportPayment::insert($dataPayment);
        }

        $dataPaymentGlobal = TransactionPaymentXendit::join('transaction_groups', 'transaction_groups.id_transaction_group', 'transaction_payment_xendits.id_transaction_group')
            ->join('transactions', 'transactions.id_transaction_group', 'transaction_groups.id_transaction_group')
            ->leftJoin('transaction_payment_balances', 'transactions.id_transaction', 'transaction_payment_balances.id_transaction')
            ->select(
                DB::raw('DATE(transactions.transaction_date) as trx_date'),
                DB::raw('COUNT(transactions.id_transaction) as trx_payment_count'),
                DB::raw('SUM(transactions.transaction_grandtotal-ifnull(transaction_payment_balances.balance_nominal, 0)) as trx_payment_nominal'),
                DB::raw("transaction_payment_xendits.type AS trx_payment"),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as created_at'),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as updated_at')
            )
            ->whereDate('transactions.transaction_date', $date)
            ->where('transactions.transaction_payment_status', 'Completed')
            ->where('transactions.transaction_grandtotal', '>', 0)
            ->groupBy('trx_payment')
            ->get()->toArray();

        if ($dataPaymentGlobal) {
            //insert global
            $insertGlobal = GlobalDailyReportPayment::insert($dataPaymentGlobal);
        }
        //end Xendit

        //balance
            $dataPayment = TransactionPaymentBalance::join('transactions', 'transactions.id_transaction', 'transaction_payment_balances.id_transaction')
            ->select(
                'transactions.id_outlet',
                DB::raw('DATE(transactions.transaction_date) as trx_date'),
                DB::raw('COUNT(transactions.id_transaction) as trx_payment_count'),
                DB::raw('SUM(transaction_payment_balances.balance_nominal) as trx_payment_nominal'),
                DB::raw("'Point' AS payment_type"),
                DB::raw("'Point' AS trx_payment"),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as created_at'),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as updated_at')
            )
            ->whereDate('transactions.transaction_date', $date)
            ->where('transactions.transaction_payment_status', 'Completed')
            ->where('transactions.transaction_grandtotal', '>', 0)
            ->groupBy('transactions.id_outlet', 'trx_payment')
            ->get()->toArray();

        if ($dataPayment) {
            //insert daily
            $insertDaily = DailyReportPayment::insert($dataPayment);
        }

            $dataPaymentGlobal = TransactionPaymentBalance::join('transactions', 'transactions.id_transaction', 'transaction_payment_balances.id_transaction')
            ->select(
                DB::raw('DATE(transactions.transaction_date) as trx_date'),
                DB::raw('COUNT(transactions.id_transaction) as trx_payment_count'),
                DB::raw('SUM(transaction_payment_balances.balance_nominal) as trx_payment_nominal'),
                DB::raw("'Point' AS trx_payment"),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as created_at'),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as updated_at')
            )
            ->whereDate('transactions.transaction_date', $date)
            ->where('transactions.transaction_payment_status', 'Completed')
            ->where('transactions.transaction_grandtotal', '>', 0)
            ->groupBy('trx_payment')
            ->get()->toArray();

        if ($dataPaymentGlobal) {
            //insert global
            $insertGlobal = GlobalDailyReportPayment::insert($dataPaymentGlobal);
        }
        //end balance

        return true;
    }

    /* REPORT MODIFIER */
    public function dailyReportModifier($outletAll, $date)
    {
        foreach ($outletAll as $outlet) {
             $modifier = DB::select(DB::raw('
                        SELECT transaction_product_modifiers.id_product_modifier, transaction_products.id_brand, transactions.id_outlet, 
                        (select SUM(transaction_product_modifiers.qty * transaction_products.transaction_product_qty)) as total_qty, 
                        (select SUM(transaction_product_modifiers.transaction_product_modifier_price)) as total_nominal, 
                        (select count(transaction_product_modifiers.id_product_modifier)) as total_rec, 
                        (select DATE(transactions.transaction_date)) as trx_date,
						(select SUM(Case When users.gender = \'Male\' Then 1 Else 0 End)) as cust_male, 
						(select SUM(Case When users.gender = \'Female\' Then 1 Else 0 End)) as cust_female, 
						(select SUM(Case When users.android_device is not null Then 1 Else 0 End)) as cust_android, 
						(select SUM(Case When users.ios_device is not null Then 1 Else 0 End)) as cust_ios, 
						(select SUM(Case When users.provider = \'Telkomsel\' Then 1 Else 0 End)) as cust_telkomsel, 
						(select SUM(Case When users.provider = \'XL\' Then 1 Else 0 End)) as cust_xl, 
						(select SUM(Case When users.provider = \'Indosat\' Then 1 Else 0 End)) as cust_indosat, 
						(select SUM(Case When users.provider = \'Tri\' Then 1 Else 0 End)) as cust_tri, 
						(select SUM(Case When users.provider = \'Axis\' Then 1 Else 0 End)) as cust_axis, 
						(select SUM(Case When users.provider = \'Smart\' Then 1 Else 0 End)) as cust_smart, 
						(select product_modifiers.text) as text,
						(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 11 && floor(datediff (now(), users.birthday)/365) <= 17 Then 1 Else 0 End)) as cust_teens, 
						(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 18 && floor(datediff (now(), users.birthday)/365) <= 24 Then 1 Else 0 End)) as cust_young_adult, 
						(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 25 && floor(datediff (now(), users.birthday)/365) <= 34 Then 1 Else 0 End)) as cust_adult,
						(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 35 && floor(datediff (now(), users.birthday)/365) <= 100 Then 1 Else 0 End)) as cust_old
                        FROM transaction_product_modifiers 
                        INNER JOIN transactions ON transaction_product_modifiers.id_transaction = transactions.id_transaction 
                        INNER JOIN transaction_products ON transaction_product_modifiers.id_transaction_product = transaction_products.id_transaction_product
						LEFT JOIN users ON users.id = transactions.id_user
						LEFT JOIN transaction_pickups ON transaction_pickups.id_transaction = transactions.id_transaction
						LEFT JOIN product_modifiers ON product_modifiers.id_product_modifier = transaction_product_modifiers.id_product_modifier
						WHERE transactions.transaction_date BETWEEN "' . date('Y-m-d', strtotime($date)) . ' 00:00:00" 
                        AND "' . date('Y-m-d', strtotime($date)) . ' 23:59:59"
                        AND transactions.id_outlet = "' . $outlet . '"
                        AND transaction_payment_status = "Completed"
                        AND transaction_pickups.reject_at IS NULL
                        AND transaction_product_modifiers.id_product_modifier_group IS NULL
                        GROUP BY transaction_product_modifiers.id_product_modifier,transaction_products.id_brand
                        ORDER BY transaction_product_modifiers.id_product_modifier ASC
                    '));

            if (!empty($modifier)) {
                $modifier = json_decode(json_encode($modifier), true);
                foreach ($modifier as $key => $value) {
                    // $sum = array();
                    $sum[$value['id_product_modifier']]['trx_date'] = $date;
                    $sum[$value['id_product_modifier']]['id_product_modifier'] = $value['id_product_modifier'];
                    $sum[$value['id_product_modifier']]['text'] = $value['text'];
                    $sum[$value['id_product_modifier']]['total_qty'] = ($sum[$value['id_product_modifier']]['total_qty'] ?? 0) + $value['total_qty'];
                    $sum[$value['id_product_modifier']]['total_nominal'] = ($sum[$value['id_product_modifier']]['total_nominal'] ?? 0) + $value['total_nominal'];
                    $sum[$value['id_product_modifier']]['total_rec'] = ($sum[$value['id_product_modifier']]['total_rec'] ?? 0) + $value['total_rec'];
                    $sum[$value['id_product_modifier']]['cust_male'] = $value['cust_male'];
                    $sum[$value['id_product_modifier']]['cust_female'] = $value['cust_female'];
                    $sum[$value['id_product_modifier']]['cust_android'] = $value['cust_android'];
                    $sum[$value['id_product_modifier']]['cust_ios'] = $value['cust_ios'];
                    $sum[$value['id_product_modifier']]['cust_telkomsel'] = $value['cust_telkomsel'];
                    $sum[$value['id_product_modifier']]['cust_xl'] = $value['cust_xl'];
                    $sum[$value['id_product_modifier']]['cust_indosat'] = $value['cust_indosat'];
                    $sum[$value['id_product_modifier']]['cust_tri'] = $value['cust_tri'];
                    $sum[$value['id_product_modifier']]['cust_axis'] = $value['cust_axis'];
                    $sum[$value['id_product_modifier']]['cust_smart'] = $value['cust_smart'];
                    $sum[$value['id_product_modifier']]['cust_teens'] = $value['cust_teens'];
                    $sum[$value['id_product_modifier']]['cust_young_adult'] = $value['cust_young_adult'];
                    $sum[$value['id_product_modifier']]['cust_adult'] = $value['cust_adult'];
                    $sum[$value['id_product_modifier']]['cust_old'] = $value['cust_old'];

                    $save = DailyReportTrxModifier::updateOrCreate([
                        'trx_date'   => date('Y-m-d', strtotime($value['trx_date'])),
                        'id_product_modifier' => $value['id_product_modifier'],
                        'id_outlet'  => $value['id_outlet'],
                        'id_brand'   => $value['id_brand']
                    ], $value);

                    $saveGlobal = GlobalDailyReportTrxModifier::updateOrCreate([
                        'trx_date'   => date('Y-m-d', strtotime($value['trx_date'])),
                        'id_product_modifier' => $value['id_product_modifier']
                    ], $sum[$value['id_product_modifier']]);

                    if (!$save) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /* NEW MONTHLY REPORT */
    public function newMonthlyReport($date)
    {
        $start = date('Y-m-1', strtotime("+0  month", strtotime($date)));
        $end = date('Y-m-t', strtotime("+0 month", strtotime($date)));

        $trans = DB::select(DB::raw('
						SELECT transactions.id_outlet, 
						(select SUM(Case When users.gender = \'Male\' Then 1 Else 0 End)) as cust_male, 
						(select SUM(Case When users.gender = \'Female\' Then 1 Else 0 End)) as cust_female, 
						(select SUM(Case When users.android_device is not null Then 1 Else 0 End)) as cust_android, 
						(select SUM(Case When users.ios_device is not null Then 1 Else 0 End)) as cust_ios, 
						(select SUM(Case When users.provider = \'Telkomsel\' Then 1 Else 0 End)) as cust_telkomsel, 
						(select SUM(Case When users.provider = \'XL\' Then 1 Else 0 End)) as cust_xl, 
						(select SUM(Case When users.provider = \'Indosat\' Then 1 Else 0 End)) as cust_indosat, 
						(select SUM(Case When users.provider = \'Tri\' Then 1 Else 0 End)) as cust_tri, 
						(select SUM(Case When users.provider = \'Axis\' Then 1 Else 0 End)) as cust_axis, 
						(select SUM(Case When users.provider = \'Smart\' Then 1 Else 0 End)) as cust_smart, 
						(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 11 && floor(datediff (now(), users.birthday)/365) <= 17 Then 1 Else 0 End)) as cust_teens, 
						(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 18 && floor(datediff (now(), users.birthday)/365) <= 24 Then 1 Else 0 End)) as cust_young_adult, 
						(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 25 && floor(datediff (now(), users.birthday)/365) <= 34 Then 1 Else 0 End)) as cust_adult,
						(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 35 && floor(datediff (now(), users.birthday)/365) <= 100 Then 1 Else 0 End)) as cust_old,
						(select SUM(transaction_subtotal)) as trx_subtotal, 
						(select SUM(transaction_tax)) as trx_tax, 
						(select SUM(transaction_shipment)) as trx_shipment, 
						(select SUM(transaction_service)) as trx_service, 
						(select SUM(transaction_discount)) as trx_discount, 
						(select SUM(transaction_grandtotal)) as trx_grand, 
						(select SUM(transaction_point_earned)) as trx_point_earned, 
						(select SUM(transaction_cashback_earned)) as trx_cashback_earned, 
						(select count(transactions.id_transaction)) as trx_count, 
						(select AVG(transaction_grandtotal)) as trx_average,
						(select SUM(trans_p.trx_total_item)) as trx_total_item
						FROM transactions 
						LEFT JOIN users ON users.id = transactions.id_user
						LEFT JOIN (
		                	select 
		                    	transaction_products.id_transaction, SUM(transaction_products.transaction_product_qty) trx_total_item
		                    	FROM transaction_products 
		                    	GROUP BY transaction_products.id_transaction
		                ) trans_p
		                	ON (transactions.id_transaction = trans_p.id_transaction) 
						WHERE transaction_date BETWEEN "' . $start . ' 00:00:00" 
						AND "' . $end . ' 23:59:59"
						AND transaction_payment_status = "Completed"
						GROUP BY transactions.id_outlet
					'));

        // print_r($trans);exit;
        if ($trans) {
            $trans = json_decode(json_encode($trans), true);
            $sum = array();
            $sum['trx_month'] = date('n', strtotime($end));
            $sum['trx_year'] = date('Y', strtotime($end));
            $sum['trx_subtotal'] = 0;
            $sum['trx_tax'] = 0;
            $sum['trx_shipment'] = 0;
            $sum['trx_service'] = 0;
            $sum['trx_discount'] = 0;
            $sum['trx_grand'] = 0;
            $sum['trx_point_earned'] = 0;
            $sum['trx_cashback_earned'] = 0;
            $sum['trx_count'] = 0;
            $sum['trx_total_item'] = 0;
            $sum['trx_average'] = 0;
            $sum['cust_male'] = 0;
            $sum['cust_female'] = 0;
            $sum['cust_android'] = 0;
            $sum['cust_ios'] = 0;
            $sum['cust_telkomsel'] = 0;
            $sum['cust_xl'] = 0;
            $sum['cust_indosat'] = 0;
            $sum['cust_tri'] = 0;
            $sum['cust_axis'] = 0;
            $sum['cust_smart'] = 0;
            $sum['cust_teens'] = 0;
            $sum['cust_young_adult'] = 0;
            $sum['cust_adult'] = 0;
            $sum['cust_old'] = 0;

            foreach ($trans as $key => $value) {
                $value['trx_month'] = date('n', strtotime($end));
                $value['trx_year'] = date('Y', strtotime($end));
                $save = MonthlyReportTrx::updateOrCreate([
                    'trx_month' => $value['trx_month'],
                    'trx_year' => $value['trx_year'],
                    'id_outlet' => $value['id_outlet']
                ], $value);

                if (!$save) {
                    return false;
                }

                $sum['trx_subtotal'] += $value['trx_subtotal'];
                $sum['trx_tax'] += $value['trx_tax'];
                $sum['trx_shipment'] += $value['trx_shipment'];
                $sum['trx_service'] += $value['trx_service'];
                $sum['trx_discount'] += $value['trx_discount'];
                $sum['trx_grand'] += $value['trx_grand'];
                $sum['trx_point_earned'] += $value['trx_point_earned'];
                $sum['trx_cashback_earned'] += $value['trx_cashback_earned'];
                $sum['trx_count'] += $value['trx_count'];
                $sum['trx_total_item'] += $value['trx_total_item'];
                $sum['trx_average'] += $value['trx_average'];
                $sum['cust_male'] += $value['cust_male'];
                $sum['cust_female'] += $value['cust_female'];
                $sum['cust_android'] += $value['cust_android'];
                $sum['cust_ios'] += $value['cust_ios'];
                $sum['cust_telkomsel'] += $value['cust_telkomsel'];
                $sum['cust_xl'] += $value['cust_xl'];
                $sum['cust_indosat'] += $value['cust_indosat'];
                $sum['cust_tri'] += $value['cust_tri'];
                $sum['cust_axis'] += $value['cust_axis'];
                $sum['cust_smart'] += $value['cust_smart'];
                $sum['cust_teens'] += $value['cust_teens'];
                $sum['cust_young_adult'] += $value['cust_young_adult'];
                $sum['cust_adult'] += $value['cust_adult'];
                $sum['cust_old'] += $value['cust_old'];
            }
            $saveGlobal = GlobalMonthlyReportTrx::updateOrCreate([
                'trx_month'  => date('n', strtotime($end)),
                'trx_year'  => date('Y', strtotime($end))
            ], $sum);

            return $trans;
        }

        return true;
    }

    /* REPORT PRODUCT */
    public function monthlyReportProduct($outletAll, $date)
    {
        $month = date('n', strtotime($date));
        $year = date('Y', strtotime($date));

        $sum = [];
        foreach ($outletAll as $outlet) {
            $product = DB::select(DB::raw('
                        SELECT transaction_products.id_product, transaction_products.type, transaction_products.id_brand, transactions.id_outlet, 
                        (select SUM(transaction_products.transaction_product_qty)) as total_qty, 
                        (select SUM(transaction_products.transaction_product_subtotal)) as total_nominal, 
                        (select SUM(transaction_products.transaction_product_discount)) as total_product_discount, 
                        (select count(transaction_products.id_product)) as total_rec, 
                        (select MONTH(transaction_date)) as trx_month,
						(select YEAR(transaction_date)) as trx_year,
						(select SUM(Case When users.gender = \'Male\' Then 1 Else 0 End)) as cust_male, 
						(select SUM(Case When users.gender = \'Female\' Then 1 Else 0 End)) as cust_female, 
						(select SUM(Case When users.android_device is not null Then 1 Else 0 End)) as cust_android, 
						(select SUM(Case When users.ios_device is not null Then 1 Else 0 End)) as cust_ios, 
						(select SUM(Case When users.provider = \'Telkomsel\' Then 1 Else 0 End)) as cust_telkomsel, 
						(select SUM(Case When users.provider = \'XL\' Then 1 Else 0 End)) as cust_xl, 
						(select SUM(Case When users.provider = \'Indosat\' Then 1 Else 0 End)) as cust_indosat, 
						(select SUM(Case When users.provider = \'Tri\' Then 1 Else 0 End)) as cust_tri, 
						(select SUM(Case When users.provider = \'Axis\' Then 1 Else 0 End)) as cust_axis, 
						(select SUM(Case When users.provider = \'Smart\' Then 1 Else 0 End)) as cust_smart, 
						(select products.product_name) as product_name,
						(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 11 && floor(datediff (now(), users.birthday)/365) <= 17 Then 1 Else 0 End)) as cust_teens, 
						(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 18 && floor(datediff (now(), users.birthday)/365) <= 24 Then 1 Else 0 End)) as cust_young_adult, 
						(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 25 && floor(datediff (now(), users.birthday)/365) <= 34 Then 1 Else 0 End)) as cust_adult,
						(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 35 && floor(datediff (now(), users.birthday)/365) <= 100 Then 1 Else 0 End)) as cust_old
                        FROM transaction_products 
                        INNER JOIN transactions ON transaction_products.id_transaction = transactions.id_transaction 
						LEFT JOIN users ON users.id = transactions.id_user
						LEFT JOIN products ON transaction_products.id_product = products.id_product
                        WHERE MONTH(transactions.transaction_date) = "' . $month . '" 
                    	AND YEAR(transactions.transaction_date) ="' . $year . '"
                        AND transactions.id_outlet = "' . $outlet . '"
                        AND transaction_payment_status = "Completed"
                        GROUP BY id_product,id_brand
                        ORDER BY id_product ASC
                    '));

            if (!empty($product)) {
                $product = json_decode(json_encode($product), true);
                foreach ($product as $key => $value) {
                    $save = MonthlyReportTrxMenu::updateOrCreate([
                        'trx_month'  => $value['trx_month'],
                        'trx_year'   => $value['trx_year'],
                        'id_product' => $value['id_product'],
                        'id_outlet'  => $value['id_outlet'],
                        'id_brand'  => $value['id_brand']
                    ], $value);


                    if (!$save) {
                        return false;
                    }
                }
            }
        }

        // update global trx menu
        $product = DB::select(DB::raw('
                    SELECT transaction_products.id_product, transaction_products.type, 
                    (select SUM(transaction_products.transaction_product_qty)) as total_qty, 
                    (select SUM(transaction_products.transaction_product_subtotal)) as total_nominal, 
                    (select SUM(transaction_products.transaction_product_discount)) as total_product_discount, 
                    (select count(transaction_products.id_product)) as total_rec, 
                    (select MONTH(transaction_date)) as trx_month,
					(select YEAR(transaction_date)) as trx_year,
					(select SUM(Case When users.gender = \'Male\' Then 1 Else 0 End)) as cust_male, 
					(select SUM(Case When users.gender = \'Female\' Then 1 Else 0 End)) as cust_female, 
					(select SUM(Case When users.android_device is not null Then 1 Else 0 End)) as cust_android, 
					(select SUM(Case When users.ios_device is not null Then 1 Else 0 End)) as cust_ios, 
					(select SUM(Case When users.provider = \'Telkomsel\' Then 1 Else 0 End)) as cust_telkomsel, 
					(select SUM(Case When users.provider = \'XL\' Then 1 Else 0 End)) as cust_xl, 
					(select SUM(Case When users.provider = \'Indosat\' Then 1 Else 0 End)) as cust_indosat, 
					(select SUM(Case When users.provider = \'Tri\' Then 1 Else 0 End)) as cust_tri, 
					(select SUM(Case When users.provider = \'Axis\' Then 1 Else 0 End)) as cust_axis, 
					(select SUM(Case When users.provider = \'Smart\' Then 1 Else 0 End)) as cust_smart, 
					(select products.product_name) as product_name,
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 11 && floor(datediff (now(), users.birthday)/365) <= 17 Then 1 Else 0 End)) as cust_teens, 
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 18 && floor(datediff (now(), users.birthday)/365) <= 24 Then 1 Else 0 End)) as cust_young_adult, 
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 25 && floor(datediff (now(), users.birthday)/365) <= 34 Then 1 Else 0 End)) as cust_adult,
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 35 && floor(datediff (now(), users.birthday)/365) <= 100 Then 1 Else 0 End)) as cust_old
                    FROM transaction_products 
                    INNER JOIN transactions ON transaction_products.id_transaction = transactions.id_transaction 
					LEFT JOIN users ON users.id = transactions.id_user
					LEFT JOIN products ON transaction_products.id_product = products.id_product
                    WHERE MONTH(transactions.transaction_date) = "' . $month . '" 
                    AND YEAR(transactions.transaction_date) ="' . $year . '"
                    AND transaction_payment_status = "Completed"
                    GROUP BY id_product
                    ORDER BY id_product ASC
                '));

        if (!empty($product)) {
            $product = json_decode(json_encode($product), true);
            $month = date('n', strtotime($date));
            $year = date('Y', strtotime($date));
            foreach ($product as $key => $value) {
                $saveGlobal = GlobalMonthlyReportTrxMenu::updateOrCreate([
                    'trx_month'  => $value['trx_month'],
                    'trx_year'   => $value['trx_year'],
                    'id_product' => $value['id_product']
                ], $value);

                if (!$save) {
                    return false;
                }
            }
        }

        return true;
    }

    /* REPORT PAYMENT */
    public function monthlyReportPayment($month, $year)
    {
        //delete report if there is already a report for the date
        $delete = MonthlyReportPayment::whereMonth('trx_month', $month)->whereYear('trx_year', $year)->delete();
        $delete = GlobalMonthlyReportPayment::whereMonth('trx_month', $month)->whereYear('trx_year', $year)->delete();

        //midtrans
            $dataPayment = TransactionPaymentMidtran::join('transaction_groups', 'transaction_groups.id_transaction_group', 'transaction_payment_midtrans.id_transaction_group')
            ->join('transactions', 'transactions.id_transaction_group', 'transaction_groups.id_transaction_group')
            ->leftJoin('transaction_payment_balances', 'transactions.id_transaction', 'transaction_payment_balances.id_transaction')
            ->select(
                'transactions.id_outlet',
                DB::raw('"' . $month . '" as trx_month'),
                DB::raw('"' . $year . '" as trx_year'),
                DB::raw('COUNT(transactions.id_transaction) as trx_payment_count'),
                DB::raw('SUM(transactions.transaction_grandtotal-ifnull(transaction_payment_balances.balance_nominal, 0)) as trx_payment_nominal'),
                DB::raw("CONCAT_WS(' ', transaction_payment_midtrans.payment_type, transaction_payment_midtrans.bank) AS trx_payment"),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as created_at'),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as updated_at')
            )
            ->whereMonth('transactions.transaction_date', $month)
            ->whereYear('transactions.transaction_date', $year)
            ->where('transactions.transaction_payment_status', 'Completed')
            ->where('transactions.transaction_grandtotal', '>', 0)
            ->groupBy('transactions.id_outlet', 'trx_payment', 'trx_month', 'trx_year')
            ->get()->toArray();

        if ($dataPayment) {
            //insert daily
            $insertDaily = MonthlyReportPayment::insert($dataPayment);
        }

            $dataPaymentGlobal = TransactionPaymentMidtran::join('transaction_groups', 'transaction_groups.id_transaction_group', 'transaction_payment_midtrans.id_transaction_group')
                ->join('transactions', 'transactions.id_transaction_group', 'transaction_groups.id_transaction_group')
                ->leftJoin('transaction_payment_balances', 'transactions.id_transaction', 'transaction_payment_balances.id_transaction')
            ->select(
                DB::raw('"' . $month . '" as trx_month'),
                DB::raw('"' . $year . '" as trx_year'),
                DB::raw('COUNT(transactions.id_transaction) as trx_payment_count'),
                DB::raw('SUM(transactions.transaction_grandtotal-ifnull(transaction_payment_balances.balance_nominal, 0)) as trx_payment_nominal'),
                DB::raw("CONCAT_WS(' ', transaction_payment_midtrans.payment_type, transaction_payment_midtrans.bank) AS trx_payment"),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as created_at'),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as updated_at')
            )
            ->whereMonth('transactions.transaction_date', $month)
            ->whereYear('transactions.transaction_date', $year)
            ->where('transactions.transaction_payment_status', 'Completed')
            ->groupBy('trx_payment', 'trx_month', 'trx_year')
            ->get()->toArray();

        if ($dataPaymentGlobal) {
            //insert global
            $insertGlobal = GlobalMonthlyReportPayment::insert($dataPaymentGlobal);
        }
        //end midtrans

        //xendit
        $dataPayment = TransactionPaymentXendit::join('transaction_groups', 'transaction_groups.id_transaction_group', 'transaction_payment_xendits.id_transaction_group')
            ->join('transactions', 'transactions.id_transaction_group', 'transaction_groups.id_transaction_group')
            ->leftJoin('transaction_payment_balances', 'transactions.id_transaction', 'transaction_payment_balances.id_transaction')
            ->select(
                'transactions.id_outlet',
                DB::raw('"' . $month . '" as trx_month'),
                DB::raw('"' . $year . '" as trx_year'),
                DB::raw('COUNT(transactions.id_transaction) as trx_payment_count'),
                DB::raw('SUM(transactions.transaction_grandtotal-ifnull(transaction_payment_balances.balance_nominal, 0)) as trx_payment_nominal'),
                DB::raw("transaction_payment_xendits.type AS trx_payment"),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as created_at'),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as updated_at')
            )
            ->whereMonth('transactions.transaction_date', $month)
            ->whereYear('transactions.transaction_date', $year)
            ->where('transactions.transaction_payment_status', 'Completed')
            ->where('transactions.transaction_grandtotal', '>', 0)
            ->groupBy('transactions.id_outlet', 'trx_payment', 'trx_month', 'trx_year')
            ->get()->toArray();

        if ($dataPayment) {
            //insert daily
            $insertDaily = MonthlyReportPayment::insert($dataPayment);
        }

        $dataPaymentGlobal = TransactionPaymentXendit::join('transaction_groups', 'transaction_groups.id_transaction_group', 'transaction_payment_xendits.id_transaction_group')
            ->join('transactions', 'transactions.id_transaction_group', 'transaction_groups.id_transaction_group')
            ->leftJoin('transaction_payment_balances', 'transactions.id_transaction', 'transaction_payment_balances.id_transaction')
            ->select(
                DB::raw('"' . $month . '" as trx_month'),
                DB::raw('"' . $year . '" as trx_year'),
                DB::raw('COUNT(transactions.id_transaction) as trx_payment_count'),
                DB::raw('SUM(transactions.transaction_grandtotal-ifnull(transaction_payment_balances.balance_nominal, 0)) as trx_payment_nominal'),
                DB::raw("transaction_payment_xendits.type AS trx_payment"),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as created_at'),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as updated_at')
            )
            ->whereMonth('transactions.transaction_date', $month)
            ->whereYear('transactions.transaction_date', $year)
            ->where('transactions.transaction_payment_status', 'Completed')
            ->groupBy('trx_payment', 'trx_month', 'trx_year')
            ->get()->toArray();

        if ($dataPaymentGlobal) {
            //insert global
            $insertGlobal = GlobalMonthlyReportPayment::insert($dataPaymentGlobal);
        }
        //end xendit

        //balance
            $dataPayment = TransactionPaymentBalance::join('transactions', 'transactions.id_transaction', 'transaction_payment_balances.id_transaction')
            ->select(
                'transactions.id_outlet',
                DB::raw('"' . $month . '" as trx_month'),
                DB::raw('"' . $year . '" as trx_year'),
                DB::raw('COUNT(transactions.id_transaction) as trx_payment_count'),
                DB::raw('SUM(transaction_payment_balances.balance_nominal) as trx_payment_nominal'),
                DB::raw("'Point' AS trx_payment"),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as created_at'),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as updated_at')
            )
            ->whereMonth('transactions.transaction_date', $month)
            ->whereYear('transactions.transaction_date', $year)
            ->where('transactions.transaction_payment_status', 'Completed')
            ->groupBy('transactions.id_outlet', 'trx_payment', 'trx_month', 'trx_year')
            ->get()->toArray();

        if ($dataPayment) {
            //insert daily
            $insertDaily = MonthlyReportPayment::insert($dataPayment);
        }

            $dataPaymentGlobal = TransactionPaymentBalance::join('transactions', 'transactions.id_transaction', 'transaction_payment_balances.id_transaction')
            ->select(
                DB::raw('"' . $month . '" as trx_month'),
                DB::raw('"' . $year . '" as trx_year'),
                DB::raw('COUNT(transactions.id_transaction) as trx_payment_count'),
                DB::raw('SUM(transaction_payment_balances.balance_nominal) as trx_payment_nominal'),
                DB::raw("'Point' AS trx_payment"),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as created_at'),
                DB::raw('"' . date('Y-m-d H:i:s') . '" as updated_at')
            )
            ->whereMonth('transactions.transaction_date', $month)
            ->whereYear('transactions.transaction_date', $year)
            ->where('transactions.transaction_payment_status', 'Completed')
            ->groupBy('trx_payment', 'trx_month', 'trx_year')
            ->get()->toArray();

        if ($dataPaymentGlobal) {
            //insert global
            $insertGlobal = GlobalMonthlyReportPayment::insert($dataPaymentGlobal);
        }
        //end balance

        return true;
    }

    /* REPORT MODIFIER */
    public function monthlyReportModifier($outletAll, $date)
    {
        $month = date('n', strtotime($date));
        $year = date('Y', strtotime($date));

        $sum = [];
        foreach ($outletAll as $outlet) {
            $modifier = DB::select(DB::raw('
                        SELECT transaction_product_modifiers.id_product_modifier, transaction_products.id_brand, transactions.id_outlet, 
                        (select SUM(transaction_product_modifiers.qty * transaction_products.transaction_product_qty)) as total_qty, 
                        (select SUM(transaction_product_modifiers.transaction_product_modifier_price)) as total_nominal, 
                        (select count(transaction_product_modifiers.id_product_modifier)) as total_rec, 
                        (select MONTH(transaction_date)) as trx_month,
						(select YEAR(transaction_date)) as trx_year,
						(select SUM(Case When users.gender = \'Male\' Then 1 Else 0 End)) as cust_male, 
						(select SUM(Case When users.gender = \'Female\' Then 1 Else 0 End)) as cust_female, 
						(select SUM(Case When users.android_device is not null Then 1 Else 0 End)) as cust_android, 
						(select SUM(Case When users.ios_device is not null Then 1 Else 0 End)) as cust_ios, 
						(select SUM(Case When users.provider = \'Telkomsel\' Then 1 Else 0 End)) as cust_telkomsel, 
						(select SUM(Case When users.provider = \'XL\' Then 1 Else 0 End)) as cust_xl, 
						(select SUM(Case When users.provider = \'Indosat\' Then 1 Else 0 End)) as cust_indosat, 
						(select SUM(Case When users.provider = \'Tri\' Then 1 Else 0 End)) as cust_tri, 
						(select SUM(Case When users.provider = \'Axis\' Then 1 Else 0 End)) as cust_axis, 
						(select SUM(Case When users.provider = \'Smart\' Then 1 Else 0 End)) as cust_smart, 
						(select product_modifiers.text) as text,
						(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 11 && floor(datediff (now(), users.birthday)/365) <= 17 Then 1 Else 0 End)) as cust_teens, 
						(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 18 && floor(datediff (now(), users.birthday)/365) <= 24 Then 1 Else 0 End)) as cust_young_adult, 
						(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 25 && floor(datediff (now(), users.birthday)/365) <= 34 Then 1 Else 0 End)) as cust_adult,
						(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 35 && floor(datediff (now(), users.birthday)/365) <= 100 Then 1 Else 0 End)) as cust_old
                        FROM transaction_product_modifiers 
                        INNER JOIN transactions ON transaction_product_modifiers.id_transaction = transactions.id_transaction 
                        INNER JOIN transaction_products ON transaction_product_modifiers.id_transaction_product = transaction_products.id_transaction_product
						LEFT JOIN users ON users.id = transactions.id_user
						LEFT JOIN transaction_pickups ON transaction_pickups.id_transaction = transactions.id_transaction
						LEFT JOIN product_modifiers ON product_modifiers.id_product_modifier = transaction_product_modifiers.id_product_modifier
                        WHERE MONTH(transactions.transaction_date) = "' . $month . '" 
                    	AND YEAR(transactions.transaction_date) ="' . $year . '"
                        AND transactions.id_outlet = "' . $outlet . '"
                        AND transaction_payment_status = "Completed"
                        AND transaction_pickups.reject_at IS NULL
                        AND transaction_product_modifiers.id_product_modifier_group IS NULL
                        GROUP BY transaction_product_modifiers.id_product_modifier,transaction_products.id_brand
                        ORDER BY transaction_product_modifiers.id_product_modifier ASC
                    '));

            if (!empty($modifier)) {
                $modifier = json_decode(json_encode($modifier), true);
                foreach ($modifier as $key => $value) {
                    $save = MonthlyReportTrxModifier::updateOrCreate([
                        'trx_month'  => $value['trx_month'],
                        'trx_year'   => $value['trx_year'],
                        'id_product_modifier' => $value['id_product_modifier'],
                        'id_outlet'  => $value['id_outlet'],
                        'id_brand'   => $value['id_brand']
                    ], $value);

                    if (!$save) {
                        return false;
                    }
                }
            }
        }

        // update global trx modifier
        $modifier = DB::select(DB::raw('
                    SELECT transaction_product_modifiers.id_product_modifier, 
                    (select SUM(transaction_product_modifiers.qty * transaction_products.transaction_product_qty)) as total_qty, 
                    (select SUM(transaction_product_modifiers.transaction_product_modifier_price)) as total_nominal, 
                    (select count(transaction_product_modifiers.id_product_modifier)) as total_rec, 
                    (select MONTH(transaction_date)) as trx_month,
					(select YEAR(transaction_date)) as trx_year,
					(select SUM(Case When users.gender = \'Male\' Then 1 Else 0 End)) as cust_male, 
					(select SUM(Case When users.gender = \'Female\' Then 1 Else 0 End)) as cust_female, 
					(select SUM(Case When users.android_device is not null Then 1 Else 0 End)) as cust_android, 
					(select SUM(Case When users.ios_device is not null Then 1 Else 0 End)) as cust_ios, 
					(select SUM(Case When users.provider = \'Telkomsel\' Then 1 Else 0 End)) as cust_telkomsel, 
					(select SUM(Case When users.provider = \'XL\' Then 1 Else 0 End)) as cust_xl, 
					(select SUM(Case When users.provider = \'Indosat\' Then 1 Else 0 End)) as cust_indosat, 
					(select SUM(Case When users.provider = \'Tri\' Then 1 Else 0 End)) as cust_tri, 
					(select SUM(Case When users.provider = \'Axis\' Then 1 Else 0 End)) as cust_axis, 
					(select SUM(Case When users.provider = \'Smart\' Then 1 Else 0 End)) as cust_smart, 
					(select product_modifiers.text) as text,
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 11 && floor(datediff (now(), users.birthday)/365) <= 17 Then 1 Else 0 End)) as cust_teens, 
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 18 && floor(datediff (now(), users.birthday)/365) <= 24 Then 1 Else 0 End)) as cust_young_adult, 
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 25 && floor(datediff (now(), users.birthday)/365) <= 34 Then 1 Else 0 End)) as cust_adult,
					(select SUM(Case When floor(datediff (now(), users.birthday)/365) >= 35 && floor(datediff (now(), users.birthday)/365) <= 100 Then 1 Else 0 End)) as cust_old
                    FROM transaction_product_modifiers 
                    INNER JOIN transactions ON transaction_product_modifiers.id_transaction = transactions.id_transaction 
                    INNER JOIN transaction_products ON transaction_product_modifiers.id_transaction_product = transaction_products.id_transaction_product
					LEFT JOIN users ON users.id = transactions.id_user
					LEFT JOIN transaction_pickups ON transaction_pickups.id_transaction = transactions.id_transaction
					LEFT JOIN product_modifiers ON product_modifiers.id_product_modifier = transaction_product_modifiers.id_product_modifier
                    WHERE MONTH(transactions.transaction_date) = "' . $month . '" 
                    AND YEAR(transactions.transaction_date) ="' . $year . '"
                    AND transaction_payment_status = "Completed"
                    AND transaction_pickups.reject_at IS NULL
                    AND transaction_product_modifiers.id_product_modifier_group IS NULL
                    GROUP BY transaction_product_modifiers.id_product_modifier
                    ORDER BY transaction_product_modifiers.id_product_modifier ASC
                '));

        if (!empty($modifier)) {
            $modifier = json_decode(json_encode($modifier), true);
            $month = date('n', strtotime($date));
            $year = date('Y', strtotime($date));
            foreach ($modifier as $key => $value) {
                $saveGlobal = GlobalMonthlyReportTrxModifier::updateOrCreate([
                    'trx_month'  => $value['trx_month'],
                    'trx_year'   => $value['trx_year'],
                    'id_product_modifier' => $value['id_product_modifier']
                ], $value);

                if (!$save) {
                    return false;
                }
            }
        }

        return true;
    }
}
