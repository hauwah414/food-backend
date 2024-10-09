<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<style>
    * {
        padding: 0;
        margin: 0;
    }

    section {
        page-break-before: always;
    }

    header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
    }

    section {
        top: 160px;
        position: relative;
        width: 100%;
    }
</style>

<body>
    <header style="margin-bottom: 20px;padding-bottom: 28px; display:flex;flex-direction:row;">
      <img src="{{$logo_its}}" style="object-fit:contain; margin-right:8px;" alt="">
        <div class="">
            <h5 style="font-size: 18px; color:rgb(52, 135, 243);margin-bottom:4px;">
                {{$title_invoice}}
            </h5>
            <p>
                 {{$company_name}}
            </p>
            <p>
                {{$company_address}}
                <br>
                Telp: {{$telp_its}}
                <br>
                Fax: {{$fax_its}} <br>
                {{$url_its}}
            </p>
        </div>
    </header>

    <section style="  position: fixed;">
        <div style="background: #7d7d7d;border-top: 1px solid #000;
    padding-top: 4px;
    padding-bottom: 4px;">
            <h4 style="text-align: center;font-size:17px;">
                NOTA PEMBAYARAN TITIPAN
            </h4>
        </div>

        <div style="display:flex;flex-direction:row; width:100%">
            <p style="width: 50%; margin-top:auto; margin-bottom:auto;">
                Kepada Yth.
                <br>{{$data['user']['name']}}
                <br>
                Departemen/Unit: {{$data['name_department']}}
            </p>
            <table style="width:50%;margin-top:18px;margin-bottom:18px; border-spacing:0;">
                <tbody>
                    <tr>
                        <th style="font-weight:400; padding:2px;width:20%;border-top:1px solid #000;">Nomor Order</th>
                        <th
                            style="font-weight:400; padding:2px; width:85%; text-align:start; border-top:1px solid #000;;">
                            : <span style="text-decoration:underline">{{$data['transaction_receipt_number']}}</span> </th>
                    </tr>
                    <tr>
                        <th style="font-weight:400; padding:2px;width:20%;">Vendor Name</th>
                        <th style="font-weight:400; padding:2px; width:85%; text-align:start;">: {{$data['outlet']['outlet_name']}}
                    </tr>
                    <tr>
                        <th style="font-weight:400; padding:2px;width:20%;">Kode Vendor</th>
                        <th style="font-weight:400; padding:2px; width:85%; text-align:start;">: {{$data['outlet']['outlet_code']}}
                        </th>
                    </tr>
                    <tr>
                        <th style="font-weight:400; padding:2px;width:20%;">Tanggal Order</th>
                        <th style="font-weight:400; padding:2px; width:85%; text-align:start;">: {{$data['transaction_date']}}
                        </th>
                    </tr>
                    <tr>
                        <th style="font-weight:400; padding:2px;width:20%;">Tanggal Diterima</th>
                        <th style="font-weight:400; padding:2px; width:85%; text-align:start;">: {{$data['date_order_received']}}</th>
                    </tr>
                    <tr>
                        <th style="font-weight:400; padding:2px;width:20%;">Tujuan</th>
                        <th style="font-weight:400; padding:2px; width:85%; text-align:start;">: {{$data['tujuan_pembelian']}}
                        </th>
                    </tr>
                    <tr>
                        <th style="font-weight:400; padding:2px;width:20%; border-bottom:1px solid #000;">Sumber Dana
                        </th>
                        <th style="font-weight:400; padding:2px; width:85%; text-align:start; border-bottom:1px solid #000;">
                            : {{$data['sumber_dana']}}
                        </th>
                    </tr>
                </tbody>
            </table>
        </div>


        <table style="width: 100%;border-spacing: 0;">
            <thead style="background: #7d7d7d;">
                <tr>
                    <th style="border: 1px solid #000; padding:4px;">ID Menu</th>
                    <th style="border: 1px solid #000; padding:4px;">Nama Menu</th>
                    <th style="border: 1px solid #000; padding:4px;">Harga (Rp)</th>
                    <th style="border: 1px solid #000; padding:4px;">Qty (porsi)</th>
                    <th style="border: 1px solid #000; padding:4px;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['transaction_products'] as $product)
                    <tr>
                        <th style="font-weight: 500; border:1px solid #000;">{{$product['product_code']}}</th>
                        <th style="font-weight: 500; border:1px solid #000;">{{$product['product_name']}}</th>
                        <th style="font-weight: 500; border:1px solid #000;">{{$product['product_base_price']}}</th>
                        <th style="font-weight: 500; border:1px solid #000;">{{$product['product_qty']}}</th>
                        <th style="font-weight: 500; border:1px solid #000;">{{$product['product_total_price']}}</th>
                        </th>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="display:flex;flex-direction:row;
    align-items: flex-start; margin-top:12px;margin-left:32px;">
            <div style="width:60%;">
                {!! QrCode::size(125)->generate($data['qrcode']) !!}

            </div>
            <table style="width:40%; border-spacing:0; margin-top:12px;">
                <tbody>
                    <tr>
                        <th style="border-top: 1px solid #000; font-weight:400; padding:4px;">Nominal pembelian </th>
                        <th style="border-top: 1px solid #000; font-weight:400; padding:4px; text-align:start;">:
                            {{$data['transaction_subtotal']}}
                        </th>
                    </tr>
                    <tr>
                        <th style="font-weight: 400;padding:4px;">Biaya pengiriman </th>
                        <th style="font-weight: 400;padding:4px; text-align:start;">:  {{$data['transaction_shipment']}}</th>
                    </tr>
                    <tr>
                        <th style="font-weight: 400;padding:4px;">Biaya lainnya </th>
                        <th style="font-weight: 400;padding:4px; text-align:start;">: Rp 0</th>
                    </tr>
                    <tr>
                        <th style="font-weight: 400;padding:4px; border-bottom:1px solid #000;">Potongan harga</th>
                        <th style="font-weight: 400;padding:4px; border-bottom:1px solid #000;text-align:start;">: Rp0
                        </th>
                    </tr>
                    <tr>
                        <th
                            style="font-weight: 400;padding:4px; border-top:1px solid #000; border-bottom:1px solid #000;">
                            Total Pembayaran</th>
                        <th
                            style="font-weight: 400;padding:4px; border-top:1px solid #000;text-align:start; border-bottom:1px solid #000;">
                            :
                            {{$data['transaction_grandtotal']}}
                        </th>
                    </tr>
                </tbody>
            </table>
        </div>
        <p style="margin-top:8px;">Pembayaran dapat dilakukan pada aplikasi ITS Food
        </p>
        
        <p style="text-align: center;margin-top:8px;">Terima kasih telah mempercayakan kami sebagai mitra penyedia kebutuhan konsumsi
            Anda.
        </p>

        <div style="display:flex; margin-top:22px;">
            <div style="margin-left:auto; margin-right:12px; ">
                <p>Surabaya, {{$data['date_order_received']}}
                </p>
                <img src="data:image/jpeg;base64,{{$ttd_finance}}" alt="">
                <p style="border-bottom:2px solid #000">{{$admin_finance}}</p>
                <p>Finance</p>
            </div>
        </div>
    </section>
</body>

</html>