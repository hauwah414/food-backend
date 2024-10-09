<?php

namespace Modules\Merchant\Http\Controllers;

use App\Http\Models\City;
use App\Http\Models\Districts;
use App\Http\Models\LogBalance;
use App\Http\Models\MonthlyReportTrx;
use App\Http\Models\Outlet;
use App\Http\Models\Product;
use App\Http\Models\ProductPhoto;
use App\Http\Models\Province;
use App\Http\Models\Subdistricts;
use App\Http\Models\TransactionPaymentBalance;
use App\Http\Models\TransactionPaymentMidtran;
use App\Http\Models\TransactionProduct;
use App\Http\Models\User;
use App\Lib\Shipper;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Http\Models\Setting;
use App\Lib\MyHelper;
use Modules\Balance\Http\Controllers\NewTopupController;
use Modules\Disburse\Entities\BankAccount;
use Modules\Disburse\Entities\BankAccountOutlet;
use Modules\Disburse\Entities\BankName;
use App\Http\Models\TransactionShipment;
use Modules\ProductVariant\Entities\ProductVariant;
use Modules\Merchant\Entities\Merchant;
use Modules\Merchant\Entities\MerchantLogBalance;
use Modules\Merchant\Http\Requests\MerchantCreateStep1;
use Modules\Merchant\Http\Requests\MerchantCreateStep2;
use Modules\Outlet\Entities\DeliveryOutlet;
use DB;
use App\Http\Models\Transaction;
use Modules\ProductVariant\Entities\ProductVariantPivot;
use Modules\PromoCampaign\Entities\PromoCampaignPromoCode;
use Modules\Transaction\Entities\TransactionGroup;
use Modules\Transaction\Entities\TransactionShipmentTrackingUpdate;
use Modules\Transaction\Http\Requests\TransactionDetail;
use Modules\UserRating\Entities\UserRating;
use Modules\UserRating\Http\Controllers\ApiUserRatingController;
use Modules\Xendit\Entities\TransactionPaymentXendit;
use Modules\UserRating\Entities\UserRatingPhoto;

class ApiMerchantTransactionController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->autocrm          = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        $this->product_variant_group = "Modules\ProductVariant\Http\Controllers\ApiProductVariantGroupController";
        $this->online_trx = "Modules\Transaction\Http\Controllers\ApiOnlineTransaction";
        $this->trx = "Modules\Transaction\Http\Controllers\ApiTransaction";
        $this->merchant = "Modules\Merchant\Http\Controllers\ApiMerchantController";
        $this->saveShipper =  "img/shipper/";
    }

    public function statusCount(Request $request)
    {
        $idUser = $request->user()->id;
        $checkMerchant = Merchant::where('id_user', $idUser)->first();
        if (empty($checkMerchant)) {
            return response()->json(['status' => 'fail', 'messages' => ['Data merchant tidak ditemukan']]);
        }

        $order_new = Transaction::where('id_outlet', $checkMerchant['id_outlet'])
                    ->where('trasaction_type', 'Delivery')
                    ->where('transaction_status', 'Pending')->count();
        $order_onprogress = Transaction::where('id_outlet', $checkMerchant['id_outlet'])
            ->where('trasaction_type', 'Delivery')
            ->where('transaction_status', 'On Progress')->count();
        $order_ondelivery = Transaction::where('id_outlet', $checkMerchant['id_outlet'])
            ->where('trasaction_type', 'Delivery')
            ->where('transaction_status', 'On Delivery')->count();
        $order_completed = Transaction::where('id_outlet', $checkMerchant['id_outlet'])
            ->where('trasaction_type', 'Delivery')
            ->where('transaction_status', 'Completed')->count();
        $order_rejected = Transaction::where('id_outlet', $checkMerchant['id_outlet'])
            ->where('trasaction_type', 'Delivery')
            ->where('transaction_status', 'Rejected')->count();

        $result = [
            'new' => $order_new,
            'on_progress' => $order_onprogress,
            'on_delivery' => $order_ondelivery,
            'completed' => $order_completed,
            'rejected' => $order_rejected
        ];

        return response()->json(MyHelper::checkGet($result));
    }

    public function listTransaction(Request $request)
    {
        $post = $request->json()->all();
        $idUser = $request->user()->id;
        $checkMerchant = Merchant::where('id_user', $idUser)->first();
        if (empty($checkMerchant)) {
            return response()->json(['status' => 'fail', 'messages' => ['Data merchant tidak ditemukan']]);
        }
        $idOutlet = $checkMerchant['id_outlet'];
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
            ],
            'Unreview' => [
                'code' => 7,
                'text' => 'Belum direview'
            ]
        ];

        $transactions = Transaction::leftJoin('transaction_shipments', 'transaction_shipments.id_transaction', '=', 'transactions.id_transaction')
                        ->leftJoin('cities', 'transaction_shipments.destination_id_city', '=', 'cities.id_city')
                        ->where('trasaction_type', 'Delivery')
                        ->where('id_outlet', $idOutlet);

        if (!empty($post['status'])) {
            $status = ($post['status'] == 'new' ? 'Pending' : $post['status']);
            $transactions = $transactions->where('transaction_status', $status);
        }

        if (!empty($post['search_receipt_number_order_id'])) {
            $transactions = $transactions->where(function ($q) use ($post) {
                $q->where('transaction_receipt_number', 'like', '%' . $post['search_receipt_number_order_id'] . '%')
                    ->orWhere('transaction_shipments.order_id', 'like', '%' . $post['search_receipt_number_order_id'] . '%');
            });
        }

        $transactions = $transactions->orderBy('transactions.transaction_date', 'desc')->paginate($post['pagination_total_row'] ?? 10)->toArray();

        foreach ($transactions['data'] ?? [] as $key => $value) {
            $countAllProduct = TransactionProduct::where('id_transaction', $value['id_transaction'])->count();
            $countAllProduct = $countAllProduct - 1;
            $product = TransactionProduct::where('id_transaction', $value['id_transaction'])
                ->join('products', 'products.id_product', 'transaction_products.id_product')->first();
            $variant = '';
            if (!empty($product['id_product_variant_group'])) {
                $variant = ProductVariantPivot::join('product_variants', 'product_variants.id_product_variant', 'product_variant_pivot.id_product_variant')
                    ->where('id_product_variant_group', $product['id_product_variant_group'])->pluck('product_variant_name')->toArray();
                $variant = implode(', ', $variant);
            }

            $image = ProductPhoto::where('id_product', $product['id_product'])->orderBy('product_photo_order', 'asc')->first()['url_product_photo'] ?? config('url.storage_url_api') . 'img/default.jpg';
            $transactions['data'][$key] = [
                'id_transaction' => $value['id_transaction'],
                'transaction_receipt_number' => $value['transaction_receipt_number'],
                'transaction_status_code' => $codeIndo[$value['transaction_status']]['code'] ?? '',
                'transaction_status_text' => $codeIndo[$value['transaction_status']]['text'] ?? '',
                'transaction_grandtotal' => $value['transaction_grandtotal'],
                'product_name' => $product['product_name'],
                'product_qty' => $product['transaction_product_qty'],
                'another_product_qty' => (empty($countAllProduct) ? null : $countAllProduct),
                'product_image' => (empty($image) ? config('url.storage_url_api') . 'img/default.jpg' : $image),
                'product_variants' => $variant,
                'delivery_city' => $value['city_name'],
                'delivery_method' => strtoupper($value['shipment_courier']),
                'delivery_service' => ucfirst($value['shipment_courier_service']),
                'transaction_date' => (!empty($value['transaction_date']) ? MyHelper::dateFormatInd($value['transaction_date'], true, true) : ''),
                'maximum_date_process' => (!empty($value['transaction_maximum_date_process']) ? MyHelper::dateFormatInd($value['transaction_maximum_date_process'], false, false) : ''),
                'maximum_date_delivery' => (!empty($value['transaction_maximum_date_delivery']) ? MyHelper::dateFormatInd($value['transaction_maximum_date_delivery'], false, false) : ''),
                'estimated_delivery' => '',
                'reject_at' => (!empty($value['transaction_reject_at']) ? MyHelper::dateFormatInd(date('Y-m-d H:i', strtotime($value['transaction_reject_at'])), true) : null),
                'reject_reason' => (!empty($value['transaction_reject_reason']) ? $value['transaction_reject_reason'] : ''),
            ];
            $ratings = null;

            $getRatings = UserRating::where('id_transaction', $value['id_transaction'])->where('id_product', $product['id_product'])->first();

            if (!empty($getRatings)) {
                $getPhotos = UserRatingPhoto::where('id_user_rating', $getRatings['id_user_rating'])->get()->toArray();
                $photos = [];
                foreach ($getPhotos as $dt) {
                    $photos[] = $dt['url_user_rating_photo'];
                }
                $currentOption = explode(',', $getRatings['option_value']);
                $ratings = [
                    "rating_value" => $getRatings['rating_value'],
                    "suggestion" => $getRatings['suggestion'],
                    "option_value" => $currentOption,
                    "photos" => $photos
                ];
            }

            if ($value['show_rate_popup'] == 1 && $value['transaction_status'] == 'Completed') {
                $transactions['data'][$key]['transaction_status_code'] = $codeIndo['Unreview']['code'] ?? '';
                $transactions['data'][$key]['transaction_status_text'] = $codeIndo['Unreview']['text'] ?? '';
            }

            $transactions['data'][$key]['ratings'] = $ratings;
        }
        return response()->json(MyHelper::checkGet($transactions));
    }

    public function detailTransaction(TransactionDetail $request)
    {
        $idUser = $request->user()->id;
        $checkMerchant = Merchant::where('id_user', $idUser)->first();
        if (empty($checkMerchant)) {
            return response()->json(['status' => 'fail', 'messages' => ['Data merchant tidak ditemukan']]);
        }
        $idOutlet = $checkMerchant['id_outlet'];

        if ($request->json('transaction_receipt_number') !== null) {
            $trx = Transaction::where(['transaction_receipt_number' => $request->json('transaction_receipt_number')])->first();
            if ($trx) {
                $id = $trx->id_transaction;
            } else {
                return MyHelper::checkGet([]);
            }
        } else {
            $id = $request->json('id_transaction');
        }

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
            ],
            'Unreview' => [
                'code' => 7,
                'text' => 'Belum direview'
            ]
        ];

        $transaction = Transaction::where(['transactions.id_transaction' => $id])
            ->leftJoin('transaction_shipments', 'transaction_shipments.id_transaction', '=', 'transactions.id_transaction')
            ->leftJoin('cities', 'transaction_shipments.destination_id_city', '=', 'cities.id_city')
            ->leftJoin('provinces', 'provinces.id_province', '=', 'cities.id_province')->first();

        if (empty($transaction)) {
            return response()->json(MyHelper::checkGet($transaction));
        }

        if ($idOutlet != $transaction['id_outlet']) {
            return MyHelper::checkGet([]);
        }
        $customer = User::where('id',$transaction->id_user)->first();
        $transactionProducts = TransactionProduct::join('products', 'products.id_product', 'transaction_products.id_product')
            ->where('id_transaction', $id)
            ->with(['variants' => function ($query) {
                $query->select('id_transaction_product', 'transaction_product_variants.id_product_variant', 'transaction_product_variants.id_product_variant', 'product_variants.product_variant_name', 'transaction_product_variant_price')
                    ->join('product_variants', 'product_variants.id_product_variant', '=', 'transaction_product_variants.id_product_variant');
            }])
            ->select('transaction_products.*', 'products.product_name')->get()->toArray();

        $products = [];
        foreach ($transactionProducts as $value) {
            $image = ProductPhoto::where('id_product', $value['id_product'])->orderBy('product_photo_order', 'asc')->first()['url_product_photo'] ?? config('url.storage_url_api') . 'img/default.jpg';
            $products[] = [
                'id_product' => $value['id_product'],
                'product_name' => $value['product_name'],
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
                'image' => $image
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
        $status_ongkir_text = "ITS";
        $vendor_fee = (int)$transaction['transaction_cogs'];
        if($transaction['status_ongkir']==0){
        $status_ongkir_text = "Vendor Fee";
          $vendor_fee = (int)$transaction['transaction_cogs']+(int)$transaction['transaction_shipment'];
        }
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

        if (!empty($trxPaymentMidtrans)) {
            $paymentMethod = $trxPaymentMidtrans['payment_type'] . (!empty($trxPaymentMidtrans['bank']) ? ' (' . $trxPaymentMidtrans['bank'] . ')' : '');
            $paymentMethod = str_replace(" ", "_", $paymentMethod);
            $paymentLogo = config('payment_method.midtrans_' . strtolower($paymentMethod) . '.logo');
        } elseif (!empty($trxPaymentXendit)) {
            $paymentMethod = $trxPaymentXendit['type'];
            $paymentMethod = str_replace(" ", "_", $paymentMethod);
            $paymentLogo = config('payment_method.xendit_' . strtolower($paymentMethod) . '.logo');
        }

        $district = Districts::join('subdistricts', 'subdistricts.id_district', 'districts.id_district')
            ->where('id_subdistrict', $transaction['depart_id_subdistrict'])->first();
        $address = [
            'destination_name' => $transaction['destination_name'],
            'destination_phone' => $transaction['destination_phone'],
            'destination_address' => $transaction['destination_address'],
            'destination_description' => $transaction['destination_description'],
            'destination_province' => $transaction['province_name'],
            'destination_city' => $transaction['city_name'],
            'destination_district' => $district['district_name'],
            'destination_subdistrict' => $district['subdistrict_name']
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
                'attachment' =>$value['url_attachment']
            ];
        }


        $ratings = [];
        $getRatings = UserRating::where('id_transaction', $transaction['id_transaction'])->get()->toArray();
        foreach ($getRatings as $rating) {
            $getPhotos = UserRatingPhoto::where('id_user_rating', $rating['id_user_rating'])->get()->toArray();
            $photos = [];
            foreach ($getPhotos as $dt) {
                $photos[] = $dt['url_user_rating_photo'];
            }

            $currentOption = explode(',', $rating['option_value']);
            $ratings[] = [
                "rating_value" => $rating['rating_value'],
                "suggestion" => $rating['suggestion'],
                "option_value" => $currentOption,
                "photos" => $photos
            ];
        }

        if ($transaction['transaction_status'] == 'Completed' && $transaction['show_rate_popup'] == 1) {
            $transaction['transaction_status'] = 'Unreview';
        }

        $result = [
            'id_transaction' => $id,
            'call' => $customer['call'],
            'status_ongkir' => $transaction['status_ongkir'],
            'status_ongkir_text' => $status_ongkir_text,
            'confirm_delivery' => $transaction['confirm_delivery'],
            'transaction_receipt_number' => $transaction['transaction_receipt_number'],
            'transaction_status_code' => $codeIndo[$transaction['transaction_status']]['code'] ?? '',
            'transaction_status_text' => $codeIndo[$transaction['transaction_status']]['text'] ?? '',
            'transaction_date' => MyHelper::dateFormatInd(date('Y-m-d H:i', strtotime($transaction['transaction_date'])), true),
            'transaction_products' => $products,
            'show_rate_popup' => $transaction['show_rate_popup'],
            'address' => $address,
            'transaction_grandtotal' => 'Rp ' . number_format((int)$grandTotal, 0, ",", "."),
            'delivery' => [
                'delivery_price' => 'Rp ' . number_format((int)$transaction['transaction_shipment'], 0, ",", "."),
                'delivery_tracking' => $tracking,
            ],
            'payment' => $paymentMethod ?? '',
            'payment_logo' => $paymentLogo ?? env('STORAGE_URL_API') . 'default_image/payment_method/default.png',
            'payment_detail' => $paymentDetail,
            'point_receive' => (!empty($transaction['transaction_cashback_earned'] && $transaction['transaction_status'] != 'Rejected') ? ($transaction['cashback_insert_status'] ? 'Mendapatkan +' : 'Anda akan mendapatkan +') . number_format((int)$transaction['transaction_cashback_earned'], 0, ",", ".") . ' point dari transaksi ini' : ''),
            'transaction_reject_reason' => $transaction['transaction_reject_reason'],
            'transaction_reject_at' => (!empty($transaction['transaction_reject_at']) ? MyHelper::dateFormatInd(date('Y-m-d H:i', strtotime($transaction['transaction_reject_at'])), true) : null),
            'ratings' => $ratings
        ];

        return response()->json(MyHelper::checkGet($result));
    }

    public function acceptTransaction(Request $request)
    {
        $post = $request->json()->all();
        $idUser = $request->user()->id;
        $level = $request->user()->level;
        if($level == "Super Admin"){
            $transaction = Transaction::where('id_transaction', $post['id_transaction'])
                        ->where('transaction_status', 'Pending')->with(['outlet', 'user'])->first();
        }else{
            $checkMerchant = Merchant::where('id_user', $idUser)->first();
            if (empty($checkMerchant)) {
                return response()->json(['status' => 'fail', 'messages' => ['Data merchant tidak ditemukan']]);
            }
            $idOutlet = $checkMerchant['id_outlet'];

            if (empty($post['id_transaction'])) {
                return response()->json(['status' => 'fail', 'messages' => ['ID can not be empty']]);
            }
              $transaction = Transaction::where('id_outlet', $idOutlet)
                    ->where('id_transaction', $post['id_transaction'])
                    ->where('transaction_status', 'Pending')->with(['outlet', 'user'])->first();
        }
        
      
        if (empty($transaction)) {
            return response()->json(['status' => 'fail', 'messages' => ['Data order tidak ditemukan']]);
        }

        $update = Transaction::where('id_transaction', $transaction['id_transaction'])
                ->update(['transaction_status' => 'On Progress']);

        if ($update) {
            TransactionShipmentTrackingUpdate::create([
                'id_transaction' => $transaction['id_transaction'],
                'tracking_description' => 'Transaksi sudah diterima oleh penjual',
                'tracking_date_time' => date('Y-m-d H:i:s')
            ]);

            $user = User::where('id', $transaction['id_user'])->first();
            $outlet = Outlet::where('id_outlet', $transaction['id_outlet'])->first();
            app($this->autocrm)->SendAutoCRM('Transaction Accepted', $user['phone'], [
                "outlet_name"      => $outlet['outlet_name'],
                'id_transaction'   => $transaction['id_transaction'],
                'receipt_number'   => $transaction['transaction_receipt_number'],
                'transaction_date'   => MyHelper::dateFormatInd($transaction['transaction_date'])
            ]);
        }

        return response()->json(MyHelper::checkUpdate($update));
    }

    public function rejectTransaction(Request $request)
    {
        $post = $request->json()->all();
        $idUser = $request->user()->id;
        $level = $request->user()->level;
        if($level == "Super Admin"){
            if (empty($post['id_transaction']) || empty($post['reject_reason'])) {
                return response()->json(['status' => 'fail', 'messages' => ['Incompleted data']]);
            }
            $transaction = Transaction::where('id_transaction', $post['id_transaction'])
                        ->where('transaction_status', 'Pending')->with(['outlet', 'user'])->first();
        }else{
           
            $checkMerchant = Merchant::where('id_user', $idUser)->first();
            if (empty($checkMerchant)) {
                return response()->json(['status' => 'fail', 'messages' => ['Data merchant tidak ditemukan']]);
            }
            $idOutlet = $checkMerchant['id_outlet'];

            if (empty($post['id_transaction']) || empty($post['reject_reason'])) {
                return response()->json(['status' => 'fail', 'messages' => ['Incompleted data']]);
            }

            $transaction = Transaction::where('id_outlet', $idOutlet)
                ->where('id_transaction', $post['id_transaction'])
                ->where('transaction_status','!=','Completed')->first();
        }
        if (empty($transaction)) {
            return response()->json(['status' => 'fail', 'messages' => ['Data order tidak ditemukan']]);
        }

        $reject = $transaction->triggerReject($post);
        $check = Transaction::where('id_transaction_group', $transaction['id_transaction_group'])->whereNotIn('transaction_status',['Completed','Rejected'])->count();
           if($check == 0){
               $check = Transaction::where('id_transaction_group', $transaction['id_transaction_group'])->where('transaction_status','Completed')->count();
               if($check){
                   TransactionGroup::where('id_transaction_group', $transaction['id_transaction_group'])->update(['transaction_payment_status' => 'Unpaid']);
               }else{
                   
                   TransactionGroup::where('id_transaction_group', $transaction['id_transaction_group'])->update(['transaction_payment_status' => 'Cancelled']);
               }
               
           }
        if (!$reject) {
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Reject transaction failed'],
            ]);
        }

        return response()->json(['status' => 'success']);
    }

    public function requestDeliveryTransaction(Request $request)
    {
        $post = $request->json()->all();
         if (empty($post['pengirim'])) {
            return response()->json(['status' => 'fail', 'messages' => ['Contact Kurir tidak ada']]);
        }
        $idUser = $request->user()->id;
         $level = $request->user()->level;
        if($level == "Super Admin"){
             $detail = Transaction::join('transaction_shipments', 'transaction_shipments.id_transaction', 'transactions.id_transaction')
                ->join('outlets', 'outlets.id_outlet', 'transactions.id_outlet')
                ->leftJoin('cities', 'cities.id_city', 'outlets.id_city')
                ->leftJoin('provinces', 'provinces.id_province', 'cities.id_province')
                ->where('transactions.id_transaction', $post['id_transaction'])
                ->where('transaction_status', 'On Progress')->first();
        }else{
           
            $checkMerchant = Merchant::where('id_user', $idUser)->first();
            if (empty($checkMerchant)) {
                return response()->json(['status' => 'fail', 'messages' => ['Data merchant tidak ditemukan']]);
            }
            $idOutlet = $checkMerchant['id_outlet'];

            if (empty($post['id_transaction'])) {
                return response()->json(['status' => 'fail', 'messages' => ['ID transaction can not be empty']]);
            }

            $detail = Transaction::join('transaction_shipments', 'transaction_shipments.id_transaction', 'transactions.id_transaction')
                ->join('outlets', 'outlets.id_outlet', 'transactions.id_outlet')
                ->leftJoin('cities', 'cities.id_city', 'outlets.id_city')
                ->leftJoin('provinces', 'provinces.id_province', 'cities.id_province')
                ->where('transactions.id_outlet', $idOutlet)
                ->where('transactions.id_transaction', $post['id_transaction'])
                ->where('transaction_status', 'On Progress')->first();
        }
        if (empty($detail)) {
            return response()->json(['status' => 'fail', 'messages' => ['Data order tidak ditemukan']]);
        }

        if (!empty($detail['order_id'])) {
            return response()->json(['status' => 'fail', 'messages' => ['Sedang menunggu pickup']]);
        }

        $district = Districts::join('subdistricts', 'subdistricts.id_district', 'districts.id_district')
            ->where('id_subdistrict', $detail['id_subdistrict'])->first();

       $address = [
            'id_province' => $detail['id_province'],
            'province_name' => $detail['province_name'],
            'id_city' => $detail['id_city'],
            'city_name' => $detail['city_name'],
            'id_district' => $district['id_district'],
            'destination_district' => $district['district_name'],
            'id_subdistrict' => $district['id_subdistrict'],
            'destination_subdistrict' => $district['subdistrict_name'],
            'address' => $detail['outlet_address'],
            'postal_code' => $detail['outlet_postal_code'],
            'phone_number' => $detail['outlet_phone']
        ];

        $description = Setting::where('key', 'delivery_request_description')->first()['value_text'] ?? '';
        $update = Transaction::where('id_transaction', $detail['id_transaction'])
                ->update(['transaction_status' => 'On Progress','confirm_delivery'=>1,"contact_kurir"=>$post['pengirim']]);
        if($update){
            
            $attachment = '';
              if($request->file('attachment')){
                  $file = $post['attachment'];
                $filename = null;
                $encode = base64_encode(fread(fopen($file, "r"), filesize($file)));
                $originalName = $file->getClientOriginalName();
                $ext = pathinfo($originalName, PATHINFO_EXTENSION);
                $upload = MyHelper::uploadFile($encode,  $this->saveShipper, $ext);
                if (isset($upload['status']) && $upload['status'] == "success") {
                    $attachment = $upload['path'];
                }
              }else{
                  if(isset($post['attachment'])){
                       $upload = MyHelper::uploadPhotoStrictSplash($post['attachment'], $this->saveShipper);
                    if (isset($upload['status']) && $upload['status'] == "success") {
                        $attachment = $upload['path'];
                    }
                  }
              }
             TransactionShipmentTrackingUpdate::create([
                'id_transaction' => $detail['id_transaction'],
                'tracking_description' =>"Paket akan dikirim oleh kurir. Contact kurir ".$post['pengirim'],
                'tracking_date_time' => date('Y-m-d H:i:s'),
                 'attachment'=>$attachment
            ]);
        }
        

        return response()->json(['status' => 'success', 'result' => [
            'outlet_name' => $detail['outlet_name'],
            'address' => $address,
        ]]);
    }

    public function listTimePickupDelivery(Request $request)
    {
        $post = $request->json()->all();
        $idUser = $request->user()->id;
        $checkMerchant = Merchant::where('id_user', $idUser)->first();
        if (empty($checkMerchant)) {
            return response()->json(['status' => 'fail', 'messages' => ['Data merchant tidak ditemukan']]);
        }
        $idOutlet = $checkMerchant['id_outlet'];

        if (empty($post['id_transaction'])) {
            return response()->json(['status' => 'fail', 'messages' => ['ID transaction can not be empty']]);
        }

        $detail = Transaction::join('transaction_shipments', 'transaction_shipments.id_transaction', 'transactions.id_transaction')
            ->join('outlets', 'outlets.id_outlet', 'transactions.id_outlet')
            ->where('transactions.id_outlet', $idOutlet)
            ->where('transactions.id_transaction', $post['id_transaction'])
            ->where('transaction_status', 'On Progress')->first();
        if (empty($detail)) {
            return response()->json(['status' => 'fail', 'messages' => ['Data order tidak ditemukan']]);
        }

        $timeZoneOutlet = City::join('provinces', 'provinces.id_province', 'cities.id_province')
                ->where('id_city', $detail['id_city'])->first()['time_zone_utc'] ?? null;
        if (empty($timeZoneOutlet)) {
            return response()->json(['status' => 'fail', 'messages' => ['Data timezone can not be empty']]);
        }

        $timeZone = [
            7 => 'Asia/Jakarta',
            8 => 'Asia/Makassar',
            9 => 'Asia/Jayapura'
        ];

        $dtRequestTimezone = $timeZone[$timeZoneOutlet];
        $shipper = new Shipper();
        $listTime = $shipper->sendRequest('Pickup Time List', 'GET', 'pickup/timeslot?time_zone=' . $dtRequestTimezone, []);

        if (empty($listTime['response']['data']['time_slots'])) {
            return response()->json(['status' => 'fail', 'messages' => ['Timeslot not available']]);
        }

        $res = $listTime['response']['data']['time_slots'];
        $result = [];
        foreach ($res as $value) {
            $start = date('Y-m-d H:i:s', strtotime($value['start_time']));
            $end = date('Y-m-d H:i:s', strtotime($value['end_time']));

            $result[] = [
                "start_time" => $start,
                "end_time" => $end
            ];
        }

        return response()->json(MyHelper::checkGet($result));
    }


    public function confirmDeliveryTransaction(Request $request)
    {
       $post = $request->all();
        $idUser = $request->user()->id;
        $checkMerchant = Merchant::where('id_user', $idUser)->first();
        
        if (empty($post['id_transaction'])) {
            return response()->json(['status' => 'fail', 'messages' => ['ID transaction and pickup status can not be empty']]);
        }
        if (empty($post['penerima'])) {
            return response()->json(['status' => 'fail', 'messages' => ['Penerima tidak boleh kosong']]);
        }
        if (empty($post['attachment'])) {
            return response()->json(['status' => 'fail', 'messages' => ['Bukti pengiriman tidak boleh kosong']]);
        }

       
         $level = $request->user()->level;
        if($level == "Super Admin"){
            $detail = Transaction::join('transaction_shipments', 'transaction_shipments.id_transaction', 'transactions.id_transaction')
                ->join('outlets', 'outlets.id_outlet', 'transactions.id_outlet')
                ->leftJoin('cities', 'cities.id_city', 'outlets.id_city')
                ->leftJoin('provinces', 'provinces.id_province', 'cities.id_province')
                ->where('transactions.id_transaction', $post['id_transaction'])
                ->where('transaction_status', 'On Progress')
                ->first();
        }else{
            $checkMerchant = Merchant::where('id_user', $idUser)->first();
            if (empty($checkMerchant)) {
                return response()->json(['status' => 'fail', 'messages' => ['Data merchant tidak ditemukan']]);
            }
            $idOutlet = $checkMerchant['id_outlet'];

            if (empty($post['id_transaction'])) {
                return response()->json(['status' => 'fail', 'messages' => ['ID can not be empty']]);
            }
              $detail = Transaction::join('transaction_shipments', 'transaction_shipments.id_transaction', 'transactions.id_transaction')
                ->join('outlets', 'outlets.id_outlet', 'transactions.id_outlet')
                ->leftJoin('cities', 'cities.id_city', 'outlets.id_city')
                ->leftJoin('provinces', 'provinces.id_province', 'cities.id_province')
                ->where('transactions.id_outlet', $idOutlet)
                ->where('transactions.id_transaction', $post['id_transaction'])
                ->where('transaction_status', 'On Progress')
                ->first();
        
        }
        
        
        if (empty($detail)) {
            return response()->json(['status' => 'fail', 'messages' => ['Data order tidak ditemukan']]);
        }

        if (!empty($detail['order_id']) && !empty($detail['shipment_pickup_code'])) {
            return response()->json(['status' => 'fail', 'messages' => ['Sedang menunggu pickup']]);
        }
        if($detail['confirm_delivery']==0){
            return response()->json(['status' => 'fail', 'messages' => ['Request Delivery sebelum melakukan konfirmasi pengiriman']]);
        }

//        $deliveryList = app($this->merchant)->availableDelivery($detail['id_outlet']);
//        $dropCounterStatus = 1;
//        foreach ($deliveryList as $value) {
//            if ($value['delivery_method'] == $detail['shipment_courier']) {
//                foreach ($value['service'] as $s) {
//                    if ($s['code'] == $detail['shipment_courier_code']) {
//                        $dropCounterStatus = $s['drop_counter_status'] ?? 1;
//                        break;
//                    }
//                }
//                break;
//            }
//        }
//
//        if ($post['pickup_status'] == true && (empty($post['pickup_time_start']) || empty($post['pickup_time_end']))) {
//            return response()->json(['status' => 'fail', 'messages' => ['Pickup time tidak boleh kosong']]);
//        }

        $items = TransactionProduct::join('products', 'products.id_product', 'transaction_products.id_product')
                ->where('id_transaction', $detail['id_transaction'])
                ->get()->toArray();

        $products = [];
        foreach ($items as $value) {
            $productName = $value['product_name'];
            if (!empty($value['id_product_variant_group'])) {
                $variants = ProductVariant::join('product_variant_pivot', 'product_variant_pivot.id_product_variant', 'product_variants.id_product_variant')
                            ->where('id_product_variant_group', $value['id_product_variant_group'])->pluck('product_variant_name')->toArray();
                $productName = $productName . '(' . implode(" ", $variants) . ')';
            }

            $products[] = [
                "name" => $productName,
                "price" => (int)$value['transaction_product_price'],
                "qty" => $value['transaction_product_qty']
            ];
        }

        $subdistrictOutlet = Subdistricts::where('id_subdistrict', $detail['depart_id_subdistrict'])
            ->join('districts', 'districts.id_district', 'subdistricts.id_district')->first();
        if (empty($subdistrictOutlet)) {
            return response()->json(['status' => 'fail', 'messages' => ['Empty district outlet']]);
        }

//        $shipper = new Shipper();
//        if (empty($detail['order_id'])) {
//            $latOutlet = $subdistrictOutlet['subdistrict_latitude'];
//            $lngOutlet = $subdistrictOutlet['subdistrict_longitude'];
//
//            $subdistrictCustomer = Subdistricts::where('id_subdistrict', $detail['destination_id_subdistrict'])
//                ->join('districts', 'districts.id_district', 'subdistricts.id_district')->first();
//            if (empty($subdistrictCustomer)) {
//                return response()->json(['status' => 'fail', 'messages' => ['Empty district customer']]);
//            }
//            $latCustomer = $subdistrictCustomer['subdistrict_latitude'];
//            $lngCustomer = $subdistrictCustomer['subdistrict_longitude'];
//
//            $dtOrderShipment = [
//                "external_id" => $detail['transaction_receipt_number'],
//                "consignee" => [
//                    "name" => $detail['destination_name'],
//                    "phone_number" => substr_replace($detail['destination_phone'], '62', 0, 1)
//                ],
//                "consigner" => [
//                    "name" => $detail['depart_name'],
//                    "phone_number" => substr_replace($detail['depart_phone'], '62', 0, 1)
//                ],
//                "courier" => [
//                    "cod" => false,
//                    "rate_id" => $detail['shipment_rate_id'],
//                    "use_insurance" => ($detail['shipment_insurance_use_status'] == 1 ? true : false)
//                ],
//                "coverage" => "domestic",
//                "destination" => [
//                    "address" => preg_replace("/[^A-Za-z0-9. -#&'=+,()]/", "", $detail['destination_address']),
//                    "area_id" => $subdistrictCustomer['id_subdistrict_external'],
//                    "lat" => $latCustomer,
//                    "lng" => $lngCustomer
//                ],
//                "origin" => [
//                    "address" => preg_replace("/[^A-Za-z0-9. -#&'=+,()]/", "", $detail['depart_address']),
//                    "area_id" => $subdistrictOutlet['id_subdistrict_external'],
//                    "lat" => $latOutlet,
//                    "lng" => $lngOutlet
//                ],
//                "package" => [
//                    "height" => $detail['shipment_total_height'],
//                    "width" => $detail['shipment_total_width'],
//                    "length" => $detail['shipment_total_length'],
//                    "weight" => $detail['shipment_total_weight'],
//                    "items" => $products,
//                    "price" => $detail['transaction_subtotal'],
//                    "package_type" => (int)Setting::where('key', 'default_package_type_delivery')->first()['value'] ?? 3
//                ],
//                "payment_type" => "postpay"
//            ];

//            $orderDelivery = $shipper->sendRequest('Order', 'POST', 'order', $dtOrderShipment);
//            if (empty($orderDelivery['response']['data']['order_id'])) {
//                return response()->json(['status' => 'fail', 'messages' => ['Failed request to third party']]);
//            }

//            $devOrder = $orderDelivery['response']['data'];
//            $orderID = $devOrder['order_id'];
//            TransactionShipment::where('id_transaction', $detail['id_transaction'])
//                ->update([
//                    'order_id' => $orderID
//                ]);
//        } else {
//            $orderID = $detail['order_id'];
//        }

//        if ($dropCounterStatus == 0) {
//            $post['pickup_status'] = true;
//        }

//        if ($post['pickup_status'] == true) {
//            $post['pickup_time_start'] = (empty($post['pickup_time_start']) ? date('Y-m-d H:i:s') : $post['pickup_time_start']);
//            $post['pickup_time_end'] = (empty($post['pickup_time_end']) ? date('Y-m-d H:i:s') : $post['pickup_time_end']);
//            //pickup request
//            $timeZoneOutlet = City::join('provinces', 'provinces.id_province', 'cities.id_province')
//                    ->where('id_city', $detail['id_city'])->first()['time_zone_utc'] ?? null;
//            if (empty($timeZoneOutlet)) {
//                return response()->json(['status' => 'fail', 'messages' => ['Data timezone can not be empty']]);
//            }
//
//            $timeZone = [
//                7 => 'Asia/Jakarta',
//                8 => 'Asia/Makassar',
//                9 => 'Asia/Jayapura'
//            ];
//            $dtPickupShipment = [
//                "data" => [
//                    "order_activation" => [
//                        "order_id" => [
//                            $orderID
//                        ],
//                        "timezone" => $timeZone[$timeZoneOutlet],
//                        "start_time" => date("c", strtotime($post['pickup_time_start'])),
//                        "end_time" => date("c", strtotime($post['pickup_time_end']))
//                    ]
//                ]
//            ];
//
//            $pickupDelivery = $shipper->sendRequest('Request Pickup', 'POST', 'pickup/timeslot', $dtPickupShipment);
//            if (empty($pickupDelivery['response']['data']['order_activations'])) {
//                return response()->json(['status' => 'fail', 'messages' => ['Failed request pickup to third party']]);
//            }
//            $devPickup = $pickupDelivery['response']['data'];
//
//            $pickupCode = $devPickup['order_activations'][0]['pickup_code'] ?? null;
//            $update = TransactionShipment::where('id_transaction', $detail['id_transaction'])
//                ->update([
//                    'shipment_pickup_time_start' => date('Y-m-d H:i:s', strtotime($post['pickup_time_start'])),
//                    'shipment_pickup_time_end' => date('Y-m-d H:i:s', strtotime($post['pickup_time_end'])),
//                    'shipment_pickup_code' => $pickupCode
//                ]);
//        }

//        if (!empty($orderID)) {
          $update = Transaction::where('id_transaction', $detail['id_transaction'])->update(['transaction_status' => 'On Delivery','confirm_delivery'=>0]);
          if($update){
              $attachment = '';
              if($request->file('attachment')){
                  $file = $post['attachment'];
                $filename = null;
                $encode = base64_encode(fread(fopen($file, "r"), filesize($file)));
                $originalName = $file->getClientOriginalName();
                $ext = pathinfo($originalName, PATHINFO_EXTENSION);
                $upload = MyHelper::uploadFile($encode,  $this->saveShipper, $ext);
                if (isset($upload['status']) && $upload['status'] == "success") {
                    $attachment = $upload['path'];
                }
              }else{
                  $upload = MyHelper::uploadPhotoStrictSplash($post['attachment'], $this->saveShipper);
                    if (isset($upload['status']) && $upload['status'] == "success") {
                        $attachment = $upload['path'];
                    }
              }
             TransactionShipmentTrackingUpdate::create([
                'id_transaction' => $detail['id_transaction'],
                'tracking_description' =>"Paket telah diterima oleh ".$post['penerima'],
                'tracking_date_time' => date('Y-m-d H:i:s'),
                 'attachment'=>$attachment
            ]);
            TransactionShipment::where('id_transaction', $detail['id_transaction'])->update(['receive_at' => date("Y-m-d H:i:s")]);
            
        }
//        }

//        $deliveryList = app($this->merchant)->availableDelivery($detail['id_outlet']);
//        $deliveryName = '';
//        $deliveryLogo = '';
//        foreach ($deliveryList as $value) {
//            if ($value['delivery_method'] == $detail['shipment_courier']) {
//                $deliveryName =  $value['delivery_name'];
//                $deliveryLogo = $value['logo'];
//                break;
//            }
//        }
        $address = [
            'id_province' => $detail['id_province'],
            'province_name' => $detail['province_name'],
            'id_city' => $detail['id_city'],
            'city_name' => $detail['city_name'],
            'id_district' => $subdistrictOutlet['id_district'],
            'district_name' => $subdistrictOutlet['district_name'],
            'id_subdistrict' => $subdistrictOutlet['id_subdistrict'],
            'subdistrict_name' => $subdistrictOutlet['subdistrict_name'],
            'address' => $detail['outlet_address'],
            'postal_code' => $detail['outlet_postal_code']
        ];

        if ($update ?? true) {
            $user = User::where('id', $detail['id_user'])->first();
            app($this->autocrm)->SendAutoCRM('Transaction Delivery Confirm', $user['phone'], [
                "date" => MyHelper::dateFormatInd($detail['transaction_date']),
                'receipt_number'   => $detail['transaction_receipt_number'],
                'id_transaction' => $detail['id_transaction']
            ]);

            return response()->json(['status' => 'success', 'result' => [
                'outlet_name' => $detail['outlet_name'],
                'outlet_phone' => $detail['outlet_phone'],
                'address' => $address
            ]]);
        } else {
            return response()->json(['status' => 'fail', 'messages' => ['Failed update']]);
        }
    }

    public function dummyUpdateStatusDelivery(Request $request)
    {
        $post = $request->json()->all();

        if (empty($post['id_transaction']) || empty($post['status_code'])) {
            return response()->json(['status' => 'fail', 'messages' => ['Incompleted data']]);
        }

        $detail = Transaction::join('transaction_shipments', 'transaction_shipments.id_transaction', 'transactions.id_transaction')
            ->join('outlets', 'outlets.id_outlet', 'transactions.id_outlet')
            ->leftJoin('cities', 'cities.id_city', 'outlets.id_city')
            ->leftJoin('provinces', 'provinces.id_province', 'cities.id_province')
            ->where('transactions.id_transaction', $post['id_transaction'])->first();
        if (empty($detail)) {
            return response()->json(['status' => 'fail', 'messages' => ['Data order tidak ditemukan']]);
        }

        $filterCode = [
            1 => 'Paket Anda sudah dibawa oleh kurir',
            2 => 'Paket Anda sedang dalam perjalanan',
            3 => 'Paket Anda sedang diantar kealamat tujuan',
            4 => 'Paket Anda sudah diterima oleh ' . $detail['destination_name'],
            5 => 'Paket sudah diterima'
        ];

        if (empty($filterCode[$post['status_code']])) {
            return response()->json(['status' => 'fail', 'messages' => ['Status code tidak ditemukan']]);
        }

        if ($post['status_code'] == 1) {
            Transaction::where('id_transaction', $detail['id_transaction'])->update(['transaction_status' => 'On Delivery']);
        } elseif ($post['status_code'] == 5) {
            $detail->triggerTransactionCompleted();
        }

        $update = TransactionShipmentTrackingUpdate::create([
            'id_transaction' => $detail['id_transaction'],
            'shipment_order_id' => $detail['order_id'],
            'tracking_description' => $filterCode[$post['status_code']],
            'tracking_date_time' => date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' + 2 minutes'))
        ]);

        return response()->json(MyHelper::checkUpdate($update));
    }

    public function deliveryTracking(Request $request)
    {
        $idUser = $request->user()->id;
        $checkMerchant = Merchant::where('id_user', $idUser)->first();
        if (empty($checkMerchant)) {
            return response()->json(['status' => 'fail', 'messages' => ['Data merchant tidak ditemukan']]);
        }
        $idOutlet = $checkMerchant['id_outlet'];

        if ($request->json('transaction_receipt_number') !== null) {
            $trx = Transaction::where(['transaction_receipt_number' => $request->json('transaction_receipt_number')])->first();
            if ($trx) {
                $id = $trx->id_transaction;
            } else {
                return MyHelper::checkGet([]);
            }
        } else {
            $id = $request->json('id_transaction');
        }

        $codeIndo = [
            'Reject' => [
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
            ],
            'Unreview' => [
                'code' => 7,
                'text' => 'Belum direview'
            ]
        ];

        $transaction = Transaction::where(['transactions.id_transaction' => $id])
            ->leftJoin('transaction_shipments', 'transaction_shipments.id_transaction', '=', 'transactions.id_transaction')
            ->leftJoin('cities', 'transaction_shipments.destination_id_city', '=', 'cities.id_city')
            ->leftJoin('provinces', 'provinces.id_province', '=', 'cities.id_province')->first();

        if (empty($transaction)) {
            return response()->json(MyHelper::checkGet($transaction));
        }

        if ($idOutlet != $transaction['id_outlet']) {
            return MyHelper::checkGet([]);
        }

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
                'description' => $value['tracking_description']
            ];
        }

        $result = [
            'delivery_id' => $transaction['order_id'],
            'delivery_method' => strtoupper($transaction['shipment_courier']),
            'delivery_service' => ucfirst($transaction['shipment_courier_service']),
            'delivery_price' => 'Rp ' . number_format((int)$transaction['transaction_shipment'], 0, ",", "."),
            'delivery_tracking' => $tracking
        ];

        return response()->json(MyHelper::checkGet($result));
    }

    public function insertBalanceMerchant($data)
    {
        $balance_nominal = $data['balance_nominal'];
        $balance_before = MerchantLogBalance::where('id_merchant', $data['id_merchant'])->sum('merchant_balance');
        $balance_after = $balance_before + $balance_nominal;

        $LogBalance = [
            'id_merchant'                    => $data['id_merchant'],
            'merchant_balance'               => $balance_nominal,
            'merchant_balance_before'        => $balance_before,
            'merchant_balance_after'         => $balance_after,
            'merchant_balance_id_reference'  => $data['id_transaction'],
            'merchant_balance_source'        => $data['source'],
            'merchant_balance_status'        => $data['merchant_balance_status'],
            'created_at'                     => date('Y-m-d H:i:s'),
            'updated_at'                     => date('Y-m-d H:i:s')
        ];

        if ($balance_nominal < 0) {
            $create = MerchantLogBalance::updateOrCreate($LogBalance);
        } else {
            $create = MerchantLogBalance::updateOrCreate(['id_merchant' => $data['id_merchant'], 'merchant_balance_id_reference' => $data['id_transaction'], 'merchant_balance_source' => $data['source']], $LogBalance);
        }


        return $create;
    }

    public function detailTransactionCommission(Request $request)
    {
        $idUser = $request->user()->id;
        $checkMerchant = Merchant::where('id_user', $idUser)->first();
        if (empty($checkMerchant)) {
            return response()->json(['status' => 'fail', 'messages' => ['Data merchant tidak ditemukan']]);
        }
        $idOutlet = $checkMerchant['id_outlet'];

        $transaction = Transaction::where('transaction_receipt_number', $request->json('transaction_receipt_number'))
        ->where('id_outlet', $idOutlet)->first();

        if (empty($transaction)) {
            return response()->json(['status' => 'fail', 'messages' => ['Data transaksi tidak ditemukan']]);
        }

        $productSubtotalFinal = $transaction['transaction_subtotal'] - $transaction['transaction_discount_item'];
        $detailProduct = [
            'product_subtotal' => 'Rp ' . number_format((int)$transaction['transaction_subtotal'], 0, ",", "."),
            'product_subtotal_final' => 'Rp ' . number_format((int)$productSubtotalFinal, 0, ",", "."),
        ];

        if ($transaction['transaction_discount_item'] > 0) {
            $detailProduct['discount'] =  'Rp ' . number_format((int)$transaction['transaction_discount_item'], 0, ",", ".");
            $detailProduct['merchant_charged'] =  '-Rp ' . number_format((int)$transaction['discount_charged_outlet'], 0, ",", ".");
        }

        if ($transaction['transaction_discount_bill'] > 0) {
            $detailProduct['discount'] =  'Rp ' . number_format((int)$transaction['transaction_discount_bill'], 0, ",", ".");
            $detailProduct['merchant_charged'] =  '-Rp ' . number_format((int)$transaction['discount_charged_outlet'], 0, ",", ".");
        }

        $customerPay = $transaction['transaction_shipment'] - $transaction['transaction_discount_delivery'];
        $deliveryDetail = [
            'delivery_price' => 'Rp ' . number_format((int)$transaction['transaction_shipment'], 0, ",", "."),
            'customer_pay' => 'Rp ' . number_format((int)$customerPay, 0, ",", "."),
        ];

        if ($transaction['transaction_discount_delivery'] > 0) {
            $deliveryDetail['discount'] =  'Rp ' . number_format((int)$transaction['transaction_discount_delivery'], 0, ",", ".");
            $deliveryDetail['merchant_charged'] =  '-Rp ' . number_format((int)$transaction['discount_charged_outlet'], 0, ",", ".");
        }

        $result = [
            'grandtotal' => 'Rp ' . number_format((int)$transaction['transaction_grandtotal'], 0, ",", "."),
            'product_detail' => $detailProduct,
            'delivery_detail' => $deliveryDetail,
        ];

        if ($transaction['transaction_tax'] > 0) {
            $result['tax'] = '-Rp ' . number_format((int)$transaction['transaction_tax'], 0, ",", ".");
        }

        if ($transaction['transaction_service'] > 0) {
            $result['service'] = '-Rp ' . number_format((int)$transaction['transaction_service'], 0, ",", ".");
        }

        return response()->json($result);
    }

    public function getTotalTransactionPending(Request $request)
    {
        $idUser = $request->user()->id;
        $checkMerchant = Merchant::where('id_user', $idUser)->first();
        if (empty($checkMerchant)) {
            return response()->json(['status' => 'fail', 'messages' => ['Data merchant tidak ditemukan']]);
        }
        $idOutlet = $checkMerchant['id_outlet'];

        $count = Transaction::where('transaction_status', 'Pending')
            ->where('id_outlet', $idOutlet)->count();

        return response()->json(['status' => 'success', 'result' => $count]);
    }

    public function autoCancel()
    {
        $log = MyHelper::logCron('Auto cancel transaction');
        try {
            $countSuccess = 0;
            $currentDate = date('Y-m-d');

            $transactions = Transaction::join('users', 'users.id', 'transactions.id_user')
                ->where('transaction_status', 'Pending')
                ->where('trasaction_type', 'Delivery')
                ->where('transaction_maximum_date_process', '<', $currentDate)->get();

            foreach ($transactions as $transaction) {
                $post = [
                    'id_transaction' => $transaction['id_transaction'],
                    'reject_reason' => 'Pesanan tidak diproses'
                ];
                $check = $transaction->triggerReject($post);
                if ($check) {
                    $countSuccess++;
                }
            }

            $log->success(['reject_count' => count($transactions), 'reject_count_success' => $countSuccess]);
            return 'success';
        } catch (\Exception $e) {
            $log->fail($e->getMessage());
        }
    }
    public function cronRejectTransaction(Request $request)
    {
        $post = $request->json()->all();
        $transaction = Transaction::where('id_transaction', $post['id_transaction'])
                        ->where('transaction_status', 'Pending')->with(['outlet', 'user'])->first();
        

        $reject = $transaction->triggerReject($post);

        if (!$reject) {
            return response()->json([
                'status'   => 'fail',
                'messages' => ['Reject transaction failed'],
            ]);
        }

        return response()->json(['status' => 'success']);
    }
}
