<?php

namespace Modules\Promotion\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Http\Models\Promotion;
use App\Http\Models\PromotionRule;
use App\Http\Models\PromotionRuleParent;
use App\Http\Models\PromotionContent;
use App\Http\Models\PromotionContentShortenLink;
use App\Http\Models\PromotionSchedule;
use App\Http\Models\PromotionQueue;
use App\Http\Models\PromotionSent;
use App\Http\Models\Deal;
use App\Http\Models\DealsVoucher;
use App\Http\Models\DealsOutlet;
use App\Http\Models\DealsUser;
use App\Http\Models\DealsPromotionTemplate;
use App\Http\Models\Outlet;
use Modules\Deals\Entities\DealsProductDiscount;
use Modules\Deals\Entities\DealsProductDiscountRule;
use Modules\Deals\Entities\DealsTierDiscountProduct;
use Modules\Deals\Entities\DealsTierDiscountRule;
use Modules\Deals\Entities\DealsBuyxgetyProductRequirement;
use Modules\Deals\Entities\DealsBuyxgetyRule;
use Modules\Deals\Entities\DealsDiscountBillRule;
use Modules\Deals\Entities\DealsDiscountBillProduct;
use Modules\Deals\Entities\DealsDiscountDeliveryRule;
use Modules\Deals\Entities\DealsUserLimit;
use Modules\Deals\Entities\DealsContent;
use Modules\Deals\Entities\DealsContentDetail;
use Modules\Deals\Entities\DealsShipmentMethod;
use Modules\Deals\Entities\DealsPaymentMethod;
use Modules\Deals\Entities\DealsBrand;
use Modules\Deals\Entities\DealsBuyxgetyProductModifier;
use Modules\Deals\Entities\DealsOutletGroup;
use Modules\Promotion\Http\Requests\DetailPromotion;
use Modules\Promotion\Http\Requests\DeleteDealsPromotionTemplate;
use App\Lib\MyHelper;
use DB;

class ApiPromotionDeals extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->dealsVoucher     = "Modules\Deals\Http\Controllers\ApiDealsVoucher";
        $this->promo_campaign   = "Modules\PromoCampaign\Http\Controllers\ApiPromoCampaign";
        $this->promo        = "Modules\PromoCampaign\Http\Controllers\ApiPromo";
    }

    public function list(Request $request)
    {
        $post = $request->json()->all();
        $deals = DealsPromotionTemplate::orderBy('updated_at', 'desc');
        if (isset($post['id_deals_promotion_template'])) {
            $deals = $deals->where('id_deals_promotion_template', $post['id_deals_promotion_template']);
        }

        if (isset($post['available'])) {
            $deals = $deals->where('step_complete', 1);
        }

        if ($request->json('brand')) {
            $deals = $deals->with(['brand', 'brands']);
        }

        $deals = $deals->get();
        if (isset($post['id_deals_promotion_template'])) {
            $deals['promotion'] = PromotionContent::join('promotions', 'promotions.id_promotion', 'promotion_contents.id_promotion')
                                                    ->where('id_deals_promotion_template', $post['id_deals_promotion_template'])
                                                    ->where('id_deals_promotion_template', $post['id_deals_promotion_template'])
                                                    ->select('promotions.*')
                                                    ->distinct()
                                                    ->orderBy('promotions.id_promotion', 'desc')
                                                    ->get();
        }
        return response()->json(MyHelper::checkGet($deals));
    }

    public function save(Request $request)
    {
        $post = $request->json()->all();

        if ($post['deals_promo_id_type'] == 'promoid') {
            $post['deals_promo_id'] = $post['deals_promo_id_promoid'];
            $post['deals_nominal'] = null;
        }

        if ($post['deals_promo_id_type'] == 'nominal') {
            $post['deals_nominal'] = $post['deals_promo_id_nominal'];
            $post['deals_promo_id'] = null;
        }

        unset($post['deals_promo_id_promoid']);
        unset($post['deals_promo_id_nominal']);

        $post['deals_start'] = date('Y-m-d H:i:s', strtotime($post['deals_start']));
        $post['deals_end'] = date('Y-m-d H:i:s', strtotime($post['deals_end']));

        $post['deals_list_outlet'] = implode(',', $post['id_outlet']);
        unset($post['id_outlet']);

        if ($post['duration'] == 'dates') {
            $post['deals_voucher_expired'] = date('Y-m-d H:i:s', strtotime($post['deals_voucher_expired']));
            $post['deals_voucher_duration'] = null;
        } else {
            $post['deals_voucher_expired'] = null;
        }
        unset($post['duration']);

        if ($post['deals_voucher_type'] == 'List Vouchers') {
            $post['deals_list_voucher'] = str_replace("\r\n", ',', $post['voucher_code']);
        } else {
            $post['deals_list_voucher'] = null;
        }
        unset($post['voucher_code']);

        if (isset($post['deals_image'])) {
            if (!file_exists('img/promotion/deals')) {
                mkdir('img/promotion/deals', 0777, true);
            }
            $upload = MyHelper::uploadPhoto($post['deals_image'], $path = 'img/promotion/deals/', 500);
            if ($upload['status'] == "success") {
                $post['deals_image'] = $upload['path'];
            } else {
                $result = [
                        'status'    => 'fail',
                        'messages'  => ['Save Promotion Deals Image failed.']
                    ];
                return response()->json($result);
            }
        }

        if (isset($post['id_deals_promotion_template'])) {
            $deals = DealsPromotionTemplate::where('id_deals_promotion_template', $post['id_deals_promotion_template'])->first();
            if ($deals && $deals['']) {
            }
            $deals = DealsPromotionTemplate::where('id_deals_promotion_template', $post['id_deals_promotion_template'])->update($post);
        } else {
            $deals = DealsPromotionTemplate::create($post);
        }

        return response()->json(MyHelper::checkCreate($deals));
    }

    public function detail(DetailPromotion $request)
    {
        $post = $request->json()->all();

        $deals = DealsPromotionTemplate::orderBy('deals_promotion_templates.updated_at', 'desc')
                ->where('deals_promotion_templates.id_deals_promotion_template', $post['id_deals_promotion_template'])
                ->with([
                    'deals_promotion_product_discount.product',
                    'deals_promotion_product_discount.brand',
                    'deals_promotion_product_discount.product_variant_pivot.product_variant',
                    'deals_promotion_product_discount_rules',
                    'deals_promotion_tier_discount_product.product',
                    'deals_promotion_tier_discount_product.brand',
                    'deals_promotion_tier_discount_product.product_variant_pivot.product_variant',
                    'deals_promotion_tier_discount_rules',
                    'deals_promotion_buyxgety_product_requirement.product',
                    'deals_promotion_buyxgety_product_requirement.brand',
                    'deals_promotion_buyxgety_product_requirement.product_variant_pivot.product_variant',
                    'deals_promotion_buyxgety_rules.product',
                    'deals_promotion_buyxgety_rules.brand',
                    'deals_promotion_buyxgety_rules.product_variant_pivot.product_variant',
                    'deals_promotion_buyxgety_rules.deals_buyxgety_product_modifiers.modifier',
                    'deals_promotion_content',
                    'deals_promotion_content.deals_promotion_content_details',
                    'created_by_user',
                    'promotion_contents.deals',
                    'brand',
                    'deals_promotion_discount_bill_rules',
                    'deals_promotion_discount_bill_products.product',
                    'deals_promotion_discount_bill_products.brand',
                    'deals_promotion_discount_bill_products.product_variant_pivot.product_variant',
                    'deals_promotion_discount_delivery_rules',
                    'deals_promotion_shipment_method',
                    'deals_promotion_payment_method',
                    'brands',
                    'outlets',
                    'outlet_groups'
                ])
                ->first();
        $outlet = explode(',', $deals->deals_list_outlet);
        $deals->outlets = Outlet::whereIn('id_outlet', $outlet ?? [])->get();

        if ($deals) {
            $deals_array = $deals->toArray();
            $getProduct = app($this->promo_campaign)->getProduct('deals_promotion', $deals_array);
            $desc = app($this->promo_campaign)->getPromoDescription('deals_promotion', $deals_array, $getProduct['product'] ?? '', true);
            $deals['description'] = $desc;
        }

        return response()->json(MyHelper::checkGet($deals));
    }

    public function createDeals($post, $id_promotion_content, $key = 0)
    {
        //kalo ada deals
        $dataDeals  = [];
        $warnings   = [];

        //get deals template
        $dealsTemplate = DealsPromotionTemplate::find($post['id_deals_promotion_template'][$key]);

        $dataDeals['deals_type']            = "Promotion";
        $dataDeals['deals_promo_id_type']   = $dealsTemplate['deals_promo_id_type'];

        if ($post['voucher_type_autogenerated'][$key] != "") {
            $dataDeals['deals_voucher_type']    = "Auto generated";
            $dataDeals['deals_total_voucher']   = $post['voucher_type_autogenerated'][$key];
        } elseif ($post['voucher_type_listvoucher'][$key] != "") {
            $dataDeals['deals_voucher_type']    = "List Vouchers";
            $ex = explode(PHP_EOL, $post['voucher_type_listvoucher'][$key]);
            $total = count($ex);
            $dataDeals['deals_total_voucher']   = $total;
        } else {
            $dataDeals['deals_voucher_type']    = "Unlimited";
            $dataDeals['deals_total_voucher']   = 0;
        }

        $dataDeals['deals_promo_id_type']   = $dealsTemplate['deals_promo_id_type'];
        if ($dealsTemplate['deals_promo_id_type'] == 'promoid') {
            $dataDeals['deals_promo_id']    = $dealsTemplate['deals_promo_id'];
        } else {
            $dataDeals['deals_promo_id']    = $dealsTemplate['deals_promo_id'];
        }

        $dataDeals['created_by']            = auth()->user()->id;
        $dataDeals['last_updated_by']       = auth()->user()->id;
        // $dataDeals['id_brand']               = $dealsTemplate['id_brand'];
        $dataDeals['deals_title']           = $dealsTemplate['deals_title'];
        $dataDeals['deals_second_title']    = $dealsTemplate['deals_second_title'];
        $dataDeals['deals_description']     = $dealsTemplate['deals_description'];
        $dataDeals['deals_image']           = $dealsTemplate['deals_image'];
        $dataDeals['charged_central']       = $dealsTemplate['charged_central'];
        $dataDeals['charged_outlet']        = $dealsTemplate['charged_outlet'];
        $dataDeals['user_limit']            = $dealsTemplate['user_limit'];
        $dataDeals['promo_type']            = $dealsTemplate['promo_type'];
        $dataDeals['is_online']             = $dealsTemplate['is_online'];
        $dataDeals['is_offline']            = $dealsTemplate['is_offline'];
        $dataDeals['is_offline']            = $dealsTemplate['is_offline'];
        $dataDeals['step_complete']         = 1;
        $dataDeals['custom_outlet_text']    = $dealsTemplate['custom_outlet_text'];
        $dataDeals['is_all_shipment']       = $dealsTemplate['is_all_shipment'];
        $dataDeals['is_all_payment']        = $dealsTemplate['is_all_payment'];
        $dataDeals['min_basket_size']       = $dealsTemplate['min_basket_size'];
        $dataDeals['product_rule']          = $dealsTemplate['product_rule'];
        $dataDeals['brand_rule']            = $dealsTemplate['brand_rule'];
        $dataDeals['product_type']          = $dealsTemplate['product_type'];
        $dataDeals['is_all_outlet']         = $dealsTemplate['is_all_outlet'];
        $dataDeals['promo_description']     = $dealsTemplate['promo_description'];

        if ($post['duration'][$key] == 'duration') {
            $dataDeals['deals_voucher_duration'] = $post['deals_voucher_expiry_duration'][$key];
            $dataDeals['deals_voucher_expired'] = null;
        } else {
            $dataDeals['deals_voucher_duration'] = null;
            $dataDeals['deals_voucher_expired'] = date('Y-m-d H:i:s', strtotime($post['deals_voucher_expiry_bydate'][$key]));
        }

        if ($post['deals_voucher_start'][$key]) {
            $dataDeals['deals_voucher_start'] = date('Y-m-d H:i:s', strtotime($post['deals_voucher_start'][$key]));
        } else {
            $dataDeals['deals_voucher_start'] = null;
        }

        if (in_array("all", explode(',', $dealsTemplate['deals_list_outlet']))) {
            $dataDeals['is_all_outlet'] = 1;
        }
        $mark = 'insert';
        if (isset($post['id_deals'][$key]) && $post['id_deals'][$key] != "") {
            $dealsQuery = Deal::where('id_deals', '=', $post['id_deals'][$key])->update($dataDeals);
            $id_deals = $post['id_deals'][$key];
            $mark = 'update';
        } else {
            $dealsQuery = Deal::create($dataDeals);
            if (!$dealsQuery) {
                $result = [
                    'status'    => 'fail',
                    'messages'  => ['Update Promotion Content Deals Failed.']
                ];
                return $result;
            }

            $id_deals = $dealsQuery->id_deals;
        }

        if ($dealsQuery) {
            if ($post['voucher_type_listvoucher'][$key] != "") {
                $ex = explode(PHP_EOL, $post['voucher_type_listvoucher'][$key]);
                $ex = array_map(
                    function ($value) {
                        return (string) strtoupper($value);
                    },
                    $ex
                );
                $list_voucher = $ex;
                $del_voucher = DealsVoucher::where('id_deals', $id_deals)
                                ->whereDoesntHave('deals_user')
                                // ->where('deals_voucher_status','available')
                                ->delete();

                $checkVoucher = DealsVoucher::whereIn('voucher_code', $ex)->pluck('voucher_code')->toArray();
                $ex2 = array_uintersect($ex, $checkVoucher, 'strcasecmp');
                $ex = array_udiff($ex, $checkVoucher, 'strcasecmp');

                if ($mark == 'insert') {
                    foreach ($ex as $voucher) {
                        $dataDealsVoucher = [];
                        $dataDealsVoucher['id_deals'] = $id_deals;
                        $dataDealsVoucher['voucher_code'] = $voucher;
                        $dataDealsVoucher['deals_voucher_status'] = "Available";

                        $queryDealsVoucher = DealsVoucher::create($dataDealsVoucher);

                        if (!$queryDealsVoucher) {
                            $result = [
                                'status'    => 'fail',
                                'messages'  => ['Update Promotion Content Deals Failed.']
                            ];
                            return $result;
                        }
                    }
                } else {
                    $dataDealsVoucher = [];

                    foreach ($ex as $value) {
                        array_push($dataDealsVoucher, [
                            'id_deals'             => $id_deals,
                            'voucher_code'         => strtoupper($value),
                            'deals_voucher_status' => 'Available',
                            'created_at'           => date('Y-m-d H:i:s'),
                            'updated_at'           => date('Y-m-d H:i:s')
                        ]);
                    }

                    $queryDealsVoucher = DealsVoucher::insert($dataDealsVoucher);

                    if (!$queryDealsVoucher) {
                        DB::rollBack();
                        $result = [
                            'status'    => 'fail',
                            'messages'  => ['Update Promotion Content Deals Failed.']
                        ];
                        return $result;
                    }
                }
            }

            if ($post['voucher_type_autogenerated'][$key] != "") {
                $del_voucher = DealsVoucher::where('id_deals', $id_deals)
                                ->whereDoesntHave('deals_user')
                                // ->where('deals_voucher_status','available')
                                ->delete();

                $delsVoucherUser = DealsVoucher::where('id_deals', '=', $id_deals)->get();
                $count_sent_voucher = count($delsVoucherUser);
                if ($count_sent_voucher > 0) {
                    $warnings[] = 'vouchers that have been sent before : ' . $count_sent_voucher;
                }
                $post['voucher_type_autogenerated'][$key]   = (int)$post['voucher_type_autogenerated'][0] - $count_sent_voucher;
                if ($post['voucher_type_autogenerated'][$key] > 0) {
                    $save = app($this->dealsVoucher)->generateVoucher($id_deals, $post['voucher_type_autogenerated'][$key]);
                } else {
                    DB::rollBack();
                    $result = [
                        'status'    => 'fail',
                        'messages'  => ['Total voucher must be more than ' . $count_sent_voucher]
                    ];
                    return $result;
                }
            }

            if ($dataDeals['deals_voucher_type'] == 'Unlimited') {
                $del_voucher = DealsVoucher::where('id_deals', $id_deals)
                                ->whereDoesntHave('deals_user')
                                // ->where('deals_voucher_status','available')
                                ->delete();
            }

            if (!$dataDeals['is_all_outlet']) {
                if ($dealsTemplate->deals_promotion_outlets || $dealsTemplate->deals_promotion_outlet_groups) {
                    // new outlet rule
                    if ($dealsTemplate->deals_promotion_outlets) {
                        $saveOutlet = $this->insertOutlet($dealsTemplate, $id_deals);
                        if (!$saveOutlet) {
                            $result = [
                                'status'    => 'fail',
                                'messages'  => ['Update Promotion Content Deals Failed.']
                            ];
                            return $result;
                        }
                    }

                    if ($dealsTemplate->deals_promotion_outlet_groups) {
                        $saveOutletGroup = $this->insertOutletGroup($dealsTemplate, $id_deals);
                        if (!$saveOutletGroup) {
                            $result = [
                                'status'    => 'fail',
                                'messages'  => ['Update Promotion Content Deals Failed.']
                            ];
                            return $result;
                        }
                    }
                } elseif ($dealsTemplate['deals_list_outlet'] != "") {
                    // old outlet rule
                    $deleteDealsOutlet =  DealsOutlet::where('id_deals', $id_deals)->delete();
                    if (!in_array("all", explode(',', $dealsTemplate['deals_list_outlet']))) {
                        $post['id_outlet'][$key] = explode(',', $dealsTemplate['deals_list_outlet']);

                        foreach ($post['id_outlet'][$key] as $id_outlet) {
                            $dataDealsOutlet = [];
                            $dataDealsOutlet['id_deals'] = $id_deals;
                            $dataDealsOutlet['id_outlet'] = $id_outlet;

                            $queryDealsOutlet = DealsOutlet::create($dataDealsOutlet);
                            if (!$queryDealsOutlet) {
                                DB::rollBack();
                                $result = [
                                    'status'    => 'fail',
                                    'messages'  => ['Update Promotion Content Deals Failed.']
                                ];
                                return $result;
                            }
                        }
                    }
                }
            }


            // save multi brand
            $saveBrand = $this->insertBrand($dealsTemplate, $id_deals);
            if (!$saveBrand) {
                $result = [
                    'status'    => 'fail',
                    'messages'  => ['Update Promotion Content Deals Failed.']
                ];
                return $result;
            }

            // save shipment method
            $saveShipment = $this->insertShipmentMethod($dealsTemplate, $id_deals);
            if (!$saveShipment) {
                $result = [
                    'status'    => 'fail',
                    'messages'  => ['Update Promotion Content Deals Failed.']
                ];
                return $result;
            }

            // save shipment method
            $savePayment = $this->insertPaymentMethod($dealsTemplate, $id_deals);
            if (!$savePayment) {
                $result = [
                    'status'    => 'fail',
                    'messages'  => ['Update Promotion Content Deals Failed.']
                ];
                return $result;
            }

            // delete all rule
            app($this->promo_campaign)->deleteAllProductRule('deals', $id_deals);

            // save promo rule
            switch ($dealsTemplate['promo_type']) {
                case 'Product discount':
                    $saveRule = $this->insertProductDiscount($dealsTemplate, $id_deals);

                    break;

                case 'Tier discount':
                    $saveRule = $this->insertTierDiscount($dealsTemplate, $id_deals);
                    break;

                case 'Buy X Get Y':
                    $saveRule = $this->insertBuyxgetyDiscount($dealsTemplate, $id_deals);
                    break;

                case 'Discount bill':
                    $saveRule = $this->insertBillDiscount($dealsTemplate, $id_deals);
                    break;

                case 'Discount delivery':
                    $saveRule = $this->insertDeliveryDiscount($dealsTemplate, $id_deals);
                    break;

                default:
                    # code...
                    break;
            }

            if (!$saveRule) {
                $result = [
                    'status'    => 'fail',
                    'messages'  => ['Update Promotion Content Deals Failed.']
                ];
                return $result;
            }

            // save content & detail content
            $saveContent = $this->insertContent($dealsTemplate, $id_deals);
            if (!$saveContent) {
                $result = [
                    'status'    => 'fail',
                    'messages'  => ['Update Promotion Content Deals Failed.']
                ];
                return $result;
            }


            $updatePromotion = PromotionContent::where('id_promotion_content', '=', $id_promotion_content)->update(['id_deals' => $id_deals]);
            $updateDeals     = Deal::where('id_deals', '=', $id_deals)->update(['step_complete' => 1]);

            $result = [
                'status'    => 'success',
                'messages'  => ['Update Promotion Content Deals Success.']
            ];
        } else {
            $result = [
                'status'    => 'fail',
                'messages'  => ['Update Promotion Content Deals Failed.']
            ];
        }

        foreach ($ex2 ?? [] as $value) {
            $warnings[] = 'Voucher ' . $value . ' already exists';
        }
        $result['warnings'] = $warnings;
        return $result;
    }

    public function insertProductDiscount($query, $id_deals)
    {
        $dealsTemplate = $query;

        $promotion_rule = $dealsTemplate->deals_promotion_product_discount_rules;
        $product_rule   = $dealsTemplate->deals_promotion_product_discount;

        $rule['id_deals']               = $id_deals;
        $rule['is_all_product']         = $promotion_rule['is_all_product'];
        $rule['discount_type']          = $promotion_rule['discount_type'];
        $rule['discount_value']         = $promotion_rule['discount_value'];
        $rule['max_product']            = $promotion_rule['max_product'];
        $rule['max_percent_discount']   = $promotion_rule['max_percent_discount'];

        $saveRule = DealsProductDiscountRule::updateOrCreate(['id_deals' => $id_deals], $rule);

        if (!$rule['is_all_product']) {
            $table = new DealsProductDiscount();
            $saveRule = $this->insertMultiProductRequirement($product_rule, $id_deals, $table);
        }

        if ($saveRule) {
            return true;
        } else {
            return false;
        }
    }

    public function insertTierDiscount($query, $id_deals)
    {
        $promotion_rule = $query->deals_promotion_tier_discount_rules;
        $product_rule   = $query->deals_promotion_tier_discount_product;

        if (isset($product_rule['id_product'])) {
            $rule['id_deals'] = $id_deals;
            $rule['id_product'] = $product_rule['id_product'];
            $rule['id_product_category'] = $product_rule['id_product_category'];

            $saveRule = DealsTierDiscountProduct::updateOrCreate(['id_deals' => $id_deals], $rule);
        } else {
            $table = new DealsTierDiscountProduct();
            $saveRule = $this->insertMultiProductRequirement($product_rule, $id_deals, $table);
        }

        foreach ($promotion_rule as $key => $value) {
            $ruleBenefit[] = [
                'id_deals'              => $id_deals,
                'min_qty'               => $value['min_qty'],
                'max_qty'               => $value['max_qty'],
                'discount_type'         => $value['discount_type'],
                'discount_value'        => $value['discount_value'],
                'max_percent_discount'  => $value['max_percent_discount'],
                'is_all_product'        => $value['is_all_product'],
                'created_at'            => date('Y-m-d H:i:s'),
                'updated_at'            => date('Y-m-d H:i:s')
            ];
        }
        $delRule = DealsTierDiscountRule::where('id_deals', $id_deals)->delete();
        $saveRule = DealsTierDiscountRule::insert($ruleBenefit);

        if ($saveRule) {
            return true;
        } else {
            return false;
        }
    }

    public function insertBuyxgetyDiscount($query, $id_deals)
    {
        $promotion_rule = $query->deals_promotion_buyxgety_rules;
        $product_rule   = $query->deals_promotion_buyxgety_product_requirement;

        if (isset($product_rule['id_product'])) {
            $rule['id_deals'] = $id_deals;
            $rule['id_product'] = $product_rule['id_product'];
            $rule['id_product_category'] = $product_rule['id_product_category'];

            $saveRule = DealsBuyxgetyProductRequirement::updateOrCreate(['id_deals' => $id_deals], $rule);
        } else {
            $table = new DealsBuyxgetyProductRequirement();
            $saveRule = $this->insertMultiProductRequirement($product_rule, $id_deals, $table);
        }

        $delRule = DealsBuyxgetyRule::where('id_deals', $id_deals)->delete();

        foreach ($promotion_rule as $value) {
            $ruleBenefit = [
                'id_deals'              => $id_deals,
                'min_qty_requirement'   => $value['min_qty_requirement'],
                'max_qty_requirement'   => $value['max_qty_requirement'],
                'discount_type'         => $value['discount_type'],
                'discount_value'        => $value['discount_value'],
                'max_percent_discount'  => $value['max_percent_discount'],
                'benefit_id_product'    => $value['benefit_id_product'],
                'benefit_qty'           => $value['benefit_qty'],
                'id_brand'              => $value['id_brand'],
                'id_product_variant_group' => $value['id_product_variant_group'],
                'is_all_product'        => $value['is_all_product'],
                'created_at'            => date('Y-m-d H:i:s'),
                'updated_at'            => date('Y-m-d H:i:s')
            ];

            $saveRule = DealsBuyxgetyRule::create($ruleBenefit);

            $ruleModifier = [];
            foreach ($value->deals_buyxgety_product_modifiers as $mod) {
                $ruleModifier[] = [
                    'id_product_modifier' => $mod['id_product_modifier'],
                    'id_deals_buyxgety_rule' => $saveRule['id_deals_buyxgety_rule'],
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s')
                ];
            }
            $saveRule = DealsBuyxgetyProductModifier::insert($ruleModifier);
        }

        if ($saveRule) {
            return true;
        } else {
            return false;
        }
    }

    public function insertBillDiscount($query, $id_deals)
    {
        $dealsTemplate = $query;

        $promotion_rule = $dealsTemplate->deals_promotion_discount_bill_rules;
        $product_rule   = $dealsTemplate->deals_promotion_discount_bill_products;

        $rule['id_deals']               = $id_deals;
        $rule['discount_type']          = $promotion_rule['discount_type'];
        $rule['discount_value']         = $promotion_rule['discount_value'];
        $rule['max_percent_discount']   = $promotion_rule['max_percent_discount'];
        $rule['is_all_product']         = $promotion_rule['is_all_product'];

        $saveRule = DealsDiscountBillRule::updateOrCreate(['id_deals' => $id_deals], $rule);

        if (!$rule['is_all_product']) {
            $table = new DealsDiscountBillProduct();
            $saveRule = $this->insertMultiProductRequirement($product_rule, $id_deals, $table);
        }

        if ($saveRule) {
            return true;
        } else {
            return false;
        }
    }

    public function insertDeliveryDiscount($query, $id_deals)
    {
        $dealsTemplate = $query;

        $promotion_rule = $dealsTemplate->deals_promotion_discount_delivery_rules;

        $rule['id_deals']               = $id_deals;
        $rule['discount_type']          = $promotion_rule['discount_type'];
        $rule['discount_value']         = $promotion_rule['discount_value'];
        $rule['max_percent_discount']   = $promotion_rule['max_percent_discount'];

        $saveRule = DealsDiscountDeliveryRule::updateOrCreate(['id_deals' => $id_deals], $rule);

        if ($saveRule) {
            return true;
        } else {
            return false;
        }
    }

    public function insertShipmentMethod($query, $id_deals)
    {
        $dealsTemplate = $query;

        $shipment_rule = $dealsTemplate->deals_promotion_shipment_method;

        $shipment = [];
        foreach ($shipment_rule as $key => $value) {
            $shipment[] = [
                'id_deals'          => $id_deals,
                'shipment_method'   => $value['shipment_method'],
                'created_at'        => date('Y-m-d H:i:s'),
                'updated_at'        => date('Y-m-d H:i:s')
            ];
        }
        $delShipment = DealsShipmentMethod::where('id_deals', $id_deals)->delete();
        $saveShipment = DealsShipmentMethod::insert($shipment);

        if ($saveShipment) {
            return true;
        } else {
            return false;
        }
    }

    public function insertPaymentMethod($query, $id_deals)
    {
        $dealsTemplate = $query;

        $payment_rule = $dealsTemplate->deals_promotion_payment_method;

        $payment = [];
        foreach ($payment_rule as $key => $value) {
            $payment[] = [
                'id_deals'          => $id_deals,
                'payment_method'    => $value['payment_method'],
                'created_at'        => date('Y-m-d H:i:s'),
                'updated_at'        => date('Y-m-d H:i:s')
            ];
        }
        $delPayment = DealsPaymentMethod::where('id_deals', $id_deals)->delete();
        $savePayment = DealsPaymentMethod::insert($payment);

        if ($savePayment) {
            return true;
        } else {
            return false;
        }
    }

    public function insertContent($query, $id_deals)
    {
        DealsContent::where('id_deals', $id_deals)->delete();
        $content = $query->deals_promotion_content->load('deals_promotion_content_details');

        foreach ($content as $key => $value) {
            $content = [
                'id_deals'  => $id_deals,
                'title'     => $value['title'],
                'order'     => $value['order'],
                'is_active' => $value['is_active']
            ];

            $saveContent = DealsContent::create($content);
            if (!$saveContent) {
                return false;
            }

            $i = 1;
            foreach ($value['deals_promotion_content_details'] as $key2 => $value2) {
                $content_detail[$i] = [
                    'id_deals_content'  => $saveContent['id_deals_content'],
                    'content'           => $value2['content'],
                    'order'             => $value2['order'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                $i++;
            }
            if (!empty($content_detail)) {
                $saveContentDetail = DealsContentDetail::insert($content_detail);
            }
        }

        return true;
    }

    public function insertBrand($query, $id_deals)
    {
        $deals_template = $query;

        $brand_template = $deals_template->deals_promotion_brands;

        $brand = [];
        foreach ($brand_template as $key => $value) {
            $brand[] = [
                'id_deals'  => $id_deals,
                'id_brand'  => $value['id_brand']
            ];
        }
        $del_brand  = DealsBrand::where('id_deals', $id_deals)->delete();
        $save_brand = DealsBrand::insert($brand);

        if ($save_brand) {
            return true;
        } else {
            return false;
        }
    }

    public function insertMultiProductRequirement($query, $id_deals, $table)
    {
        $product_rule = $query;
        $product = [];
        foreach ($product_rule as $key => $value) {
            $product[] = [
                'id_deals' => $id_deals,
                'id_product' => $value['id_product'],
                'id_brand' => $value['id_brand'],
                'id_product_category' => $value['id_product_category'],
                'id_product_variant_group' => $value['id_product_variant_group']
            ];
        }
        $delRule    = $table::where('id_deals', $id_deals)->delete();
        $saveRule   = $table::insert($product);

        return $saveRule;
    }

    public function insertOutlet($query, $id_deals)
    {
        $deals_template = $query;

        $outlet_template = $deals_template->deals_promotion_outlets;

        $outlet = [];
        foreach ($outlet_template as $key => $value) {
            $outlet[] = [
                'id_deals'  => $id_deals,
                'id_outlet' => $value['id_outlet']
            ];
        }

        $del_outlet     = DealsOutlet::where('id_deals', $id_deals)->delete();
        $save_outlet    = DealsOutlet::insert($outlet);
        if ($save_outlet) {
            return true;
        } else {
            return false;
        }
    }

    public function insertOutletGroup($query, $id_deals)
    {
        $deals_template = $query;

        $outlet_group_template = $deals_template->deals_promotion_outlet_groups;

        $outlet_group = [];
        foreach ($outlet_group_template as $key => $value) {
            $outlet_group[] = [
                'id_deals'  => $id_deals,
                'id_outlet_group' => $value['id_outlet_group']
            ];
        }

        $del_outlet_group   = DealsOutletGroup::where('id_deals', $id_deals)->delete();
        $save_outlet_group  = DealsOutletGroup::insert($outlet_group);

        if ($save_outlet_group) {
            return true;
        } else {
            return false;
        }
    }

    public function deleteDeals($promoContent, $id_promotion_content)
    {
        if ($promoContent->id_deals != null) {
            $deal = Deal::where('id_deals', $promoContent->id_deals)->first();
            if ($deal->total_claimed == 0 && $promoContent->promotion_count_voucher_give == 0) {
                DealsOutlet::where('id_deals', $promoContent->id_deals)->delete();
                DealsVoucher::where('id_deals', $promoContent->id_deals)->delete();
                $delete = Deal::where('id_deals', $promoContent->id_deals)->delete();

                if ($delete) {
                    return true;
                } else {
                    return false;
                }
            }
        }
    }

    public function checkComplete($dataDeals, &$step, &$errors)
    {
        $errors = [];
        $deals = $dataDeals->toArray();
        if ($deals['is_online'] == 1) {
            if (
                empty($deals['deals_promotion_product_discount_rules'])
                && empty($deals['deals_promotion_tier_discount_rules'])
                && empty($deals['deals_promotion_buyxgety_rules'])
                && empty($deals['deals_promotion_discount_bill_rules'])
                && empty($deals['deals_promotion_discount_delivery_rules'])
            ) {
                $step = 2;
                $errors = 'Deals Promotion not complete';
                return false;
            } else {
                $products = $deals['deals_promotion_product_discount'] ?? $deals['deals_promotion_tier_discount_product'] ?? $deals['deals_promotion_buyxgety_product_requirement'];
                if (isset($deals['deals_list_outlet'])) {
                    $outlets = explode(',', $deals['deals_list_outlet']);
                    if (in_array('all', $outlets)) {
                        $deals['is_all_outlet'] = 1;
                        $outlets = [];
                    }
                }
                if (
                    !empty($outlets)
                    && !empty($products)
                    && ($deals['is_all_outlet'] ?? 0) != 1
                    && ($deals['deals_promotion_product_discountuct_discount_rules']['is_all_product'] ?? 0) != 1
                ) {
                    $check_brand_product = app($this->promo)->checkBrandProduct($outlets, $products);
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
                $errors = 'Deals Promotion not complete';
                return false;
            }
        }

        if (empty($deals['deals_promotion_content']) || empty($deals['deals_description'])) {
            $step = 3;
            $errors = 'Deals Promotion not complete';
            return false;
        }

        return true;
    }

    public function participant(Request $request)
    {
        $post = $request->json()->all();
        $deals = PromotionContent::where('id_deals_promotion_template', $request->json('id_deals_promotion_template'));
        // if ($request->json('id_deals')) {
        //     $deals->where('deals_vouchers.id_deals', $request->json('id_deals'));
        // }

        if ($request->json('rule')) {
             // $this->filterUserVoucher($deals,$request->json('rule'),$request->json('operator')??'and');
        }

        $deals = $deals->with([
                    'promotion',
                    'promotion.schedules',
                    'deals'
                ]);
        $deals = $deals->paginate(10);
        return response()->json(MyHelper::checkGet($deals));
    }

    /* DELETE REQUEST */
    public function deleteReq(DeleteDealsPromotionTemplate $request)
    {
        DB::beginTransaction();

        $check = $this->checkDelete($request->json('id_deals_promotion_template'));

        if ($check) {
            // delete image first
            $this->deleteImage($request->json('id_deals_promotion_template'));

            $delete = DealsPromotionTemplate::where('id_deals_promotion_template', $request->json('id_deals_promotion_template'))->delete();

            if ($delete) {
                DB::commit();
            } else {
                DB::rollBack();
            }

            return response()->json(MyHelper::checkDelete($delete));
        } else {
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Deals template that have been used cannot be deleted.']
            ]);
        }
    }

    /* CHECK DELETE */
    public function checkDelete($id)
    {
        $check = PromotionContent::where('id_deals_promotion_template', $id)->first();

        if ($check) {
            return false;
        }
        return true;
    }

    /* DELETE IMAGE */
    public function deleteImage($id)
    {
        $cekImage = DealsPromotionTemplate::where('id_deals_promotion_template', $id)->get()->first();

        if (!empty($cekImage)) {
            if (!empty($cekImage->deals_image)) {
                $delete = MyHelper::deletePhoto($cekImage->deals_image);
            }
            if (!empty($cekImage->deals_warning_image)) {
                $delete = MyHelper::deletePhoto($cekImage->deals_warning_image);
            }
        }
        return true;
    }
}
