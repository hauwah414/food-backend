<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Pembelian</title>
    <style>
        /* Style sesuai kebutuhan Anda */
        body {
            font-family: Times New Roman, Times, serif;
            margin: 0;
            padding: 0;
        }
        .header {
            margin-bottom: 10px;
        }
        .content {
            margin: 0 auto;
            width: 95%;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 0px solid #ddd;
            padding: 8px;
            text-align: left;
        }
/*        th {
            background-color: #f2f2f2;
        }*/
        .barcode {
            text-align: center;
            margin-top: 20px;
        }
        .barcode img {
            max-width: 50%;
            height: auto;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr style="margin-top: 5px ;border: 0">
            <th style="background:transparent; border: 0">
                        <img src="data:image/jpeg;base64,{{$logo_its}}"  style="height: 125px; object-fit:contain; margin-right:8px;" alt="">
            </th>
            <th style="background:transparent; border: 0">
                <p style="font-size: 18px; color:rgb(52, 135, 243);margin-bottom:2px;">
                {{$title_invoice}}
            </p>
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
            </p></th>
        </tr>
    </table> 
    <div class="content">
        <div style="background: #7d7d7d;
    padding-top: 2px;
    padding-bottom: 2px; text-align: center;font-size:25px;">
            <b>NOTA PEMBAYARAN TITIPAN</b>
        </div>
        
        
    <table style="width: 100%;border-spacing: 0;">
            <tbody>
                <tr>
                    <th style="width: 50%; font-weight:400; text-align:left;border: 0">
                        <p style=" margin-top:auto; margin-bottom:auto;">
                            Kepada Yth.
                            <br>{{$data['user']['name']??null}}
                            <br>
                            Departemen/Unit: {{$data['name_department']??null}}
                        </p>
                    </th>
                    <th style="width: 50%;border: 0">
                        <table style="width:100%;margin-top:18px;margin-bottom:18px; border-spacing:0;">
                            <tbody>
                                <tr>
                                    <th style="font-weight:400; padding:2px;width:20%;border-top:1px solid #000;">Nomor
                                        Order</th>
                                    <th style="font-weight:400; padding:2px; width:80%; text-align:start; border-top:1px solid #000;;">
                                        : <span style="text-decoration:underline">{{$data['transaction_receipt_number']??null}}</span> </th>
                                </tr>
                                <tr>
                                    <th style="font-weight:400; padding:2px;width:20%;">Tanggal <br> Order</th>
                                    <th style="font-weight:400; padding:2px; width:80%; text-align:start;">: {{$data['transaction_date']??null}}
                                    </th>
                                </tr>
                                <tr>
                                    <th style="font-weight:400; padding:2px;width:20%;">Tanggal <br> Diterima</th>
                                    <th style="font-weight:400; padding:2px; width:80%; text-align:start;">: {{$data['date_order_received']??null}}</th>
                                </tr>
                                <tr>
                                    <th style="font-weight:400; padding:2px;width:20%;">Tujuan</th>
                                    <th style="font-weight:400; padding:2px; width:80%; text-align:start;">:
                                        {{$data['tujuan_pembelian']??null}}
                                    </th>
                                </tr>
                                <tr>
                                    <th style="font-weight:400; padding:2px;width:20%;">
                                        Sumber Dana
                                    </th>
                                    <th style="font-weight:400; padding:2px; width:85%; text-align:start;">
                                        : {{$data['sumber_dana']??null}}
                                    </th>
                                </tr>
                                <tr>
                                    <th style="font-weight:400; padding:2px;width:20%; border-bottom:1px solid #000;">
                                        Nama Vendor
                                    </th>
                                    <th style="font-weight:400; padding:2px; width:85%; text-align:start; border-bottom:1px solid #000;">
                                        : {{$data['outlet_name']??null}}
                                    </th>
                                </tr>
                            </tbody>
                        </table>
                    </th>
                </tr>
            </tbody>
        </table>
        
        
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
                @foreach($data['transaction_products'] as $datas)
                <tr>
                    <th style="font-weight: 500; border:1px solid #000;">{{$datas['product_code']}}</th>
                    <th style="font-weight: 500; border:1px solid #000;">{{$datas['product_name']}}</th>
                    <th style="font-weight: 500; border:1px solid #000;">{{$datas['product_base_price']}}</th>
                    <th style="font-weight: 500; border:1px solid #000;">{{$datas['product_qty']}}</th>
                    <th style="font-weight: 500; border:1px solid #000;">{{$datas['product_total_price']}}
                    </th>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        <table style="width: 100%;border-spacing: 0; margin-top:22px;">
            <tbody>
                <tr>
                    <th style="width: 50%;">
                        <img style="width: 150px; height:150px;" src="data:image/jpeg;base64,{{$qrcode}}" alt="">
                    </th>
                    <th style="width: 50%; vertical-align:top;">
                        <table style="width:100%; border-spacing:0;">
                            <tbody>
                                <tr>
                                    <th style="border-top: 1px solid #000; font-weight:400; padding:4px;">Nominal
                                        pembelian </th>
                                    <th style="border-top: 1px solid #000; font-weight:400; padding:4px; text-align:start;">
                                        :
                                        {{$data['transaction_subtotal']??null}}
                                    </th>
                                </tr>
                                <tr>
                                    <th style="font-weight: 400;padding:4px;">Biaya pengiriman </th>
                                    <th style="font-weight: 400;padding:4px; text-align:start;">: {{$data['transaction_shipment_text']??null}}</th>
                                </tr>
                                <tr>
                                    <th style="font-weight: 400;padding:4px;">Biaya lainnya </th>
                                    <th style="font-weight: 400;padding:4px; text-align:start;">: Rp0</th>
                                </tr>
                                <tr>
                                    <th style="font-weight: 400;padding:4px; border-bottom:1px solid #000;">Potongan
                                        harga</th>
                                    <th style="font-weight: 400;padding:4px; border-bottom:1px solid #000;text-align:start;">
                                        : {{$data['transaction_discount']??null}}
                                    </th>
                                </tr>
                                <tr>
                                    <th style="font-weight: 400;padding:4px; border-top:1px solid #000; border-bottom:1px solid #000;">
                                        Total Pembayaran</th>
                                    <th style="font-weight: 400;padding:4px; border-top:1px solid #000;text-align:start; border-bottom:1px solid #000;">
                                        :{{$data['transaction_grandtotal']??null}}
                                    </th>
                                </tr>
                            </tbody>
                        </table>
                    </th>
                </tr>
            </tbody>
        </table>
        
        <p style="margin-top: 8px;margin-bottom:14px;">Pembayaran dapat ditransfer ke rekening:
        </p>
        
        <table style="width:100%">
            <tbody><tr>
                <th style="font-weight: 400;text-align:start ;">
                    Bank
                </th>
                <th style="text-align:start; font-weight:400;">: BNI KLN ITS/Bank Mandiri
                </th>
            </tr>
            <tr>
                <th style="font-weight: 400;text-align:start ;">
                    No Rekening
                </th>
                <th style="text-align:start; font-weight:400;">
                    : 2618055172 (BNI) / 1400002620186 (Bank Mandiri)
                </th>
            </tr>
            <tr>
                <th style="font-weight: 400;text-align:start ;">
                    Atas Nama
                </th>
                <th style="text-align:start; font-weight:400;">: PT. Usaha Tugu Adi Mandiri
                </th>
            </tr>
        </tbody></table>
        
<p style="margin-top:8px;">Atau melalui nomor virtual account nomor order ini yang tertera pada aplikasi
            itsfood.id
        </p>

        <table style="width:220px; margin-left:auto; margin-top:32px;">
                            <tbody>
                                <tr>
                                    <th style="font-weight: 500;">Surabaya,</th>
                                </tr>
                                <tr>
                                    <th><img src="data:image/jpeg;base64,{{$ttd_finance}}"  alt=""></th>
                                </tr>
                                <tr>
                                    <th style="border-bottom: 1px solid #000; font-weight: 500;">{{$admin_finance}}</th>
                                </tr>
                                <tr>
                                    <th style="font-weight: 500;">Finance</th>
                                </tr>
                            </tbody>
                        </table>
        
    </div>
</body>
</html>
