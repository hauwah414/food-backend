<?php

namespace Modules\Transaction\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Http\Models\Setting;
use App\Http\Models\Product;
use App\Http\Models\ProductPrice;
use App\Http\Models\Outlet;
use App\Lib\MyHelper;
use DB;
use Image;
use Modules\Disburse\Entities\BankName;

class ApiSettingTransaction extends Controller
{
    public function __construct()
    {
        ini_set('max_execution_time', 0);
        date_default_timezone_set('Asia/Jakarta');
        $this->setting_trx   = "Modules\Transaction\Http\Controllers\ApiSettingTransactionV2";
    }

    public function settingTrx(Request $request)
    {
        $post = $request->json()->all();
        $outlet = Outlet::where('id_outlet', $post['id_outlet'])->first();
        if (empty($outlet)) {
            DB::rollback();
            return response()->json([
                'status'    => 'fail',
                'messages'  => ['Outlet Not Found']
            ]);
        }

        $totalDisProduct = 0;

        $productDis = app($this->setting_trx)->discountProduct($post);
        if ($productDis) {
            $totalDisProduct = $productDis;
        }

        $post['dis_sem'] = $totalDisProduct;

        $count = $this->count($post);
        $order = Setting::where('key', 'transaction_grand_total_order')->value('value');
        $exp   = explode(',', $order);

        for ($i = 0; $i < count($exp); $i++) {
            if (substr($exp[$i], 0, 5) == 'empty') {
                unset($exp[$i]);
                continue;
            }

            if (!isset($post['shipping'])) {
                if ($exp[$i] == 'shipping') {
                    unset($exp[$i]);
                    continue;
                }
            }
        }

        if (isset($post['balance'])) {
            array_splice($exp, 1, 0, 'balance');
        }

        array_values($exp);

        $imp = implode(',', $exp);

        $sub = 0;
        $tax = 0;
        $service = 0;
        $dis = 0;
        $ship = 0;
        $balance = 0;

        if (isset($count['subtotal'])) {
            $sub = $count['subtotal'];
        }

        if (isset($count['tax'])) {
            $tax = $count['tax'];
        }

        if (isset($count['service'])) {
            $service = $count['service'];
        }

        if (isset($count['discount'])) {
            $dis = $count['discount'];
        }

        if (isset($count['shipping'])) {
            $ship = $count['shipping'];
        }

        if (isset($post['balance'])) {
            $balance = $post['balance'];
        }

        $total = $sub + $tax + $service - $dis + $ship - $balance;
        if ($total < 1) {
            $total = 0;
        }

        if (isset($post['balance'])) {
            $result = [
                'order'    => $imp,
                'subtotal' => $sub,
                'balance'  => $balance,
                'tax'      => $tax,
                'service'  => $service,
                'discount' => $dis,
                'shipping' => $ship,
                'total'    => $total,
            ];
        } else {
            $result = [
                'order'    => $imp,
                'subtotal' => $sub,
                'tax'      => $tax,
                'service'  => $service,
                'discount' => $dis,
                'shipping' => $ship,
                'total'    => $total,
            ];
        }

        foreach ($result as $key => $value) {
            if (!isset($post['shipping'])) {
                if ($result[$key] == 'shipping') {
                    unset($result[$key]);
                    continue;
                }
            }
        }

        array_values($result);

        return response()->json([
            'status' => 'success',
            'result' => $result
        ]);
    }

    public function count($post)
    {
        $grandTotal = app($this->setting_trx)->grandTotal();

        foreach ($grandTotal as $keyTotal => $valueTotal) {
            if ($valueTotal == 'subtotal') {
                $post['sub'] = app($this->setting_trx)->countTransaction($valueTotal, $post);
                if (gettype($post['sub']) != 'array') {
                    $mes = ['Data Not Valid'];

                    if (isset($post['sub']->original['messages'])) {
                        $mes = $post['sub']->original['messages'];

                        if ($post['sub']->original['messages'] == ['Price Product Not Found']) {
                            if (isset($post['sub']->original['product'])) {
                                $mes = ['Price Product Not Found with product ' . $post['sub']->original['product'] . ' at outlet ' . $outlet['outlet_name']];
                            }
                        }
                    }

                    DB::rollback();
                    return response()->json([
                        'status'    => 'fail',
                        'messages'  => $mes
                    ]);
                }

                $post['subtotal'] = array_sum($post['sub']);
                $post['subtotal'] = $post['subtotal'] - $post['dis_sem'];
            } elseif ($valueTotal == 'discount') {
                $post['dis'] = app($this->setting_trx)->countTransaction($valueTotal, $post);
                $mes = ['Data Not Valid'];

                if (isset($post['sub']->original['messages'])) {
                    $mes = $post['sub']->original['messages'];

                    if ($post['sub']->original['messages'] == ['Price Product Not Found']) {
                        if (isset($post['sub']->original['product'])) {
                            $mes = ['Price Product Not Found with product ' . $post['sub']->original['product'] . ' at outlet ' . $outlet['outlet_name']];
                        }
                    }

                    DB::rollback();
                    return response()->json([
                        'status'    => 'fail',
                        'messages'  => $mes
                    ]);
                }

                $post['discount'] = $post['dis'] + $post['dis_sem'];
            } else {
                $post[$valueTotal] = app($this->setting_trx)->countTransaction($valueTotal, $post);
            }
        }

        return $post;
    }
    /**
     * Check credit card payment gateway for transaction
     * @param  Request $request [description]
     * @return Array           [description]
     */
    public function ccPayment(Request $request)
    {
        $pg = Setting::select('value')->where('key', 'credit_card_payment_gateway')->pluck('value')->first() ?: 'Ipay88';
        return MyHelper::checkGet($pg);
    }

    public function packageDetailDelivery(Request $request)
    {
        $post = $request->json()->all();

        if (empty($post)) {
            $setting_dimension = (array)json_decode(Setting::where('key', 'package_detail_delivery')->first()->value_text ?? null);
            return response()->json(MyHelper::checkGet($setting_dimension));
        } else {
            $dimension = [
                'package_name' => $post['package_name'] ?? "",
                'package_description' => $post['package_description'] ?? "",
                'length' => str_replace('.', "", $post['length']) ?? 0,
                'width' => str_replace('.', "", $post['width']) ?? 0,
                'height' => str_replace('.', "", $post['height']) ?? 0,
                'weight' => str_replace('.', "", $post['weight']) ?? 0
            ];
            $update = Setting::updateOrCreate(['key' => 'package_detail_delivery'], ['value_text' => json_encode($dimension)]);
            return response()->json(MyHelper::checkUpdate($update));
        }
    }

    public function imageDelivery(Request $request)
    {
        $post = $request->json()->all();

        if (empty($post)) {
            $setting  = json_decode(MyHelper::setting('available_delivery', 'value_text', '[]'), true) ?? [];
            $default_image = Setting::where('key', 'default_image_delivery')->first()->value ?? null;
            $delivery = [];

            foreach ($setting as $value) {
                if ($value['show_status'] == 1) {
                    if (!empty($value['logo'])) {
                        $value['logo'] = config('url.storage_url_api') . $value['logo'];
                    }
                    $delivery[] = $value;
                }
            }

            usort($delivery, function ($a, $b) {
                return $a['position'] - $b['position'];
            });

            $result = [
                'default_image_delivery' => (!empty($default_image) ? config('url.storage_url_api') . $default_image : null),
                'delivery' => $delivery
            ];
            return response()->json(MyHelper::checkGet($result));
        } else {
            if (!empty($post['image_default'])) {
                $decoded = base64_decode($post['image_default']);
                $img    = Image::make($decoded);
                $width  = $img->width();
                $height = $img->height();

                $upload = MyHelper::uploadPhotoStrict($post['image_default'], $path = 'default_image/delivery/', $width, $height, 'delivery_default_image');

                if ($upload['status'] == "success") {
                    Setting::updateOrCreate(['key' => 'default_image_delivery'], ['value' => $upload['path']]);
                }
            }

            $setting  = json_decode(MyHelper::setting('available_delivery', 'value_text', '[]'), true) ?? [];
            foreach ($post['images'] ?? [] as $key => $image) {
                $decoded = base64_decode($image);
                $img    = Image::make($decoded);
                $width  = $img->width();
                $height = $img->height();

                $upload = MyHelper::uploadPhotoStrict($image, $path = 'default_image/delivery/', $width, $height, $key);

                if ($upload['status'] == "success") {
                    $check = array_search($key, array_column($setting, 'code'));
                    if ($check !== false) {
                        $setting[$check]['logo'] = $upload['path'];
                    }
                }
            }

            $update = Setting::where('key', 'available_delivery')->update(['value_text' => json_encode($setting)]);

            return MyHelper::checkUpdate($update);
        }
    }

    public function settingMdr(Request $request)
    {
        $post = $request->all();

        Setting::updateOrCreate(['key' => 'mdr_charged'], ['value' => $post['mdr_charged']]);
        $update = Setting::updateOrCreate(['key' => 'mdr_formula'], ['value_text' => json_encode($post['mdr_formula'])]);

        return response()->json(MyHelper::checkUpdate($update));
    }

    public function settingWithdrawal(Request $request)
    {
        $post = $request->all();

        Setting::updateOrCreate(['key' => 'withdrawal_fee_global'], ['value' => $post['withdrawal_fee_global']]);
        foreach ($post['data'] as $key => $value) {
            $update = BankName::where('id_bank_name', $key)->update(['withdrawal_fee_formula' => $value['value']]);
        }

        return response()->json(MyHelper::checkUpdate($update));
    }
    public function settingOngkir(Request $request)
    {
        $post = $request->all();

        $update = Setting::updateOrCreate(['key' => 'default_ongkos_kirim'], ['value' => $post['default_ongkos_kirim']]);
        $update = Setting::updateOrCreate(['key' => 'default_ongkos_kirim_flat'], ['value' => $post['default_ongkos_kirim_flat']]);
        $update = Setting::updateOrCreate(['key' => 'ongkos_kirim'], ['value' => $post['ongkos_kirim']]);
       

        return response()->json(MyHelper::checkUpdate($update));
    }
    public function settingReminder(Request $request)
    {
        $post = $request->all();

        $update = Setting::updateOrCreate(['key' => 'reminder-tagihan-pembayaran'], ['value' => $post['reminder-tagihan-pembayaran']]);
       
        return response()->json(MyHelper::checkUpdate($update));
    }
    public function settingExpired(Request $request)
    {
        $post = $request->all();

       $update = Setting::updateOrCreate(['key' => 'expired-date-tagihan-pembayaran'], ['value' => $post['expired-date-tagihan-pembayaran']]);
       return response()->json(MyHelper::checkUpdate($update));
    }
    public function settingReceived(Request $request)
    {
        $post = $request->all();

       $update = Setting::updateOrCreate(['key' => 'date-order-received'], ['value' => $post['date-order-received']]);
       

        return response()->json(MyHelper::checkUpdate($update));
    }
}
