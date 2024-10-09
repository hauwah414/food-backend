<?php

namespace Modules\Subscription\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Lib\MyHelper;
use Modules\Subscription\Entities\Subscription;
use Modules\Subscription\Entities\SubscriptionPaymentMidtran;
use Modules\Subscription\Entities\FeaturedSubscription;
use Modules\Subscription\Entities\SubscriptionOutlet;
use Modules\Subscription\Entities\SubscriptionUser;
use Modules\Subscription\Entities\SubscriptionUserVoucher;
use App\Http\Models\Outlet;
use App\Http\Models\LogPoint;
use App\Http\Models\User;
use App\Http\Models\Setting;
use Modules\Subscription\Http\Requests\CreateSubscriptionVoucher;
use Illuminate\Support\Facades\Schema;
use DB;

class ApiSubscriptionClaim extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');

        $this->deals   = "Modules\Deals\Http\Controllers\ApiDeals";
        $this->subscription   = "Modules\Subscription\Http\Controllers\ApiSubscription";
        $this->voucher = "Modules\Subscription\Http\Controllers\ApiSubscriptionVoucher";
        $this->setting = "Modules\Transaction\Http\Controllers\ApiOnlineTransaction";
        if (\Module::collections()->has('Autocrm')) {
            $this->autocrm  = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        }
    }

    /* CLAIM SUBSCRIPTION */
    public function claim(Request $request)
    {

        try {
            $dataSubs = $this->checkSubsData($request->json('id_subscription'));
            $id_user = $request->user()->id;
            $dataSubsUser = $this->checkSubsUser($id_user, $dataSubs);
            if (empty($dataSubs)) {
                return response()->json([
                    'status'   => 'fail',
                    // 'messages' => ['Subscription not found']
                    'messages' => ['Subscription tidak ditemukan']
                ]);
            } else {
                // CEK VALID DATE
                if ($this->checkValidDate($dataSubs)) {
                    // if (!empty($dataSubs->deals_voucher_price_cash) || $dataSubs->deals_promo_id_type == "nominal") {
                    if (!empty($dataSubs->subscription_price_cash) || !empty($dataSubs->subscription_price_point)) {
                        return response()->json([
                            'status' => 'fail',
                            'messages' => ['You have to pay subscription.']
                        ]);
                    } else {
                        if ($this->checkSubsPoint($dataSubs, $request->user()->id)) {
                            // CEK LIMIT USER
                            if ($this->checkUserLimit($dataSubs, $dataSubsUser)) {
                                // CEK IF USER SUBSCRIPTION IS EXPIRED OR NULL
                                if ($this->checkSubsUserExpired($dataSubs, $dataSubsUser)) {
                                    $id_subscription = $dataSubs->id_subscription;

                                    // count claimed deals by id_deals_subscription (how many times deals are claimed)
                                    $subsClaimed = 0;
                                    if ($dataSubs->subscription_total != null) {
                                        $subsVoucher = SubscriptionUser::where('id_subscription', '=', $id_subscription)->count();

                                        if ($subsVoucher > 0) {
                                            $subsClaimed = $subsVoucher;
                                            if (is_float($subsClaimed)) { // if miss calculate use deals_total_claimed
                                                $subsClaimed = $dataSubs->subscription_bought;
                                            }
                                        }
                                    }

                                    // check available voucher
                                    if ($dataSubs->subscription_total > $subsClaimed || $dataSubs->subscription_total == null) {
                                        // create subscription voucher x times and subscription user

                                        // create user Subscription
                                        DB::beginTransaction();
                                        $user_subs = $this->createSubscriptionUser($id_user, $dataSubs);
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
                                        }   // end of for

                                        // update deals total bought
                                        DB::commit();
                                        $updateSubs = $this->updateSubs($dataSubs);
                                    } else {
                                        DB::rollback();
                                        return response()->json([
                                            'status'   => 'fail',
                                            'messages' => ['Voucer telah habis']
                                        ]);
                                    }

                                    /* dikomen karena log point, sekarang masih belum dipakai
                                    // UPDATE POINT
                                    if (!$this->updatePoint($voucher)) {
                                        DB::rollback();
                                        return response()->json([
                                            'status'   => 'fail',
                                            'messages' => ['Proses pengambilan Subscription gagal. Silakan coba kembali']
                                        ]);
                                    }*/


                                    if (\Module::collections()->has('Autocrm')) {
                                        $phone = $request->user()->phone;
                                        $autocrm = app($this->autocrm)->SendAutoCRM(
                                            'Get Free Subscription Success',
                                            $phone,
                                            [
                                                'bought_at'             => date('Y-m-d H:i:s'),
                                                'subscription_title'    => $dataSubs->subscription_title,
                                                'id_subscription_user'  => $voucher->id_subscription_user,
                                                'id_subscription'       => $dataSubs->id_subscription
                                            ]
                                        );
                                    }
                                    $return = [
                                        'id_subscription_user' => $voucher->id_subscription_user,
                                        'id_subscription' => $dataSubs->id_subscription,
                                        'paid_status' => $voucher->paid_status,
                                        'webview_success' => config('url.api_url') . 'api/webview/subscription/success/' . $voucher->id_subscription_user
                                    ];
                                    if ($return['paid_status'] == 'Completed') {
                                        $return['title'] = 'Success';
                                    } elseif ($return['paid_status'] == 'Pending') {
                                        $return['title'] = 'Pending';
                                    } elseif ($return['paid_status'] == 'Free') {
                                        $return['title'] = 'Success';
                                    }
                                    return response()->json(MyHelper::checkCreate($return));
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
                                'status'   => 'fail',
                                // 'messages' => ['Your point is not enough.']
                                'messages' => ['Point tidak cukup untuk pembelian Subscription.']
                            ]);
                        }
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
                'messages' => ['Proses pengambilan Subscription gagal. Silakan coba kembali']
            ]);
        }
    }

    // check if subscription user data exists
    public function checkSubsUser($id_user, $subs)
    {

        $subs_user = SubscriptionUser::join('subscription_user_vouchers', 'subscription_user_vouchers.id_subscription_user', '=', 'subscription_users.id_subscription_user')
        ->where('id_user', '=', $id_user)
        ->where('subscription_users.id_subscription', '=', $subs->id_subscription)
        ->where('paid_status', '<>', 'Cancelled')
        ->orderBy('bought_at', 'DESC')
        ->groupBy('subscription_user_vouchers.id_subscription_user')
        ->get();

        return $subs_user;
    }

    // check user limit
    public function checkUserLimit($subs, $subsUser)
    {
        if (empty($subs)) {
            return false;
        }

        if ($subs['user_limit'] != 0) {
            if (!empty($subsUser)) {
                foreach ($subsUser as $key => $value) {
                    if (($value['paid_status'] ?? false) == "Free" || ($value['paid_status'] ?? false) == "Completed") {
                        continue;
                    } else {
                        unset($subsUser[$key]);
                    }
                }
                if (count($subsUser) >= $subs['user_limit']) {
                    return false;
                }
            }
        }

        return true;
    }

    // check last subscription user expired date
    public function checkSubsUserExpired($subs, $subsUser)
    {
        $now = date('Y-m-d H:i:s');

        if (empty($subsUser[0]) || (isset($subs->new_purchase_after) && $subs->new_purchase_after == 'No Limit')) {
            return true;
        } elseif (isset($subs->new_purchase_after)) {
            switch ($subs->new_purchase_after) {
                case 'Empty':
                    $available = SubscriptionUserVoucher::where('id_subscription_user', $subsUser[0]->id_subscription_user)
                                ->join('transactions', 'transactions.id_transaction', '=', 'subscription_user_vouchers.id_transaction')
                                ->where('transaction_payment_status', 'Completed')
                                ->whereNotNull('used_at')
                                ->count();

                    if ($available == $subs->subscription_voucher_total) {
                        return true;
                    }
                    if (isset($subsUser[0]['subscription_expired_at']) && strtotime($subsUser[0]['subscription_expired_at']) <= strtotime($now)) {
                        return true;
                    }
                    break;
                case 'Expired':
                    if (isset($subsUser[0]['subscription_expired_at']) && strtotime($subsUser[0]['subscription_expired_at']) <= strtotime($now)) {
                        return true;
                    }
                    break;
                case 'Empty Expired':
                    $available = SubscriptionUserVoucher::where('id_subscription_user', $subsUser[0]->id_subscription_user)
                                ->join('transactions', 'transactions.id_transaction', '=', 'subscription_user_vouchers.id_transaction')
                                ->where('transaction_payment_status', 'Completed')
                                ->whereNotNull('used_at')
                                ->count();
                    if ($available == $subs->subscription_voucher_total) {
                        return true;
                    }
                    if (isset($subsUser[0]['subscription_expired_at']) && strtotime($subsUser[0]['subscription_expired_at']) <= strtotime($now)) {
                        return true;
                    }
                    break;
                default:
                    return true;
                    break;
            }
            return false;
        } elseif (isset($subsUser[0]['subscription_expired_at']) && strtotime($subsUser[0]['subscription_expired_at']) <= strtotime($now)) {
            return true;
        } else {
            return false;
        }
    }
    /* CHECK IF USER ALREADY CLAIMED */
    public function checkUserClaimed($user, $subs)
    {

        $now = date('Y-m-d H:i:s');

        $claimed = SubscriptionUser::join('subscription_user_vouchers', 'subscription_user_vouchers.id_subscription_user', '=', 'subscription_users.id_subscription_user')
        ->where('id_user', '=', $user->id)
        ->where('subscription_users.id_subscription', '=', $subs->id_subscription)
        ->orderBy('subscription_expired_at')
        ->get();

        if (empty($subs)) {
            return false;
        }

        if ($subs['user_limit'] != 0) {
            if (!empty($claimed)) {
                if (count($claimed) >= $subs['user_limit']) {
                    return false;
                }
            }
        }

        if (empty($claimed) || empty($claimed[0])) {
            return true;
        } elseif (isset($claimed[0]['subscription_expired_at']) && strtotime($claimed[0]['subscription_expired_at']) <= strtotime($now)) {
            return true;
        } else {
            return false;
        }
    }

    /* CHECK USER HAVE ENOUGH POINT */
    public function checkSubsPoint($subs, $user)
    {
        if (!empty($subs->subscription_price_point)) {
            if ($subs->subscription_price_point > $this->getPoint($user)) {
                return false;
            }
        }

        return true;
    }

    /* CHECK VALID DATE */
    public function checkValidDate($subs)
    {
        if (empty($subs->subscription_start) && empty($subs->subscription_end)) {
            return true;
        }

        if (strtotime($subs->subscription_start) <= strtotime(date('Y-m-d H:i:s')) && strtotime($subs->subscription_end) >= strtotime(date('Y-m-d H:i:s'))) {
            return true;
        }

        return false;
    }

    /* CHECK DATA SUBSCRIPTION */
    public function checkSubsData($id_subscription)
    {
        $subs = Subscription::where('id_subscription', '=', $id_subscription)->first();

        return $subs;
    }

    /* UPDATE Subscription */
    public function updateSubs($dataSubs)
    {
        $total_bought_subs = SubscriptionUser::where('id_subscription', '=', $dataSubs->id_subscription)->where('paid_status', '!=', 'Cancelled')->count();
        $update = Subscription::where('id_subscription', $dataSubs->id_subscription)->update(['subscription_bought' => $total_bought_subs]);
        $update = is_int($update) ? true : false;
        return $update;
    }

    /* CREATE USER */
    public function createSubscriptionUser($id, $dataSubs, $price = null)
    {
        $subscription_price_point = $dataSubs->subscription_price_point ?? 0;
        $subscription_price_cash = $dataSubs->subscription_price_cash ?? 0;

        $data = [
            'id_user'                   => $id,
            'id_subscription'           => $dataSubs->id_subscription,
            'bought_at'                 => date('Y-m-d H:i:s'),
            'subscription_price_point'  => $subscription_price_point,
            'subscription_price_cash'   => $subscription_price_cash,
        ];

        // EXPIRED DATE
        // add id deals
        $data['id_subscription'] = $dataSubs->id_subscription;
        // get expired date of subscription
        if (!empty($dataSubs->subscription_voucher_duration)) {
            if ($dataSubs->subscription_voucher_start > date('Y-m-d H:i:s')) {
                $data['subscription_expired_at'] = date('Y-m-d H:i:s', strtotime($dataSubs->subscription_voucher_start . " +" . $dataSubs->subscription_voucher_duration . " days"));
            } else {
                $data['subscription_expired_at'] = date('Y-m-d H:i:s', strtotime("+" . $dataSubs->subscription_voucher_duration . " days"));
            }
        } else {
            $data['subscription_expired_at'] = $dataSubs->subscription_voucher_expired;
        }

        if ($dataSubs->subscription_voucher_start > date('Y-m-d H:i:s')) {
            $data['subscription_active_at'] = date('Y-m-d H:i:s', strtotime($dataSubs->subscription_voucher_start));
        } else {
            $data['subscription_active_at'] = date('Y-m-d H:i:s');
        }

        // CHECK PAYMENT = FREE / NOT
        if (empty($dataSubs->subscription_price_cash) && empty($dataSubs->subscription_price_point)) {
            $data['paid_status'] = "Free";
        }

        if (!empty($dataSubs->subscription_price_cash) && empty(!$dataSubs->subscription_price_point)) {
            $data['paid_status'] = "Pending";
        }

        // CHECK PAYMENT WITH POINT
        // SUM POINT
        // if ($dataSubs->subscription_voucher_price_point <= $this->getPoint($id)) {
        //     $data['paid_status'] = "success";
        // }
        // else {
        //     $data['paid_status'] = "Pending";
        // }

        $save = app($this->voucher)->createVoucherUser($data);

        return $save;
    }

    /*=============================================================================*/
    //
    //
    /*=============================================================================*/


    /* GET POINT */
    public function getPoint($user)
    {
        // if (Schema::hasTable('log_points')) {

        //     $point = DB::table('log_points')->where('id_user', $user)->sum('point');

        //     return $point;
        // }

        //point is balance
        if (Schema::hasTable('log_balances')) {
            $point = DB::table('log_balances')->where('id_user', $user)->sum('balance');

            return $point;
        }

        return 0;
    }

    public function updatePoint($voucher)
    {
        $user = User::with('memberships')->where('id', $voucher->id_user)->first();

        $user_member = $user->toArray();
        $level = null;
        $point_percentage = 0;
        if (!empty($user_member['memberships'][0]['membership_name'])) {
            $level = $user_member['memberships'][0]['membership_name'];
        }
        if (isset($user_member['memberships'][0]['benefit_point_multiplier'])) {
            $point_percentage = $user_member['memberships'][0]['benefit_point_multiplier'];
        }

        // $setting = app($this->setting)->setting('point_conversion_value');
        $setting = Setting::where('key', 'point_conversion_value')->pluck('value')->first();

        $dataCreate        = [
            'id_user'          => $voucher->id_user,
            'id_reference'     => $voucher->id_subscription_user,
            'source'           => "Subscription User",
            'point'            => -$voucher->subscription_price_point,
            'voucher_price'    => $voucher->subscription_price_point,
            'point_conversion' => $setting,
            'membership_level'            => $level,
            'membership_point_percentage' => $point_percentage
        ];
        $save = LogPoint::create($dataCreate);

        // update user point
        $new_user_point = LogPoint::where('id_user', $user->id)->sum('point');
        $user->update(['points' => $new_user_point]);

        return $save;
    }
}
