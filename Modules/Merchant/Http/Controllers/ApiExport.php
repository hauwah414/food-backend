<?php

namespace Modules\Merchant\Http\Controllers;

use App\Http\Models\Outlet;
use App\Http\Models\Product;
use App\Http\Models\ProductPhoto;
use App\Http\Models\Subdistricts;
use App\Http\Models\TransactionProduct;
use App\Http\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Http\Models\Setting;
use App\Lib\MyHelper;
use Modules\Brand\Entities\Brand;
use Modules\Brand\Entities\BrandOutlet;
use Modules\Brand\Entities\BrandProduct;
use Modules\Disburse\Entities\BankAccount;
use Modules\Merchant\Entities\Merchant;
use Modules\Merchant\Entities\MerchantGrading;
use Modules\Merchant\Entities\MerchantLogBalance;
use Modules\Merchant\Http\Requests\MerchantCreateStep1;
use Modules\Merchant\Http\Requests\MerchantCreateStep2;
use Modules\Product\Entities\ProductDetail;
use Modules\Product\Entities\ProductGlobalPrice;
use Modules\Product\Entities\ProductWholesaler;
use Modules\ProductVariant\Entities\ProductVariant;
use Modules\ProductVariant\Entities\ProductVariantGroup;
use Modules\ProductVariant\Entities\ProductVariantGroupDetail;
use Modules\ProductVariant\Entities\ProductVariantGroupWholesaler;
use Modules\ProductVariant\Entities\ProductVariantPivot;
use DB;
use Image;
use App\Http\Models\ProductServingMethod;
use App\Http\Models\ProductCustomGroup;
use Modules\Disburse\Entities\BankAccountOutlet;
use App\Http\Models\ProductMultiplePhoto;
use App\Http\Models\WithdrawTransaction;
use Illuminate\Support\Facades\Storage;

class ApiExport extends Controller
{
      public $savePDF = "export/";
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->autocrm          = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        $this->product_variant_group = "Modules\ProductVariant\Http\Controllers\ApiProductVariantGroupController";
    }

    public function exportHutang(Request $request)
    {
        $list = MerchantLogBalance::join('merchants', 'merchants.id_merchant', 'merchant_log_balances.id_merchant')
            ->join('outlets', 'outlets.id_outlet', 'merchants.id_outlet')
            ->leftJoin('bank_accounts', 'bank_accounts.id_bank_account', 'merchant_log_balances.merchant_balance_id_reference')
            ->where('merchant_balance_source', 'Withdrawal')
            ->whereBetween('merchant_log_balances.created_at', [$request->date_start,$request->date_end])
            ->select('merchant_log_balances.*', 'outlets.*', 'merchant_log_balances.created_at as request_at','bank_accounts.*');
       $list = $list->orderBy('merchant_log_balances.created_at', 'desc')->get();
        $data = array();
        foreach($list as $value){
            $data[]=array(
                    'Date'=>date('Y-m-d', strtotime($value['created_at'])),
                    'Reference No.'=>$value['beneficiary_account'],
                    'Description'=>'Payment To'.$value['outlet_name'],
                    'Supplier Code'=>$value['outlet_code'],
                    'Department Code'=>'N/A',
                    'Project Code'=>'N/A',
                    'Cash Account Code'=>$value['beneficiary_account'],
                    'Cash Currency Code'=>"IDR",
                    'Cash Exchange Rate'=>1,
                    'Item Invoice Code'=>"N/A",
                    'Item Currency Code'=>'IDR',
                    'Item exchange Rate'=>1,
                    'Item Discount Rate'=>'0',
                    'Item Discount Amount'=>'0',
                    'Item Amount Origin'=> abs($value['merchant_balance']),
                    'Others Account Code'=>$value['beneficiary_account'],
                    'Other Currency Code'=>'IDR',
                    'Others Exchange Rate'=>1,
                    'Others Amount Origin'=>'0',
                );
        }
        $datas[]=array(
            'title' => 'Pembayaran Supplier',
            'head' => array(
                    'Date',
                    'Reference No.',
                    'Description',
                    'Supplier Code',
                    'Department Code',
                    'Project Code',
                    'Cash Account Code',
                    'Cash Currency Code',
                    'Cash Exchange Rate',
                    'Item Invoice Code',
                    'Item Currency Code',
                    'Item exchange Rate',
                    'Item Discount Rate',
                    'Item Discount Amount',
                    'Item Amount Origin',
                    'Others Account Code',
                    'Other Currency Code',
                    'Others Exchange Rate',
                    'Others Amount Origin',
                ),
            'body' => $data,
        );
         $excelFile = 'export-hutang-'. strtotime(date('Y-m-d H:i:s')).'.xlsx';
         $directory = $this->savePDF.'hutang/'.$excelFile;
         Storage::disk(env('STORAGE'))->delete($directory);
         $store = (new \App\Exports\HutangExport($datas))->store($directory, null, null);
        return response()->json(MyHelper::checkGet($datas));
    }
    
}
