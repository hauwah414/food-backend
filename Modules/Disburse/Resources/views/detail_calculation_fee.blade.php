<!DOCTYPE html>
<html>
<body>

<table style="border: 1px solid black">
    <thead>
    <tr>
        <th style="background-color: #dcdcdc;" width="20"> Recipient Number </th>
        <th style="background-color: #dcdcdc;" width="20"> Transaction Date </th>
        <th style="background-color: #dcdcdc;" width="20"> Outlet </th>
        <th style="background-color: #dcdcdc;" width="20"> Gross Sales </th>
        <th style="background-color: #dcdcdc;" width="20"> Discount </th>
        <th style="background-color: #dcdcdc;" width="20"> Nama Promo </th>
        <th style="background-color: #dcdcdc;" width="20"> Promo </th>
        <th style="background-color: #dcdcdc;" width="20"> Promo Cashback </th>
        <th style="background-color: #dcdcdc;" width="20"> Cashback </th>
        <th style="background-color: #dcdcdc;" width="20"> Delivery </th>
        <th style="background-color: #dcdcdc;" width="20"> Sub Total </th>
        <th style="background-color: #dcdcdc;" width="20"> Biaya Jasa </th>
        <th style="background-color: #dcdcdc;" width="20"> Payment </th>
        <th style="background-color: #dcdcdc;" width="20"> MDR PG </th>
        @if(isset($show_another_income) && $show_another_income == 1)
        <th style="background-color: #dcdcdc;" width="20"> Income Promo </th>
        <th style="background-color: #dcdcdc;" width="20"> Income Subscription </th>
        <th style="background-color: #dcdcdc;" width="20"> Income Bundling Product </th>
        <th style="background-color: #dcdcdc;" width="20"> Income Promo Cashback </th>
        @endif
        <th style="background-color: #dcdcdc;" width="20"> Income Outlet </th>
    </tr>
    </thead>
    <tbody>
    @if(!empty($data))
        @foreach($data as $val)
            <?php
            $discount = 0;
            $sub = 0;
            if(!empty($val['transaction_payment_subscription'])) {
                $sub = $val['transaction_payment_subscription']['subscription_nominal'];
                $discount = $sub;
            }else{
                $discount = abs($val['transaction_discount']);
            }
            ?>
            <tr>
                <td style="text-align: left">{{$val['transaction_receipt_number']}}</td>
                <td style="text-align: left">{{date('d M Y H:i', strtotime($val['transaction_date']))}}</td>
                <td style="text-align: left">{{$val['outlet_code']}}-{{$val['outlet_name']}}</td>
                <td style="text-align: left">{{$val['transaction_subtotal']+(float)$val['bundling_product_total_discount']}}</td>
                <td style="text-align: left">{{(float)$val['bundling_product_total_discount']}}</td>
                <td style="text-align: left">
                    <?php
                    $promoName = '';
                    if(count($val['vouchers']) > 0){
                        $promoName = $val['vouchers'][0]['deal']['deals_title'];
                    }elseif (!empty($val['promo_campaign'])){
                        $promoName = $val['promo_campaign']['promo_title'];
                    }elseif(!empty($val['transaction_payment_subscription'])) {
                        if(empty($val['transaction_payment_subscription']['subscription_title'])){
                            $promoName = 'Unknown Promo';
                        }else{
                            $promoName = $val['transaction_payment_subscription']['subscription_title'];
                        }
                    }elseif(isset($val['subscription_user_voucher']['subscription_user']['subscription']['subscription_title'])) {
                        $promoName = $val['subscription_user_voucher']['subscription_user']['subscription']['subscription_title'];
                    }elseif($val['discount_central'] > 0){
                        $promoName = 'Unknown Promo';
                    }

                    echo htmlspecialchars($promoName);
                    ?>
                </td>
                <td style="text-align: left">
                    <?php
                    if(!empty(abs($val['transaction_discount_delivery']))){
                        echo (float)abs($val['transaction_discount_delivery']);
                    }else{
                        echo (float)$discount;
                    }
                    ?>
                </td>
                <td style="text-align: left">
                    <?php
                    $promoName = '';
                    if(!empty($val['promo_payment_gateway_name'])){
                        $promoName = $val['promo_payment_gateway_name'];
                    }

                    echo htmlspecialchars($promoName);
                    ?>
                </td>
                <td style="text-align: left">
                    <?php
                        if(!empty($val['promo_payment_gateway_name'])){
                            echo (float)$val['total_received_cashback'];
                        }
                    ?>
                </td>
                <td style="text-align: left">{{$val['transaction_shipment_go_send']+$val['transaction_shipment']}}</td>
                <td style="text-align: left">{{$val['transaction_grandtotal']-$sub}}</td>
                <td style="text-align: left">{{(float)$val['fee_item']}}</td>
                <td style="text-align: left">
                    <?php
                        $payment = '';
                        if(!empty($val['payment_type'])){
                            $payment = $val['payment_type'];
                        }elseif(!empty($val['payment_method'])){
                            $payment = $val['payment_method'];
                        }elseif(!empty($val['id_transaction_payment_shopee_pay'])){
                            $payment = 'Shopeepay';
                        }
                        echo $payment;
                    ?>
                </td>
                <td style="text-align: left">{{(float)$val['payment_charge']}}</td>
                @if(isset($show_another_income) && $show_another_income == 1)
                <td style="text-align: left">{{(float)$val['discount_central']}}</td>
                <td style="text-align: left">{{(float)$val['subscription_central']}}</td>
                <td style="text-align: left">{{(float)$val['bundling_product_fee_central']}}</td>
                <td style="text-align: left">{{(float)$val['fee_promo_payment_gateway_central']}}</td>
                @endif
                <td style="text-align: left">{{(float)$val['income_outlet']}}</td>
            </tr>
        @endforeach
    @else
        <tr><td colspan="10" style="text-align: center">Data Not Available</td></tr>
    @endif
    </tbody>
</table>

</body>
</html>

