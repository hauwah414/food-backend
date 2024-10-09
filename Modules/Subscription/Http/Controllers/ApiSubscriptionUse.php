<?php

namespace Modules\Subscription\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Lib\MyHelper;
use Modules\Subscription\Entities\Subscription;
use Modules\Subscription\Entities\FeaturedSubscription;
use Modules\Subscription\Entities\SubscriptionOutlet;
use Modules\Subscription\Entities\SubscriptionContent;
use Modules\Subscription\Entities\SubscriptionContentDetail;
use Modules\Subscription\Entities\SubscriptionUser;
use Modules\Subscription\Entities\SubscriptionUserVoucher;
use Modules\Deals\Entities\DealsContent;
use Modules\Deals\Entities\DealsContentDetail;
use App\Http\Models\Setting;
use Modules\Subscription\Http\Requests\ListSubscription;
use Modules\Subscription\Http\Requests\Step1Subscription;
use Modules\Subscription\Http\Requests\Step2Subscription;
use Modules\Subscription\Http\Requests\Step3Subscription;
use Modules\Subscription\Http\Requests\DetailSubscription;
use Modules\PromoCampaign\Lib\PromoCampaignTools;
use DB;
use Illuminate\Support\Facades\Auth;

class ApiSubscriptionUse extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');

        $this->promo_campaign       = "Modules\PromoCampaign\Http\Controllers\ApiPromoCampaign";
    }

    public function checkSubscription($id_subscription_user = null, $outlet = null, $product = null, $product_detail = null, $active = null, $id_subscription_user_voucher = null, $brand = null, $promo_rule = null)
    {
        if (!empty($id_subscription_user_voucher)) {
            $subs = SubscriptionUserVoucher::where('id_subscription_user_voucher', '=', $id_subscription_user_voucher);
        } else {
            $subs = SubscriptionUserVoucher::where('subscription_users.id_subscription_user', '=', $id_subscription_user);
        }

        $subs = $subs->join('subscription_users', 'subscription_users.id_subscription_user', '=', 'subscription_user_vouchers.id_subscription_user')
                ->whereIn('subscription_users.paid_status', ['Free', 'Completed'])
                ->whereNull('subscription_user_vouchers.used_at')
                ->where('id_user', Auth::id());

        if (!empty($outlet)) {
            $subs = $subs->with([
                        'subscription_user.subscription.outlets_active',
                        'subscription_user.subscription.outlet_groups'
                    ]);
        }

        if (!empty($product)) {
            $subs = $subs->with(
                'subscription_user.subscription.subscription_products'
            );
        }

        if (!empty($brand)) {
            $subs = $subs->with(
                'subscription_user.subscription.brand',
                'subscription_user.subscription.brands'
            );
        }

        if (!empty($product_detail)) {
            $subs = $subs->with([
                            'subscription_user.subscription.subscription_products.product' => function ($q) {
                                $q->select('id_product', 'id_product_category', 'product_code', 'product_name');
                            }
                        ]);
        }

        if (!empty($active)) {
            $subs = $subs->where('subscription_users.subscription_expired_at', '>=', date('Y-m-d H:i:s'))
                        ->where(function ($q) {
                            $q->where('subscription_users.subscription_active_at', '<=', date('Y-m-d H:i:s'))
                                ->orWhereNull('subscription_users.subscription_active_at');
                        });
        }

        if (!empty($promo_rule)) {
            $subs = $subs->with(
                'subscription_user.subscription.subscription_shipment_method',
                'subscription_user.subscription.subscription_payment_method'
            );
        }

        $subs = $subs->first();

        return $subs;
    }

    public function calculate($request, $id_subscription_user, $subtotal, $subtotal_per_brand, $item, $id_outlet, &$errors, &$errorProduct = 0, &$product = "", &$applied_product = "", $delivery_fee = 0)
    {
        if (empty($id_subscription_user)) {
            return 0;
        }

        $subs = $this->checkSubscription($id_subscription_user, "outlet", "product", "product_detail", $active = null, $id_subscription_user_voucher = null, "brand");

        // check if subscription exists
        if (!$subs) {
            $errors[] = 'Subscription not valid';
            return 0;
        }

        $subs_obj = $subs;
        $subs = $subs->toArray();
        $type = $subs['subscription_user']['subscription']['subscription_discount_type'];

        // check expired date
        if ($subs['subscription_expired_at'] < date('Y-m-d H:i:s')) {
            $errors[] = 'Subscription is expired';
            return 0;
        }

        // check active date
        if (!empty($subs['subscription_active_at']) && $subs['subscription_active_at'] > date('Y-m-d H:i:s')) {
            $errors[] = 'Subscription is not active yet';
            return 0;
        }

        // check daily usage limit
        if (!empty($subs['subscription_user']['subscription']['daily_usage_limit'])) {
            $subs_voucher_today = SubscriptionUserVoucher::where('id_subscription_user', '=', $id_subscription_user)
                            ->whereDate('used_at', date('Y-m-d'))
                            ->count();
            if ($subs_voucher_today >= $subs['subscription_user']['subscription']['daily_usage_limit']) {
                $errors[] = 'Subscription telah mencapai limit penggunaan harian';
                return 0;
            }
        }
        // check outlet & brands
        $pct = new PromoCampaignTools();
        if (!empty($subs['subscription_user']['subscription']['id_brand'])) {
            $check_outlet = $pct->checkOutletRule($id_outlet, $subs['subscription_user']['subscription']['is_all_outlet'], $subs['subscription_user']['subscription']['outlets_active'], $subs['subscription_user']['subscription']['id_brand']);
        } else {
            $promo_brands = $subs_obj->subscription_user->subscription->subscription_brands->pluck('id_brand')->toArray();
            $check_outlet = $pct->checkOutletBrandRule(
                $id_outlet,
                $subs['subscription_user']['subscription']['is_all_outlet'],
                $subs['subscription_user']['subscription']['outlets_active'],
                $promo_brands,
                $subs['subscription_user']['subscription']['brand_rule'],
                $subs['subscription_user']['subscription']['outlet_groups']
            );
        }

        if (!$check_outlet) {
            $errors[] = 'Cannot use subscription at this outlet';
            return 0;
        }

        // check product
        $check = false;
        if (!empty($subs['subscription_user']['subscription']['id_brand'])) {
            if (!empty($subs['subscription_user']['subscription']['subscription_products'])) {
                $promo_product = $subs['subscription_user']['subscription']['subscription_products'];
                foreach ($promo_product as $key => $value) {
                    foreach ($item as $key2 => $value2) {
                        if ($value['id_product'] == $value2['id_product']) {
                            $check = true;
                            break;
                        }
                    }
                    if ($check) {
                        break;
                    }
                }
            } else {
                $id_brand = !empty($subs['subscription_user']['subscription']['id_brand']);
                foreach ($item as $key => $value) {
                    if ($value['id_brand'] == $id_brand) {
                        $check = true;
                        break;
                    }
                }
            }
        } else {
            if (!empty($subs['subscription_user']['subscription']['subscription_products'])) {
                // selected item
                $promo_product = $subs['subscription_user']['subscription']['subscription_products'];
                $check = $pct->checkProductRule($subs_obj->subscription_user->subscription, $promo_brands, $promo_product, $item);
            } else {
                // all item
                $promo_brand_flipped = array_flip($promo_brands);
                foreach ($item as $key => $value) {
                    if (isset($promo_brand_flipped[$value['id_brand']])) {
                        $check = true;
                        break;
                    }
                }
            }
        }

        foreach ($request['bundling_promo'] ?? [] as $bp) {
            if (isset($promo_brand_flipped[$bp['id_brand']])) {
                $check = true;
                break;
            }
        }

        if (!$check) {
            $pct = new PromoCampaignTools();
            $total_product = count($promo_product ?? []);
            $product_name = $pct->getProductName($promo_product ?? [], $subs_obj->subscription_user->subscription->product_rule);

            // $message = $pct->getMessage('error_product_discount')['value_text']??'Promo hanya akan berlaku jika anda membeli <b>%product_name%</b>.';
            $message = 'Promo hanya berlaku jika membeli <b>%product_name%</b>.';
            $message = MyHelper::simpleReplace($message, ['product_name' => $product_name]);
            $errors[] = $message;

            $getProduct  = app($this->promo_campaign)->getProduct('subscription', $subs['subscription_user']['subscription'], $id_outlet);
            $product = $getProduct['product'] ?? '';
            $applied_product = $getProduct['applied_product'][0] ?? '';
            if ($applied_product == '*') {
                $applied_product = null;
            }
            $errorProduct = 'all';
            return 0;
        }

        // check minimal transaction
        if (!empty($subs['subscription_user']['subscription']['subscription_minimal_transaction'])) {
            $min_basket_size = $subs['subscription_user']['subscription']['subscription_minimal_transaction'];
            $check_min_trx = false;
            $promo_brand_flipped = array_flip($promo_brands);
            $subtotal_promo_brand = 0;
            foreach ($subtotal_per_brand as $key => $value) {
                if (!isset($promo_brand_flipped[$key])) {
                    continue;
                }
                $subtotal_promo_brand += $value;
                if ($subtotal_promo_brand >= $min_basket_size) {
                    $check_min_trx = true;
                }
            }

            if (!$check_min_trx) {
                // $errors[] = 'Total transaction is not meet minimum transasction to use Subscription';
                $errors[] = 'Total transaksi belum mencapai syarat minimum untuk menggunakan Subscription ini.';
                $errorProduct = 'all';

                return 0;
            }
        }

        // check shipment
        if (isset($subs['subscription_user']['subscription']['is_all_shipment']) && isset($request['type'])) {
            $promo_shipment = $subs_obj->subscription_user->subscription->subscription_shipment_method->pluck('shipment_method');

            if ($subs['subscription_user']['subscription']['subscription_discount_type'] == 'discount_delivery') {
                if ($request['type'] == 'Pickup Order') {
                    $errors[] = 'Promo tidak dapat digunakan untuk Pick Up';
                    return false;
                }
                if (count($promo_shipment) == 1 && $promo_shipment[0] == 'Pickup Order') {
                    $subs['subscription_user']['subscription']['is_all_shipment'] = 1;
                }
            }

            $shipment_method = ($request['type'] == 'Pickup Order' || $request['type'] == 'GO-SEND') ? $request['type'] : $request['courier'];
            $check_shipment = $pct->checkShipmentRule($subs['subscription_user']['subscription']['is_all_shipment'], $shipment_method, $promo_shipment);

            if (!$check_shipment) {
                $errors[] = 'Promo tidak dapat digunakan untuk tipe order ini';
                return false;
            }
        }

        // check payment
        if (
            isset($subs['subscription_user']['subscription']['is_all_payment'])
            && isset($request['payment_type'])
            && (isset($request['payment_id']) || isset($request['payment_detail']))
        ) {
            $promo_payment = $subs_obj->subscription_user->subscription->subscription_payment_method->pluck('payment_method');
            $payment_method = $pct->getPaymentMethod($request['payment_type'], $request['payment_id'], $request['payment_detail']);
            $check_payment = $pct->checkPaymentRule($subs['subscription_user']['subscription']['is_all_payment'], $payment_method, $promo_payment);

            if (!$check_payment) {
                $errors[] = 'Promo tidak dapat digunakan untuk metode pembayaran ini';
                return false;
            }
        }

        switch ($subs['subscription_user']['subscription']['subscription_discount_type']) {
            case 'discount_delivery':
                if (!empty($subs['subscription_user']['subscription']['subscription_voucher_nominal'])) {
                    $discount_type = 'Nominal';
                    $discount_value = $subs['subscription_user']['subscription']['subscription_voucher_nominal'];
                    $max_percent_discount = 0;
                } elseif (!empty($subs['subscription_user']['subscription']['subscription_voucher_percent'])) {
                    $discount_type = 'Percent';
                    $discount_value = $subs['subscription_user']['subscription']['subscription_voucher_percent'];
                    $max_percent_discount = $subs['subscription_user']['subscription']['subscription_voucher_percent_max'];
                } else {
                    $errors[] = 'Subscription not valid.';
                    return 0;
                }

                if (!empty($delivery_fee)) {
                    $result = $pct->discountDelivery(
                        $delivery_fee,
                        $discount_type,
                        $discount_value,
                        $max_percent_discount
                    );
                } else {
                    $result = 0;
                }
                break;

            default:
                /*
                    subscription type
                    - payment_method
                    - discount
                */
                $promo_brand_flipped = array_flip($promo_brands);
                $subtotal = 0;
                foreach ($subtotal_per_brand as $key => $value) {
                    if (!isset($promo_brand_flipped[$key])) {
                        continue;
                    }
                    $subtotal += $value;
                }

                // sum subs discount
                if (!empty($subs['subscription_user']['subscription']['subscription_voucher_nominal'])) {
                    $result = $subs['subscription_user']['subscription']['subscription_voucher_nominal'];

                    if ($result > $subtotal) {
                        $result = $subtotal;
                    }
                } elseif (!empty($subs['subscription_user']['subscription']['subscription_voucher_percent'])) {
                    $result = $subtotal * ($subs['subscription_user']['subscription']['subscription_voucher_percent'] / 100);

                    if (!empty($subs['subscription_user']['subscription']['subscription_voucher_percent_max'])) {
                        if ($result > $subs['subscription_user']['subscription']['subscription_voucher_percent_max']) {
                            $result = $subs['subscription_user']['subscription']['subscription_voucher_percent_max'];
                        }
                    }
                } else {
                    $errors[] = 'Subscription not valid.';
                    return 0;
                }

                break;
        }


        return [
            'type' => $type,
            'value' => $result
        ];
    }

    public function checkDiscount($request, $post)
    {
        $data_subs = SubscriptionUser::where('id_subscription_user', $request->id_subscription_user)->with('subscription')->first();
        if (!$data_subs) {
            return [
                'status' => 'fail',
                'messages' => ['Promo is not valid']
            ];
        }
        $subs_type = $data_subs['subscription']['subscription_discount_type'];

        if ($subs_type != 'payment_method') {
            $check_subs = $this->calculate($request, $request->id_subscription_user, $post['subtotal'], $post['sub']['subtotal_per_brand'], $post['item'], $post['id_outlet'], $subs_error, $errorProduct, $subs_product, $subs_applied_product, $post['delivery_fee'] ?? 0);

            if (!empty($subs_error)) {
                return [
                    'status'    => 'fail',
                    'messages'  => $subs_error ?? ['Promo not valid']
                ];
            }

            return MyHelper::checkGet($check_subs);
        } else {
            return MyHelper::checkGet(['type' => $subs_type]);
        }
    }
}
