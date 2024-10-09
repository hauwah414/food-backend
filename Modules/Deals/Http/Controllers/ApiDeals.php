<?php

namespace Modules\Deals\Http\Controllers;

use App\Http\Models\Configs;
use App\Http\Models\DealTotal;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Lib\MyHelper;
use App\Http\Models\Outlet;
use App\Http\Models\Deal;
use App\Http\Models\DealsOutlet;
use App\Http\Models\DealsPaymentManual;
use App\Http\Models\DealsPaymentMidtran;
use App\Http\Models\DealsUser;
use App\Http\Models\DealsVoucher;
use App\Http\Models\SpinTheWheel;
use App\Http\Models\Setting;
use Modules\Brand\Entities\Brand;
use App\Http\Models\DealsPromotionTemplate;
use Modules\ProductVariant\Entities\ProductGroup;
use App\Http\Models\Product;
use Modules\Promotion\Entities\DealsPromotionBrand;
use Modules\Promotion\Entities\DealsPromotionOutlet;
use Modules\Promotion\Entities\DealsPromotionOutletGroup;
use Modules\Promotion\Entities\DealsPromotionContent;
use Modules\Promotion\Entities\DealsPromotionContentDetail;
use Modules\Deals\Entities\DealsProductDiscount;
use Modules\Deals\Entities\DealsProductDiscountRule;
use Modules\Deals\Entities\DealsTierDiscountProduct;
use Modules\Deals\Entities\DealsTierDiscountRule;
use Modules\Deals\Entities\DealsBuyxgetyProductRequirement;
use Modules\Deals\Entities\DealsBuyxgetyRule;
use Modules\Deals\Entities\DealsUserLimit;
use Modules\Deals\Entities\DealsContent;
use Modules\Deals\Entities\DealsContentDetail;
use Modules\Deals\Entities\DealsBrand;
use Modules\Deals\Entities\DealsOutletGroup;
use DB;
use Modules\Deals\Http\Requests\Deals\Create;
use Modules\Deals\Http\Requests\Deals\Update;
use Modules\Deals\Http\Requests\Deals\Delete;
use Modules\Deals\Http\Requests\Deals\ListDeal;
use Modules\Deals\Http\Requests\Deals\DetailDealsRequest;
use Modules\Deals\Http\Requests\Deals\UpdateContentRequest;
use Modules\Deals\Http\Requests\Deals\ImportDealsRequest;
use Modules\Deals\Http\Requests\Deals\UpdateComplete;
use Illuminate\Support\Facades\Schema;
use Image;
use App\Jobs\SendDealsJob;

class ApiDeals extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->user     = "Modules\Users\Http\Controllers\ApiUser";
        $this->hidden_deals     = "Modules\Deals\Http\Controllers\ApiHiddenDeals";
        $this->autocrm = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        $this->subscription = "Modules\Subscription\Http\Controllers\ApiSubscription";
        $this->promo_campaign       = "Modules\PromoCampaign\Http\Controllers\ApiPromoCampaign";
        $this->promotion_deals      = "Modules\Promotion\Http\Controllers\ApiPromotionDeals";
        $this->deals_claim    = "Modules\Deals\Http\Controllers\ApiDealsClaim";
        $this->promo        = "Modules\PromoCampaign\Http\Controllers\ApiPromo";
    }

    public $saveImage = "img/deals/";


    public function rangePoint()
    {
        $start = Setting::where('key', 'point_range_start')->get()->first();
        $end = Setting::where('key', 'point_range_end')->get()->first();

        if (!$start) {
            $start['value'] = 0;
        }

        if (!$end) {
            $end['value'] = 1000000;
        }

        return response()->json([
            'status'    => 'success',
            'result'    => [
                'point_range_start' => $start['value'],
                'point_range_end'   => $end['value'],
            ]
        ]);
    }

    /* CHECK INPUTAN */
    public function checkInputan($post)
    {
        $data = [];

        if (isset($post['deals_promo_id_type'])) {
            $data['deals_promo_id_type'] = $post['deals_promo_id_type'];
        }
        if (isset($post['deals_type'])) {
            $data['deals_type'] = $post['deals_type'];
        }
        if (isset($post['deals_voucher_type'])) {
            $data['deals_voucher_type'] = $post['deals_voucher_type'];
            if ($data['deals_voucher_type'] == 'Unlimited') {
                $data['deals_total_voucher'] = 0;
            }

            if ($post['deals_type'] == 'Promotion') {
                if ($post['deals_voucher_type'] == 'List Vouchers') {
                    $data['deals_list_voucher'] = str_replace("\r\n", ',', $post['voucher_code']);
                } else {
                    $data['deals_list_voucher'] = null;
                }
            }
        }
        if (isset($post['deals_promo_id'])) {
            $data['deals_promo_id'] = $post['deals_promo_id'];
        }
        if (isset($post['deals_title'])) {
            $data['deals_title'] = $post['deals_title'];
        }
        if (isset($post['deals_second_title'])) {
            $data['deals_second_title'] = $post['deals_second_title'];
        }
        if (isset($post['deals_description'])) {
            $data['deals_description'] = $post['deals_description'];
        }
        if (isset($post['product_type'])) {
            $data['product_type'] = $post['product_type'];
        }
        if (isset($post['deals_tos'])) {
            $data['deals_tos'] = $post['deals_tos'];
        }
        if (isset($post['deals_short_description'])) {
            $data['deals_short_description'] = $post['deals_short_description'];
        }
        if (isset($post['deals_image'])) {
            if ($post['deals_type'] == 'Promotion') {
                $promotionPath = 'img/promotion/deals/';
            }
            if (!file_exists($promotionPath ?? $this->saveImage)) {
                mkdir($promotionPath ?? $this->saveImage, 0777, true);
            }

            $upload = MyHelper::uploadPhotoStrict($post['deals_image'], ($promotionPath ?? $this->saveImage), 500, 500);

            if (isset($upload['status']) && $upload['status'] == "success") {
                $data['deals_image'] = $upload['path'];
            } else {
                $result = [
                    'error'    => 1,
                    'status'   => 'fail',
                    'messages' => ['fail upload image']
                ];

                return $result;
            }
        }
        // if (isset($post['deals_video'])) {
        //     $data['deals_video'] = $post['deals_video'];
        // }
        if (isset($post['id_product'])) {
            $data['id_product'] = $post['id_product'];
        }
        /*if (isset($post['id_brand'])) {
            $data['id_brand'] = $post['id_brand'];
        }*/
        if (isset($post['deals_start'])) {
            $data['deals_start'] = date('Y-m-d H:i:s', strtotime($post['deals_start']));
        }
        if (isset($post['deals_end'])) {
            $data['deals_end'] = date('Y-m-d H:i:s', strtotime($post['deals_end']));
        }
        if (isset($post['deals_publish_start'])) {
            $data['deals_publish_start'] = date('Y-m-d H:i:s', strtotime($post['deals_publish_start']));
        }
        if (isset($post['deals_publish_end'])) {
            $data['deals_publish_end'] = date('Y-m-d H:i:s', strtotime($post['deals_publish_end']));
        }

        // ---------------------------- DURATION
        if (isset($post['deals_voucher_duration'])) {
            $data['deals_voucher_duration'] = $post['deals_voucher_duration'];
        }
        if (empty($post['deals_voucher_duration']) || is_null($post['deals_voucher_duration'])) {
            $data['deals_voucher_duration'] = null;
        }

        // ---------------------------- EXPIRED
        if (isset($post['deals_voucher_expired'])) {
            $data['deals_voucher_expired'] = $post['deals_voucher_expired'];
        }
        if (empty($post['deals_voucher_expired']) || is_null($post['deals_voucher_expired'])) {
            $data['deals_voucher_expired'] = null;
        }
        // ---------------------------- VOUCHER START
        $data['deals_voucher_start'] = $post['deals_voucher_start'] ?? null;
        // ---------------------------- POINT
        if (isset($post['deals_voucher_price_point'])) {
            $data['deals_voucher_price_point'] = $post['deals_voucher_price_point'];
        }

        if (empty($post['deals_voucher_price_point']) || is_null($post['deals_voucher_price_point'])) {
            $data['deals_voucher_price_point'] = null;
        }

        // ---------------------------- CASH
        if (isset($post['deals_voucher_price_cash'])) {
            $data['deals_voucher_price_cash'] = $post['deals_voucher_price_cash'];
        }
        if (empty($post['deals_voucher_price_cash']) || is_null($post['deals_voucher_price_cash'])) {
            $data['deals_voucher_price_cash'] = null;
        }

        if (isset($post['deals_total_voucher'])) {
            $data['deals_total_voucher'] = $post['deals_total_voucher'];
        }
        if (isset($post['deals_total_claimed'])) {
            $data['deals_total_claimed'] = $post['deals_total_claimed'];
        }
        if (isset($post['deals_total_redeemed'])) {
            $data['deals_total_redeemed'] = $post['deals_total_redeemed'];
        }
        if (isset($post['deals_total_used'])) {
            $data['deals_total_used'] = $post['deals_total_used'];
        }
        /* replaced by check filter outlet
        if (isset($post['id_outlet'])) {
            if ($post['deals_type'] == 'Promotion') {
                $data['deals_list_outlet'] = implode(',', $post['id_outlet']);
                unset($data['id_outlet']);
            }else{
                $data['id_outlet'] = $post['id_outlet'];
            }
            if (in_array("all", $post['id_outlet'])){
                $data['is_all_outlet'] = 1;
                $data['id_outlet'] = [];
            }else{
                $data['is_all_outlet'] = 0;
            }
        }*/
        if (isset($post['user_limit'])) {
            $data['user_limit'] = $post['user_limit'];
        } else {
            $data['user_limit'] = 0;
        }

        if (isset($post['is_online'])) {
            $data['is_online'] = 1;
        } else {
            $data['is_online'] = 0;
        }

        if (isset($post['is_offline'])) {
            $data['is_offline'] = 1;
        } else {
            $data['is_offline'] = 0;
            $data['deals_promo_id_type'] = null;
            $data['deals_promo_id'] = null;
        }

        if (isset($post['charged_central']) || isset($post['charged_outlet'])) {
            $data['charged_central'] = $post['charged_central'];
            $data['charged_outlet'] = $post['charged_outlet'];
        }

        if (isset($post['custom_outlet_text'])) {
            $data['custom_outlet_text'] = $post['custom_outlet_text'];
        }

        if (isset($post['id_brand'])) {
            $data['id_brand'] = $post['id_brand'];
        }

        if (isset($post['brand_rule'])) {
            $data['brand_rule'] = $post['brand_rule'];
        }

        if (isset($post['product_type'])) {
            $data['product_type'] = $post['product_type'];
        }

        if (isset($post['product_type'])) {
            $data['product_type'] = $post['product_type'];
        }

        if (isset($post['filter_outlet'])) {
            switch ($post['filter_outlet']) {
                case 'selected_outlet':
                    $data['id_outlet'] = $post['id_outlet'] ?? [];
                    $data['is_all_outlet'] = 0;
                    unset($data['id_outlet_group']);
                    break;

                case 'outlet_group':
                    $data['id_outlet_group'] = $post['id_outlet_group'] ?? [];
                    $data['is_all_outlet'] = 0;
                    unset($data['id_outlet']);
                    break;

                default:
                    $data['is_all_outlet'] = 1;
                    unset($data['id_outlet']);
                    unset($data['id_outlet_group']);
                    break;
            }
        }

        return $data;
    }

    /* CREATE */
    public function create($data)
    {
        $data = $this->checkInputan($data);
        $data['created_by'] = auth()->user()->id;
        // error
        if (isset($data['error'])) {
            unset($data['error']);
            return response()->json($data);
        }

        //for 1 brand
        if (!isset($data['id_brand'])) {
            $configBrand = Configs::where('config_name', 'use brand')->select('is_active')->first();
            if (isset($configBrand['is_active']) && $configBrand['is_active'] != '1') {
                $brand = Brand::select('id_brand')->first();
                if (isset($brand['id_brand'])) {
                    $data['id_brand'] = $brand['id_brand'];
                }
            }
        } else {
            $data_brand = $data['id_brand'];
            unset($data['id_brand']);
        }

        if ($data['deals_type'] == 'Promotion') {
            $save = DealsPromotionTemplate::create($data);
        } else {
            $save = Deal::create($data);
        }
        if ($save) {
            if (isset($data['id_outlet']) && $data['is_all_outlet'] == 0) {
                if (isset($data['id_outlet'])) {
                    $saveOutlet = $this->saveOutlet($data['deals_type'], $save, $data['id_outlet']);

                    if (!$saveOutlet) {
                        return false;
                    }
                }
            }

            if (isset($data['id_outlet_group']) && $data['is_all_outlet'] == 0) {
                if (isset($data['id_outlet_group'])) {
                    $saveOutlet = $this->saveOutletGroup($data['deals_type'], $save, $data['id_outlet_group']);

                    if (!$saveOutlet) {
                        return false;
                    }
                }
            }

            if (isset($data_brand)) {
                $save_brand = $this->saveBrand($save, $data_brand);
                if (!$save_brand) {
                    return false;
                }
            }
        }
        return $save;
    }

    /* CREATE REQUEST */
    public function createReq(Create $request)
    {
        DB::beginTransaction();
        $save = $this->create($request->json()->all());

        if ($save) {
            DB::commit();
            $dt = '';
            switch ($save->deals_type) {
                case 'Deals':
                    $dt = 'Deals';
                    break;
                case 'Hidden':
                    $dt = 'Inject Voucher';
                    break;
                case 'WelcomeVoucher':
                    $dt = 'Welcome Voucher';
                    break;
                case 'Quest':
                    $dt = 'Quest Voucher';
                    break;
            }

            if ($dt !== '') {
                $save->setAppends(['deals_shipment_text', 'deals_payment_text', 'deals_outlet_text', 'brand_rule_text']);
                $deals = $save->toArray();
                $send = app($this->autocrm)->SendAutoCRM('Create ' . $dt, $request->user()->phone, [
                    'voucher_type' => $deals['deals_voucher_type'] ?? '',
                    'promo_id_type' => $deals['deals_promo_id_type'] ?? '',
                    'promo_id' => $deals['deals_promo_id'] ?? '',
                    'detail' => view('deals::emails.detail', ['detail' => $deals])->render(),
                    'created_at' => $deals['created_at'] ? date('d F Y H:i', strtotime($deals['created_at'])) : '',
                    'updated_at' => $deals['updated_at'] ? date('d F Y H:i', strtotime($deals['updated_at'])) : '',
                ] + $deals, null, true);
            }
        } else {
            DB::rollBack();
        }

        return response()->json(MyHelper::checkCreate($save));
    }

    /* LIST */
    public function listDeal(ListDeal $request)
    {
        if ($request->json('forSelect2')) {
            $deals = Deal::select('id_deals', 'deals_title')
                    ->where('deals_type', 'Deals')
                    ->whereDoesntHave('featured_deals');

            if ($request->json('featured')) {
                $deals = $deals->where('deals_end', '>', date('Y-m-d H:i:s'))
                        ->where('deals_publish_end', '>', date('Y-m-d H:i:s'))
                        ->where('step_complete', '=', 1);
            }

            return MyHelper::checkGet($deals->get());
        }

        $deals = (new Deal())->newQuery();
        $user = $request->user();
        $curBalance = (int) $user->balance ?? 0;
        if ($request->json('admin')) {
            $deals->addSelect('id_brand', 'deals_voucher_expired');
            $deals->with('brand');
        } else {
            if ($request->json('deals_type') != 'WelcomeVoucher' && !$request->json('web')) {
                $deals->where('deals_end', '>', date('Y-m-d H:i:s'));
            }
        }
        if ($request->json('id_outlet') && is_integer($request->json('id_outlet'))) {
            $deals = $deals->join('deals_outlets', 'deals.id_deals', 'deals_outlets.id_deals')
                ->where('id_outlet', $request->json('id_outlet'))
                ->addSelect('deals.*')->distinct();
        }

        // brand
        if ($request->json('id_brand')) {
            $deals->where('id_brand', $request->json('id_brand'));
        }
        // deals subscription
        if ($request->json('deals_type') == "Subscription") {
            $deals->with('deals_subscriptions');
        }

        if ($request->json('id_deals')) {
            $deals->with([
                'deals_vouchers',
                // 'deals_vouchers.deals_voucher_user',
                // 'deals_vouchers.deals_user.user'
            ])->where('id_deals', $request->json('id_deals'))->with(['deals_content', 'deals_content.deals_content_details', 'outlets', 'outlets.city', 'product','brand']);
        } else {
            $deals->addSelect('id_deals', 'deals_title', 'deals_second_title', 'deals_voucher_price_point', 'deals_voucher_price_cash', 'deals_total_voucher', 'deals_total_claimed', 'deals_voucher_type', 'deals_image', 'deals_start', 'deals_end', 'deals_type', 'is_offline', 'is_online', 'step_complete', 'deals_total_used', 'promo_type', 'deals_promo_id_type', 'deals_promo_id');
            if (strpos($request->user()->level, 'Admin') >= 0) {
                $deals->addSelect('deals_promo_id', 'deals_publish_start', 'deals_publish_end', 'created_at');
            }
        }
        if ($request->json('rule')) {
             $this->filterList($deals, $request->json('rule'), $request->json('operator') ?? 'and');
        }
        if ($request->json('publish')) {
            $deals->where(function ($q) {
                $q->where('deals_publish_start', '<=', date('Y-m-d H:i:s'))
                    ->where('deals_publish_end', '>=', date('Y-m-d H:i:s'));
            });

            $deals->where(function ($q) {
                $q->where('deals_voucher_type', 'Unlimited')
                    ->orWhereRaw('(deals.deals_total_voucher - deals.deals_total_claimed) > 0 ');
            });
            $deals->where('step_complete', '=', 1);

            $deals->whereDoesntHave('deals_user_limits', function ($q) use ($user) {
                $q->where('id_user', $user->id);
            });
        }

        if ($request->json('deals_type')) {
            // get > 1 deals types
            if (is_array($request->json('deals_type'))) {
                $deals->whereIn('deals_type', $request->json('deals_type'));
            } else {
                $deals->where('deals_type', $request->json('deals_type'));
            }
        }

        if ($request->json('deals_type_array')) {
            // get > 1 deals types
            $deals->whereIn('deals_type', $request->json('deals_type_array'));
        }

        if ($request->json('deals_promo_id')) {
            $deals->where('deals_promo_id', $request->json('deals_promo_id'));
        }

        if ($request->json('key_free')) {
            $deals->where(function ($query) use ($request) {
                $query->where('deals_title', 'LIKE', '%' . $request->json('key_free') . '%')
                    ->orWhere('deals_second_title', 'LIKE', '%' . $request->json('key_free') . '%');
            });
        }


        /* ========================= TYPE ========================= */
        $deals->where(function ($query) use ($request) {
            // cash
            if ($request->json('voucher_type_paid')) {
                $query->orWhere(function ($amp) use ($request) {
                    $amp->whereNotNull('deals_voucher_price_cash');
                    if (is_numeric($val = $request->json('price_range_start'))) {
                        $amp->where('deals_voucher_price_cash', '>=', $val);
                    }
                    if (is_numeric($val = $request->json('price_range_end'))) {
                        $amp->where('deals_voucher_price_cash', '<=', $val);
                    }
                });
                // print_r('voucher_type_paid');
                // print_r($query->get()->toArray());die();
            }

            if ($request->json('voucher_type_point')) {
                $query->orWhere(function ($amp) use ($request) {
                    $amp->whereNotNull('deals_voucher_price_point');
                    if (is_numeric($val = $request->json('point_range_start'))) {
                        $amp->where('deals_voucher_price_point', '>=', $val);
                    }
                    if (is_numeric($val = $request->json('point_range_end'))) {
                        $amp->where('deals_voucher_price_point', '<=', $val);
                    }
                });
                // print_r('voucher_type_point');
                // print_r($query->get()->toArray());die();
            }

            if ($request->json('voucher_type_free')) {
                $query->orWhere(function ($amp) use ($request) {
                    $amp->whereNull('deals_voucher_price_point')->whereNull('deals_voucher_price_cash');
                });
                // print_r('voucher_type_free');
                // print_r($query->get()->toArray());die();
            }
        });

        // print_r($deals->get()->toArray());
        // $deals = $deals->orderBy('deals_start', 'ASC');

        if ($request->json('lowest_point')) {
            $deals->orderBy('deals_voucher_price_point', 'ASC');
        }

        if ($request->json('highest_point')) {
            $deals->orderBy('deals_voucher_price_point', 'DESC');
        }

        if ($request->json('alphabetical')) {
            $deals->orderBy('deals_title', 'ASC');
        } elseif ($request->json('newest')) {
            $deals->orderBy('deals_publish_start', 'DESC');
        } elseif ($request->json('oldest')) {
            $deals->orderBy('deals_publish_start', 'ASC');
        } elseif ($request->json('updated_at')) {
            $deals->orderBy('updated_at', 'DESC');
        } else {
            $deals->orderBy('deals_end', 'ASC');
        }
        if ($request->json('id_city')) {
            $deals->with('outlets', 'outlets.city');
        }

        if ($request->json('paginate') && $request->json('admin')) {
            return $this->dealsPaginate($deals, $request);
        }

        $deals = $deals->get()->toArray();
        // print_r($deals); exit();

        if (!empty($deals)) {
            $city = "";

            // jika ada id city yg faq
            if ($request->json('id_city')) {
                $city = $request->json('id_city');
            }

            $deals = $this->kotacuks($deals, $city, $request->json('admin'));
        }

        if ($request->json('highest_available_voucher')) {
            $tempDeals = [];
            $dealsUnlimited = $this->unlimited($deals);

            if (!empty($dealsUnlimited)) {
                foreach ($dealsUnlimited as $key => $value) {
                    array_push($tempDeals, $deals[$key]);
                }
            }

            $limited = $this->limited($deals);

            if (!empty($limited)) {
                $tempTempDeals = [];
                foreach ($limited as $key => $value) {
                    array_push($tempTempDeals, $deals[$key]);
                }

                $tempTempDeals = $this->highestAvailableVoucher($tempTempDeals);

                $tempDeals =  array_merge($tempDeals, $tempTempDeals);
            }

            $deals = $tempDeals;
        }

        if ($request->json('lowest_available_voucher')) {
            $tempDeals = [];

            $limited = $this->limited($deals);

            if (!empty($limited)) {
                foreach ($limited as $key => $value) {
                    array_push($tempDeals, $deals[$key]);
                }

                $tempDeals = $this->lowestAvailableVoucher($tempDeals);
            }

            $dealsUnlimited = $this->unlimited($deals);

            if (!empty($dealsUnlimited)) {
                foreach ($dealsUnlimited as $key => $value) {
                    array_push($tempDeals, $deals[$key]);
                }
            }

            $deals = $tempDeals;
        }



        // if deals detail, add webview url & btn text
        if ($request->json('id_deals') && !empty($deals)) {
            //url webview
            $deals[0]['webview_url'] = config('url.app_url') . "webview/deals/" . $deals[0]['id_deals'] . "/" . $deals[0]['deals_type'];
            // text tombol beli
            $deals[0]['button_status'] = 0;
            //text konfirmasi pembelian
            if ($deals[0]['deals_voucher_price_type'] == 'free') {
                //voucher free
                $deals[0]['button_text'] = 'Ambil';
                $payment_message = Setting::where('key', 'payment_messages')->pluck('value_text')->first() ?? 'Kamu yakin ingin mengambil voucher ini?';
                $payment_message = MyHelper::simpleReplace($payment_message, ['deals_title' => $deals[0]['deals_title']]);
            } elseif ($deals[0]['deals_voucher_price_type'] == 'point') {
                $deals[0]['button_text'] = 'Tukar';
                $payment_message = Setting::where('key', 'payment_messages_point')->pluck('value_text')->first() ?? 'Anda akan menukarkan %point% points anda dengan Voucher %deals_title%?';
                $payment_message = MyHelper::simpleReplace($payment_message, ['point' => $deals[0]['deals_voucher_price_point'],'deals_title' => $deals[0]['deals_title']]);
            } else {
                $deals[0]['button_text'] = 'Beli';
                $payment_message = Setting::where('key', 'payment_messages_cash')->pluck('value_text')->first() ?? 'Anda akan membeli Voucher %deals_title% dengan harga %cash% ?';
                $payment_message = MyHelper::simpleReplace($payment_message, ['cash' => $deals[0]['deals_voucher_price_cash'],'deals_title' => $deals[0]['deals_title']]);
            }
            $payment_success_message = Setting::where('key', 'payment_success_messages')->pluck('value_text')->first() ?? 'Apakah kamu ingin menggunakan Voucher sekarang?';
            $deals[0]['payment_message'] = $payment_message;
            $deals[0]['payment_success_message'] = $payment_success_message;
            if ($deals[0]['deals_voucher_price_type'] == 'free' && $deals[0]['deals_status'] == 'available') {
                $deals[0]['button_status'] = 1;
            } else {
                if ($deals[0]['deals_voucher_price_type'] == 'point') {
                    $deals[0]['button_status'] = $deals[0]['deals_voucher_price_point'] <= $curBalance ? 1 : 0;
                    if ($deals[0]['deals_voucher_price_point'] > $curBalance) {
                        $deals[0]['payment_fail_message'] = Setting::where('key', 'payment_fail_messages')->pluck('value_text')->first() ?? 'Mohon maaf, point anda tidak cukup';
                    }
                } else {
                    if ($deals[0]['deals_status'] == 'available') {
                        $deals[0]['button_status'] = 1;
                    }
                }
            }
        }

        //jika mobile di pagination
        if (!$request->json('web')) {
            //pagination
            if ($request->get('page')) {
                $page = $request->get('page');
            } else {
                $page = 1;
            }

            $resultData = [];
            $paginate   = 10;
            $start      = $paginate * ($page - 1);
            $all        = $paginate * $page;
            $end        = $all;
            $next       = true;

            if ($all > count($deals)) {
                $end = count($deals);
                $next = false;
            }


            for ($i = $start; $i < $end; $i++) {
                $deals[$i]['time_to_end'] = strtotime($deals[$i]['deals_end']) - time();
                array_push($resultData, $deals[$i]);
            }

            $result['current_page']  = $page;
            $result['data']          = $resultData;
            $result['total']         = count($resultData);
            $result['next_page_url'] = null;
            if ($next == true) {
                $next_page = (int) $page + 1;
                $result['next_page_url'] = ENV('APP_API_URL') . 'api/deals/list?page=' . $next_page;
            }


            // print_r($deals); exit();
            if (!$result['total']) {
                $result = [];
            }

            if (
                $request->json('voucher_type_point') ||
                $request->json('voucher_type_paid') ||
                $request->json('voucher_type_free') ||
                $request->json('id_city') ||
                $request->json('key_free')
            ) {
                $resultMessage = 'Maaf, voucher yang kamu cari belum tersedia';
            } else {
                $resultMessage = 'Nantikan penawaran menarik dari kami';
            }
            return response()->json(MyHelper::checkGet($result, $resultMessage));
        } else {
            return response()->json(MyHelper::checkGet($deals));
        }
    }

    /* list of deals that haven't ended yet */
    public function listActiveDeals(Request $request)
    {
        $post = $request->json()->all();

        $deals = Deal::where('deals_type', 'Deals')
                ->where('deals_end', '>', date('Y-m-d H:i:s'))
                ->where('step_complete', '=', 1)
                ->orderBy('updated_at', 'DESC');

        if (isset($post['select'])) {
            $deals = $deals->select($post['select']);
        }
        $deals = $deals->get();
        return response()->json(MyHelper::checkGet($deals));
    }

    /* LIST */
    public function myDeal(Request $request)
    {
        $post = $request->json()->all();
        $user = $request->user();

        $deals = DealsUser::with(['deals_voucher.deal'])
        ->where('id_user', $user['id'])
        ->where('id_deals_user', $post['id_deals_user'])
        ->whereNull('redeemed_at')
        ->whereIn('paid_status', ['Completed','Free'])
        ->first();

        return response()->json(MyHelper::checkGet($deals));
    }
    public function filterList($query, $rules, $operator = 'and')
    {
        $newRule = [];
        foreach ($rules as $var) {
            $rule = [$var['operator'] ?? '=',$var['parameter']];
            if ($rule[0] == 'like') {
                $rule[1] = '%' . $rule[1] . '%';
            }
            $newRule[$var['subject']][] = $rule;
        }
        $where = $operator == 'and' ? 'where' : 'orWhere';
        $subjects = ['deals_title','deals_title','deals_second_title','deals_promo_id_type','deals_promo_id','id_brand','deals_total_voucher','deals_start', 'deals_end', 'deals_publish_start', 'deals_publish_end', 'deals_voucher_start', 'deals_voucher_expired', 'deals_voucher_duration', 'user_limit', 'total_voucher_subscription', 'deals_total_claimed', 'deals_total_redeemed', 'deals_total_used', 'created_at', 'updated_at'];
        foreach ($subjects as $subject) {
            if ($rules2 = $newRule[$subject] ?? false) {
                foreach ($rules2 as $rule) {
                    $query->$where($subject, $rule[0], $rule[1]);
                }
            }
        }
        if ($rules2 = $newRule['voucher_code'] ?? false) {
            foreach ($rules2 as $rule) {
                $query->{$where . 'Has'}('deals_vouchers', function ($query) use ($rule) {
                    $query->where('deals_vouchers.voucher_code', $rule[0], $rule[1]);
                });
            }
        }
        if ($rules2 = $newRule['used_by'] ?? false) {
            foreach ($rules2 as $rule) {
                $query->{$where . 'Has'}('deals_vouchers.deals_voucher_user', function ($query) use ($rule) {
                    $query->where('phone', $rule[0], $rule[1]);
                });
            }
        }
        if ($rules2 = $newRule['deals_total_available'] ?? false) {
            foreach ($rules2 as $rule) {
                $query->$where(DB::raw('(deals.deals_total_voucher - deals.deals_total_claimed)'), $rule[0], $rule[1]);
            }
        }
        if ($rules2 = $newRule['id_outlet'] ?? false) {
            foreach ($rules2 as $rule) {
                $query->{$where . 'Has'}('outlets', function ($query) use ($rule) {
                    $query->where('outlets.id_outlet', $rule[0], $rule[1]);
                });
            }
        }
        if ($rules2 = $newRule['voucher_claim_time'] ?? false) {
            foreach ($rules2 as $rule) {
                $rule[1] = strtotime($rule[1]);
                $query->{$where . 'Has'}('deals_vouchers', function ($query) use ($rule) {
                    $query->whereHas('deals_user', function ($query) use ($rule) {
                        $query->where(DB::raw('UNIX_TIMESTAMP(deals_users.claimed_at)'), $rule[0], $rule[1]);
                    });
                });
            }
        }
        if ($rules2 = $newRule['voucher_redeem_time'] ?? false) {
            foreach ($rules2 as $rule) {
                $rule[1] = strtotime($rule[1]);
                $query->{$where . 'Has'}('deals_vouchers', function ($query) use ($rule) {
                    $query->whereHas('deals_user', function ($query) use ($rule) {
                        $query->where('deals_users.redeemed_at', $rule[0], $rule[1]);
                    });
                });
            }
        }
        if ($rules2 = $newRule['voucher_used_time'] ?? false) {
            foreach ($rules2 as $rule) {
                $rule[1] = strtotime($rule[1]);
                $query->{$where . 'Has'}('deals_vouchers', function ($query) use ($rule) {
                    $query->whereHas('deals_user', function ($query) use ($rule) {
                        $query->where('deals_users.used_at', $rule[0], $rule[1]);
                    });
                });
            }
        }
    }
    /* UNLIMITED */
    public function unlimited($deals)
    {
        $unlimited = array_filter(array_column($deals, "available_voucher"), function ($deals) {
            if ($deals == "*") {
                return $deals;
            }
        });

        return $unlimited;
    }

    public function limited($deals)
    {
        $limited = array_filter(array_column($deals, "available_voucher"), function ($deals) {
            if ($deals != "*") {
                return $deals;
            }
        });

        return $limited;
    }

    /* SORT DEALS */
    public function highestAvailableVoucher($deals)
    {
        usort($deals, function ($a, $b) {
            return $a['available_voucher'] < $b['available_voucher'];
        });

        return $deals;
    }

    public function lowestAvailableVoucher($deals)
    {
        usort($deals, function ($a, $b) {
            return $a['available_voucher'] > $b['available_voucher'];
        });

        return $deals;
    }

    /* INI LIST KOTA */
    public function kotacuks($deals, $city = "", $admin = false)
    {
        $timeNow = date('Y-m-d H:i:s');

        foreach ($deals as $key => $value) {
            $markerCity = 0;

            $deals[$key]['outlet_by_city'] = [];

            // set time
            $deals[$key]['time_server'] = $timeNow;

            if (!empty($value['outlets'])) {
                // ambil kotanya dulu
                $kota = array_column($value['outlets'], 'city');
                $kota = array_values(array_map("unserialize", array_unique(array_map("serialize", $kota))));

                // jika ada pencarian kota
                if (!empty($city)) {
                    $cariKota = array_search($city, array_column($kota, 'id_city'));

                    if (is_integer($cariKota)) {
                        $markerCity = 1;
                    }
                }

                foreach ($kota as $k => $v) {
                    if ($v) {
                        $kota[$k]['outlet'] = [];

                        foreach ($value['outlets'] as $outlet) {
                            if ($v['id_city'] == $outlet['id_city']) {
                                unset($outlet['pivot']);
                                unset($outlet['city']);

                                array_push($kota[$k]['outlet'], $outlet);
                            }
                        }
                    } else {
                        unset($kota[$k]);
                    }
                }

                $deals[$key]['outlet_by_city'] = $kota;
            }

            // unset($deals[$key]['outlets']);
            // jika ada pencarian kota
            if (!empty($city)) {
                if ($markerCity == 0) {
                    unset($deals[$key]);
                    continue;
                }
            }

            $calc = $value['deals_total_voucher'] - $value['deals_total_claimed'];

            if ($value['deals_voucher_type'] == "Unlimited") {
                $calc = '*';
            }

            if (is_numeric($calc) && $value['deals_total_voucher'] !== 0) {
                if ($calc || $admin) {
                    $deals[$key]['percent_voucher'] = $calc * 100 / $value['deals_total_voucher'];
                } else {
                    unset($deals[$key]);
                    continue;
                }
            } else {
                $deals[$key]['percent_voucher'] = 100;
            }

            $deals[$key]['show'] = 1;
            $deals[$key]['available_voucher'] = (string) $calc;
            $deals[$key]['available_voucher_text'] = "";
            if ($calc != "*") {
                $deals[$key]['available_voucher_text'] = $calc . " kupon tersedia";
            }
            // deals masih ada?
            // print_r($deals[$key]['available_voucher']);
        }

        // print_r($deals); exit();
        $deals = array_values($deals);

        return $deals;
    }

    /* LIST USER */
    public function listUserVoucher(Request $request)
    {
        $post = $request->json()->all();
        $deals = DealsUser::join('deals_vouchers', 'deals_vouchers.id_deals_voucher', '=', 'deals_users.id_deals_voucher');

        if ($request->json('id_deals')) {
            $deals->where('deals_vouchers.id_deals', $request->json('id_deals'));
        }

        if ($request->json('rule')) {
             $this->filterUserVoucher($deals, $request->json('rule'), $request->json('operator') ?? 'and');
        }

        $deals = $deals->with([
                    'user',
                    'outlet',
                    'dealVoucher.transaction_voucher' => function ($q) {
                        $q->where('status', '=', 'success');
                    },
                    'dealVoucher.transaction_voucher.transaction' => function ($q) {
                        $q->select(
                            'id_transaction',
                            'transaction_receipt_number',
                            'trasaction_type',
                            'transaction_grandtotal'
                        );
                    }
                ]);
        $data = $deals->orderBy('claimed_at', "DESC")->paginate(10)->toArray();
        $data['data'] = $deals->paginate(10)
                        ->each(function ($q) {
                            $q->setAppends([
                                'get_transaction'
                            ]);
                        })
                        ->toArray();

        return response()->json(MyHelper::checkGet($data));
    }

    /* FILTER LIST USER VOUCHER */
    public function filterUserVoucher($query, $rules, $operator = 'and')
    {
        $newRule = [];
        foreach ($rules as $var) {
            $rule = [$var['operator'] ?? '=',$var['parameter']];
            if ($rule[0] == 'like') {
                $rule[1] = '%' . $rule[1] . '%';
            }
            $newRule[$var['subject']][] = $rule;
        }

        $where = $operator == 'and' ? 'where' : 'orWhere';

        if ($rules2 = $newRule['status'] ?? false) {
            foreach ($rules2 as $rule) {
                if ($rule[1] == 'used') {
                    $query->{$where . 'NotNull'}('used_at');
                } elseif ($rule[1] == 'expired') {
                    $query->$where(function ($q) {
                        $q->whereNotNull('voucher_expired_at')
                            ->whereDate('voucher_expired_at', '<', date("Y-m-d H:i:s"));
                    });
                } elseif ($rule[1] == 'redeemed') {
                    $query->{$where . 'NotNull'}('redeemed_at');
                } else {
                    $query->{$where . 'NotNull'}('claimed_at');
                }
            }
        }
        if ($rules2 = $newRule['used_by'] ?? false) {
            foreach ($rules2 as $rule) {
                $query->{$where . 'Has'}('user', function ($query) use ($rule) {
                    $query->where('phone', $rule[0], $rule[1]);
                });
            }
        }
        if ($rules2 = $newRule['claim_date'] ?? false) {
            foreach ($rules2 as $rule) {
                $query->{$where . 'Date'}('claimed_at', $rule[0], date("Y-m-d H:i:s", strtotime($rule[1])));
            }
        }
        if ($rules2 = $newRule['id_outlet'] ?? false) {
            foreach ($rules2 as $rule) {
                $query->{$where . 'Has'}('outlet', function ($query) use ($rule) {
                    $query->where('outlets.id_outlet', $rule[0], $rule[1]);
                });
            }
        }
    }

    /* LIST VOUCHER */
    public function listVoucher(Request $request)
    {

        if ($request->select) {
            $deals = DealsVoucher::select($request->select);
        } else {
            $deals = DealsVoucher::select('*');
        }

        if ($request->json('id_deals')) {
            $deals->where('id_deals', $request->json('id_deals'));
        }

        if ($request->is_all) {
            $deals = $deals->get();
        } else {
            $deals = $deals->paginate(10);
        }

        return response()->json(MyHelper::checkGet($deals));
    }

    /* UPDATE */
    public function update($id, $data)
    {
        $data = $this->checkInputan($data);

        $deals = Deal::find($id);
        $data['step_complete'] = 0;
        $data['last_updated_by'] = auth()->user()->id;

        $del_rule   = false;
        if ($data['id_brand'] && is_array($data['id_brand'])) {
            $brand_now  = $deals->deals_brands->pluck('id_brand')->toArray();
            $brand_new  = $data['id_brand'];
            $data_brand = $brand_new;

            $check_brand = array_merge(array_diff($brand_now, $brand_new), array_diff($brand_new, $brand_now));

            if (!empty($check_brand)) {
                $del_rule = true;
            }
        }

        if (
            $data['is_online'] == 0
            || (isset($data['id_brand'])
                && ((!is_array($data['id_brand']) && $data['id_brand'] != $deals['id_brand'])
                    || (is_array($data['id_brand']) && $del_rule)
                    )
                )
        ) {
            app($this->promo_campaign)->deleteAllProductRule('deals', $id);
        }

        if (($data['deals_voucher_type'] ?? false) != 'List Vouchers') {
            DealsVoucher::where('id_deals', $id)->delete();
        }

        if (!empty($deals['deals_total_claimed'])) {
            return false;
        }
        // error
        if (isset($data['error'])) {
            unset($data['error']);
            return response()->json($data);
        }

        // delete old images
        if (isset($data['deals_image'])) {
            $this->deleteImage($id);
        }

        //delete outlet
        $this->deleteOutlet('deals', $id);

        // save outlet
        if (isset($data['id_outlet'])) {
            if ($data['is_all_outlet'] == 0) {
                $saveOutlet = $this->saveOutlet('deals', $deals, $data['id_outlet']);
            }
            unset($data['id_outlet']);
        }

        // save outlet group
        if (isset($data['id_outlet_group'])) {
            if ($data['is_all_outlet'] == 0) {
                $saveOutlet = $this->saveOutletGroup('deals', $deals, $data['id_outlet_group']);
            }
            unset($data['id_outlet_group']);
        }

        if (isset($data['id_brand'])) {
            $save_brand = $this->saveBrand($deals, $data['id_brand']);
            unset($data['id_brand']);
            if (!$save_brand) {
                return false;
            }
        }

        $save = Deal::where('id_deals', $id)->update($data);

        return $save;
    }

    /* DELETE IMAGE */
    public function deleteImage($id)
    {
        $cekImage = Deal::where('id_deals', $id)->get()->first();

        if (!empty($cekImage)) {
            if (!empty($cekImage->deals_image)) {
                $delete = MyHelper::deletePhoto($cekImage->deals_image);
            }
        }
        return true;
    }

    /* UPDATE REQUEST */
    public function updateReq(Update $request)
    {
        DB::beginTransaction();
        if ($request->json('id_deals')) {
            $save = $this->update($request->json('id_deals'), $request->json()->all());

            if ($save) {
                DB::commit();
                $dt = '';
                switch (strtolower($request->json('deals_type'))) {
                    case 'deals':
                        $dt = 'Deals';
                        break;
                    case 'hidden':
                        $dt = 'Inject Voucher';
                        break;
                    case 'welcomevoucher':
                        $dt = 'Welcome Voucher';
                        break;
                    case 'quest':
                        $dt = 'Quest Voucher';
                        break;
                }

                if ($dt !== '') {
                    $deals = Deal::where('id_deals', $request->json('id_deals'))
                        ->first();
                    $deals->setAppends(['deals_shipment_text', 'deals_payment_text', 'deals_outlet_text', 'brand_rule_text']);
                    $deals = $deals->toArray();

                    $send = app($this->autocrm)->SendAutoCRM('Update ' . $dt, $request->user()->phone, [
                        'voucher_type' => $deals['deals_voucher_type'] ?: '',
                        'promo_id_type' => $deals['deals_promo_id_type'] ?: '',
                        'promo_id' => $deals['deals_promo_id'] ?: '',
                        'detail' => view('deals::emails.detail', ['detail' => $deals])->render(),
                        'created_at' => $deals['created_at'] ? date('d F Y H:i', strtotime($deals['created_at'])) : '-',
                        'updated_at' => $deals['updated_at'] ? date('d F Y H:i', strtotime($deals['updated_at'])) : '-',
                    ] + $deals, null, true);
                }
                return response()->json(MyHelper::checkUpdate($save));
            } else {
                DB::rollBack();
                return response()->json(['status' => 'fail','messages' => ['Cannot update deals because someone has already claimed a voucher']]);
            }
        } else {
            $save = $this->updatePromotionDeals($request->json('id_deals_promotion_template'), $request->json()->all());

            if ($save) {
                DB::commit();
                return response()->json(MyHelper::checkUpdate($save));
            } else {
                DB::rollBack();
                return response()->json(['status' => 'fail','messages' => ['Update Promotion Deals Failed']]);
            }
        }
    }

    /* DELETE */
    public function delete($id)
    {
        // delete outlet
        DealsOutlet::where('id_deals', $id)->delete();

        $delete = Deal::where('id_deals', $id)->delete();
        return $delete;
    }

    /* DELETE REQUEST */
    public function deleteReq(Delete $request)
    {
        DB::beginTransaction();

        // check spin the wheel
        if ($request->json('deals_type') !== null && $request->json('deals_type') == "Spin") {
            $spin = SpinTheWheel::where('id_deals', $request->json('id_deals'))->first();
            if ($spin != null) {
                return response()->json([
                    'status'   => 'fail',
                    'messages' => ['Item already used in Spin The Wheel Setting.']
                ]);
            }
        }

        $check = $this->checkDelete($request->json('id_deals'));
        if ($check) {
            // delete image first
            $this->deleteImage($request->json('id_deals'));

            $delete = $this->delete($request->json('id_deals'));

            if ($delete) {
                DB::commit();
            } else {
                DB::rollBack();
            }

            return response()->json(MyHelper::checkDelete($delete));
        } else {
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Deal already used.']
            ]);
        }
    }

    /* CHECK DELETE */
    public function checkDelete($id)
    {
        $database = [
            'deals_vouchers',
            'deals_payment_manuals',
            'deals_payment_midtrans',
        ];

        foreach ($database as $val) {
            // check apakah ada atau nggak tablenya
            if (Schema::hasTable($val)) {
                $cek = DB::table($val);

                if ($val == "deals_vouchers") {
                    $cek->where('deals_voucher_status', '=', 'Sent');
                }

                $cek = $cek->where('id_deals', $id)->first();

                if (!empty($cek)) {
                    return false;
                }
            }
        }

        return true;
    }

    /* OUTLET */
    public function saveOutlet($deals_type, $deals, $id_outlet = [])
    {
        if ($deals_type == 'Promotion') {
            $id_deals = $deals->id_deals_promotion_template;
            $outlet_table = new DealsPromotionOutlet();
        } else {
            $id_deals = $deals->id_deals;
            $outlet_table = new DealsOutlet();
        }

        $dataOutlet = [];
        foreach ($id_outlet as $value) {
            array_push($dataOutlet, [
                'id_outlet' => $value,
                'id_deals'  => $id_deals
            ]);
        }

        if (!empty($dataOutlet)) {
            $save = $outlet_table::insert($dataOutlet);

            return $save;
        } else {
            return false;
        }

        return true;
    }

    /* OUTLET */
    public function saveOutletGroup($deals_type, $deals, $id_outlet_group = [])
    {

        if ($deals_type == 'Promotion') {
            $id_deals = $deals->id_deals_promotion_template;
            $outlet_group_table = new DealsPromotionOutletGroup();
        } else {
            $id_deals = $deals->id_deals;
            $outlet_group_table = new DealsOutletGroup();
        }

        $data_outlet = [];
        foreach ($id_outlet_group as $value) {
            array_push($data_outlet, [
                'id_outlet_group' => $value,
                'id_deals'  => $id_deals
            ]);
        }

        if (!empty($data_outlet)) {
            $save = $outlet_group_table::insert($data_outlet);

            return $save;
        } else {
            return false;
        }

        return true;
    }

    /* DELETE OUTLET */
    public function deleteOutlet($deals_type, $id_deals)
    {
        if ($deals_type == 'Promotion') {
            $delete = DealsPromotionOutlet::where('id_deals', $id_deals)->delete();
            $delete = DealsPromotionOutletGroup::where('id_deals', $id_deals)->delete();
        } else {
            $delete = DealsOutlet::where('id_deals', $id_deals)->delete();
            $delete = DealsOutletGroup::where('id_deals', $id_deals)->delete();
        }

        return $delete;
    }

    public function saveBrand($deals, $id_brand)
    {

        if (isset($deals->id_deals_promotion_template)) {
            $table = new DealsPromotionBrand();
            $id_deals = $deals->id_deals_promotion_template;
        } else {
            $table = new DealsBrand();
            $id_deals = $deals->id_deals;
        }
        $delete = $table::where('id_deals', $id_deals)->delete();

        $data_brand = [];

        foreach ($id_brand as $value) {
            array_push($data_brand, [
                'id_brand'  => $value,
                'id_deals'  => $id_deals
            ]);
        }

        if (!empty($data_brand)) {
            $save = $table::insert($data_brand);

            return $save;
        } else {
            return false;
        }

        return true;
    }

    /*Welcome Voucher*/
    public function listDealsWelcomeVoucher(Request $request)
    {
        $getDeals = Deal::where('deals_type', 'WelcomeVoucher')
            ->select('deals.*')
            ->get()->toArray();
        $configUseBrand = Configs::where('config_name', 'use brand')->first();

        if ($configUseBrand['is_active']) {
            foreach ($getDeals as $key => $data) {
                $brands = DealsBrand::leftJoin('brands', 'brands.id_brand', 'deals_brands.id_brand')
                            ->where('id_deals', $data['id_deals'])
                            ->pluck('brands.name_brand')->toArray();
                $brands = array_filter($brands);
                $stringName = '';
                if (!empty($brands)) {
                    $stringName = '(' . implode(',', $brands) . ')';
                }
                $getDeals[$key]['name_brand'] = $stringName;
            }
        }

        $result = [
            'status' => 'success',
            'result' => $getDeals
        ];
        return response()->json($result);
    }

    public function welcomeVoucherSetting(Request $request)
    {
        $setting = Setting::where('key', 'welcome_voucher_setting')->first();
        $configUseBrand = Configs::where('config_name', 'use brand')->first();
        $getDeals = DealTotal::join('deals', 'deals.id_deals', 'deals_total.id_deals')
            ->select('deals.*', 'deals_total.deals_total')
            ->get()->toArray();

        if ($configUseBrand['is_active']) {
            foreach ($getDeals as $key => $data) {
                $brands = DealsBrand::leftJoin('brands', 'brands.id_brand', 'deals_brands.id_brand')
                            ->where('id_deals', $data['id_deals'])
                            ->pluck('brands.name_brand')->toArray();
                $brands = array_filter($brands);
                $stringName = '';
                if (!empty($brands)) {
                    $stringName = '(' . implode(',', $brands) . ')';
                }
                $getDeals[$key]['name_brand'] = $stringName;
            }
        }

        $result = [
            'status' => 'success',
            'data' => [
                'setting' => $setting,
                'deals' => $getDeals
            ]
        ];
        return response()->json($result);
    }

    public function welcomeVoucherSettingUpdate(Request $request)
    {
        $post = $request->json()->all();

        $deleteDealsTotal = DB::table('deals_total')->delete();//Delete all data from tabel deals total

        //insert data
        $arrInsert = [];
        $list_id = $post['list_deals_id'];
        $list_deals_total = $post['list_deals_total'];
        $count = count($list_id);

        for ($i = 0; $i < $count; $i++) {
            $data = [
                'id_deals' => $list_id[$i],
                'deals_total' => $list_deals_total[$i],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            array_push($arrInsert, $data);
        }

        $insert = DealTotal::insert($arrInsert);
        if ($insert) {
            $result = [
                'status' => 'success'
            ];
        } else {
            $result = [
                'status' => 'fail'
            ];
        }

        return response()->json($result);
    }

    public function welcomeVoucherSettingUpdateStatus(Request $request)
    {
        $post = $request->json()->all();
        $status = $post['status'];
        $updateStatus = Setting::where('key', 'welcome_voucher_setting')->update(['value' => $status]);

        return response()->json(MyHelper::checkUpdate($updateStatus));
    }

    public function injectWelcomeVoucher($user, $phone)
    {
        $now = date("Y-m-d H:i:s");
        $getDeals = DealTotal::join('deals', 'deals.id_deals', '=', 'deals_total.id_deals')
                    ->where('deals_start', "<", $now)
                    ->where('deals_end', ">", $now)
                    ->where('step_complete', '=', '1')
                    ->where(function ($q) {
                        $q->where('deals_total_voucher', '0')
                        ->orWhereColumn('deals_total_claimed', '<', 'deals_total_voucher');
                    })
                    ->select('deals.*', 'deals_total.deals_total')->get();

        if (!$getDeals->isEmpty()) {
            $getDeals = $getDeals->toArray();
            $data = [
                'deals' => $getDeals,
                'user'  => $user,
                'phone' => $phone
            ];
            SendDealsJob::dispatch($data)->allOnConnection('dealsqueue');
        }

        return true;
    }

    public function detail(DetailDealsRequest $request)
    {
        $post = $request->json()->all();
        $user = $request->user();

        $deals = $this->getDealsData($post['id_deals'], $post['step'], $post['deals_type']);

        if (isset($deals)) {
            $deals = $deals->toArray();
        } else {
            $deals = false;
        }

        if ($deals) {
            $result = [
                'status'  => 'success',
                'result'  => $deals
            ];
        } else {
            $result = [
                'status'  => 'fail',
                'messages'  => ['Deals Not Found']
            ];
        }

        return response()->json($result);
    }

    public function getDealsData($id_deals, $step, $deals_type = 'Deals')
    {
        $post['id_deals'] = $id_deals;
        $post['step'] = $step;
        $post['deals_type'] = $deals_type;

        if ($deals_type == 'Promotion' || $deals_type == 'deals_promotion') {
            $deals = DealsPromotionTemplate::where('id_deals_promotion_template', '=', $post['id_deals']);
            $table = 'deals_promotion';
        } else {
            if ($deals_type == 'promotion-deals') {
                $post['deals_type'] = 'promotion';
            }
            $deals = Deal::where('id_deals', '=', $post['id_deals'])->where('deals_type', '=', $post['deals_type']);
            $table = 'deals';
        }

        if (($post['step'] == 1 || $post['step'] == 'all')) {
            $deals = $deals->with(['outlets', 'outlet_groups']);
        }

        if (($post['step'] == 1 || $post['step'] == 'all')) {
            $deals = $deals->with([$table . '_brands']);
        }

        if ($post['step'] == 2 || $post['step'] == 'all') {
            $deals = $deals->with([
                $table . '_product_discount.product',
                $table . '_product_discount.brand',
                $table . '_product_discount.product_variant_pivot.product_variant',
                $table . '_product_discount_rules',
                $table . '_tier_discount_product.product',
                $table . '_tier_discount_product.brand',
                $table . '_tier_discount_product.product_variant_pivot.product_variant',
                $table . '_tier_discount_rules',
                $table . '_buyxgety_product_requirement.product',
                $table . '_buyxgety_product_requirement.brand',
                $table . '_buyxgety_product_requirement.product_variant_pivot.product_variant',
                $table . '_buyxgety_rules.product',
                $table . '_buyxgety_rules.brand',
                $table . '_buyxgety_rules.product_variant_pivot.product_variant',
                $table . '_buyxgety_rules.deals_buyxgety_product_modifiers.modifier',
                $table . '_discount_bill_rules',
                $table . '_discount_bill_products.product',
                $table . '_discount_bill_products.brand',
                $table . '_discount_bill_products.product_variant_pivot.product_variant',
                $table . '_discount_delivery_rules',
                $table . '_shipment_method',
                $table . '_payment_method',
                'brand',
                'brands',
                'created_by_user' => function ($q) {
                    $q->select('id', 'name', 'level');
                }
            ]);
        }

        if ($post['step'] == 3 || $post['step'] == 'all') {
            $deals = $deals->with([$table . '_content.' . $table . '_content_details']);
        }

        if ($post['step'] == 'all') {
            // $deals = $deals->with(['created_by_user']);
        }

        $deals = $deals->first();

        if ($deals) {
            if ($post['step'] == 'all' && $deals_type != 'Promotion' && $deals_type != 'promotion-deals') {
                $deals_array = $deals->toArray();
                if ($deals_type == 'Deals' || $deals_type == 'Hidden' || $deals_type == 'WelcomeVoucher' || $deals_type == 'Quest') {
                    $type = 'deals';
                } else {
                    $type = $deals_type;
                }
                $getProduct = app($this->promo_campaign)->getProduct($type, $deals_array);
                $desc = app($this->promo_campaign)->getPromoDescription($type, $deals_array, $getProduct['product'] ?? '', true);
                $deals['description'] = $desc;
            }
        }

        if ($deals_type != 'Promotion' && $post['step'] == 'all') {
            $used_voucher = DealsVoucher::join('transaction_vouchers', 'deals_vouchers.id_deals_voucher', 'transaction_vouchers.id_deals_voucher')
                            ->where('id_deals', $deals->id_deals)
                            ->where('transaction_vouchers.status', 'success')
                            ->count();
            $deals->deals_total_used = $used_voucher;
        }

        return $deals;
    }

    public function updateContent(UpdateContentRequest $request)
    {
        $post = $request->json()->all();

        db::beginTransaction();

        if ($post['deals_type'] != 'Promotion') {
            $source = 'deals';
            $check = Deal::where('id_deals', '=', $post['id_deals'])->first();
            /* change to can update content deals even after someone has already claimed a voucher
            if (!empty($check['deals_total_claimed']) ) {
                return [
                    'status'  => 'fail',
                    'messages' => 'Cannot update deals because someone has already claimed a voucher'
                ];
            }*/
        } else {
            $source = 'deals_promotion';
            $check = DealsPromotionTemplate::where('id_deals_promotion_template', '=', $post['id_deals'])->first();
        }

        if (empty($check)) {
            return [
                'status'  => 'fail',
                'messages' => 'Deals not found'
            ];
        }

        // $update = app($this->subscription)->createOrUpdateContent($post, $source);
        $update = $this->updateContentV2($post, $source);
        if ($update) {
            if ($post['deals_type'] != 'Promotion') {
                $update = Deal::where('id_deals', '=', $post['id_deals'])->update(['deals_description' => $post['deals_description'], 'last_updated_by' => auth()->user()->id]);
            } else {
                $update = DealsPromotionTemplate::where('id_deals_promotion_template', '=', $post['id_deals'])->update(['deals_description' => $post['deals_description'], 'step_complete' => 0, 'last_updated_by' => auth()->user()->id]);
            }

            if ($update) {
                DB::commit();
            } else {
                DB::rollBack();
                return  response()->json([
                    'status'   => 'fail',
                    'messages' => 'Update Deals failed'
                ]);
            }
        } else {
            DB::rollBack();
            return  response()->json([
                'status'   => 'fail',
                'messages' => 'Update Deals failed'
            ]);
        }

         return response()->json(MyHelper::checkUpdate($update));
    }

    /*============================= Start Filter & Sort V2 ================================*/
    public function listDealV2(Request $request)
    {
        $deals = (new Deal())->newQuery();
        $deals->where('deals_type', '!=', 'WelcomeVoucher');
        $deals->where(function ($q) {
            $q->where('deals_publish_start', '<=', date('Y-m-d H:i:s'))
            ->where('deals_publish_end', '>=', date('Y-m-d H:i:s'))
            ->where('deals_end', '>=', date('Y-m-d H:i:s'));
        });
        $deals->where(function ($q) {
            $q->where('deals_voucher_type', 'Unlimited')
                ->orWhereRaw('(deals.deals_total_voucher - deals.deals_total_claimed) > 0 ');
        });
        $deals->where('step_complete', '=', 1);

        if ($request->json('id_outlet') && is_integer($request->json('id_outlet'))) {
            $deals->leftJoin('deals_outlets', 'deals.id_deals', 'deals_outlets.id_deals')
                ->where(function ($query) use ($request) {
                    $query->where('id_outlet', $request->json('id_outlet'))
                            ->orWhere('deals.is_all_outlet', '=', 1);
                })
                ->addSelect('deals.*')->distinct();
        }

        // brand
        if ($request->json('id_brand')) {
            $deals->where('id_brand', $request->json('id_brand'));
        }

        $deals->addSelect('id_brand', 'deals.id_deals', 'deals_title', 'deals_second_title', 'deals_voucher_price_point', 'deals_voucher_price_cash', 'deals_total_voucher', 'deals_total_claimed', 'deals_voucher_type', 'deals_image', 'deals_start', 'deals_end', 'deals_type', 'is_offline', 'is_online');

        if ($request->json('key_free')) {
            $deals->where(function ($query) use ($request) {
                $query->where('deals_title', 'LIKE', '%' . $request->json('key_free') . '%')
                    ->orWhere('deals_second_title', 'LIKE', '%' . $request->json('key_free') . '%');
            });
        }

        $deals->where(function ($query) use ($request) {

            if (!$request->json('voucher_type_cash') &&  !$request->json('voucher_type_point') &&  !$request->json('voucher_type_free')) {
                if ($request->json('min_price')) {
                    $query->where('deals_voucher_price_cash', '>=', $request->json('min_price'));
                }

                if ($request->json('max_price')) {
                    $query->where('deals_voucher_price_cash', '<=', $request->json('max_price'));
                }
            } else {
                if ($request->json('voucher_type_cash')) {
                    $query->orWhere(function ($amp) use ($request) {
                        $amp->whereNotNull('deals_voucher_price_cash');
                        if ($val = $request->json('min_price')) {
                            $amp->where('deals_voucher_price_cash', '>=', $val);
                        }
                        if ($val = $request->json('max_price')) {
                            $amp->where('deals_voucher_price_cash', '<=', $val);
                        }
                    });
                }

                if ($request->json('voucher_type_point')) {
                    $query->orWhere(function ($amp) use ($request) {
                        $amp->whereNotNull('deals_voucher_price_point');
                        if ($val = $request->json('min_interval_point')) {
                            $amp->where('deals_voucher_price_point', '>=', $val);
                        }
                        if ($val = $request->json('max_interval_point')) {
                            $amp->where('deals_voucher_price_point', '<=', $val);
                        }
                    });
                }

                if ($request->json('voucher_type_free')) {
                    $query->orWhere(function ($amp) use ($request) {
                        $amp->whereNull('deals_voucher_price_point')->whereNull('deals_voucher_price_cash');
                    });
                }
            }
        });

        if ($request->json('sort')) {
            if ($request->json('sort') == 'best') {
                $deals->orderBy('deals_total_claimed', 'desc');
            } elseif ($request->json('sort') == 'new') {
                $deals->orderBy('deals_publish_start', 'desc');
            } elseif ($request->json('sort') == 'periode') {
                $deals->orderBy('deals_end', 'asc');
            }
        }
        $deals = $deals->with('brand')->get()->toArray();

        if (!empty($deals)) {
            $city = "";

            // jika ada id city yg faq
            if ($request->json('id_city')) {
                $city = $request->json('id_city');
            }

            $deals = $this->kotacuks($deals, $city, $request->json('admin'));
        }

        if ($request->get('page')) {
            $page = $request->get('page');
        } else {
            $page = 1;
        }

        $resultData = [];
        $paginate   = 10;
        $start      = $paginate * ($page - 1);
        $all        = $paginate * $page;
        $end        = $all;
        $next       = true;

        if ($all >= count($deals)) {
            $end = count($deals);
            $next = false;
        }


        for ($i = $start; $i < $end; $i++) {
            $deals[$i]['time_to_end']       = strtotime($deals[$i]['deals_end']) - time();
            $deals[$i]['deals_start_indo']  = MyHelper::dateFormatInd($deals[$i]['deals_start'], false, false) . ' pukul ' . date('H:i', strtotime($deals[$i]['deals_start']));
            $deals[$i]['deals_end_indo']    = MyHelper::dateFormatInd($deals[$i]['deals_end'], false, false) . ' pukul ' . date('H:i', strtotime($deals[$i]['deals_end']));
            $deals[$i]['time_server_indo']  = MyHelper::dateFormatInd(date('Y-m-d H:i:s'), false, false) . ' pukul ' . date('H:i', strtotime(date('Y-m-d H:i:s')));
            array_push($resultData, $deals[$i]);
        }

        $result['current_page']  = $page;
        $result['data']          = $resultData;
        $result['total']         = count($resultData);
        $result['next_page_url'] = null;
        if ($next == true) {
            $next_page = (int) $page + 1;
            $result['next_page_url'] = ENV('APP_API_URL') . 'api/deals/list/v2?page=' . $next_page;
        }

        if (!$result['total']) {
            $result = [];
        }

        if (
            $request->json('voucher_type_point') ||
            $request->json('voucher_type_cash') ||
            $request->json('voucher_type_free') ||
            $request->json('key_free')
        ) {
            $resultMessage = 'Maaf, voucher yang kamu cari belum tersedia';
        } else {
            $resultMessage = 'Nantikan penawaran menarik dari kami';
        }
        return response()->json(MyHelper::checkGet($result, $resultMessage));
    }
    /*============================= End Filter & Sort V2 ================================*/

    public function updateComplete(UpdateComplete $request)
    {
        $post = $request->json()->all();

        $check = $this->checkComplete($post['id_deals'], $step, $errors, $post['deals_type']);

        if ($check) {
            if ($post['deals_type'] == 'Promotion' || $post['deals_type'] == 'deals_promotion') {
                $update = DealsPromotionTemplate::where('id_deals_promotion_template', '=', $post['id_deals'])->update(['step_complete' => 1, 'last_updated_by' => auth()->user()->id]);
            } else {
                $update = Deal::where('id_deals', '=', $post['id_deals'])->update(['step_complete' => 1, 'last_updated_by' => auth()->user()->id]);
            }

            if ($update) {
                return ['status' => 'success'];
            } else {
                return ['status' => 'fail', 'messages' => ['Update deals failed']];
            }
        } else {
            return [
                'status'    => 'fail',
                'step'      => $step,
                'messages'  => $errors
            ];
        }
    }

    public function checkComplete($id, &$step, &$errors = [], $promo_type = null)
    {
        $errors = [];
        $deals = $this->getDealsData($id, 'all', $promo_type);
        if (!$deals) {
            $errors[] = 'Deals not found';
            return false;
        }

        if ($promo_type == 'deals_promotion') {
            return app($this->promotion_deals)->checkComplete($deals, $step, $errors);
        }

        $deals = $deals->load('deals_outlets')->toArray();
        if ($deals['is_online'] == 1) {
            if (
                empty($deals['deals_product_discount_rules'])
                && empty($deals['deals_tier_discount_rules'])
                && empty($deals['deals_buyxgety_rules'])
                && empty($deals['deals_discount_bill_rules'])
                && empty($deals['deals_discount_delivery_rules'])
            ) {
                $step = 2;
                $errors[] = 'Deals not complete';
                return false;
            } else {
                $products = $deals['deals_product_discount'] ?? $deals['deals_tier_discount_product'] ?? $deals['deals_buyxgety_product_requirement'];

                if (
                    !empty($deals['deals_outlets'])
                    && !empty($products)
                    && $deals['is_all_outlet'] != 1
                    && ($deals['deals_product_discount_rules']['is_all_product'] ?? 0) != 1
                ) {
                    $check_brand_product = app($this->promo)->checkBrandProduct($deals['deals_outlets'], $products);
                    if ($check_brand_product['status'] == false) {
                        $step = 2;
                        $errors = array_merge($errors, $check_brand_product['messages'] ?? ['Outlet tidak mempunyai produk dengan brand yang sesuai.']);
                        return false;
                    }
                }
            }
        }


        if ($deals['is_offline'] == 1) {
            if (empty($deals['deals_promo_id_type']) && empty($deals['deals_promo_id'])) {
                $step = 2;
                $errors[] = 'Deals not complete';
                return false;
            }
        }

        if (empty($deals['deals_content']) || empty($deals['deals_description'])) {
            $step = 3;
            $errors[] = 'Deals not complete';
            return false;
        }

        return true;
    }

    public function getProductByCode($product_type, $code)
    {
        if ($product_type == 'single') {
            $product = Product::select('id_product')->whereIn('product_code', $code)->first();
        } else {
            $product = ProductGroup::select('id_product_group')->where('product_group_code', $code)->first();
        }

        return $product['id_product'] ?? $product['id_product_group'] ?? null;
    }

    public function dealsPaginate($query, $request)
    {
        $query->with('brands');
        $query = $query->addSelect('deals.updated_at')->paginate($request->paginate);

        return MyHelper::checkGet($query);
    }

    /* UPDATE */
    public function updatePromotionDeals($id, $data)
    {
        $data = $this->checkInputan($data);
        $deals = DealsPromotionTemplate::find($id);
        unset(
            $data['deals_type'],
            $data['deals_voucher_price_point'],
            $data['deals_voucher_price_cash']
        );
        $data['step_complete'] = 0;
        $data['last_updated_by'] = auth()->user()->id;

        $del_rule   = false;
        if ($data['id_brand'] && is_array($data['id_brand'])) {
            $brand_now  = $deals->deals_promotion_brands->pluck('id_brand')->toArray();
            $brand_new  = $data['id_brand'];
            $data_brand = $brand_new;

            $check_brand = array_merge(array_diff($brand_now, $brand_new), array_diff($brand_new, $brand_now));

            if (!empty($check_brand)) {
                $del_rule = true;
            }
        }
        if (
            $data['is_online'] == 0
            || (isset($data['id_brand'])
                && ((!is_array($data['id_brand']) && $data['id_brand'] != $deals['id_brand'])
                    || (is_array($data['id_brand']) && $del_rule)
                    )
                )
        ) {
            app($this->promo_campaign)->deleteAllProductRule('deals_promotion', $id);
        }

        if (!isset($data['id_brand'])) {
            $configBrand = Configs::where('config_name', 'use brand')->select('is_active')->first();
            if (isset($configBrand['is_active']) && $configBrand['is_active'] != '1') {
                $brand = Brand::select('id_brand')->first();
                if (isset($brand['id_brand'])) {
                    $data['id_brand'] = $brand['id_brand'];
                }
            }
        }

        if (isset($data['id_brand']) && is_array($data['id_brand'])) {
            $save_brand = $this->saveBrand($deals, $data['id_brand']);
            unset($data['id_brand']);
            if (!$save_brand) {
                return false;
            }
        }

        //delete outlet
        $this->deleteOutlet('Promotion', $id);

        // save outlet
        if (isset($data['id_outlet'])) {
            if ($data['is_all_outlet'] == 0) {
                $saveOutlet = $this->saveOutlet('Promotion', $deals, $data['id_outlet']);
            }
            unset($data['id_outlet']);
        }

        // save outlet group
        if (isset($data['id_outlet_group'])) {
            if ($data['is_all_outlet'] == 0) {
                $saveOutlet = $this->saveOutletGroup('Promotion', $deals, $data['id_outlet_group']);
            }
            unset($data['id_outlet_group']);
        }

        // error
        if (isset($data['error'])) {
            unset($data['error']);
            return ($data);
        }

        // delete old images
        if (isset($data['deals_image'])) {
            app($this->promotion_deals)->deleteImage($id);
        }

        $save = DealsPromotionTemplate::where('id_deals_promotion_template', $id)->update($data);

        return $save;
    }

    public function updateContentV2($data, $type = 'deals')
    {
        $post = $data;

        if ($type == 'deals') {
            $contentTable = new DealsContent();
            $contentTableDetail = new DealsContentDetail();
        } elseif ($type == 'deals_promotion') {
            $contentTable = new DealsPromotionContent();
            $contentTableDetail = new DealsPromotionContentDetail();
            $type = 'deals';
        }

        $data_content = [];
        $data_content_detail = [];
        $content_order = 1;

        //Rapiin data yg masuk
        foreach ($post['id_deals_content'] as $key => $value) {
            $data_content[$key]['id_deals'] = $post['id_deals'];
            $data_content[$key]['id_deals_content'] = $value;
            $data_content[$key]['title'] = $post['content_title'][$key];
            $data_content[$key]['is_active'] = ($post['visible'][$key + 1] ?? 0) ? 1 : null;
            $data_content[$key]['order'] = ($content_order++);
            $data_content[$key]['created_at'] = date('Y-m-d H:i:s');
            $data_content[$key]['updated_at'] = date('Y-m-d H:i:s');

            $detail_order = 1;
            if (($post['id_content_detail'][$key + 1] ?? 0)) {
                foreach ($post['id_content_detail'][$key + 1] as $key2 => $value2) {
                    $data_content_detail[$key][$key2]['id_deals_content'] = $value;
                    $data_content_detail[$key][$key2]['id_deals_content_detail'] = $value2;
                    $data_content_detail[$key][$key2]['content'] = $post['content_detail'][$key + 1][$key2];
                    $data_content_detail[$key][$key2]['order'] = $detail_order++;
                    $data_content_detail[$key][$key2]['created_at'] = date('Y-m-d H:i:s');
                    $data_content_detail[$key][$key2]['updated_at'] = date('Y-m-d H:i:s');
                }
            }
        }

        // hapus content & detail
        $del_content = $contentTable::where('id_deals', '=', $post['id_deals'])->delete();

        // create content & detail
        foreach ($post['id_deals_content'] as $key => $value) {
            $save = $contentTable::create($data_content[$key]);

            $id_deals_content = $save['id_deals_content'];

            if (($post['id_content_detail'][$key + 1] ?? 0)) {
                foreach ($post['id_content_detail'][$key + 1] as $key2 => $value2) {
                    $data_content_detail[$key][$key2]['id_deals_content'] = $id_deals_content;

                    $save = $contentTableDetail::create($data_content_detail[$key][$key2]);
                }
            }
        }

        // update description
        // $data_subs['deals_description'] = $post['deals_description'];
        // $save = Deal::where('id_deals','=',$post['id_deals'])->update($data_subs);

        return $save;
    }

    public function listAllDeals(Request $request)
    {
        $post = $request->json()->all();

        $list = Deal::where('deals_type', $post['deals_type'])
            ->select('id_deals', 'deals_title')
            ->get()->toArray();
        return response()->json(MyHelper::checkGet($list));
    }
}
