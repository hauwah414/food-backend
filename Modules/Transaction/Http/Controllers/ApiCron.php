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

class ApiCron extends Controller
{
    public $saveImage = "img/transaction/manual-payment/";

    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->shopeepay      = 'Modules\ShopeePay\Http\Controllers\ShopeePayController';
        $this->xendit         = 'Modules\Xendit\Http\Controllers\XenditController';
        $this->shipper         = 'Modules\Transaction\Http\Controllers\ApiShipperController';
    }

    public function orderReceived(Request $request)
    {
        
        $log = MyHelper::logCron('Auto Order Received');
          try {
        $data = Transaction::where(['transaction_status' => 'On Delivery','confirm_delivery'=>0])->get();
        foreach($data as $value){
            $transaction = Transaction::join('transaction_shipments', 'transaction_shipments.id_transaction', 'transactions.id_transaction')
            ->where('transaction_receipt_number',$value['transaction_receipt_number'])->first();
            if (empty($transaction)) {
                continue;
            }
            if ($transaction['transaction_status'] == 'Completed') {
                continue;
            }

            $received = app($this->shipper)->completedTransaction($transaction);
        }
         $log->success();
            return response()->json([
                'status' => 'success',
            ]);
        } catch (\Exception $e) {
            $log->fail($e->getMessage());
        }
    }
}
