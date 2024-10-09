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
use App\Http\Models\Notification;

class ApiNotifications extends Controller
{

    public function count()
    {
        $data = Notification::where('id_user',Auth::user()->id)->where('status',1)->count();
        if(!$data){
            $data = 0;
        }
         return response()->json(['status'    => 'success', 'result'  => $data]);
    }
    public function detail($id)
    {
        $list = Notification::where('id_notification',$id)->first();
        if($list){
            $list->status = 0;
            $list->save();
        }
        return MyHelper::checkGet($list);
    }
    public function index(Request $request)
    {
        $post = $request->json()->all();
        $list = Notification::where('id_user', Auth::user()->id)->orderby('created_at','DESC');
        if ($post['pagination_total_row']) {
            $list = $list->paginate($post['pagination_total_row']);
        }else{
            $list = $list->get()->toArray();
        }
        return MyHelper::checkGet($list);
    }
}
