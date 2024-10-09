<?php

namespace Modules\Transaction\Http\Controllers;

use App\Http\Models\DailyTransactions;
use App\Http\Models\ProductPhoto;
use App\Http\Models\Subdistricts;
use App\Http\Models\TransactionConsultation;
use App\Http\Models\TransactionConsultationRecomendation;
use App\Http\Models\TransactionPaymentBalance;
use App\Http\Models\TransactionPaymentOvo;
use App\Jobs\DisburseJob;
use App\Jobs\FraudJob;
use App\Lib\Ovo;
use App\Lib\Shipper;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Http\Models\Setting;
use App\Http\Models\Product;
use App\Http\Models\ProductPrice;
use App\Http\Models\ProductCategory;
use Modules\Brand\Entities\Brand;
use Modules\Brand\Entities\BrandProduct;
use App\Http\Models\ProductModifier;
use App\Http\Models\User;
use App\Http\Models\UserAddress;
use App\Http\Models\Outlet;
use App\Http\Models\Transaction;
use App\Http\Models\TransactionProduct;
use App\Http\Models\TransactionProductModifier;
use Modules\IPay88\Entities\TransactionPaymentIpay88;
use Modules\Merchant\Entities\Merchant;
use Modules\Product\Entities\ProductWholesaler;
use Modules\ProductBundling\Entities\BundlingOutlet;
use Modules\ProductBundling\Entities\BundlingProduct;
use Modules\ProductVariant\Entities\ProductVariantGroup;
use Modules\ProductVariant\Entities\ProductVariantGroupDetail;
use Modules\ProductVariant\Entities\ProductVariantGroupSpecialPrice;
use Modules\ProductVariant\Entities\ProductVariantGroupWholesaler;
use Modules\ProductVariant\Entities\TransactionProductVariant;
use App\Http\Models\TransactionShipment;
use App\Http\Models\TransactionPickup;
use App\Http\Models\TransactionPickupGoSend;
use App\Http\Models\TransactionPaymentMidtran;
use App\Http\Models\TransactionAdvanceOrder;
use App\Http\Models\LogPoint;
use App\Http\Models\LogBalance;
use App\Http\Models\ManualPaymentMethod;
use App\Http\Models\UserOutlet;
use App\Http\Models\TransactionSetting;
use Modules\Product\Entities\ProductDetail;
use Modules\Product\Entities\ProductGlobalPrice;
use Modules\Product\Entities\ProductSpecialPrice;
use Modules\SettingFraud\Entities\FraudSetting;
use App\Http\Models\Configs;
use App\Http\Models\Holiday;
use App\Http\Models\OutletToken;
use App\Http\Models\UserLocationDetail;
use App\Http\Models\Deal;
use App\Http\Models\TransactionVoucher;
use App\Http\Models\DealsUser;
use Modules\PromoCampaign\Entities\PromoCampaign;
use Modules\PromoCampaign\Entities\PromoCampaignPromoCode;
use Modules\PromoCampaign\Entities\PromoCampaignReferral;
use Modules\PromoCampaign\Entities\PromoCampaignReferralTransaction;
use Modules\PromoCampaign\Entities\UserReferralCode;
use Modules\PromoCampaign\Entities\UserPromo;
use Modules\ShopeePay\Entities\TransactionPaymentShopeePay;
use Modules\Subscription\Entities\TransactionPaymentSubscription;
use Modules\Subscription\Entities\Subscription;
use Modules\Subscription\Entities\SubscriptionUser;
use Modules\Subscription\Entities\SubscriptionUserVoucher;
use Modules\PromoCampaign\Entities\PromoCampaignReport;
use Modules\Balance\Http\Controllers\NewTopupController;
use Modules\PromoCampaign\Lib\PromoCampaignTools;
use Modules\Outlet\Entities\DeliveryOutlet;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use Guzzle\Http\EntityBody;
use Guzzle\Http\Message\Request as RequestGuzzle;
use Guzzle\Http\Message\Response as ResponseGuzzle;
use Guzzle\Http\Exception\ServerErrorResponseException;
use Modules\Transaction\Entities\TransactionBundlingProduct;
use Modules\Transaction\Entities\TransactionGroup;
use Modules\Transaction\Entities\TransactionProductConsultationRedeem;
use Modules\UserFeedback\Entities\UserFeedbackLog;
use DB;
use DateTime;
use App\Lib\MyHelper;
use App\Lib\Midtrans;
use App\Lib\GoSend;
use App\Lib\WeHelpYou;
use App\Lib\PushNotificationHelper;
use Modules\Transaction\Http\Requests\Transaction\NewTransaction;
use Modules\Transaction\Http\Requests\Transaction\ConfirmPayment;
use Modules\Transaction\Http\Requests\CheckTransaction;
use Modules\ProductVariant\Entities\ProductVariant;
use App\Http\Models\TransactionMultiplePayment;
use Modules\ProductBundling\Entities\Bundling;
use Modules\Xendit\Entities\TransactionPaymentXendit;

use Modules\Transaction\Http\Requests\CartCreate;
use Modules\Transaction\Http\Requests\CartDelete;
use App\Http\Models\Cart;
use App\Http\Models\CartCustom;
use App\Http\Models\ProductPriceUser;
use Auth;
use App\Http\Models\ProductServingMethod;
use App\Http\Models\CartServingMethod;
use Illuminate\Http\Exceptions\HttpResponseException;
use Modules\Transaction\Http\Requests\CartDeleteMultiple;

class ApiCart extends Controller
{
    public $saveImage = "img/payment/manual/";

    public function __construct()
    {
        ini_set('max_execution_time', 0);
        date_default_timezone_set('Asia/Jakarta');

        $this->balance       = "Modules\Balance\Http\Controllers\BalanceController";
        $this->membership    = "Modules\Membership\Http\Controllers\ApiMembership";
        $this->autocrm       = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        $this->transaction   = "Modules\Transaction\Http\Controllers\ApiTransaction";
        $this->notif         = "Modules\Transaction\Http\Controllers\ApiNotification";
        $this->setting_fraud = "Modules\SettingFraud\Http\Controllers\ApiFraud";
        $this->setting_trx   = "Modules\Transaction\Http\Controllers\ApiSettingTransactionV2";
        $this->promo_campaign       = "Modules\PromoCampaign\Http\Controllers\ApiPromoCampaign";
        $this->subscription_use     = "Modules\Subscription\Http\Controllers\ApiSubscriptionUse";
        $this->promo       = "Modules\PromoCampaign\Http\Controllers\ApiPromo";
        $this->outlet       = "Modules\Outlet\Http\Controllers\ApiOutletController";
        $this->plastic       = "Modules\Plastic\Http\Controllers\PlasticController";
        $this->voucher  = "Modules\Deals\Http\Controllers\ApiDealsVoucher";
        $this->subscription  = "Modules\Subscription\Http\Controllers\ApiSubscriptionVoucher";
        $this->bundling      = "Modules\ProductBundling\Http\Controllers\ApiBundlingController";
        $this->merchant = "Modules\Merchant\Http\Controllers\ApiMerchantController";
        $this->promo_trx     = "Modules\Transaction\Http\Controllers\ApiPromoTransaction";
    }

    public function add(CartCreate $request)
    {
        $post = $request->all();
        $create = array();
        $product = Product::join('merchants','merchants.id_merchant','products.id_merchant')
                ->where('id_product',$post['id_product'])->first();
        if($product){
            if($post['qty']<$product['min_transaction']){
                throw new HttpResponseException(response()->json(['status' => 'fail', 'messages'  => ['Minimal transction '.$product['min_transaction']]], 200));
            }
            if($post['custom']=='box'){
                $get = Cart::where([
                    'id_user'=>Auth::user()->id,
                    'id_product'=>$post['id_product']
                ])->first();
                if($get){
                    $custom = CartCustom::where('id_cart',$get->id_cart)->delete();
                    $custom = CartServingMethod::where('id_cart',$get->id_cart)->delete();
                }
                
                $create = Cart::UpdateOrCreate([
                    'id_user'=>Auth::user()->id,
                    'id_product'=>$post['id_product']
                ],[
                    'qty'=>$post['qty'],
                    'id_outlet'=>$product['id_outlet'],
                    'custom'=>1
                ]);
                if(isset($post['serving_method']['id_product_serving_method'])){
                    $serving = CartServingMethod::create([
                        'id_product_serving_method'=>$post['serving_method']['id_product_serving_method'],
                        'id_cart'=>$create->id_cart
                    ]);
                }
                if(isset($post['item'])){
                    foreach($post['item'] as $value){
                        $create = CartCustom::create([
                            'id_product'=>$value,
                            'id_cart'=>$create->id_cart
                        ]);
                    }
                }
            }else{
                $create = Cart::UpdateOrCreate([
                    'id_user'=>Auth::user()->id,
                    'id_product'=>$post['id_product']
                ],[
                    'qty'=>$post['qty'],
                    'id_outlet'=>$product['id_outlet'],
                    'custom'=>0
                ]);
            }
        }
        return MyHelper::checkCreate($create);
    }
    public function delete(CartDelete $request)
    {
        $post = $request->all();
        $delete = Cart::where([
            'id_user'=>Auth::user()->id,
            'id_product'=>$post['id_product']
        ])->delete();
        return MyHelper::checkDelete($delete);
    }
    
    public function deleteMultiple(CartDeleteMultiple $request)
    {
        $post = $request->all();
        $delete = Cart::where([
            'id_user'=>Auth::user()->id
        ])->whereIn('id_cart',$post['id_cart'])->delete();
        return MyHelper::checkDelete($delete);
    }
    public function count()
    {
        $data = Cart::where('id_user',Auth::user()->id)->count();
        return MyHelper::checkGet($data);
    }
    public function index()
    {
        $cart = Cart::where('id_user',Auth::user()->id)->groupby('id_outlet')->select('id_outlet')->distinct()->get();
        $data = array();
        foreach($cart as $value){
            $item = Cart::where('id_outlet',$value['id_outlet'])->where('id_user',Auth::user()->id)->select('id_cart','id_product','qty','custom')->get();
            $data_item = array();
            foreach($item as $val){
                $product = Product::where('id_product',$val['id_product'])->first();
                if($val['qty']<$product['min_transaction']){
                    $val['qty']=$product['min_transaction'];
                }
                $val['min_transaction'] = $product['min_transaction'];
                if($val['custom']==1){
                    $serving = CartServingMethod::where('id_cart',$val['id_cart'])->first();
                    $val['id_product_serving_method'] = $serving->id_product_serving_method;
                    $product = CartCustom::where('id_cart',$val['id_cart'])->select('id_product')->get();
                    $items = array();
                    foreach ($product as $v) {
                        $items[] = $v['id_product'];
                    }
                    $val['item'] = $items;
                }else{
                    $data_item[]=$val;
                }
            }
            $data[] = array(
                'id_outlet'=>$value['id_outlet'],
                'items'=>$item
            );
        }
        return MyHelper::checkGet($data);
    }
    public function cartTransaction(Request $request)
    {
        $post = $request->json()->all();

        if (!empty($post)) {
            $itemsCheck = $this->checkDataTransaction($post['items'] ?? $post, 0, 1, 0, [], 0, $request->user()->id);
            $items = $itemsCheck['items'];
            $subtotal = $itemsCheck['subtotal'];

            return response()->json(MyHelper::checkGet([
                'items' => $items,
                'subtotal' => (int)$subtotal,
                'subtotal_text' => 'Rp ' . number_format((int)$subtotal, 0, ",", ".")
            ]));
        } else {
            return response()->json(['status'    => 'fail', 'messages'  => ['Item can not be empty']]);
        }
    }

   public function checkDataTransaction($post, $from_new = 0, $from_cart = 0, $from_check = 0, $dtAddress = [], $fromRecipeDoctor = 0, $id_user = null)
    {
        $items = $this->mergeProducts($post);

        $availableCheckout = true;
        $canBuyStatus = true;
        $subtotal = 0;
        $taxTotal = 0;
        $deliveryPrice = 0;
        $errorMsg = [];
        $weight = [];
        $needRecipeData = [];
        $needRecipeStatus = 0;

        foreach ($items as $index => $value) {
            $errorMsgSubgroup = [];
            $merchant = Merchant::where('id_outlet', $value['id_outlet'])->first();
            $checkOutlet = Outlet::where('id_outlet', $value['id_outlet'])->where('outlet_status', 'Active')->first();
            if (!empty($checkOutlet)) {
                $productSubtotal = 0;
                $weightProduct = 0;
                $dimentionProduct = 0;
                foreach ($value['items'] as $key => $item) {
                    if($item['custom']==0){
                        $idWholesaler = null;
                        $idWholesalerVariant = null;
                        $error = '';
                        $product = Product::select('need_recipe_status','product_type',  'product_weight', 'product_width', 'product_length', 'product_height', 'id_merchant', 'product_category_name', 'products.id_product_category', 'id_product', 'product_code', 'product_name', 'product_description', 'product_code', 'product_variant_status')
                            ->leftJoin('product_categories', 'product_categories.id_product_category', 'products.id_product_category')
                            ->where('product_visibility', 'Visible')
                            ->where('id_product', $item['id_product'])->first();

                        if (empty($product)) {
                            if (!empty($from_new)) {
                                $errorMsg[] = 'Produk tidak valid';
                            }

                            unset($value['items'][$key]);
                            continue;
                        } else {
                            $product = $product->toArray();
                        }
                        $product['product_price'] = 0;
                        $productGlobalPrice = ProductPriceUser::where([
                            'id_product'=>$item['id_product'],
                            'id_user'=>Auth::user()->id,
                            ])->first();
                        if(!$productGlobalPrice){
                           $productGlobalPrice = ProductGlobalPrice::where('id_product', $item['id_product'])->first(); 
                        }else{
                            $productGlobalPrice['product_global_price'] = $productGlobalPrice['product_price'];
                        }

                        if ($productGlobalPrice) {
                            $service = 0;
                            $tax = 0;
                            $dtTaxService = ['subtotal' =>  $productGlobalPrice['product_global_price']];
                            $serviceCalculate = round(app($this->setting_trx)->countTransaction('service', $dtTaxService));
                            $service = $service + $serviceCalculate;
                            $taxCalculate = round(app($this->setting_trx)->countTransaction('tax', $dtTaxService));
                            $tax = $tax + $taxCalculate;

                            $service = round($service);
                            $tax = round($tax);
                           $product['product_price'] = $productGlobalPrice['product_global_price']+$tax;
                            $product['product_price_discount'] = $productGlobalPrice['global_price_discount_percent']??0;
                            $product['product_price_before_discount'] = $productGlobalPrice['global_price_before_discount']??0;
                        }

                        $product['stock_item'] = 0;
                        $product['variants'] = '';
                        $variantPriceFinal = 0;
                       $product['stock_item'] = ProductDetail::where('id_product', $item['id_product'])->where('id_outlet', $value['id_outlet'])->first()['product_detail_stock_item'] ?? 0;


                        $image = ProductPhoto::where('id_product', $product['id_product'])->orderBy('product_photo_order', 'asc')->first()['product_photo'] ?? null;
                        $product['image'] = (empty($image) ? config('url.storage_url_api') . 'img/default.jpg' : config('url.storage_url_api') . $image);

                        if (empty($productGlobalPrice['product_global_price'])) {
                            $error = 'Harga produk tidak valid';
                        }


    //                    if ($item['qty'] > $product['stock_item']) {
    //                        $error = 'Jumlah item yang Anda pilih melebihi batas maksimal stock';
    //                    }

                        if ($item['qty'] < 1) {
                            $error = 'Jumlah item tidak valid';
                        }

                        if ($product['product_price'] <= 0) {
                            $error = 'Produk tidak valid';
                        }

                        if (!empty($product['product_weight'])) {
                            $w = ($product['product_weight'] * $item['qty']) / 1000;
                            $weight[] = $w;
                            $weightProduct = $weightProduct + $w;
                            $dimentionProduct = $dimentionProduct + ($product['product_width'] * $product['product_height'] * $product['product_length'] * $item['qty']);
                        } else {
                            $error = 'Produk tidak valid';
                        }

                        if ($id_user == $merchant['id_user']) {
                            $error = 'Tidak bisa membeli produk sendiri';
                        }

                        $totalPrice = (int)$product['product_price'] * $item['qty'];
                        $productSubtotal = $productSubtotal + (int)$totalPrice;
                        $value['items'][$key] = [
                            "id_product" => $item['id_product'],
                            "product_type" => $product['product_type'],
                            "product_category_name" => $product['product_category_name'],
                            "product_name" => $product['product_name'],
                            "product_base_price" => (int)$productGlobalPrice['product_global_price'],
                            "product_price" => (int)$product['product_price'],
                            "product_price_text" => 'Rp ' . number_format((int)$product['product_price'], 0, ",", "."),
                            "product_discount" => $product['product_price_discount'],
                            "product_price_before_discount" => $product['product_price_before_discount'],
                            "product_price_before_discount_text" => 'Rp ' . number_format($product['product_price_before_discount'], 0, ",", "."),
                            "product_price_subtotal" => (int)$totalPrice,
                            "product_price_subtotal_text" => 'Rp ' . number_format((int)$totalPrice, 0, ",", "."),
                            "product_variant_price" => (int)$variantPriceFinal,
                            "variants" => $product['variants'],
                            "qty" => $item['qty'],
                            "note" => $item['note']??null,
                            "current_stock" => $product['stock_item'],
                            "custom" => $item['custom'],
                            "id_product_wholesaler" => $idWholesaler ?? null,
                            "wholesaler_minimum" => $product['wholesaler_minimum'] ?? null,
                            "need_recipe_status" => $product['need_recipe_status'],
                            "can_buy_status" => $canBuyStatus,
                            "image" => $product['image'],
                            "error_message" => $error
                        ];

                        $idBrand = BrandProduct::where('id_product', $item['id_product'])->first()['id_brand'] ?? null;
                        if (!empty($idBrand)) {
                            $value['items'][$key]['id_brand'] = $idBrand;
                        }

                        if (!empty($error)) {
                            $errorMsg[] = $error;
                        }

                        if ($item['qty'] > $product['stock_item']) {
                            $availableCheckout = false;
                        }

                        if ($from_check == 1 && !empty($error)) {
                            unset($value['items'][$key]);
                            continue;
                        }
                    }else{
                        $idWholesaler = null;
                        $idWholesalerVariant = null;
                        $error = '';
                        $product = Product::select('need_recipe_status','product_type', 'product_weight', 'product_width', 'product_length', 'product_height', 'id_merchant', 'product_category_name', 'products.id_product_category', 'id_product', 'product_code', 'product_name', 'product_description', 'product_code', 'product_variant_status')
                            ->leftJoin('product_categories', 'product_categories.id_product_category', 'products.id_product_category')
                            ->where('product_visibility', 'Visible')
                            ->where('id_product', $item['id_product'])->first();

                        if (empty($product)) {
                            if (!empty($from_new)) {
                                $errorMsg[] = 'Produk tidak valid';
                            }

                            unset($value['items'][$key]);
                            continue;
                        } else {
                            $product = $product->toArray();
                        }
                        $product['product_price'] = 0;
                        $productGlobalPrice = array();
                        if($item['item']){
                            $pri = 0;
                            foreach($item['item'] as $v){
                                $productGlobalPrices = ProductPriceUser::where([
                                'id_product'=>$v,
                                'id_user'=>Auth::user()->id,
                                ])->first();
                                if(!$productGlobalPrices){
                                   $productGlobalPrices = ProductGlobalPrice::where('id_product', $v)->first(); 
                                }else{
                                    $productGlobalPrices['product_global_price'] = $productGlobalPrices['product_price'];
                                }
                                $pri = $pri + (int)$productGlobalPrices['product_global_price'];
                            }
                             $productGlobalPrice['product_global_price'] = $pri;
                        }
                        if ($productGlobalPrice) {
                            $service = 0;
                            $tax = 0;
                            $dtTaxService = ['subtotal' =>  $productGlobalPrice['product_global_price']];
                            $serviceCalculate = round(app($this->setting_trx)->countTransaction('service', $dtTaxService));
                            $service = $service + $serviceCalculate;
                            $taxCalculate = round(app($this->setting_trx)->countTransaction('tax', $dtTaxService));
                            $tax = $tax + $taxCalculate;

                            $service = round($service);
                            $tax = round($tax);
                           $product['product_price'] = $productGlobalPrice['product_global_price']+$tax;
                            $product['product_price_discount'] = $productGlobalPrice['global_price_discount_percent']??0;
                            $product['product_price_before_discount'] = $productGlobalPrice['global_price_before_discount']??0;
                        }

                        $product['stock_item'] = 0;
                        $product['variants'] = '';
                        $variantPriceFinal = 0;
                       $product['stock_item'] = ProductDetail::where('id_product', $item['id_product'])->where('id_outlet', $value['id_outlet'])->first()['product_detail_stock_item'] ?? 0;


                        $image = ProductPhoto::where('id_product', $product['id_product'])->orderBy('product_photo_order', 'asc')->first()['product_photo'] ?? null;
                        $product['image'] = (empty($image) ? config('url.storage_url_api') . 'img/default.jpg' : config('url.storage_url_api') . $image);

                        if (empty($productGlobalPrice['product_global_price'])) {
                            $error = 'Harga produk tidak valid';
                        }
                        $product['serving_method'] = null;
                        if(isset($item['id_product_serving_method'])){
                           $product['serving_method'] = ProductServingMethod::where('id_product_serving_method', $item['id_product_serving_method'])
                                   ->select('serving_name','unit_price','package')->first();    
                        }
                        $product['serving_method_price'] = 0;
                        if($product['serving_method']){
                            if($product['serving_method']['package']=='all'){
                                $product['serving_method_price'] = $product['serving_method']['unit_price'];
                            }else{
                                 $product['serving_method_price'] = $product['serving_method']['unit_price']*$item['qty'];
                            }
                        }

    //                    if ($item['qty'] > $product['stock_item']) {
    //                        $error = 'Jumlah item yang Anda pilih melebihi batas maksimal stock';
    //                    }


                        if ($product['product_price'] <= 0) {
                            $error = 'Produk tidak valid';
                        }

                        if (!empty($product['product_weight'])) {
                            $w = ($product['product_weight'] * $item['qty']) / 1000;
                            $weight[] = $w;
                            $weightProduct = $weightProduct + $w;
                            $dimentionProduct = $dimentionProduct + ($product['product_width'] * $product['product_height'] * $product['product_length'] * $item['qty']);
                        } else {
                            $error = 'Produk tidak valid';
                        }

                        if ($id_user == $merchant['id_user']) {
                            $error = 'Tidak bisa membeli produk sendiri';
                        }
                        $totalPrice = ((int)$product['product_price'] * $item['qty'])+ $product['serving_method_price'];
                        $productSubtotal = $productSubtotal + (int)$totalPrice;
                        $value['items'][$key] = [
                            "id_product" => $item['id_product'],
                            "product_type" => $product['product_type'],
                            "product_category_name" => $product['product_category_name'],
                            "product_name" => $product['product_name'],
                            "product_base_price" => (int)$productGlobalPrice['product_global_price'],
                            "product_price" => (int)$product['product_price'],
                            "product_price_text" => 'Rp ' . number_format((int)$product['product_price'], 0, ",", "."),
                            "product_discount" => $product['product_price_discount'],
                            "product_price_before_discount" => $product['product_price_before_discount'],
                            "product_price_before_discount_text" => 'Rp ' . number_format($product['product_price_before_discount'], 0, ",", "."),
                            "product_price_subtotal" => (int)$totalPrice,
                            "product_price_subtotal_text" => 'Rp ' . number_format((int)$totalPrice, 0, ",", "."),
                            "product_variant_price" => (int)$variantPriceFinal,
                            "variants" => $product['variants'],
                            "qty" => $item['qty'],
                            "note" => $item['note']??null,
                            "current_stock" => $product['stock_item'],
                            "custom" => $item['custom'],
                            "id_product_wholesaler" => $idWholesaler ?? null,
                            "wholesaler_minimum" => $product['wholesaler_minimum'] ?? null,
                            "need_recipe_status" => $product['need_recipe_status'],
                            "can_buy_status" => $canBuyStatus,
                            "image" => $product['image'],
                            "serving_method" => $product['serving_method']??null,
                            "serving_method_price" => $product['serving_method_price'],
                            "error_message" => $error
                        ];

                        if (!empty($error)) {
                            $errorMsg[] = $error;
                        }


                        if ($from_check == 1 && !empty($error)) {
                            unset($value['items'][$key]);
                            continue;
                        }
                    }
                }

                if (!empty($value['items'])) {
                    $subtotal = $subtotal + $productSubtotal;

                    $s = round($dimentionProduct ** (1 / 3), 0);
                    $items[$index]['outlet_holiday_status'] = $checkOutlet['outlet_is_closed'];
                    $items[$index]['outlet_name'] = $checkOutlet['outlet_name'];
                    $items[$index]['items_total_height'] = (int)$s;
                    $items[$index]['items_total_width'] = (int) $s;
                    $items[$index]['items_total_length'] = (int) $s;
                    $items[$index]['items_total_weight'] = (int) ceil($weightProduct);
                    $items[$index]['items_subtotal'] = $productSubtotal;
                    $items[$index]['items_subtotal_text'] = 'Rp ' . number_format($productSubtotal, 0, ",", ".");
                    $items[$index]['items'] = array_values($value['items']);

                    if ($checkOutlet['outlet_is_closed'] == 1 && ($from_check == 1 || $from_new == 1)) {
                        $errorMsg[] = 'Toko sedang libur';
                        $errorMsgSubgroup[] = 'Toko sedang libur';
                        unset($value['items'][$key]);
                        continue;
                    }
                    if ($from_cart == 0) {
                        $subdistrictOutlet = Subdistricts::where('id_subdistrict', $checkOutlet['id_subdistrict'])
                            ->join('districts', 'districts.id_district', 'subdistricts.id_district')->first();
                        if (empty($subdistrictOutlet)) {
                            $errorMsg[] = 'Address toko tidak valid';
                            $errorMsgSubgroup[] = 'Address toko tidak valid';
                            if ($from_new == 1) {
                                unset($value['items'][$key]);
                                continue;
                            }
                        }
                        $latOutlet = $subdistrictOutlet['subdistrict_latitude'];
                        $lngOutlet = $subdistrictOutlet['subdistrict_longitude'];

                        $subdistrictCustomer = Subdistricts::where('id_subdistrict', $dtAddress['id_subdistrict'])
                                    ->join('districts', 'districts.id_district', 'subdistricts.id_district')->first();
                        if (empty($subdistrictCustomer)) {
                            $errorMsg[] = 'Address tidak valid';
                            $errorMsgSubgroup[] = 'Address tidak valid';
                            if ($from_new == 1) {
                                unset($value['items'][$key]);
                                continue;
                            }
                        }
                        $latCustomer = $subdistrictCustomer['subdistrict_latitude'];
                        $lngCustomer = $subdistrictCustomer['subdistrict_longitude'];

                        $dtDeliveryPrice = [
                            "cod" =>  false,
                            "for_order" => true,
                            "destination" => [
                                "area_id" => $subdistrictCustomer['id_subdistrict_external'],
                                "lat" => $latCustomer,
                                "lng" => $lngCustomer,
                                "suburb_id" => $subdistrictCustomer['id_district_external']
                            ],
                            "origin" => [
                                "area_id" => $subdistrictOutlet['id_subdistrict_external'],
                                "lat" => $latOutlet,
                                "lng" => $lngOutlet,
                                "suburb_id" => $subdistrictOutlet['id_district_external']
                            ],
                            "weight" => (int) ceil($weightProduct),
                            "height" => (int)$s,
                            "width" => (int)$s,
                            "length" => (int)$s,
                            "item_value" => $productSubtotal,
                            "limit" => 100,
                            "sort_by" => [
                                "final_price"
                            ],
                        ];
                        $distance = ceil(MyHelper::getDistance($latOutlet, $lngOutlet, $latCustomer, $lngCustomer));
                        $price_distance = MyHelper::getPriceDistance($productSubtotal, $distance,$value['id_outlet']);
                        $rate = MyHelper::getOngkir($productSubtotal, $distance,$value['id_outlet']);
                        $items[$index]['distance'] = $distance;
                        $items[$index]['price_distance'] = $price_distance;
                        $items[$index]['total_delivery'] = $rate;
                        $productSubtotal = $productSubtotal+$rate;
                        $items[$index]['subtotal'] = $productSubtotal;
                        $items[$index]['subtotal_text'] = 'Rp ' . number_format($productSubtotal, 0, ",", ".");

                        $items[$index]['error_messages'] = implode('. ', array_unique($errorMsgSubgroup));
                        $deliveryPrice = $deliveryPrice + $rate;
                    }
                } else {
                    if (empty($errorMsg)) {
                        $errorMsg[] = 'Stock produk habis';
                    }
                    unset($items[$index]);
                    continue;
                }
            } else {
                $errorMsg[] = 'Outlet tidak ditemukan';
                unset($items[$index]);
                continue;
            }
        }
        
        return [
            'subtotal' => $subtotal,
            'tax' => $taxTotal,
            'items' => array_values($items),
            'total_delivery' => $deliveryPrice,
            'available_checkout' => $availableCheckout,
            'error_messages' => implode('. ', array_unique($errorMsg)),
            'weight' => array_sum($weight),
            'pupop_need_consultation' => ($canBuyStatus ? false : true),
            'data_need_recipe' => $needRecipeData
        ];
    }

    public function mergeProducts($items)
    {
        $tmp = [];
      
        foreach ($items as $value) {
            if (!empty($value['id_outlet'])) {
                $tmp[$value['id_outlet']]['id_outlet'] = $value['id_outlet'] ?? null;
                $tmp[$value['id_outlet']]['id_user_address'] = $value['id_user_address'] ?? '';
                $tmp[$value['id_outlet']]['transaction_date'] = $value['transaction_date'] ?? '';
                $tmp[$value['id_outlet']]['items'] = array_merge($tmp[$value['id_outlet']]['items'] ?? [], $value['items']);
            }
        }
        
        $items = array_values($tmp);

        // create unique array
        foreach ($items as $index => $val) {
          
            $new_items = [];
            $item_qtys = [];
            $id_custom = [];
            $id_product_serving_method = [];
            $product = [];
            foreach ($val['items'] as $item) {
                $new_item = [
                    'id_product' => $item['id_product'],
                ];
                $pos = array_search($new_item, $new_items);
                if ($pos === false) {
                    $new_items[] = $new_item;
                    $item_qtys[] = $item['qty'];
                    $id_custom[] = $item['custom'] ?? 0;
                    if($item['custom'] == 1){
                       $id_product_serving_method[] = $item['id_product_serving_method']; 
                       $product[] = $item['item']; 
                    }
                } else {
                    $item_qtys[$pos] += $item['qty'];
                }
            }
            foreach ($new_items as $key => &$value) {
                $value['qty'] = $item_qtys[$key];
                $value['custom'] = $id_custom[$key];
                if($id_custom[$key]??0 == 1){
                    $value['id_product_serving_method'] = $id_product_serving_method[$key];
                    $value['item'] = $product[$key];
                }
            }

            $items[$index]['items'] = $new_items;
        }

        return $items;
    }
}
