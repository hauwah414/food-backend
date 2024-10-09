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
use App\Http\Models\Payment;
use App\Http\Models\PaymentGroup;
use App\Http\Models\PaymentXendit;
use Auth;
use App\Http\Models\ProductPriceUser;
use Modules\Favorite\Entities\Favorite;
use App\Http\Models\ProductServingMethod;
use App\Http\Models\ProductCustomGroup;

class ApiBeTransactionGroup extends Controller
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
    
    public function transaction(Request $request)
    {
        $post = $request->json()->all();
        $filterCode = [
            1 => 'Pending',
            2 => 'Unpaid',
            3 => 'Paid',
            4 => 'Completed',
            5 => 'Cancelled',
        ];
        $codeIndo = [
            'Pending' => [
                'code' => 1,
                'text' => 'Pending'
            ],
            'Unpaid' => [
                'code' => 2,
                'text' => 'Belum dibayar'
            ],
            'Paid' => [
                'code' => 3,
                'text' => 'Dibayar'
            ],
            'Completed' => [
                'code' => 4,
                'text' => 'Selesai'
            ],
            'Cancelled' => [
                'code' => 5,
                'text' => 'Dibatalkan'
            ],
            
        ];

       $list = TransactionGroup::leftJoin('users', 'users.id', 'transaction_groups.id_user')
            ->leftJoin('departments', 'departments.id_department', 'transaction_groups.id_department')   
            ->orderBy('transaction_group_date', 'desc')
//            ->wherein('transaction_payment_status', ['Unpaid','Pending','Paid'])
            ->select('transaction_groups.*',  'users.name','users.phone','users.email','departments.name_department');

        if (
            isset($post['date_start']) && !empty($post['date_start']) &&
            isset($post['date_end']) && !empty($post['date_end'])
        ) {
            $start_date = date('Y-m-d', strtotime($post['date_start']));
            $end_date = date('Y-m-d', strtotime($post['date_end']));

            $list->whereDate('transaction_groups.transaction_group_date', '>=', $start_date)
                ->whereDate('transaction_groups.transaction_group_date', '<=', $end_date);
        }
          if (!empty($post['filter_status_code'])) {
            $filterStatus = [];
            foreach ($post['filter_status_code'] as $code) {
                if (!empty($filterCode[$code])) {
                    $filterStatus[] = $filterCode[$code];
                }
            }

            $list = $list->whereIn('transaction_payment_status', $filterStatus);
        }
         if (isset($post['conditions']) && !empty($post['conditions'])) {
            $rule = 'and';
            if (isset($post['rule'])) {
                $rule = $post['rule'];
            }

            if ($rule == 'and') {
                foreach ($post['conditions'] as $row) {
                    if (($row['operator'] == '=' || $row['operator'] == 'like') && empty($row['parameter'])) {
                        continue;
                    }

                    if (isset($row['subject'])) {
                        $subject = $row['subject'];
                        if ($subject == 'transaction_receipt_number') {
                            $subject = 'transactions.transaction_receipt_number';
                        } elseif ($subject == 'transaction_group_receipt_number') {
                            $subject = 'transaction_groups.transaction_receipt_number';
                        }

                        if ($row['operator'] == '=' || empty($row['parameter'])) {
                            $list->where($subject, (empty($row['parameter']) ? $row['operator'] : $row['parameter']));
                        } else {
                            $list->where($subject, 'like', '%' . $row['parameter'] . '%');
                        }
                    }
                }
            } else {
                $list->where(function ($subquery) use ($post) {
                    foreach ($post['conditions'] as $row) {
                        if (($row['operator'] == '=' || $row['operator'] == 'like') && empty($row['parameter'])) {
                            continue;
                        }

                        if (isset($row['subject'])) {
                            $subject = $row['subject'];
                            if ($subject == 'transaction_receipt_number') {
                                $subject = 'transactions.transaction_receipt_number';
                            } elseif ($subject == 'transaction_group_receipt_number') {
                                $subject = 'transaction_groups.transaction_receipt_number';
                            }

                            if ($row['operator'] == '=' || empty($row['parameter'])) {
                                $subquery->orWhere($subject, (empty($row['parameter']) ? $row['operator'] : $row['parameter']));
                            } else {
                                $subquery->orWhere($subject, 'like', '%' . $row['parameter'] . '%');
                            }
                        }
                    }
                });
            }
        }
        if(isset($post['row'])){
            $list = $list->paginate($post['row']??25)->toArray();
            foreach ($list['data'] ?? [] as $key => $value) {
                 $list['data'][$key] = [
                     'id_transaction_group' => $value['id_transaction_group'],
                     'id_user' => $value['id_user'],
                     'transaction_receipt_number' => $value['transaction_receipt_number'],
                     'transaction_subtotal' => $value['transaction_subtotal'],
                     'transaction_shipment' => $value['transaction_shipment'],
                     'transaction_tax' => $value['transaction_tax'],
                     'transaction_service' => $value['transaction_service'],
                     'transaction_grandtotal' => $value['transaction_grandtotal'],
                     'transaction_discount' => $value['transaction_discount'],
                     'transaction_payment_type' => $value['transaction_payment_type'],
                     'transaction_void_date' => $value['transaction_void_date'],
                     'transaction_group_date' => $value['transaction_group_date'],
                     'transaction_payment_status_code' => $codeIndo[$value['transaction_payment_status']]['code'] ?? '',
                     'transaction_payment_status' => $codeIndo[$value['transaction_payment_status']]['text'] ?? '',
                     'transaction_completed_at' => $value['transaction_completed_at'],
                     'sumber_dana' => $value['sumber_dana'],
                     'tujuan_pembelian' => $value['tujuan_pembelian'],
                     'transaction_cogs' => $value['transaction_cogs'],
                     'id_department' => $value['id_department'],
                     'name_department' => $value['name_department'],
                     'name' => $value['name'],
                     'phone' => $value['phone'],
                     'email' => $value['email'],
                 ];
             } 
        }else{
            $list = $list->get();
            foreach ($list ?? [] as $key => $value) {
                 $list[$key] = [
                     'id_transaction_group' => $value['id_transaction_group'],
                     'id_user' => $value['id_user'],
                     'transaction_receipt_number' => $value['transaction_receipt_number'],
                     'transaction_subtotal' => $value['transaction_subtotal'],
                     'transaction_shipment' => $value['transaction_shipment'],
                     'transaction_tax' => $value['transaction_tax'],
                     'transaction_service' => $value['transaction_service'],
                     'transaction_grandtotal' => $value['transaction_grandtotal'],
                     'transaction_discount' => $value['transaction_discount'],
                     'transaction_payment_type' => $value['transaction_payment_type'],
                     'transaction_void_date' => $value['transaction_void_date'],
                     'transaction_group_date' => $value['transaction_group_date'],
                     'transaction_payment_status_code' => $codeIndo[$value['transaction_payment_status']]['code'] ?? '',
                     'transaction_payment_status' => $codeIndo[$value['transaction_payment_status']]['text'] ?? '',
                     'transaction_completed_at' => $value['transaction_completed_at'],
                     'sumber_dana' => $value['sumber_dana'],
                     'tujuan_pembelian' => $value['tujuan_pembelian'],
                     'transaction_cogs' => $value['transaction_cogs'],
                     'id_department' => $value['id_department'],
                     'name_department' => $value['name_department'],
                     'name' => $value['name'],
                     'phone' => $value['phone'],
                     'email' => $value['email'],
                 ];
             } 
        }
        return response()->json(MyHelper::checkGet($list??0));
    }
  public function transactionDetail(Request $request)
    {
        $group = TransactionGroup::where('transaction_receipt_number',$request->id??null)->first();
        if(!$group){
           return response()->json(MyHelper::checkGet($group));
        }
        $transaction = Transaction::where('id_transaction_group',$group->id_transaction_group??null)->get();
        $data['group'] = $group;
        foreach ($transaction as $value){
           $result = $this->callTransactionDetail($value);
            if(isset($result['status'])&&$result['status']=='fail'){
              continue;   
            }
            $data['transaction'][] = $result;
        }
        return response()->json(MyHelper::checkGet($data));
    }

    public function callTransactionDetail($request)
    {
        $id = $request['id_transaction'];

        $codeIndo = [
            'Rejected' => [
                'code' => 1,
                'text' => 'Dibatalkan'
            ],
            'Unpaid' => [
                'code' => 2,
                'text' => 'Belum dibayar'
            ],
            'Pending' => [
                'code' => 3,
                'text' => 'Menunggu Konfirmasi'
            ],
            'On Progress' => [
                'code' => 4,
                'text' => 'Diproses'
            ],
            'On Delivery' => [
                'code' => 5,
                'text' => 'Dikirim'
            ],
            'Completed' => [
                'code' => 6,
                'text' => 'Selesai'
            ]
        ];

        $transaction = Transaction::join('outlets', 'outlets.id_outlet', 'transactions.id_outlet')
            ->where(['transactions.id_transaction' => $id])
            ->orWhere(['transactions.transaction_receipt_number' => $id])
            ->leftJoin('transaction_shipments', 'transaction_shipments.id_transaction', '=', 'transactions.id_transaction')
            ->leftJoin('cities', 'transaction_shipments.destination_id_city', '=', 'cities.id_city')
            ->leftJoin('provinces', 'provinces.id_province', '=', 'cities.id_province')->with(['outlet']);

        
        $transaction = $transaction->first();
        if (empty($transaction)) {
            return [
                'status' => 'fail',
                'messages' => ['Transaction not found']
            ];
        }

        if ($transaction['receive_at']) { // kalau sudah sampai tapi belum diselesaikan, codenya 7
            $codeIndo['On Delivery']['code'] = 7;
        }

        $transactionProducts = TransactionProduct::join('products', 'products.id_product', 'transaction_products.id_product')
                            ->where('id_transaction', $id)
                            ->with(['variants' => function ($query) {
                                $query->select('id_transaction_product', 'transaction_product_variants.id_product_variant', 'transaction_product_variants.id_product_variant', 'product_variants.product_variant_name', 'transaction_product_variant_price')
                                    ->join('product_variants', 'product_variants.id_product_variant', '=', 'transaction_product_variants.id_product_variant');
                            }])
                            ->select('transaction_products.*', 'products.product_name','products.min_transaction')->get()->toArray();

        $products = [];
        foreach ($transactionProducts as $value) {
            $existRating = UserRating::where('id_transaction', $value['id_transaction'])->where('id_product', $value['id_product'])->first();
            $image = ProductPhoto::where('id_product', $value['id_product'])->orderBy('product_photo_order', 'asc')->first()['url_product_photo'] ?? config('url.storage_url_api') . 'img/default.jpg';
            $products[] = [
                'id_transaction_product' => $value['id_transaction_product'],
                'id_product' => $value['id_product'],
                'product_name' => $value['product_name'],
                'min_transaction' => $value['min_transaction'],
                'product_qty' => $value['transaction_product_qty'],
                'need_recipe_status' =>  $value['transaction_product_recipe_status'],
                'product_label_price_before_discount' => ($value['transaction_product_price_base'] > $value['transaction_product_price'] ? 'Rp ' . number_format((int)$value['transaction_product_price_base'], 0, ",", ".") : 0),
                'product_base_price' => 'Rp ' . number_format((int)$value['transaction_product_price'], 0, ",", "."),
                'product_total_price' => 'Rp ' . number_format((int)$value['transaction_product_subtotal'], 0, ",", "."),
                'discount_all' => (int)$value['transaction_product_discount_all'],
                'discount_all_text' => 'Rp ' . number_format((int)$value['transaction_product_discount_all'], 0, ",", "."),
                'discount_each_product' => (int)$value['transaction_product_base_discount'],
                'discount_each_product_text' => 'Rp ' . number_format((int)$value['transaction_product_base_discount'], 0, ",", "."),
                'note' => $value['transaction_product_note'],
                'variants' => implode(', ', array_column($value['variants'], 'product_variant_name')),
                'image' => $image,
                'reviewed_status' => (!empty($existRating) ? true : false)
            ];
        }

        $paymentDetail = [
            [
                'text' => 'Subtotal',
                'value' => 'Rp ' . number_format((int)$transaction['transaction_subtotal'], 0, ",", ".")
            ],
            [
                'text' => 'Biaya Kirim',
                'value' => 'Rp ' . number_format((int)$transaction['transaction_shipment'], 0, ",", ".")
            ]
        ];

        if ($transaction['transaction_cogs'] > 0) {
            $paymentDetail[] = [
                'text' => 'COGS',
                'value' => 'Rp ' . number_format((int)$transaction['transaction_cogs'], 0, ",", ".")
            ];
        }
        if ($transaction['transaction_service'] > 0) {
            $paymentDetail[] = [
                'text' => 'Sharing Profit',
                'value' => 'Rp ' . number_format((int)$transaction['transaction_service'], 0, ",", ".")
            ];
        }

        if ($transaction['transaction_tax'] > 0) {
            $paymentDetail[] = [
                'text' => 'Pajak',
                'value' => 'Rp ' . number_format((int)$transaction['transaction_tax'], 0, ",", ".")
            ];
        }
        $vendor_fee = (int)$transaction['transaction_cogs']+(int)$transaction['transaction_shipment'];
        $paymentDetail[] = [
            'text' => "Seller's Profit",
            'value' => 'Rp ' . number_format($vendor_fee, 0, ",", ".")
        ];

        if (!empty($transaction['transaction_discount'])) {
            $codePromo = PromoCampaignPromoCode::where('id_promo_campaign_promo_code', $transaction['id_promo_campaign_promo_code'])->first()['promo_code'] ?? '';
            $paymentDetail[] = [
                'text' => 'Discount' . (!empty($transaction['transaction_discount_delivery']) ? ' Biaya Kirim' : '') . (!empty($codePromo) ? ' (' . $codePromo . ')' : ''),
                'value' => '-Rp ' . number_format((int)abs($transaction['transaction_discount']), 0, ",", ".")
            ];
        }

        $grandTotal = $transaction['transaction_grandtotal'];
        $trxPaymentBalance = TransactionPaymentBalance::where('id_transaction', $transaction['id_transaction'])->first()['balance_nominal'] ?? 0;

        if (!empty($trxPaymentBalance)) {
            $paymentDetail[] = [
                'text' => 'Point yang digunakan',
                'value' => '-' . number_format($trxPaymentBalance, 0, ",", ".")
            ];
            $grandTotal = $grandTotal - $trxPaymentBalance;
        }

        $trxPaymentMidtrans = TransactionPaymentMidtran::where('id_transaction_group', $transaction['id_transaction_group'])->first();
        $trxPaymentXendit = TransactionPaymentXendit::where('id_transaction_group', $transaction['id_transaction_group'])->first();

        $paymentURL = null;
        $paymentToken = null;
        $paymentType = null;
        if (!empty($trxPaymentMidtrans)) {
            $paymentMethod = $trxPaymentMidtrans['payment_type'] . (!empty($trxPaymentMidtrans['bank']) ? ' (' . $trxPaymentMidtrans['bank'] . ')' : '');
            $paymentMethod = str_replace(" ", "_", $paymentMethod);
            $paymentLogo = config('payment_method.midtrans_' . strtolower($paymentMethod) . '.logo');
            $redirect = config('payment_method.midtrans_' . strtolower($paymentMethod) . '.redirect');
            $paymentType = 'Xendit';//'Midtrans';
            if ($transaction['transaction_status'] == 'Unpaid') {
                $paymentURL = $trxPaymentMidtrans['redirect_url'];
                $paymentToken = $trxPaymentMidtrans['token'];
            }
        } elseif (!empty($trxPaymentXendit)) {
            $paymentMethod = $trxPaymentXendit['type'];
            $paymentMethod = str_replace(" ", "_", $paymentMethod);
            $paymentLogo = config('payment_method.xendit_' . strtolower($paymentMethod) . '.logo');
            $redirect = config('payment_method.xendit_' . strtolower($paymentMethod) . '.redirect');
            $paymentType = 'Xendit';
            if ($transaction['transaction_status'] == 'Unpaid') {
                $paymentURL = $trxPaymentXendit['checkout_url'];
            }
        }
        $district = Districts::join('subdistricts', 'subdistricts.id_district', 'districts.id_district')
            ->where('id_subdistrict', $transaction['depart_id_subdistrict'])->first();
        $subdistrict = Subdistricts::join('districts','districts.id_district','subdistricts.id_district')
                ->where('id_subdistrict', $transaction['destination_id_subdistrict'])->first();
        $address = [
            'destination_name' => $transaction['destination_name']??null,
            'destination_phone' => $transaction['destination_phone']??null,
            'destination_address' => $transaction['destination_address']??null,
            'destination_description' => $transaction['destination_description']??null,
            'destination_province' => $transaction['province_name']??null,
            'destination_city' => $transaction['city_name']??null,
            'destination_district' => $subdistrict['district_name']??null,
            'destination_subdistrict' => $subdistrict['subdistrict_name']??null
        ];
        
        $tracking = [];
        $trxTracking = TransactionShipmentTrackingUpdate::where('id_transaction', $id)->orderBy('tracking_date_time', 'desc')->orderBy('id_transaction_shipment_tracking_update', 'desc')->get()->toArray();
        foreach ($trxTracking as $value) {
            $trackingDate = date('Y-m-d H:i', strtotime($value['tracking_date_time']));
            $timeZone = 'WIB';
            if (!empty($value['tracking_timezone']) && $value['tracking_timezone'] == '+0800') {
                $trackingDate = date('Y-m-d H:i', strtotime('+ 1 hour', strtotime($value['tracking_date_time'])));
                $timeZone = 'WITA';
            } elseif (!empty($value['tracking_timezone']) && $value['tracking_timezone'] == '+0900') {
                $trackingDate = date('Y-m-d H:i', strtotime('+ 2 hour', strtotime($value['tracking_date_time'])));
                $timeZone = 'WIT';
            }

            $tracking[] = [
                'date' => MyHelper::dateFormatInd($trackingDate, true) . ' ' . $timeZone,
                'description' => $value['tracking_description'],
                'attachment'=>$value['url_attachment']
            ];
        }
        $group = TransactionGroup::where('id_transaction_group', $transaction['id_transaction_group'])->first();
        $merchant = Merchant::join('users','users.id','merchants.id_user')->where('id_outlet',$transaction['id_outlet'])->select('id')->first();
      $call = User::where('id',$merchant['id'])->first();
      $producted = array(
            'id_outlet'=>$transaction['id_outlet'],
            'id_transaction' => $id
        );  
      $result = [
            'id_transaction' => $id,
            'status_ongkir' => $transaction['status_ongkir'],
            'call' => $call['call']??null,
            'contact_kurir' => $transaction['call_contact_kurir']??null,
            'transaction_shipment' => $transaction['transaction_shipment'],
            'id_transaction_group' => $transaction['id_transaction_group'],
            'confirm_delivery' => $transaction['confirm_delivery'],
            'note' => $transaction['note'],
            'sumber_dana' => $group['sumber_dana']??null,
            'tujuan_pembelian' => $group['tujuan_pembelian']??null,
            'receipt_number_group' => $group['transaction_receipt_number']??null,
            'transaction_receipt_number' => $transaction['transaction_receipt_number'],
            'transaction_status_code' => $codeIndo[$transaction['transaction_status']]['code'] ?? '',
            'transaction_status_text' => $codeIndo[$transaction['transaction_status']]['text'] ?? '',
            'transaction_date' => MyHelper::dateFormatInd(date('Y-m-d H:i', strtotime($transaction['transaction_date'])), true),
            'transaction_date_text' => date('Y-m-d H:i', strtotime($transaction['transaction_date'])),
            'transaction_products' => $products,
            'show_rate_popup' => $transaction['show_rate_popup'],
            'address' => $address,
            'transaction_grandtotal' => 'Rp ' . number_format($grandTotal, 0, ",", "."),
            'outlet' => $transaction['outlet']??null,
            'outlet_name' => $transaction['outlet_name'],
            'outlet_logo' => (empty($transaction['outlet_image_logo_portrait']) ? config('url.storage_url_api') . 'img/default.jpg' : config('url.storage_url_api') . $transaction['outlet_image_logo_portrait']),
            'delivery' => [
                'delivery_price' => 'Rp ' . number_format((int)$transaction['transaction_shipment'], 0, ",", "."),
                'delivery_tracking' => $tracking,
                'estimated' => $transaction['shipment_courier_etd']
            ],
            'user' => User::where('id', $transaction['id_user'])->select('name', 'email', 'phone')->first(),
            'payment' => $paymentMethod ?? '',
            'payment_logo' => $paymentLogo ?? env('STORAGE_URL_API') . 'default_image/payment_method/default.png',
            'payment_type' => TransactionGroup::where('id_transaction_group', $transaction['id_transaction_group'])->first()['transaction_payment_type'] ?? '',
            'payment_token' => $paymentToken,
            'payment_url' => $paymentURL,
            'payment_detail' => $paymentDetail,
            'point_receive' => (!empty($transaction['transaction_cashback_earned'] && $transaction['transaction_status'] != 'Rejected') ? ($transaction['cashback_insert_status'] ? 'Mendapatkan +' : 'Anda akan mendapatkan +') . number_format((int)$transaction['transaction_cashback_earned'], 0, ",", ".") . ' point dari transaksi ini' : ''),
            'transaction_reject_reason' => $transaction['transaction_reject_reason'],
            'transaction_reject_at' => (!empty($transaction['transaction_reject_at']) ? MyHelper::dateFormatInd(date('Y-m-d H:i', strtotime($transaction['transaction_reject_at'])), true) : null),
            'item' => $this->listProduct($producted)
        ];

        return $result;
    }
    public function listProduct($post)
    {
        if (!empty($post['id_outlet'])) {
            $idMerchant = Merchant::where('id_outlet', $post['id_outlet'])->first()['id_merchant'] ?? null;
            if (empty($idMerchant)) {
                return response()->json(['status' => 'fail', 'messages' => ['Outlet not found']]);
            }
        }else{
            return response()->json(['status' => 'fail', 'messages' => ['Outlet not found']]);
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
        return $list;
    }
}
