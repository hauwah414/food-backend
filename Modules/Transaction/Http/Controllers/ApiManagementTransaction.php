<?php

namespace Modules\Transaction\Http\Controllers;

use App\Http\Models\Subdistricts;
use App\Http\Models\Districts;
use App\Http\Models\Deal;
use App\Http\Models\ProductPhoto;
use App\Http\Models\TransactionProductModifier;
use App\Lib\Shipper;
use Illuminate\Pagination\Paginator;
use App\Http\Models\Transaction;
use App\Http\Models\TransactionProduct;
use App\Http\Models\TransactionPayment;
use Modules\PromoCampaign\Entities\PromoCampaignPromoCode;
use App\Http\Models\TransactionPickupGoSend;
use App\Http\Models\TransactionPickupWehelpyou;
use App\Http\Models\Province;
use App\Http\Models\City;
use App\Http\Models\User;
use App\Http\Models\Courier;
use App\Http\Models\Product;
use App\Http\Models\ProductPrice;
use App\Http\Models\ProductModifierPrice;
use App\Http\Models\ProductModifierGlobalPrice;
use App\Http\Models\Setting;
use App\Http\Models\StockLog;
use App\Http\Models\UserAddress;
use App\Http\Models\ManualPayment;
use App\Http\Models\ManualPaymentMethod;
use App\Http\Models\ManualPaymentTutorial;
use App\Http\Models\TransactionPaymentManual;
use App\Http\Models\TransactionPaymentOffline;
use App\Http\Models\TransactionPaymentBalance;
use Modules\Disburse\Entities\MDR;
use Modules\IPay88\Entities\TransactionPaymentIpay88;
use App\Http\Models\TransactionMultiplePayment;
use App\Http\Models\Outlet;
use App\Http\Models\LogPoint;
use App\Http\Models\LogBalance;
use App\Http\Models\TransactionShipment;
use App\Http\Models\TransactionPickup;
use App\Http\Models\TransactionPaymentMidtran;
use Modules\ProductVariant\Entities\ProductVariant;
use Modules\ProductVariant\Entities\ProductVariantPivot;
use Modules\ProductVariant\Entities\TransactionProductVariant;
use Modules\ShopeePay\Entities\TransactionPaymentShopeePay;
use App\Http\Models\DealsUser;
use App\Http\Models\DealsPaymentMidtran;
use App\Http\Models\DealsPaymentManual;
use Modules\IPay88\Entities\DealsPaymentIpay88;
use Modules\ShopeePay\Entities\DealsPaymentShopeePay;
use App\Http\Models\UserTrxProduct;
use Modules\Brand\Entities\Brand;
use Modules\Product\Entities\ProductGlobalPrice;
use Modules\Product\Entities\ProductSpecialPrice;
use Modules\Transaction\Entities\TransactionShipmentTrackingUpdate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Subscription\Entities\SubscriptionUserVoucher;
use Modules\Transaction\Entities\LogInvalidTransaction;
use Modules\Transaction\Entities\TransactionBundlingProduct;
use Modules\Transaction\Http\Requests\RuleUpdate;
use Modules\Transaction\Http\Requests\TransactionDetail;
use Modules\Transaction\Http\Requests\TransactionHistory;
use Modules\Transaction\Http\Requests\TransactionFilter;
use Modules\Transaction\Http\Requests\TransactionNew;
use Modules\Transaction\Http\Requests\TransactionShipping;
use Modules\Transaction\Http\Requests\GetProvince;
use Modules\Transaction\Http\Requests\GetCity;
use Modules\Transaction\Http\Requests\GetSub;
use Modules\Transaction\Http\Requests\GetAddress;
use Modules\Transaction\Http\Requests\GetNearbyAddress;
use Modules\Transaction\Http\Requests\AddAddress;
use Modules\Transaction\Http\Requests\UpdateAddress;
use Modules\Transaction\Http\Requests\DeleteAddress;
use Modules\Transaction\Http\Requests\ManualPaymentCreate;
use Modules\Transaction\Http\Requests\ManualPaymentEdit;
use Modules\Transaction\Http\Requests\ManualPaymentUpdate;
use Modules\Transaction\Http\Requests\ManualPaymentDetail;
use Modules\Transaction\Http\Requests\ManualPaymentDelete;
use Modules\Transaction\Http\Requests\MethodSave;
use Modules\Transaction\Http\Requests\MethodDelete;
use Modules\Transaction\Http\Requests\ManualPaymentConfirm;
use Modules\Transaction\Http\Requests\ShippingGoSend;
use Modules\Transaction\Entities\TransactionGroup;
use Modules\ProductVariant\Entities\ProductVariantGroup;
use Modules\ProductVariant\Entities\ProductVariantGroupSpecialPrice;
use Modules\Xendit\Entities\TransactionPaymentXendit;
use Modules\UserRating\Entities\UserRating;
use App\Lib\MyHelper;
use App\Lib\GoSend;
use App\Lib\Midtrans;
use Validator;
use Hash;
use DB;
use Mail;
use Image;
use Illuminate\Support\Facades\Log;
use Modules\Quest\Entities\Quest;
use Modules\Transaction\Http\Requests\TransactionDetailVA;
use Modules\Merchant\Entities\Merchant;
use App\Http\Models\Cart;
use App\Http\Models\ProductPriceUser;
use Modules\Favorite\Entities\Favorite;
use App\Http\Models\ProductServingMethod;
use App\Http\Models\ProductCustomGroup;
use Modules\Product\Entities\ProductDetail;
use App\Http\Models\TransactionProductServingMethod;
use App\Http\Models\TransactionProductBox;

class ApiManagementTransaction  extends Controller
{
    public $saveImage = "img/transaction/manual-payment/";

    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->shopeepay      = 'Modules\ShopeePay\Http\Controllers\ShopeePayController';
        $this->xendit         = 'Modules\Xendit\Http\Controllers\XenditController';
        $this->shipper         = 'Modules\Transaction\Http\Controllers\ApiShipperController';
        $this->setting_trx   = "Modules\Transaction\Http\Controllers\ApiSettingTransactionV2";
        $this->management_merchant = "Modules\Merchant\Http\Controllers\ApiMerchantManagementController";
    }
    public function updateDate(Request $request)
    {
        $post = $request->json()->all();
        $user = $request->user();
        $check = Transaction::where('id_transaction', $post['id_transaction'])->first();
        if (empty($check)) {
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Transaction Not Found'],
            ]);
        }
        $outlet = Outlet::where('id_outlet',$check['id_outlet'])
                ->first();
        if(strtotime($outlet->open) <= strtotime(date('H:i', strtotime($post['transaction_date']))) && strtotime($outlet->close) >= strtotime(date('H:i', strtotime($post['transaction_date'])))){
            $check->transaction_date =$post['transaction_date'];
            $check->save();
            return response()->json([
                'status' => 'success',
                'result' => $check,
            ]);
        }
        return response()->json(['status'    => 'fail', 'messages'  => ['Outlet '.$outlet->outlet_name .'Buka dari jam '.$outlet->open.'-'.$outlet->close]]);
    }
    public function updateSumberDana(Request $request)
    {
        $post = $request->json()->all();
        $user = $request->user();
        $check = TransactionGroup::where('id_transaction_group', $post['id_transaction_group'])->first();
        if (empty($check)) {
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Transaction Not Found'],
            ]);
        }
        $check = TransactionGroup::where('id_transaction_group', $post['id_transaction_group'])->update([
            'sumber_dana'=>$post['sumber_dana'],
            'tujuan_pembelian'=>$post['tujuan_pembelian'],
        ]);
       return MyHelper::checkUpdate($check);
    }
    public function updateQty(Request $request)
    {
        
        $post = $request->json()->all();
        $user = $request->user();
        
        DB::beginTransaction();
        if (empty($post)) {
            DB::rollback();
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Failed update Quantity'],
            ]);
        }
        $total_transaction_product_subtotal = 0;
        $transaction_cogs = 0;
        $total_transaction_product_price_service = 0;
        foreach($post['item'] as $value){
           $transaction_product = TransactionProduct::where('id_transaction_product',$value['id_transaction_product'])->first();
//           $transaction_product = Transaction::where('id_transaction',$transaction_product['id_transaction'])->first();
           if($transaction_product){
               $transaction_product_price = $transaction_product['transaction_product_price'];
               $transaction_product_subtotal = $transaction_product_price * $value['qty'] + $transaction_product['transaction_serving_method'];
               $total_transaction_product_subtotal = $total_transaction_product_subtotal + $transaction_product_subtotal;
               $transaction_cogs = $transaction_cogs + ($transaction_product['transaction_product_cogs']* $value['qty']);
               $total_transaction_product_price_service = $total_transaction_product_price_service + ($transaction_product['transaction_product_price_service']* $value['qty']);
               $transaction_product['transaction_product_qty'] = $value['qty'];
               $transaction_product['transaction_product_subtotal'] = $transaction_product_subtotal;
               $transaction_product['transaction_product_net'] = $transaction_product_subtotal;
               $transaction_product->save();
           }
        }
        $transaction = Transaction::where('id_transaction',$post['id_transaction'])->first();
        if($transaction){
            $transaction = Transaction::where('id_transaction',$post['id_transaction'])->update([
                'transaction_subtotal'=>$total_transaction_product_subtotal,
                'transaction_gross'=>$total_transaction_product_subtotal,
                'transaction_service'=>$total_transaction_product_price_service,
                'transaction_grandtotal'=>$total_transaction_product_subtotal+$transaction['transaction_shipment'],            
                'transaction_cogs'=>$transaction_cogs,            
            ]);
        }else{
            DB::rollback();
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Failed update Quantity'],
            ]);
        }
       $transaction = Transaction::where('id_transaction_group',$post['id_transaction_group'])->get();
       $subtotal = 0;
       $service = 0;
       $cogs = 0;
       foreach ($transaction as $value) {
        $subtotal = $subtotal + $value['transaction_subtotal'];
        $service = $service + $value['transaction_service'];
        $cogs = $cogs + $value['transaction_cogs'];
       }
       $transaction_group = TransactionGroup::where('id_transaction_group',$post['id_transaction_group'])->first();
       if($transaction_group){
           $transaction_group = TransactionGroup::where('id_transaction_group',$post['id_transaction_group'])->update([
               'transaction_subtotal'=>$subtotal,
               'transaction_service'=>$service,
               'transaction_cogs'=>$cogs,
               'transaction_grandtotal'=>$subtotal+$transaction_group['transaction_shipment'],
           ]);
       }else{
           DB::rollback();
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Failed update Quantity'],
            ]);
       }
       DB::commit();
       return MyHelper::checkUpdate($transaction_group);
    }
    public function updateOngkir(Request $request)
    {
        
        $post = $request->json()->all();
        $user = $request->user();
        
        DB::beginTransaction();
        if (empty($post)) {
            DB::rollback();
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Failed update Quantity'],
            ]);
        }
   
        $transaction = Transaction::where('id_transaction',$post['id_transaction'])->first();
        if($transaction){
            Transaction::where('id_transaction',$post['id_transaction'])->update([
                'status_ongkir'=>(int)$post['status_ongkir'],
                'transaction_shipment'=>(int)$post['transaction_shipment'],
                'transaction_grandtotal'=>$transaction['transaction_grandtotal']-$transaction['transaction_shipment']+(int)$post['transaction_shipment'],       
            ]);
        }else{
            DB::rollback();
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Failed update Ongkir'],
            ]);
        }
       $transactions = Transaction::where('id_transaction_group',$transaction['id_transaction_group'])->get();
       $shipment = 0;
       foreach ($transactions as $value) {
        $shipment = $shipment+$value['transaction_shipment'];
       }
       $transaction_group = TransactionGroup::where('id_transaction_group',$transaction['id_transaction_group'])->first();
       if($transaction_group){
           $transaction_group = TransactionGroup::where('id_transaction_group',$transaction['id_transaction_group'])->update([
               'transaction_shipment'=>$shipment,
               'transaction_grandtotal'=>$shipment+$transaction_group['transaction_grandtotal']-$transaction_group['transaction_shipment'],
           ]);
       }else{
           DB::rollback();
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Failed update Quantity'],
            ]);
       }
       DB::commit();
       return MyHelper::checkUpdate($transaction_group);
    }
    public function itemDelete(Request $request)
    {
        
        $post = $request->json()->all();
        $user = $request->user();
        
        DB::beginTransaction();
       if (empty($post)) {
            DB::rollback();
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Failed delete item'],
            ]);
        }
        $transaction_product = TransactionProduct::where('id_transaction_product',$post['id_transaction_product'])->first();
        if($transaction_product){
            $transactions = TransactionProduct::where('id_transaction_product',$post['id_transaction_product'])->delete();
        }else{
            DB::rollback();
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Failed update Quantity'],
            ]);
        }
        $subtotal = 0;
        $total_transaction_product_price_service = 0;
        $cogs = 0;
        $service = 0;
        $transaction = TransactionProduct::where('id_transaction',$transaction_product['id_transaction'])->get();
        foreach($transaction as $value){
          $subtotal = $subtotal + $value['transaction_product_subtotal'];
          $cogs = $cogs + ($value['transaction_product_cogs']*$value['transaction_product_qty']);
          $service = $service + ($value['transaction_product_price_service']*$value['transaction_product_qty']);
        }
        
        $transaction = Transaction::where('id_transaction',$transaction_product['id_transaction'])->first();
        if($transaction){
            $transactions = Transaction::where('id_transaction',$transaction_product['id_transaction'])->update([
                'transaction_subtotal'=>$subtotal,
                'transaction_gross'=>$subtotal,
                'transaction_service'=>$service,
                'transaction_grandtotal'=>$subtotal+$transaction['transaction_shipment'],            
                'transaction_cogs'=>$cogs,            
            ]);
        }else{
            DB::rollback();
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Failed update Quantity'],
            ]);
        }
       $transaction_data = Transaction::where('id_transaction_group',$transaction['id_transaction_group'])->get();
       $subtotal = 0;
       $service = 0;
       $cogs = 0;
       foreach ($transaction_data as $value) {
        $subtotal = $subtotal + $value['transaction_subtotal'];
        $service = $service + $value['transaction_service'];
        $cogs = $cogs + $value['transaction_cogs'];
       }
       $transaction_group = TransactionGroup::where('id_transaction_group',$transaction['id_transaction_group'])->first();
       if($transaction_group){
           $transaction_group = TransactionGroup::where('id_transaction_group',$transaction['id_transaction_group'])->update([
               'transaction_subtotal'=>$subtotal,
               'transaction_service'=>$service,
               'transaction_cogs'=>$cogs,
               'transaction_grandtotal'=>$cogs+$transaction_group['transaction_shipment'],
           ]);
       }else{
           DB::rollback();
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Failed update Quantity'],
            ]);
       }
       $transaction_group = TransactionGroup::where('id_transaction_group',$transaction['id_transaction_group'])->first();
       DB::commit();
       return MyHelper::checkUpdate($transaction_group);
    }
    
    public function itemAdd(Request $request)
    {
        
        $post = $request->json()->all();
        $user = $request->user();
        $availableCheckout = true;
        $canBuyStatus = true;
        $subtotal = 0;
        $total_cogs = 0;
        $total_service = 0;
        $taxTotal = 0;
        $deliveryPrice = 0;
        $errorMsg = [];
        $weight = [];
        $taxOutlet = 0;
            $serviceOutlet = 0;
            $cogsOutlet = 0;
        $data = array();
        DB::beginTransaction();
        $trans = Transaction::where('id_transaction',$post['id_transaction'])->first();
       if (empty($trans)) {
            DB::rollback();
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Tambah menu gagal'],
            ]);
        }
        $merchant = Merchant::where('id_outlet', $trans['id_outlet'])->first();
        $checkOutlet = Outlet::where('id_outlet', $trans['id_outlet'])->where('outlet_status', 'Active')->first();
        if (empty($checkOutlet)) {
            DB::rollback();
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Outlet tidak ada'],
            ]);
        }
        $productSubtotal = 0;
                $weightProduct = 0;
                $dimentionProduct = 0;
        foreach($post['items'] as $key => $item){
          if($item['custom']==0){
                        $idWholesaler = null;
                        $idWholesalerVariant = null;
                        $error = '';
                        $product = Product::select('need_recipe_status','product_type','min_transaction',  'product_weight', 'product_width', 'product_length', 'product_height', 'id_merchant', 'product_category_name', 'products.id_product_category', 'id_product', 'product_code', 'product_name', 'product_description', 'product_code', 'product_variant_status')
                            ->leftJoin('product_categories', 'product_categories.id_product_category', 'products.id_product_category')
                            ->where('product_visibility', 'Visible')
                            ->where('id_product', $item['id_product'])->first();
                        
                        if (empty($product)) {
                            if (!empty($from_new)) {
                                $errorMsg[] = 'Produk tidak valid';
                            }

                            unset($items);
                            continue;
                        } else {
                            $product = $product->toArray();
                        }
                        $product['product_price'] = 0;
                        $productGlobalPrice = ProductPriceUser::where([
                            'id_product'=>$item['id_product'],
                            'id_user'=>$trans->id_user,
                            ])->first();
                        if(!$productGlobalPrice){
                           $productGlobalPrice = ProductGlobalPrice::where('id_product', $item['id_product'])->first(); 
                          $base = (int)ProductGlobalPrice::where('id_product', $item['id_product'])->first()['product_global_price']; 
                        }else{
                            $productGlobalPrice['product_global_price'] = $productGlobalPrice['product_price'];
                            $base = (int)ProductGlobalPrice::where('id_product', $item['id_product'])->first()['product_global_price']; 
                        }

                        if ($productGlobalPrice) {
                            $service = 0;
                            $tax = 0;
                            $dtTaxService = ['subtotal' =>  $productGlobalPrice['product_global_price']];
                            $taxCalculate = round(app($this->setting_trx)->countTransaction('tax', $dtTaxService));
                            $tax = $tax + $taxCalculate;
                            
                            $tax = round($tax);
                           $product['product_price'] = $productGlobalPrice['product_global_price']+$tax;
                           
                            $serviceCalculate = round(app($this->setting_trx)->countTaxService('service', ['subtotal' =>  $product['product_price']],$checkOutlet->fee,$base));
                            $service = $service + $serviceCalculate;
                            $service = round($service);
                            $cogs =  $product['product_price'] - $service;
                            $product['product_price_discount'] = $productGlobalPrice['global_price_discount_percent']??0;
                            $product['product_price_before_discount'] = $productGlobalPrice['global_price_before_discount']??0;
                        }

                        $product['stock_item'] = 0;
                        $product['variants'] = '';
                        $variantPriceFinal = 0;
                       $product['stock_item'] = ProductDetail::where('id_product', $item['id_product'])->where('id_outlet', $trans['id_outlet'])->first()['product_detail_stock_item'] ?? 0;


                        $image = ProductPhoto::where('id_product', $product['id_product'])->orderBy('product_photo_order', 'asc')->first()['product_photo'] ?? null;
                        $product['image'] = (empty($image) ? config('url.storage_url_api') . 'img/default.jpg' : config('url.storage_url_api') . $image);

                        if (empty($productGlobalPrice['product_global_price'])) {
                            $error = 'Harga produk tidak valid';
                        }


    //                    if ($item['qty'] > $product['stock_item']) {
    //                        $error = 'Jumlah item yang Anda pilih melebihi batas maksimal stock';
    //                    }
                        if ($item['qty'] < $product['min_transaction']) {
                             $availableCheckout = false;
                             
//                            $item['qty'] = $product['min_transaction'];
                            $error = 'Jumlah '.$product['product_name'].' item harus minimal transaksi '.$product['min_transaction'];
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


                        $totalPrice = (int)$product['product_price'] * $item['qty'];
                        $productSubtotal = $productSubtotal + (int)$totalPrice;
                        $data[] = [
                            "id_product" => $item['id_product'],
                            "product_type" => $product['product_type'],
                            "product_category_name" => $product['product_category_name'],
                            "product_name" => $product['product_name'],
                            "product_base_price" => (int)$productGlobalPrice['product_global_price'],
                            "product_price" => (int)$product['product_price'],
                            "tax" => (int)$tax,
                            "service" => (int)$service,
                            "cogs" => (int)$cogs,
                            "serving_method_price" => (int)0,
                            "product_price_text" => 'Rp ' . number_format((int)$product['product_price'], 0, ",", "."),
                            "product_discount" => $product['product_price_discount'],
                            "product_price_before_discount" => $product['product_price_before_discount'],
                            "product_price_before_discount_text" => 'Rp ' . number_format($product['product_price_before_discount'], 0, ",", "."),
                            "product_price_subtotal" => (int)$totalPrice,
                            "product_price_subtotal_text" => 'Rp ' . number_format((int)$totalPrice, 0, ",", "."),
                            "product_variant_price" => (int)$variantPriceFinal,
                            "variants" => $product['variants'],
                            "qty" => $item['qty'],
                            "min_transaction" => $product['min_transaction'],
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


                        if (!empty($error)) {
                            $errorMsg[] = $error;
                        }
                        $total_cogs = $total_cogs+($cogs*$item['qty']);
                        $cogsOutlet  = $cogsOutlet+($cogs*$item['qty']);
                        $total_service = $total_service+($service*$item['qty']);
                        $taxTotal = $taxTotal+($tax*$item['qty']);
                        $taxOutlet = $taxOutlet+($tax*$item['qty']);
                        $serviceOutlet = $serviceOutlet+($service*$item['qty']);

                    }else{
                        $idWholesaler = null;
                        $idWholesalerVariant = null;
                        $error = '';
                       $product = Product::select('need_recipe_status','product_type', 'min_transaction','product_weight', 'product_width', 'product_length', 'product_height', 'id_merchant', 'product_category_name', 'products.id_product_category', 'id_product', 'product_code', 'product_name', 'product_description', 'product_code', 'product_variant_status')
                            ->leftJoin('product_categories', 'product_categories.id_product_category', 'products.id_product_category')
                            ->where('product_visibility', 'Visible')
                            ->where('id_product', $item['id_product'])->first();
                         if ($item['qty'] <= $product['min_transaction']) {
                                    $item ['qty'] = $product['min_transaction'];
        //                            $error = 'Jumlah item harus melebihi minimal transaksi';
                                }
                        if (empty($product)) {
                            if (!empty($from_new)) {
                                $errorMsg[] = 'Produk tidak valid';
                            }

                            unset($item[$key]);
                            continue;
                        } else {
                            $product = $product->toArray();
                        }
                        $product['product_price'] = 0;
                        $product['products'] = null;
                        $productGlobalPrice = array();
                        if($item['item']){
                            $pri = 0;
                            $pro = array();
                            foreach($item['item'] as $v){
                                $productGlobalPrices = ProductPriceUser::where([
                                'id_product'=>$v,
                                'id_user'=>$trans->id_user,
                                ])->first();
                                $produc_item= Product::select('need_recipe_status','min_transaction','product_type', 'product_weight', 'product_width', 'product_length', 'product_height', 'id_merchant', 'product_category_name', 'products.id_product_category', 'id_product', 'product_code', 'product_name', 'product_description', 'product_code', 'product_variant_status')
                                    ->leftJoin('product_categories', 'product_categories.id_product_category', 'products.id_product_category')
                                    ->where('product_visibility', 'Visible')
                                    ->where('id_product', $v)->first();
                                
                                if(!$productGlobalPrices){
                                   $productGlobalPrices = ProductGlobalPrice::where('id_product', $v)->first();
                                   $base = ProductGlobalPrice::where('id_product', $v)->first()['product_global_price'];
                                }else{
                                    $productGlobalPrices['product_global_price'] = $productGlobalPrices['product_price'];
                                    $base = ProductGlobalPrice::where('id_product', $v)->first()['product_global_price'];
                                }
                                $base_price = 0;
                                if ($productGlobalPrices) {
                                    $service = 0;
                                    $tax = 0;
                                    $dtTaxService = ['subtotal' =>  $productGlobalPrices['product_global_price']];
                                    $taxCalculate = round(app($this->setting_trx)->countTransaction('tax', $dtTaxService));
                                    $tax = $tax + $taxCalculate;
                                    $tax = round($tax);
                                    $base_price = $productGlobalPrices['product_global_price'];
                                    $productGlobalPricess['product_global_price'] = $productGlobalPrices['product_global_price']+$tax;
                                    $serviceCalculate = round(app($this->setting_trx)->countTaxService('service', ['subtotal' =>  $productGlobalPricess['product_global_price']],$checkOutlet->fee,$base));
                                    $service = $service + $serviceCalculate;
                                    $service = round($service);
                                  
                                }
                                    
                                    
                                    
                                    
                                $pro[] = array(
                                    'id_product'=>$v,
                                    'product_name'=>$produc_item['product_name'],
                                    'product_global_price'=>$productGlobalPricess['product_global_price'],
                                    'base_price'=>$base_price,
                                    'tax'=>$tax,
                                    'service'=>$service,
                                    'cogs'=>$base-$service
                                );
                                $pri = $pri + (int)$productGlobalPrices['product_global_price'];
                            }
                             $productGlobalPrice['product_global_price'] = $pri;
                             $product['products'] = $pro;
                        }
                        $service = 0;
                        $tax = 0;
                        $cogs = 0;
                         $product['product_price'] = 0;
                        foreach($product['products'] as $values){
                            $service = $service+$values['service'];
                             $product['product_price'] =  $product['product_price']+$values['product_global_price'];
                            $tax = $tax+$values['tax'];
                            $cogs = $cogs+((int)$values['base_price']-$values['service']);
                            
                        }
                        if ($productGlobalPrice) {
                            $product['product_price_discount'] = $productGlobalPrice['global_price_discount_percent']??0;
                            $product['product_price_before_discount'] = $productGlobalPrice['global_price_before_discount']??0;
                        }

                        $product['stock_item'] = 0;
                        $product['variants'] = '';
                        $variantPriceFinal = 0;
                       $product['stock_item'] = ProductDetail::where('id_product', $item['id_product'])->where('id_outlet', $trans['id_outlet'])->first()['product_detail_stock_item'] ?? 0;


                        $image = ProductPhoto::where('id_product', $product['id_product'])->orderBy('product_photo_order', 'asc')->first()['product_photo'] ?? null;
                        $product['image'] = (empty($image) ? config('url.storage_url_api') . 'img/default.jpg' : config('url.storage_url_api') . $image);

                        if (empty($productGlobalPrice['product_global_price'])) {
                            $error = 'Harga produk tidak valid';
                        }
                        $product['serving_method'] = null;
                        if(isset($item['id_product_serving_method'])){
                           $product['id_product_serving_method'] = $item['id_product_serving_method'];
                           $product['serving_method'] = ProductServingMethod::where('id_product_serving_method', $item['id_product_serving_method'])
                                   ->select('id_product_serving_method','serving_name','unit_price','package')->first();    
                           
                        }
                        $product['serving_method_price'] = 0;
                        if($product['serving_method']){
                            if($product['serving_method']['package']=='all'){
                                $product['serving_method_price'] = $product['serving_method']['unit_price'];
                            }else{
                                 $product['serving_method_price'] = $product['serving_method']['unit_price']*$item['qty'];
                            }
                        }
                        if ($item['qty'] < $product['min_transaction']) {
                            $availableCheckout = false;
                           $error = 'Jumlah item '.$product['product_name'].' harus minimal transaksi '.$product['min_transaction'];
                        }


                        if ($product['product_price'] <= 0) {
                            $error = 'Produk tidak valid';
                        }
                        if(empty($value['transaction_date'])) {
                                $errorMsg[] = 'Transaction date tidak valid';
                             }
                        if (!empty($product['product_weight'])) {
                            $w = ($product['product_weight'] * $item['qty']) / 1000;
                            $weight[] = $w;
                            $weightProduct = $weightProduct + $w;
                            $dimentionProduct = $dimentionProduct + ($product['product_width'] * $product['product_height'] * $product['product_length'] * $item['qty']);
                        } else {
                            $error = 'Produk tidak valid';
                        }

                       
                        $totalPrice = ((int)$product['product_price'] * $item['qty'])+ $product['serving_method_price'];
                        $productSubtotal = $productSubtotal + (int)$totalPrice;
                        $data[] = [
                            "id_product" => $item['id_product'],
                            "product_type" => $product['product_type'],
                            "product_category_name" => $product['product_category_name'],
                            "product_name" => $product['product_name'],
                            "product_base_price" => (int)$productGlobalPrice['product_global_price'],
                            "product_price" => (int)$product['product_price'],
                            "tax" => (int)$tax,
                            "service" => (int)$service,
                            "cogs" => $cogs,
                            "serving_method_price" => $product['serving_method_price'],
                            "product_price_text" => 'Rp ' . number_format((int)$product['product_price'], 0, ",", "."),
                            "product_discount" => $product['product_price_discount'],
                            "product_price_before_discount" => $product['product_price_before_discount'],
                            "product_price_before_discount_text" => 'Rp ' . number_format($product['product_price_before_discount'], 0, ",", "."),
                            "product_price_subtotal" => (int)$totalPrice,
                            "product_price_subtotal_text" => 'Rp ' . number_format((int)$totalPrice, 0, ",", "."),
                            "product_variant_price" => (int)$variantPriceFinal,
                            "variants" => $product['variants'],
                            "qty" => $item['qty'],
                            "min_transaction" => $product['min_transaction'],
                            "note" => $item['note']??null,
                            "current_stock" => $product['stock_item'],
                            "custom" => $item['custom'],
                            "id_product_wholesaler" => $idWholesaler ?? null,
                            "wholesaler_minimum" => $product['wholesaler_minimum'] ?? null,
                            "need_recipe_status" => $product['need_recipe_status'],
                            "can_buy_status" => $canBuyStatus,
                            "image" => $product['image'],
                            "id_product_serving_method" => $product['id_product_serving_method']??null,
                            "serving_method" => $product['serving_method']??null,
                            "serving_method_price" => $product['serving_method_price'],
                            "products" => $product['products'],
                            "error_message" => $error
                        ];
                        $total_cogs = $total_cogs+($cogs*$item['qty']);
                        $total_service = $total_service+($service*$item['qty']);
                        $taxTotal = $taxTotal+($tax*$item['qty']);
                        $taxOutlet = $taxOutlet+($tax*$item['qty']);
                        $serviceOutlet = $serviceOutlet+($service*$item['qty']);
                        
                        $cogsOutlet  = $cogsOutlet+($cogs*$item['qty']);
                        if (!empty($error)) {
                            $errorMsg[] = $error;
                        }

                    }
        }
       foreach ($data as $keyProduct => $valueProduct) {
                $checkProduct = Product::where('id_product', $valueProduct['id_product'])->first();
                if (empty($checkProduct)) {
                    DB::rollback();
                    return response()->json([
                        'status'    => 'fail',
                        'messages'  => ['Menu tidak ditemukan']
                    ]);
                }

                $variantSubtotal = $valueProduct['product_variant_price'] - $valueProduct['product_base_price'];
                $dataProduct = [
                    'id_transaction'               => $post['id_transaction'],
                    'id_product'                   => $checkProduct['id_product'],
                    'type'                         => $checkProduct['product_type'],
                    'transaction_product_recipe_status' => $checkProduct['need_recipe_status'],
                    'id_product_variant_group'     => (empty($valueProduct['id_product_variant_group']) ? null : $valueProduct['id_product_variant_group']),
                    'id_brand'                     => null,
                    'id_outlet'                    => $trans['id_outlet'],
                    'id_user'                      => $trans['id_user'],
                    'product_note'      => $valueProduct['note'],
                    'transaction_product_qty'      => $valueProduct['qty'],
                    'transaction_product_price'    => $valueProduct['product_price'],
                    'transaction_product_price_base' => (!empty($valueProduct['product_price_before_discount']) ? $valueProduct['product_price_before_discount'] : $valueProduct['product_price']),
                    'transaction_product_price_tax' => $valueProduct['tax'],
                    'transaction_product_price_service' => $valueProduct['service'],
                    'transaction_product_subtotal' => $valueProduct['product_price_subtotal'],
                    'transaction_product_net' => $valueProduct['product_price_subtotal'],
                    'transaction_variant_subtotal' => ($variantSubtotal < 0 ? 0 : $variantSubtotal),
                    'transaction_product_note'     => $valueProduct['note'],
                    'transaction_product_wholesaler_minimum_qty'  => $valueProduct['wholesaler_minimum'] ?? null,
                    'created_at'                   => date('Y-m-d'),
                    'updated_at'                   => date('Y-m-d H:i:s'),
                    'transaction_product_discount' => $valueProduct['total_discount'] ?? 0,
                    'transaction_product_discount_all' => $valueProduct['total_discount'] ?? 0,
                    'transaction_product_qty_discount' => $valueProduct['qty_discount'] ?? 0,
                    'transaction_product_base_discount' => $valueProduct['base_discount_each_item'] ?? 0,
                    'transaction_product_cogs'=>$valueProduct['cogs'],
                    'transaction_serving_method'=>$valueProduct['serving_method_price'],
                    'transaction_product_fee'=>$checkOutlet->fee,
                ];
                $trx_product = TransactionProduct::create($dataProduct);
                if (!$trx_product) {
                    DB::rollback();
                    return response()->json([
                        'status'    => 'fail',
                        'messages'  => ['Insert Product Transaction Failed']
                    ]);
                }
                if($valueProduct['product_type']=="box"){
                    if(isset($valueProduct['serving_method'])){
                       $serv = TransactionProductServingMethod::create([
                            'id_transaction_product'=>$trx_product->id_transaction_product,
                            'id_product_serving_method'=>$valueProduct['serving_method']['id_product_serving_method'],
                            'serving_name'=>$valueProduct['serving_method']['serving_name'],
                            'unit_price'=>$valueProduct['serving_method']['unit_price'],
                            'package'=>$valueProduct['serving_method']['package'],
                        ]);
                    }
                    if(isset($valueProduct['products'])){
                        foreach($valueProduct['products'] as $pro){
                            $serv = TransactionProductBox::create([
                                'id_transaction_product'=>$trx_product->id_transaction_product,
                                'id_product'=>$pro['id_product'],
                                'product_price'=>$pro['product_global_price'],
                                'base_price'=>(int)$pro['base_price'],
                                'tax'=>$pro['tax'],
                                'service'=>$pro['service'],
                                'cogs'=>$pro['cogs'],
                                'fee'=>$checkOutlet->fee,
                            ]);
                        }
                    }
                }
                
            }
            
            
            $transaction = TransactionProduct::where('id_transaction',$post['id_transaction'])->get();
            foreach($transaction as $value){
              $subtotal = $subtotal + $value['transaction_product_subtotal'];
              $cogs = $cogs + ($value['transaction_product_cogs']*$value['transaction_product_qty']);
              $service = $service + ($value['transaction_product_price_service']*$value['transaction_product_qty']);
            }

            $transaction = Transaction::where('id_transaction',$post['id_transaction'])->first();
            if($transaction){
                $transactions = Transaction::where('id_transaction',$post['id_transaction'])->update([
                    'transaction_subtotal'=>$subtotal,
                    'transaction_gross'=>$subtotal,
                    'transaction_service'=>$service,
                    'transaction_grandtotal'=>$subtotal+$transaction['transaction_shipment'],            
                    'transaction_cogs'=>$cogs,            
                ]);
            }else{
                DB::rollback();
                return response()->json([
                    'status'   => 'fail',
                    'messages' => ['Failed update Quantity'],
                ]);
            }
           $transaction_data = Transaction::where('id_transaction_group',$transaction['id_transaction_group'])->get();
           $subtotal = 0;
           $service = 0;
           $cogs = 0;
           foreach ($transaction_data as $value) {
            $subtotal = $subtotal + $value['transaction_subtotal'];
            $service = $service + $value['transaction_service'];
            $cogs = $cogs + $value['transaction_cogs'];
           }
           $transaction_group = TransactionGroup::where('id_transaction_group',$transaction['id_transaction_group'])->first();
           if($transaction_group){
               $transaction_group = TransactionGroup::where('id_transaction_group',$transaction['id_transaction_group'])->update([
                   'transaction_subtotal'=>$subtotal,
                   'transaction_service'=>$service,
                   'transaction_cogs'=>$cogs,
                   'transaction_grandtotal'=>$cogs+$transaction_group['transaction_shipment'],
               ]);
           }else{
               DB::rollback();
                return response()->json([
                    'status'   => 'fail',
                    'messages' => ['Failed update Quantity'],
                ]);
           }
           $transaction_group = TransactionGroup::where('id_transaction_group',$transaction['id_transaction_group'])->first();
           DB::commit();
            return MyHelper::checkUpdate($transaction_group);
            
    }
    
     public function listProduct(Request $request)
    {
        $post = $request->json()->all();
        if (!empty($post['id_outlet'])) {
            $idMerchant = Merchant::where('id_outlet', $post['id_outlet'])->first()['id_merchant'] ?? null;
            if (empty($idMerchant)) {
                return response()->json(['status' => 'fail', 'messages' => ['Outlet not found']]);
            }
        }else{
            return response()->json(['status' => 'fail', 'messages' => ['Outlet not found']]);
        }

        if (!empty($post['promo'])) {
            $availablePromo = $this->getProductFromPromo($post['promo']);
        }
        $trans = Transaction::where('id_transaction',$post['id_transaction'])->first();
        $id_product = TransactionProduct::where('id_transaction',$post['id_transaction'])->get()->pluck('id_product');
        $list = Product::leftJoin('product_global_price', 'product_global_price.id_product', '=', 'products.id_product')
            ->join('product_detail', 'product_detail.id_product', '=', 'products.id_product')
            ->leftJoin('outlets', 'outlets.id_outlet', 'product_detail.id_outlet')
            ->leftJoin('cities', 'outlets.id_city', 'outlets.id_city')
            ->join('product_categories', 'product_categories.id_product_category', 'products.id_product_category')
            ->where('outlet_status', 'Active')
            ->where('product_visibility', 'Visible')
            ->where('product_detail_visibility', 'Visible')
            ->whereNotIn('products.id_product', $id_product)
            ->groupBy('products.id_product');

        if (!empty($idMerchant)) {
            $list = $list->where('id_merchant', $idMerchant);
        }

        if (!empty($post['search_key'])) {
            if (strpos($post['search_key'], " ") !== false) {
                $list = $list->whereRaw('MATCH (product_name) AGAINST ("' . $post['search_key'] . '" IN BOOLEAN MODE)');
            } else {
                $list->where('product_name', 'like', '%' . $post['search_key'] . '%');
            }
        }

        if (empty($idMerchant)) {
            $list = $list->where('outlet_is_closed', 0);
        }

        if (!empty($post['id_product_category'])) {
            $list = $list->where('product_categories.id_product_category', $post['id_product_category']);
        }

        if (isset($post['all_best_seller']) && $post['all_best_seller']) {
            $list = $list->where('product_count_transaction', '>', 0);
        }

        if (isset($post['all_recommendation']) && $post['all_recommendation']) {
            $list = $list->where('product_recommendation_status', 1);
        }

        if (!empty($post['rating'])) {
            $min = min($post['rating']);
            $max = 5;
            $list = $list->where('products.total_rating', '>=', $min)
                ->where('products.total_rating', '<=', $max);

            if (empty($post['filter_sorting'])) {
                $list = $list->orderBy('products.total_rating', 'desc');
            }
        }

        $list = $list->select(
            'products.id_product',
            'products.total_rating',
            DB::raw('
                    floor(products.total_rating) as rating
                '),
            'products.product_name',
            'products.product_code',
            'products.product_type',
            'products.min_transaction',
            'products.product_description',
            'product_variant_status',
            'product_global_price as product_price',
            'global_price_discount_percent as product_label_discount',
            'global_price_before_discount as product_label_price_before_discount',
            'product_detail_stock_status as stock_status',
            'product_detail.id_outlet',
            'need_recipe_status',
            'product_categories.product_category_name',
            'products.product_count_transaction',
            'outlet_is_closed as outlet_holiday_status',
            'outlets.id_outlet',
            'outlets.outlet_latitude',
            'outlets.outlet_longitude');
        if (!empty($availablePromo)) {
            $list = $list->whereIn('products.id_product', $availablePromo);
        }

        if (!empty($post['filter_category'])) {
            $list = $list->whereIn('product_categories.id_product_category', $post['filter_category']);
        }

        if (!empty($post['filter_min_price'])) {
            $list = $list->where(function ($q) use ($post) {
                $q->where(function ($query1) use ($post) {
                    $query1->where('product_global_price', '>=', $post['filter_min_price'])
                        ->where('product_variant_status', 0);
                });
                $q->orWhereHas('base_price_variant', function ($query2) use ($post) {
                    $query2->where('product_variant_group_price', '>=', $post['filter_min_price']);
                });
            });
        }

        if (!empty($post['filter_max_price'])) {
            $list = $list->where(function ($q) use ($post) {
                $q->where(function ($query1) use ($post) {
                    $query1->where('product_global_price', '<=', $post['filter_max_price'])
                        ->where('product_variant_status', 0);
                });
                $q->orWhereHas('base_price_variant', function ($query2) use ($post) {
                    $query2->where('product_variant_group_price', '<=', $post['filter_max_price']);
                });
            });
        }

        if (isset($post['range']) && $post['range'] != null) {
            $start = 0;
            foreach ($post['range'] as $v) {
                $start++;
            }
            if ($start == 2) {
                $list = $list->whereBetween('product_global_price', $post['range']);
            }
        }
        if (isset($post['city']) && $post['city'] != null) {
            $list = $list->wherein('outlets.id_city', $post['city']);
        }
        if (isset($post['sell']) && $post['sell'] != null) {
            if (isset($post['sell']['operator'])) {
                if ($post['sell']['operator'] == 'between') {
                    if (isset($post['sell']['start']) && isset($post['sell']['end'])) {
                        $list = $list->whereBetween('products.product_count_transaction', [$post['sell']['start'],$post['sell']['end']]);
                    }
                } else {
                    if (isset($post['sell']['value'])) {
                        $list = $list->where('products.product_count_transaction', $post['sell']['operator'], $post['sell']['value']);
                    }
                }
            }
        }
        
            $list = $list->get()->toArray();

            foreach ($list as $key => $product) {
                if ($product['product_variant_status']) {
                    $outlet = Outlet::where('id_outlet', $product['id_outlet'])->first();
                    $variantTree = Product::getVariantTree($product['id_product'], $outlet);
                    if (empty($variantTree['base_price'])) {
                        $list[$key]['stock_status'] = 'Sold Out';
                    }
                    $list[$key]['product_price'] = ($variantTree['base_price'] ?? false) ?: $product['product_price'];
                    $list[$key]['product_label_discount'] = ($variantTree['base_price_discount_percent'] ?? false) ?: $product['product_label_discount'];
                    $list[$key]['product_label_price_before_discount'] = ($variantTree['base_price_before_discount'] ?? false) ?: $product['product_label_price_before_discount'];
                }
                unset($list[$key]['product_variant_status']);
                $productGlobalPrices = ProductPriceUser::where([
                    'id_product'=>$product['id_product'],
                    'id_user'=>$trans->id_user,
                    ])->first();
                 if($productGlobalPrices){
                      $dtTaxService = ['subtotal' =>  (int)$productGlobalPrices['product_price']];
                 }else{
                      $dtTaxService = ['subtotal' =>  (int)$list[$key]['product_price']];
                 }
                $tax = round(app($this->setting_trx)->countTransaction('tax', $dtTaxService));
                $tax = round($tax);
                $list[$key]['product_price'] = $dtTaxService['subtotal']+$tax;
                $favorite = Favorite::where('id_product', $product['id_product'])->where('id_user', $trans->id_user)->first();
                $list[$key]['favorite'] = (!empty($favorite) ? true : false);
                $image = ProductPhoto::where('id_product', $product['id_product'])->orderBy('product_photo_order', 'asc')->first();
                $list[$key]['image'] = (!empty($image['product_photo']) ? config('url.storage_url_api') . $image['product_photo'] : config('url.storage_url_api') . 'img/default.jpg');
                $list[$key]['sold'] = app($this->management_merchant)->productCount($product['product_count_transaction']);
                unset($list[$key][$key]['product_count_transaction']);
                if($product['product_type']=='box'){
                    $serving_method = ProductServingMethod::where('id_product',$product['id_product'])
                            ->select(
                                    'id_product_serving_method',
                                    'id_product',
                                    'serving_name',
                                    'unit_price',
                                    'package',
                                    )
                            ->get();

                    $list[$key]['serving_method'] = $serving_method;
                    $group = ProductCustomGroup::where('id_product_parent',$product['id_product'])->get();
                    $pro = array();
                    foreach ($group as $value) {
                        $prod = Product::leftJoin('product_global_price', 'product_global_price.id_product', '=', 'products.id_product')
                                ->where('products.id_product',$value['id_product'])->select('products.id_product',
                                    'products.product_name',
                                    'products.product_code',
                                    'product_global_price as product_price')
                                ->first();
                        if($prod){
                            $select = false;
                              $productGlobalPrices = ProductPriceUser::where([
                                'id_product'=>$prod['id_product'],
                                'id_user'=>$trans->id_user,
                                ])->first();
                             if($productGlobalPrices){
                                  $dtTaxService = ['subtotal' =>  (int)$productGlobalPrices['product_price']];
                             }else{
                                  $dtTaxService = ['subtotal' =>  (int)$prod['product_price']];
                             }
                            $tax = round(app($this->setting_trx)->countTransaction('tax', $dtTaxService));
                            $tax = round($tax);
                            $prod['product_price'] = $dtTaxService['subtotal']+$tax;
                            $prod['select'] = $select;
                            $pro[] = $prod;
                        }
                    }
                    $list[$key]['product_custom'] = $pro;
                }
            }

            $list = array_values($list);
        return response()->json(MyHelper::checkGet($list));
    }
}
