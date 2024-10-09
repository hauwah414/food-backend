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
use PDF;
use Illuminate\Support\Facades\Storage;
use QrCode;
use App\Http\Models\Payment;

class ApiExportTransaction extends Controller
{
    public $saveImage = "img/transaction/manual-payment/";
    public $savePDF = "export/";

    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->shopeepay      = 'Modules\ShopeePay\Http\Controllers\ShopeePayController';
        $this->xendit         = 'Modules\Xendit\Http\Controllers\XenditController';
        $this->shipper         = 'Modules\Transaction\Http\Controllers\ApiShipperController';
    }
    public function transactionCompleted(Request $request)
    {
        $post = $request->json()->all();
        
       $list = Payment::leftJoin('users', 'users.id', 'payments.id_user')
            ->leftJoin('departments', 'departments.id_department', 'users.id_department')
            ->orderBy('transaction_group_date', 'desc')
            ->where('transaction_payment_status', 'Completed')
            ->select('payments.*',  'users.*','departments.*')->with('payments','xendits');

        if (
            isset($post['date_start']) && !empty($post['date_start']) &&
            isset($post['date_end']) && !empty($post['date_end'])
        ) {
            $start_date = date('Y-m-d', strtotime($post['date_start']));
            $end_date = date('Y-m-d', strtotime($post['date_end']));

            $list->whereDate('transaction_group_date', '>=', $start_date)
                ->whereDate('transaction_group_date', '<=', $end_date);
        }
      $list = $list->get();
        $data = array();
        foreach($list as $value){
            $data[] = array(
                'date'=>date('Y-m-d', strtotime($value['transaction_group_date'])),
                'number'=>$value['transaction_payment_number'],
                'description'=>'Paymentfrom'.$value['name'].'_'.$value['name_department'],
                'customer.code'=>$value['phone'],
                'department.code'=>$value['name_department'],
                'cash.account.code'=>$value['xendits']['account_number'],
                'cash.currency.code'=>"IDR",
                'cash.exchange_rate'=>1,
                'line_items.discount.rate'=>'0',
                'line_items.discount.amount'=>'0',
                'line_items.amount_origin'=>$value['transaction_grandtotal'],
                'others[0].account.code'=>$value['id_department'],
                'others[0].currency.code'=>"IDR",
                'others[0].exchange_rate'=>1,
                'others[0].amount_origin'=>'0',
            );
        }
        $datas[]=array(
            'title' => 'Piutang Customer',
            'head' => array(
                    'date',
                    'number',
                    'description',
                    'customer.code',
                    'department.code',
                    'cash.account.code',
                    'cash.currency.code',
                    'cash.exchange_rate',
                    'line_items.discount.rate',
                    'line_items.discount.amount',
                    'line_items.amount_origin',
                    'others[0].account.code',
                    'others[0].currency.code',
                    'others[0].exchange_rate',
                    'others[0].amount_origin',
                ),
            'body' => $data,
        );
         $excelFile = 'export-piutang-'. strtotime(date('Y-m-d H:i:s')).'.xlsx';
         $directory = $this->savePDF.'piutang/'.$excelFile;
         Storage::disk(env('STORAGE'))->delete($directory);
         $store = (new \App\Exports\PiutangExport($datas))->store($directory, null, null);
//        if($store){
//              $update = Payment::where('transaction_payment_number',$pay['transaction_payment_number'])->update([
//                  'file_rekap'=>$directory
//              ]);
//          }
         
        return response()->json(MyHelper::checkGet($datas));
    }
    public function transactionDetail(Request $request)
    {
        $post = $request->json()->all();
        $id = $request->user()->id;
        if(empty($post['transaction_payment_number'])){
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['Transaction Not Found']
            ]);
        }
     
        $list = Payment::leftJoin('users', 'users.id', 'payments.id_user')
            ->orderBy('transaction_group_date', 'desc')
            ->where('id_user', $id)
            ->where('transaction_payment_number', $post['transaction_payment_number'])
            ->select('payments.*')->with('payments','xendits')->first();
        if(!$list){
            return response()->json([
                  'status'    => 'fail',
                  'messages'  => ['Transaction Not Found']
              ]);
        }
        $step = Setting::where('key', strtolower($list['xendits']['type']))->first()['value_text']??[];
        if($list['xendits']){
            $list['account_number'] = $list['xendits']['account_number'];
            $list['xendit_status'] = $list['xendits']['status'];
            $list['expiration_date'] = $list['xendits']['expiration_date']??'Paid';
            unset($list['xendits']);
        }
        foreach ($list['payments'] ?? [] as $key => $value) {
            $trx = TransactionGroup::where('id_transaction_group',$value['id_transaction_group'])->first();
            $list['payments'][$key] = [
                'id_transaction_group' => $value['id_transaction_group'],
                'transaction_group_date' => $trx['transaction_group_date'],
                'transaction_receipt_number' => $trx['transaction_receipt_number'],
                'transaction_payment_status' => $trx['transaction_payment_status'],
               'transaction_subtotal' => $trx['transaction_subtotal'],
                'transaction_discount' => $trx['transaction_discount'],
                'transaction_subtotal_text' =>'Rp ' . number_format($trx['transaction_subtotal'], 0, ",", "."),
                'transaction_shipment' => $trx['transaction_shipment'],
                'transaction_shipment_text' =>'Rp ' . number_format($trx['transaction_shipment'], 0, ",", "."),
                'transaction_tax' => $trx['transaction_tax'],
                'transaction_tax_text' =>'Rp ' . number_format($trx['transaction_tax'], 0, ",", "."),
                'transaction_service' => $trx['transaction_service'],
                'transaction_service_text' =>'Rp ' . number_format($trx['transaction_service'], 0, ",", "."),
                'transaction_grandtotal' => $trx['transaction_grandtotal'],
                'transaction_grandtotal_text' =>'Rp ' . number_format($trx['transaction_grandtotal'], 0, ",", "."),
            ];
        }
         $list['summary'] = [
            [
                'name' => 'Subtotal',
                'value' => 'Rp ' . number_format($list['transaction_subtotal'], 0, ",", ".")
            ],
            [
                'name' => 'Biaya Pajak',
                'value' => 'Rp ' . number_format($list['transaction_tax'], 0, ",", ".")
            ],
            [
                'name' => 'Biaya Service',
                'value' => 'Rp ' . number_format($list['transaction_service'], 0, ",", ".")
            ],
            [
                'name' => 'Biaya Kirim',
                'value' => 'Rp ' . number_format($list['transaction_shipment'], 0, ",", ".")
            ],
            [
                'name' => 'Biaya Pembayaran',
                'value' => 'Rp ' . number_format($list['transaction_mdr'], 0, ",", ".")
            ]
        ];
         $list['transaction_grandtotal_text'] = 'Rp ' . number_format($list['transaction_grandtotal'], 0, ",", ".");
         $list['step'] = $step;
        return response()->json(MyHelper::checkGet($list));
    }
    
     public function pembelianFood(Request $request)
    {
        $list =Transaction::join('transaction_groups', 'transaction_groups.id_transaction_group', 'transactions.id_transaction_group')
            ->leftjoin('departments', 'departments.id_department', 'transaction_groups.id_department')
            ->join('outlets', 'outlets.id_outlet', 'transactions.id_outlet')
            ->leftJoin('users', 'users.id', 'transactions.id_user')
            ->where('transactions.transaction_status', 'Completed')
            ->where('trasaction_type', 'Delivery')
            ->whereBetween('transaction_date', [$request->date_start,$request->date_end])
            ->orderBy('transaction_date', 'desc')
            ->select('transaction_groups.transaction_receipt_number as transaction_receipt_number_group', 'transactions.*', 'outlets.*', 'users.*','transaction_groups.id_department as id_depart','name_department');
        $list = $list->get();
        $data = array();
        foreach($list as $value){
            $desc = null;
            $tran = TransactionProduct::where('id_transaction',$value['id_transaction'])
                    ->join('products', 'products.id_product', 'transaction_products.id_product')->get();
            $i = 0;
            $product_code = null;
            $qty = 0;
            foreach($tran as $v){
                if($i>0){
                    $desc .= ', '.$v['product_name'];
                    $product_code .= ', '.$v['product_code'];
                }else{
                    $desc .= $v['product_name'];
                    $product_code .= $v['product_code'];
                }
                $qty = $qty + $v['transaction_product_qty'];
                $i++;
            }
            $data[]=array(
                    'Transaction Date'=>date('Y-m-d', strtotime($value['transaction_date'])),
                    'Reference No.'=>$value['transaction_receipt_number'],
                    'Description'=>'Penjualan '.$desc.' untuk '.$value['name'].'_'.$value['name_department'],
                    'Supplier Code'=>$value['outlet_code'],
                    'Order No.'=>'N/A',
                    'Currency Code'=>'IDR',
                    'Exchange Rate'=>1,
                    'Department Code'=>$value['name_department'],
                    'Project Code'=>'N/A',
                    'Warhouse Code'=>'Head Quarter',
                    'Item Product Code'=>$product_code,
                    'Item service (Account Code)'=>'-',
                    'Item Unit Code'=>'Pcs',
                    'Item Quantity'=>$qty,
                    'Item Price'=>$value['transaction_grandtotal'],
                    'Item Discount'=>$value['transaction_discount'],
                    'Item Description'=>'',
                    'Item Tax Code'=>$value['transaction_tax'],
                    'Item Department Code'=>'Food',
                    'Item Project Code'=>'N/A',
                    'Item Warhouse Code'=>'Head Quarter',
                    'Item Note'=>$product_code,
                    'Cash'=>'FALSE',
                    'Payment Account Cash'=>'N/A',
                    'Status'=>'approved',
                    'Discount Days'=>'0',
                    'Due Date'=>'0',
                    'Due Days'=>'0',
                    'Early Discount Rate'=>'0',
                    'Late Charge Rate'=>'0',
                    'Document Number'=>'',
                    'Document Date'=>'',
                    'Purchasing Dept'=>'',
                    'Memo Of Credit / Debit'=>'',
                    'Delivery Date'=>'',
                    'Biaya Lain'=>'',
                );
        }
        $datas[]=array(
            'title' => 'Pembayaran Supplier',
            'head' => array(
                    'Transaction Date',
                    'Reference No.',
                    'Description',
                    'Supplier Code',
                    'Order No.',
                    'Currency Code',
                    'Exchange Rate',
                    'Department Code',
                    'Project Code',
                    'Warhouse Code',
                    'Item Product Code',
                    'Item service (Account Code)',
                    'Item Unit Code',
                    'Item Quantity',
                    'Item Price',
                    'Item Discount',
                    'Item Description',
                    'Item Tax Code',
                    'Item Department Code',
                    'Item Project Code',
                    'Item Warhouse Code',
                    'Item Note',
                    'Cash',
                    'Payment Account Cash',
                    'Status',
                    'Discount Days',
                    'Due Date',
                    'Due Days',
                    'Early Discount Rate',
                    'Late Charge Rate',
                    'Document Number',
                    'Document Date',
                    'Purchasing Dept',
                    'Memo Of Credit / Debit',
                    'Delivery Date',
                    'Biaya Lain',
                ),
            'body' => $data,
        );
         $excelFile = 'export-pembelian-'. strtotime(date('Y-m-d H:i:s')).'.xlsx';
         $directory = $this->savePDF.'pembelian/'.$excelFile;
         Storage::disk(env('STORAGE'))->delete($directory);
         $store = (new \App\Exports\PembelianExport($datas))->store($directory, null, null);
        return response()->json(MyHelper::checkGet($datas));
    }
     public function penjualanFood(Request $request)
    {
        $list =Transaction::join('transaction_groups', 'transaction_groups.id_transaction_group', 'transactions.id_transaction_group')
            ->leftjoin('departments', 'departments.id_department', 'transaction_groups.id_department')
            ->join('outlets', 'outlets.id_outlet', 'transactions.id_outlet')
            ->leftJoin('users', 'users.id', 'transactions.id_user')
            ->where('transactions.transaction_status', 'Completed')
            ->where('trasaction_type', 'Delivery')
            ->whereBetween('transaction_date', [$request->date_start,$request->date_end])
            ->orderBy('transaction_date', 'desc')
            ->select('transaction_groups.transaction_receipt_number as transaction_receipt_number_group', 'transactions.*', 'outlets.*', 'users.*','transaction_groups.id_department as id_depart','name_department');
        $list = $list->get();
        $data = array();
        foreach($list as $value){
            $desc = null;
            $tran = TransactionProduct::where('id_transaction',$value['id_transaction'])
                    ->join('products', 'products.id_product', 'transaction_products.id_product')->get();
            $i = 0;
            $product_code = null;
            $qty = 0;
            foreach($tran as $v){
                if($i>0){
                    $desc .= ', '.$v['product_name'];
                    $product_code .= ', '.$v['product_code'];
                }else{
                    $desc .= $v['product_name'];
                    $product_code .= $v['product_code'];
                }
                $qty = $qty + $v['transaction_product_qty'];
                $i++;
            }
            $data[]=array(
                    'Transaction Date'=>date('Y-m-d', strtotime($value['transaction_date'])),
                    'Reference No.'=>$value['transaction_receipt_number'],
                    'Description'=>'Penjualan '.$desc.' untuk '.$value['name'].'_'.$value['name_department'],
                    'Customer Code'=>$value['phone'],
                    'Order No.'=>'N/A',
                    'Currency Code'=>'IDR',
                    'Exchange Rate'=>1,
                    'Department Code'=>$value['name_department'],
                    'Project Code'=>'N/A',
                    'Warhouse Code'=>'Head Quarter',
                    'Item Product Code'=>$product_code,
                    'Item service (Account Code)'=>'-',
                    'Item Unit Code'=>'Pcs',
                    'Item Quantity'=>$qty,
                    'Item Price'=>$value['transaction_grandtotal'],
                    'Item Discount'=>$value['transaction_discount'],
                    'Item Description'=>'',
                    'Item Tax Code'=>$value['transaction_tax'],
                    'Item Department Code'=>'Food',
                    'Item Project Code'=>'N/A',
                    'Item Warhouse Code'=>'Head Quarter',
                    'Item Note'=>$product_code,
                    'Cash'=>'FALSE',
                    'Payment Account Cash'=>'N/A',
                    'Status'=>'approved',
                    'Discount Days'=>'0',
                    'Due Date'=>'0',
                    'Due Days'=>'0',
                    'Early Discount Rate'=>'0',
                    'Late Charge Rate'=>'0',
                    'Document Number'=>'',
                    'Document Date'=>'',
                    'Purchasing Dept'=>'',
                    'Memo Of Credit / Debit'=>'',
                    'Delivery Date'=>'',
                    'Biaya Lain'=>'',
                );
        }
        $datas[]=array(
            'title' => 'Pembayaran Supplier',
            'head' => array(
                    'Transaction Date',
                    'Reference No.',
                    'Description',
                    'Customer Code',
                    'Order No.',
                    'Currency Code',
                    'Exchange Rate',
                    'Department Code',
                    'Project Code',
                    'Warhouse Code',
                    'Item Product Code',
                    'Item service (Account Code)',
                    'Item Unit Code',
                    'Item Quantity',
                    'Item Price',
                    'Item Discount',
                    'Item Description',
                    'Item Tax Code',
                    'Item Department Code',
                    'Item Project Code',
                    'Item Warhouse Code',
                    'Item Note',
                    'Cash',
                    'Payment Account Cash',
                    'Status',
                    'Discount Days',
                    'Due Date',
                    'Due Days',
                    'Early Discount Rate',
                    'Late Charge Rate',
                    'Document Number',
                    'Document Date',
                    'Purchasing Dept',
                    'Memo Of Credit / Debit',
                    'Delivery Date',
                    'Biaya Lain',
                ),
            'body' => $data,
        );
         $excelFile = 'export-penjualan-'. strtotime(date('Y-m-d H:i:s')).'.xlsx';
         $directory = $this->savePDF.'penjualan/'.$excelFile;
         Storage::disk(env('STORAGE'))->delete($directory);
         $store = (new \App\Exports\PenjualanExport($datas))->store($directory, null, null);
        return response()->json(MyHelper::checkGet($datas));
    }
}
