<table style="border:1px solid #C0C0C0;border-collapse:collapse;padding:5px; width: 100%">
    <tbody>
        @foreach($detail as $key => $value)
        <?php
        
        // hide this column
        if(in_array($key, ['step_complete','used_code','url_deals_warning_image','id_deals','id_product','deals_total_claimed','deals_total_redeemed','deals_total_used','deals_tos','claim_allowed','total_voucher_subscription','url_image','url_deals_image', 'deals_status', 'deals_voucher_price_type', 'deals_voucher_price_pretty', 'url_webview', 'url_deals_warning_image','deals_description','deals_short_description', 'is_online', 'is_offline', 'deals_promo_id', 'deals_promo_id_type','deals_shipment_text', 'deals_payment_text', 'deals_outlet_text', 'outlets', 'deals_payment_method', 'deals_shipment_method', 'id_brand', 'deals_brands', 'brand_rule_text', 'brands', 'outlet_groups'])){
            continue;
        }

        if(strpos(strtolower($key), '_date') !== false || strpos(strtolower($key), 'date_') !== false || strpos(strtolower($key), '_at') !== false || strpos(strtolower($key), 'expired') !== false || strpos(strtolower($key), '_end') !== false || strpos(strtolower($key), '_start') !== false ){
            $value = $value?date('d F Y H:i', strtotime($value)):'';
        } elseif (strpos(strtolower($key), '_time') !== false || strpos(strtolower($key), 'time_') !== false) {
            $value = $value?date('H:i', strtotime(date('Y-m-d ').$value)):'';
        } elseif (strpos(strtolower($key), '_image') !== false || strpos(strtolower($key), 'image_') !== false) {
            if(strpos(strtolower($value), 'http') !== false) {
                $value = "<img src='$value' style='max-width: 300px'/>";
            } elseif ($value) {
                $value = '<img src="'.config('url.storage_url_api').$value.'" style="max-width: 300px"/>';
            }
        }

        switch($key) {
            case 'deals_voucher_duration':
                $value = $value?number_format($value,0,',','.'):'';
                break;

            case 'deals_voucher_price_cash':
                $value = $value?\App\Lib\MyHelper::requestNumber($value,'_CURRENCY'):'';
                break;

            case 'deals_voucher_price_point':
                $value = $value?\App\Lib\MyHelper::requestNumber($value,'_POINT'):'';
                break;

            case 'deals_total_voucher':
                $value = $value?number_format($value,0,',','.'):'Unlimited';
                break;

            case 'user_limit':
                $value = $value?number_format($value,0,',','.'):'Unlimited';
                break;

            case 'is_all_outlet':
                $key = 'Outlet';
                $value = $detail['deals_outlet_text'];
                break;

            case 'is_all_shipment':
                $key = 'Shipment';
                $value = $detail['deals_shipment_text'];
                break;

            case 'is_all_payment':
                $key = 'Payment';
                $value = $detail['deals_payment_text'];
                break;

            case 'brand_rule':
                $value = $detail['brand_rule_text'];
                break;

            case 'created_by':
                $value = $value?(\App\Http\Models\User::select('name')->where('id',$value)->pluck('name')->first()?:$value):'Unknown';
                break;

            case 'last_updated_by':
                $value = $value?(\App\Http\Models\User::select('name')->where('id',$value)->pluck('name')->first()?:$value):'Unknown';
                break;

        }

        $key = str_replace(['deals_'],'',$key);
        ?>
        <tr>
            <th style="border:1px solid #C0C0C0;padding:5px;background:#F0F0F0">{{trim(ucwords(str_replace('_',' ',$key)))}}</th>
            <td style="border:1px solid #C0C0C0;padding:5px;">{!!$value?:'-'!!}</td>
        </tr>
        @endforeach
    </tbody>
</table>