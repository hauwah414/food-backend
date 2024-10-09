<?php

namespace Modules\Report\Http\Controllers;

use App\Http\Models\User;
use App\Http\Models\Transaction;
use App\Http\Models\TransactionProduct;
use App\Http\Models\Treatment;
use App\Http\Models\Consultation;
use App\Http\Models\Outlet;
use App\Http\Models\LogPoint;
use App\Http\Models\Reservation;
use App\Http\Models\LogActivitiesApps;
use App\Http\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Report\Http\Requests\DetailReport;
use App\Lib\MyHelper;
use Validator;
use Hash;
use DB;
use Mail;
use Modules\Merchant\Entities\MerchantLogBalance;

class ApiDashboard extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    public function cogs(Request $request)
    {
        $post = $request->json()->all();
         if ($post['date_start'] > $post['date_end']) {
            return response()->json([
                'status'    => 'fail',
                'messages'    => 'Date start must be smaller than date end'
            ]);
        }
        $transaction = Transaction::whereBetween('transaction_date',[$post['date_start'],$post['date_end']])
                ->whereIn('transaction_status',['On Progress','On Delivery','Completed']);
       if (isset($post['id_outlet'])) {
            $transaction = $transaction->where('transactions.id_outlet',$post['id_outlet']);
        }
           $transaction = $transaction->select(DB::raw('DATE_FORMAT(transaction_date, "%Y-%m-%d") as date'),DB::raw('
                                        sum(
                                       transactions.transaction_subtotal
                                        ) as subtotal
                                    '),DB::raw('
                                        sum(
                                       transactions.transaction_cogs 
                                        ) as cogs
                                    ')
                               )
                       ->groupby('date')
                       ->orderby('transactions.transaction_date','asc')
                       ->get();
           $data = array();
            foreach ($transaction as $key => $value) {
               $data[] = array(
                   'date'=>$value['date'],
                   'subtotal'=>(int)$value['subtotal'],
                   'cogs'=>(int)$value['cogs'],
               );
            }
        return response()->json([
            'status'    => 'success',
            'result'    => $data
        ]);
    }
   public function omset(Request $request)
    {
        $post = $request->json()->all();
         if ($post['date_start'] > $post['date_end']) {
            return response()->json([
                'status'    => 'fail',
                'messages'    => 'Date start must be smaller than date end'
            ]);
        }
       $transaction = Transaction::join('transaction_products','transaction_products.id_transaction','transactions.id_transaction')
                ->join('products','products.id_product','transaction_products.id_product')
                ->whereBetween('transactions.transaction_date',[$post['date_start'],$post['date_end']])
                ->whereIn('transactions.transaction_status',['On Progress','On Delivery','Completed']);
       if (isset($post['id_outlet'])) {
            $transaction = $transaction->where('transactions.id_outlet',$post['id_outlet']);
        }
           $transaction = $transaction->select(DB::raw('DATE_FORMAT(transactions.transaction_date, "%Y-%m-%d") as date'),DB::raw('
                                        sum(
                                      CASE WHEN
                                       products.status_preorder = "1" THEN transaction_products.transaction_product_subtotal ELSE 0
                                       END
                                        ) as pre_order
                                    '),DB::raw('
                                         sum(
                                      CASE WHEN
                                       products.status_preorder = "0" THEN transaction_products.transaction_product_subtotal ELSE 0
                                       END
                                        ) as cepat_saji
                                    ')
                               )
                       ->groupby('date')
                       ->orderby('transactions.transaction_date','asc')
                       ->get();
           $data = array();
            foreach ($transaction as $key => $value) {
               $data[] = array(
                   'date'=>$value['date'],
                   'pre_order'=>(int)$value['pre_order'],
                   'cepat_saji'=>(int)$value['cepat_saji'],
               );
            }
        return response()->json([
            'status'    => 'success',
            'result'    => $data
        ]);
    }
    public function omsetOutlet(Request $request)
    {
        $post = $request->json()->all();
         if ($post['date_start'] > $post['date_end']) {
            return response()->json([
                'status'    => 'fail',
                'messages'    => 'Date start must be smaller than date end'
            ]);
        }
       $transaction = Transaction::join('transaction_products','transaction_products.id_transaction','transactions.id_transaction')
                ->join('products','products.id_product','transaction_products.id_product')
                ->join('outlets','outlets.id_outlet','transactions.id_outlet')
                ->whereBetween('transactions.transaction_date',[$post['date_start'],$post['date_end']])
                ->whereIn('transactions.transaction_status',['On Progress','On Delivery','Completed']);
       if (isset($post['id_outlet'])) {
            $transaction = $transaction->where('transactions.id_outlet',$post['id_outlet']);
        }
           $transaction = $transaction->select('outlets.outlet_name as name',
                                    DB::raw('
                                       count(
                                       transactions.id_transaction 
                                        ) qty
                                    '),
                                    DB::raw('
                                       sum(
                                       transactions.transaction_cogs 
                                        ) as nominal
                                    ')
                               )
                       ->groupby('outlets.id_outlet')
                       ->orderby('nominal','desc')
                       ->get();
           $data = array();
            foreach ($transaction as $key => $value) {
               $data[] = array(
                   'name'=>$value['name'],
                   'qty'=>$value['qty'],
                   'nominal'=>(int)$value['nominal'],
               );
            }
        return response()->json([
            'status'    => 'success',
            'result'    => $data
        ]);
    }
    public function categori(Request $request)
    {
        $post = $request->json()->all();
         if ($post['date_start'] > $post['date_end']) {
            return response()->json([
                'status'    => 'fail',
                'messages'    => 'Date start must be smaller than date end'
            ]);
        }
       $transaction = Transaction::whereBetween('transactions.transaction_date',[$post['date_start'],$post['date_end']])
                ->whereIn('transactions.transaction_status',['On Progress','On Delivery','Completed'])
                ->join('transaction_products', 'transaction_products.id_transaction', 'transactions.id_transaction')
                ->join('products','products.id_product','transaction_products.id_product')
                ->join('product_categories','product_categories.id_product_category','products.id_product_category');
       if (isset($post['id_outlet'])) {
            $transaction = $transaction->where('transactions.id_outlet',$post['id_outlet']);
        }
           $transaction = $transaction->select('product_categories.product_category_name as network',
                           DB::raw('
                                  count(
                                  transaction_products.id_product
                                  ) as MAU
                              '))
                       ->groupby('transaction_products.id_product')
                       ->orderby('MAU','asc')
                       ->get();
          $data = array();
            foreach ($transaction as $key => $value) {
               $data[] = array(
                   'category'=>$value['network'],
                   'value'=>$value['MAU'],
               );
            }
        return response()->json([
            'status'    => 'success',
            'result'    => $data
        ]);
    }
    public function departmenPemesanan(Request $request)
    {
        $post = $request->json()->all();
         if ($post['date_start'] > $post['date_end']) {
            return response()->json([
                'status'    => 'fail',
                'messages'    => 'Date start must be smaller than date end'
            ]);
        }
       $transaction = Transaction::whereBetween('transactions.transaction_date',[$post['date_start'],$post['date_end']])
                ->whereIn('transactions.transaction_status',['On Progress','On Delivery','Completed'])
                ->join('transaction_groups', 'transaction_groups.id_transaction_group', 'transactions.id_transaction_group')
                ->join('departments', 'departments.id_department', 'transaction_groups.id_department')
                ->join('transaction_products', 'transaction_products.id_transaction', 'transactions.id_transaction');
       if (isset($post['id_outlet'])) {
            $transaction = $transaction->where('transactions.id_outlet',$post['id_outlet']);
        }
           $transaction = $transaction->select('departments.name_department as Nama',
                           DB::raw('
                                  count(
                                  transactions.id_transaction
                                  ) as qty_transaksi
                              '),
                            DB::raw('
                                  sum(
                                  transactions.transaction_subtotal
                                  ) as total_transaksi
                              ')
                   )
                       ->groupby('transaction_groups.id_department')
                       ->orderby('total_transaksi','desc')
                       ->limit(5)
                       ->get();
          $data = array();
            foreach ($transaction as $key => $value) {
               $data[] = array(
                   'name'=>$value['Nama'],
                   'qty'=>$value['qty_transaksi'],
                   'total'=>(int)$value['total_transaksi'],
               );
            }
        return response()->json([
            'status'    => 'success',
            'result'    => $data
        ]);
    }
    public function departmenPiutang(Request $request)
    {
        $post = $request->json()->all();
         if ($post['date_start'] > $post['date_end']) {
            return response()->json([
                'status'    => 'fail',
                'messages'    => 'Date start must be smaller than date end'
            ]);
        }
       $transaction = Transaction::whereBetween('transactions.transaction_date',[$post['date_start'],$post['date_end']])
                ->whereIn('transactions.transaction_status',['On Progress','On Delivery','Completed'])
                ->whereIn('transaction_groups.transaction_payment_status',['Pending','Unpaid'])
                ->join('transaction_groups', 'transaction_groups.id_transaction_group', 'transactions.id_transaction_group')
                ->join('departments', 'departments.id_department', 'transaction_groups.id_department')
                ->join('transaction_products', 'transaction_products.id_transaction', 'transactions.id_transaction');
       if (isset($post['id_outlet'])) {
            $transaction = $transaction->where('transactions.id_outlet',$post['id_outlet']);
        }
           $transaction = $transaction->select('departments.name_department as Nama',
                           DB::raw('
                                  count(
                                  transactions.id_transaction
                                  ) as qty_transaksi
                              '),
                            DB::raw('
                                  sum(
                                  transactions.transaction_subtotal
                                  ) as total_transaksi
                              ')
                   )
                       ->groupby('transaction_groups.id_department')
                       ->orderby('total_transaksi','desc')
                       ->limit(5)
                       ->get();
          $data = array();
            foreach ($transaction as $key => $value) {
               $data[] = array(
                   'name'=>$value['Nama'],
                   'qty'=>$value['qty_transaksi'],
                   'total'=>(int)$value['total_transaksi'],
               );
            }
        return response()->json([
            'status'    => 'success',
            'result'    => $data
        ]);
    }
    public function vendor(Request $request)
    {
        $post = $request->json()->all();
         if ($post['date_start'] > $post['date_end']) {
            return response()->json([
                'status'    => 'fail',
                'messages'    => 'Date start must be smaller than date end'
            ]);
        }
       $transaction =  MerchantLogBalance::join('merchants', 'merchants.id_merchant', 'merchant_log_balances.id_merchant')
                    ->join('outlets', 'outlets.id_outlet', 'merchants.id_outlet')
                    ->where('merchant_balance_source', 'Transaction Completed')
                    ->where('merchant_balance_status','!=','Completed')
                    ->whereBetween('merchant_log_balances.created_at',[$post['date_start'],$post['date_end']]);
           $transaction = $transaction->select('outlets.outlet_name as name',
                           DB::raw('
                                  count(
                                  merchant_log_balances.id_merchant_log_balance
                                  ) as qty_transaksi
                              '),
                            DB::raw('
                                  sum(
                                  merchant_log_balances.merchant_balance
                                  ) as total_hutang
                              ')
                   )
                       ->groupby('outlets.id_outlet')
                       ->orderby('total_hutang','desc')
                       ->limit(5)
                       ->get();
          $data = array();
            foreach ($transaction as $key => $value) {
               $data[] = array(
                   'name'=>$value['name'],
                   'qty'=>$value['qty_transaksi'],
                   'total_hutang'=>(int)$value['total_hutang'],
               );
            }
        return response()->json([
            'status'    => 'success',
            'result'    => $data
        ]);
    }
    
    public function vendorWithdrawal(Request $request)
    {
        $post = $request->json()->all();
         if ($post['date_start'] > $post['date_end']) {
            return response()->json([
                'status'    => 'fail',
                'messages'    => 'Date start must be smaller than date end'
            ]);
        }
       $transaction =  MerchantLogBalance::where('merchant_balance_source', 'Withdrawal')
                    ->where('merchant_balance_status','=','Completed')
                    ->whereBetween('merchant_log_balances.created_at',[$post['date_start'],$post['date_end']]);
           $transaction = $transaction->select(
                           DB::raw('ABS(
                                  sum(
                                  merchant_log_balances.merchant_balance
                                  )) as total
                              ')
                   )
                       ->first();
         
        return response()->json([
            'status'    => 'success',
            'result'    => $transaction->total??0
        ]);
    }
    public function customerPay(Request $request)
    {
        $post = $request->json()->all();
         if ($post['date_start'] > $post['date_end']) {
            return response()->json([
                'status'    => 'fail',
                'messages'    => 'Date start must be smaller than date end'
            ]);
        }
        $transaction = Transaction::join('transaction_groups','transaction_groups.id_transaction_group','transactions.id_transaction')
                ->whereBetween('transactions.transaction_date',[$post['date_start'],$post['date_end']])
                ->whereIn('transactions.transaction_status',['On Progress','On Delivery','Completed'])
                ->whereIn('transaction_groups.transaction_payment_status',['Completed']);
       if (isset($post['id_outlet'])) {
            $transaction = $transaction->where('transactions.id_outlet',$post['id_outlet']);
        }
           $transaction = $transaction->select(DB::raw('
                                        sum(
                                       transactions.transaction_grandtotal 
                                        ) as grandtotal
                                    ')
                               )
                       ->first();
          
        return response()->json([
            'status'    => 'success',
            'result'    => $transaction->grandtotal??0
        ]);
    }
    public function customerUnpaid(Request $request)
    {
        $post = $request->json()->all();
         if ($post['date_start'] > $post['date_end']) {
            return response()->json([
                'status'    => 'fail',
                'messages'    => 'Date start must be smaller than date end'
            ]);
        }
        $transaction = Transaction::join('transaction_groups','transaction_groups.id_transaction_group','transactions.id_transaction')
                ->whereBetween('transactions.transaction_date',[$post['date_start'],$post['date_end']])
                ->whereIn('transactions.transaction_status',['On Progress','On Delivery','Completed'])
                ->whereIn('transaction_groups.transaction_payment_status',['Pending','Unpaid']);
       if (isset($post['id_outlet'])) {
            $transaction = $transaction->where('transactions.id_outlet',$post['id_outlet']);
        }
           $transaction = $transaction->select(DB::raw('
                                        sum(
                                       transactions.transaction_grandtotal 
                                        ) as grandtotal
                                    ')
                               )
                       ->first();
          
        return response()->json([
            'status'    => 'success',
            'result'    => $transaction->grandtotal??0
        ]);
    }
    public function home(Request $request)
    {
        $post = $request->json()->all();
         if ($post['date_start'] > $post['date_end']) {
            return response()->json([
                'status'    => 'fail',
                'messages'    => 'Date start must be smaller than date end'
            ]);
        }
        $array = array();
        $transaction =  MerchantLogBalance::where('merchant_balance_source', 'Withdrawal')
                    ->where('merchant_balance_status','=','Completed')
                    ->whereBetween('merchant_log_balances.created_at',[$post['date_start'],$post['date_end']]);
           $transaction = $transaction->select(
                           DB::raw('ABS(
                                  sum(
                                  merchant_log_balances.merchant_balance
                                  )) as total
                              ')
                   )
                       ->first();
        $array[] = array(
            "title"=> "Dana Vendor",
            "amount"=> number_format($transaction->total??0,0,",","."),
            "tooltip"=> "Total dana vendor yang sudah terbayarkan",
            "show"=> 1,
            "color"=> 'green',
            "icon"=> "fa fa-money"
        ); 
        $transaction = Transaction::join('transaction_groups','transaction_groups.id_transaction_group','transactions.id_transaction')
                ->whereBetween('transactions.transaction_date',[$post['date_start'],$post['date_end']])
                ->whereIn('transactions.transaction_status',['On Progress','On Delivery','Completed'])
                ->whereIn('transaction_groups.transaction_payment_status',['Completed']);
       
           $transaction = $transaction->select(DB::raw('
                                        sum(
                                       transactions.transaction_grandtotal 
                                        ) as grandtotal
                                    ')
                               )
                       ->first();
        $array[] = array(
            "title"=> "Pembayaran Customer",
            "amount"=> number_format($transaction->grandtotal??0,0,",","."),
            "tooltip"=> "Total dana yang sudah di bayarkan customer",
            "show"=> 1,
            "color"=> 'blue',
            "icon"=> "fa fa-money"
        ); 
         $transaction = Transaction::join('transaction_groups','transaction_groups.id_transaction_group','transactions.id_transaction')
                ->whereBetween('transactions.transaction_date',[$post['date_start'],$post['date_end']])
                ->whereIn('transactions.transaction_status',['On Progress','On Delivery','Completed'])
                ->whereIn('transaction_groups.transaction_payment_status',['Pending','Unpaid']);
       
           $transaction = $transaction->select(DB::raw('
                                        sum(
                                       transactions.transaction_grandtotal 
                                        ) as grandtotal
                                    ')
                               )
                       ->first();
        $array[] = array(
            "title"=> "Piutang Customer",
            "amount"=> number_format($transaction->grandtotal??0,0,",","."),
            "tooltip"=> "Total dana yang belum di bayarkan customer",
            "show"=> 1,
            "color"=> 'yellow',
            "icon"=> "fa fa-money"
        ); 
        $transaction = Outlet::count();
        $array[] = array(
            "title"=> "Jumlah Vendor",
            "amount"=> number_format($transaction??0,0,",","."),
            "tooltip"=> "Total vendor yang terdaftar",
            "show"=> 1,
            "color"=> 'green',
            "icon"=> "fa fa-university"
        ); 
        $transaction = Outlet::where('outlet_status','Active')->count();
        $array[] = array(
            "title"=> "Jumlah Vendor Active",
            "amount"=> number_format($transaction??0,0,",","."),
            "tooltip"=> "Total vendor yang aktif",
            "show"=> 1,
            "color"=> 'purple',
            "icon"=> "fa fa-home"
        ); 
        $transaction = Outlet::where('outlet_status','Inactive')->count();
        $array[] = array(
            "title"=> "Jumlah Vendor Inactive",
            "amount"=> number_format($transaction??0,0,",","."),
            "tooltip"=> "Total vendor yang tidak aktif",
            "show"=> 1,
            "color"=> 'red',
            "icon"=> "fa fa-bank"
        ); 
        $transaction = Product::count();
        $array[] = array(
            "title"=> "Jumlah Product",
            "amount"=> number_format($transaction??0,0,",","."),
            "tooltip"=> "Total produk yang ditambahkan",
           "show"=> 1,
            "color"=> 'blue',
            "icon"=> "fa fa-shopping-cart"
        ); 
        $transaction = Product::where('is_inactive',0)->count();
        $array[] = array(
            "title"=> "Jumlah Product Active",
            "amount"=> number_format($transaction??0,0,",","."),
            "tooltip"=> "Total produk yang aktif",
            "show"=> 1,
            "color"=> 'green',
            "icon"=> "icon-wallet"
        ); 
        $transaction = Product::where('is_inactive',1)->count();
        $array[] = array(
            "title"=> "Jumlah Product Inactive",
            "amount"=> number_format($transaction??0,0,",","."),
            "tooltip"=> "Total produk yang tidak aktif",
            "show"=> 1,
            "color"=> 'yellow',
            "icon"=> "icon-wallet"
        ); 
        $transaction = Product::where('is_approved',1)->count();
        $array[] = array(
            "title"=> "Jumlah Product Diterima",
            "amount"=> number_format($transaction??0,0,",","."),
            "tooltip"=> "Total produk yang diterima oleh admin",
            "show"=> 1,
            "color"=> 'green',
            "icon"=> "icon-wallet"
        ); 
        $transaction = Product::where('is_approved',0)->count();
        $array[] = array(
            "title"=> "Jumlah Product Belum Diterima",
            "amount"=> number_format($transaction??0,0,",","."),
            "tooltip"=> "Total produk yang diterima oleh admin",
            "show"=> 1,
            "color"=> 'yellow',
            "icon"=> "fa fa-cloud"
        ); 
        return response()->json([
            'status'    => 'success',
            'result'    => $array
        ]);
    }
    public function merchant(Request $request)
    {
        $post = $request->json()->all();
         if ($post['date_start'] > $post['date_end']) {
            return response()->json([
                'status'    => 'fail',
                'messages'    => 'Date start must be smaller than date end'
            ]);
        }
       $startDate = $post['date_start'];
          $endDate   = $post['date_end'];
         $end_date = date('Y-m', strtotime($startDate));
          $s = 2;
        $ar = array();
       for($i=1;$i<$s;$i){
              if($startDate>=$end_date){
               $end_date = date('Y-m-d', strtotime($end_date.'+1 months'));
              }
              if($end_date>=$endDate){
                  $end_date = $endDate;
              }
              $ar[]= array(
                  'start'=>$startDate,
                  'end'=>$end_date,
              );
              
              if($end_date>=$endDate){
                  break;
              }
              $startDate = date('Y-m-d', strtotime($end_date.'+1 days'));
          }
       return response()->json(['status' => 'success', 'result' => $ar]);  
      
    }
}   
