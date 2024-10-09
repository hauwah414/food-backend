<?php

namespace Modules\Merchant\Http\Controllers;

use App\Http\Models\InboxGlobal;
use App\Http\Models\InboxGlobalRead;
use App\Http\Models\MonthlyReportTrx;
use App\Http\Models\Outlet;
use App\Http\Models\Product;
use App\Http\Models\ProductPhoto;
use App\Http\Models\Subdistricts;
use App\Http\Models\TransactionProduct;
use App\Http\Models\User;
use App\Http\Models\UserInbox;
use App\Jobs\DisburseJob;
use App\Lib\Shipper;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Http\Models\Setting;
use App\Lib\MyHelper;
use Modules\Brand\Entities\Brand;
use Modules\Brand\Entities\BrandOutlet;
use Modules\Brand\Entities\BrandProduct;
use Modules\Disburse\Entities\BankAccount;
use Modules\Disburse\Entities\BankAccountOutlet;
use Modules\Disburse\Entities\BankName;
use Modules\Disburse\Entities\Disburse;
use Modules\InboxGlobal\Http\Requests\MarkedInbox;
use Modules\Merchant\Entities\Merchant;
use Modules\Merchant\Entities\MerchantInbox;
use Modules\Merchant\Entities\MerchantLogBalance;
use Modules\Merchant\Http\Requests\MerchantCreateStep1;
use Modules\Merchant\Http\Requests\MerchantCreateStep2;
use Modules\Outlet\Entities\DeliveryOutlet;
use DB;
use App\Http\Models\Transaction;
use Modules\Merchant\Entities\MerchantGrading;

class ApiBEMerchantController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->autocrm          = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        $this->product_variant_group = "Modules\ProductVariant\Http\Controllers\ApiProductVariantGroupController";
        $this->online_trx = "Modules\Transaction\Http\Controllers\ApiOnlineTransaction";
    }


    public function bankList()
    {
        $list = BankName::select('id_bank_name', 'bank_code', 'bank_name', 'bank_image')->get()->toArray();
        foreach ($list as $key => $val) {
            $list[$key]['bank_image'] = (empty($val['bank_image']) ? config('url.storage_url_api') . 'img/default.jpg' : config('url.storage_url_api') . $val['bank_image']);
        }
        return response()->json(MyHelper::checkGet($list));
    }

    public function bankAccountCheck(Request $request)
    {
        $post = $request->all();

        if (empty($post['beneficiary_account'])) {
            return response()->json(['status' => 'fail', 'messages' => ['Account number can not be empty']]);
        }

        if (empty($post['id_bank_name'])) {
            return response()->json(['status' => 'fail', 'messages' => ['ID can not be empty']]);
        }

        $arr = [1,1];
        shuffle($arr);

        if ($arr[0]) {
            return response()->json(['status' => 'success', 'result' => [
                'beneficiary_name' => $post['beneficiary_name'],
                'beneficiary_account' => $post['beneficiary_account']
            ]]);
        } else {
            return response()->json(['status' => 'fail', 'messages' => ['Akun tidak ditemukan']]);
        }
    }

    public function bankAccountCreate(Request $request)
    {
        $post = $request->all();
        $idUser = $request->user()->id;
        $checkMerchant = Merchant::where('id_user', $idUser)->first();
        if (empty($checkMerchant)) {
            return response()->json(['status' => 'fail', 'messages' => ['Data merchant tidak ditemukan']]);
        }

        if (empty($post['beneficiary_account'])) {
            return response()->json(['status' => 'fail', 'messages' => ['Account number can not be empty']]);
        }

        if (empty($post['id_bank_name'])) {
            return response()->json(['status' => 'fail', 'messages' => ['ID can not be empty']]);
        }

        $check = BankAccount::where('beneficiary_account', $post['beneficiary_account'])->first();
        if (empty($check)) {
            $save = BankAccount::create([
                'id_bank_name' => $post['id_bank_name'],
                'beneficiary_name' => $post['beneficiary_name'] ?? $request->user()->name,
                'beneficiary_account' => $post['beneficiary_account']
                ]);
            $check['id_bank_account'] = $save['id_bank_account'];
        }

        $save = BankAccountOutlet::updateOrCreate([
            'id_bank_account' => $check['id_bank_account'],
            'id_outlet' => $checkMerchant['id_outlet']
        ], [
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        return response()->json(MyHelper::checkUpdate($save));
    }

    public function bankAccountList(Request $request)
    {
//        $idUser = $request->user()->id;
//        $checkMerchant = Merchant::where('id_user', $idUser)->first();
//        if (empty($checkMerchant)) {
//            return response()->json(['status' => 'fail', 'messages' => ['Data merchant tidak ditemukan']]);
//        }
        $checkMerchant = $request->json()->all();
        $list = BankAccount::join('bank_name', 'bank_name.id_bank_name', 'bank_accounts.id_bank_name')
                ->join('bank_account_outlets', 'bank_account_outlets.id_bank_account', 'bank_accounts.id_bank_account')
                ->select('bank_account_outlets.id_bank_account', 'beneficiary_name', 'beneficiary_account', 'bank_image', 'bank_name.bank_name')
                ->where('id_outlet', $checkMerchant['id_outlet'])
                ->get();
        return response()->json(['status' => 'success' , 'result' => $list]);
    }

    public function bankAccountDelete(Request $request)
    {
        $post = $request->all();

        if (empty($post['id_bank_account'])) {
            return response()->json([
                'status' => 'fail',
                'messages' => ['ID can not be empty']
            ]);
        }

        $delete = BankAccount::where('id_bank_account', $post['id_bank_account'])->delete();
        if ($delete) {
            BankAccountOutlet::where('id_bank_account', $post['id_bank_account'])->delete();
        }

        return response()->json(MyHelper::checkDelete($delete));
    }

}
