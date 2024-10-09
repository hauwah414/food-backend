<?php

namespace App\Lib;

class Apiwha
{
    public function sendold($api_key, $number, $text)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => "https://panel.Apiwha.com/send_message.php?apikey=" . $api_key . "&number=" . $number . "&text=" . urlencode($text),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        ));

        $hasil = curl_exec($curl);
        $err = curl_error($curl);

        if ($err) {
            return json_encode($err);
        } else {
            return json_encode($hasil);
        }
    }
    public function balance()
    {
        $dt = json_encode($this->data);
        $curlHandle = curl_init("");
        curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $dt);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($dt)));
        curl_setopt($curlHandle, CURLOPT_TIMEOUT, 5);
        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 5);
        $hasil = curl_exec($curlHandle);
        $curl_errno = curl_errno($curlHandle);
        $curl_error = curl_error($curlHandle);
        $http_code  = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        curl_close($curlHandle);
        if ($curl_errno > 0) {
            $senddata = array(
            'sending_respon' => array(
                'globalstatus' => 90,
                'globalstatustext' => $curl_errno . "|" . $http_code)
            );
            $hasil = json_encode($senddata);
        } else {
            if ($http_code <> "200") {
                $senddata = array(
                'sending_respon' => array(
                'globalstatus' => 90,
                'globalstatustext' => $curl_errno . "|" . $http_code)
                );
                $hasil = json_encode($senddata);
            }
        }
        return $hasil;
    }
     public function send($api_key, $number, $text,$url,$title)
    {
        $curl = curl_init();
        $token = env('TOKEN_WA');
        $payload = [
            "data" => [
                [
                    'phone' => $number,
                    'message' =>$text
                ]
            ]
        ];
//        return $payload = [
//            "data" => [
//                [
//                    'phone' => $number,
//                    'message' => [
//                        'content' => $text, 
//                    ],
//                ]
//            ]
//        ];
//        if($title == "Merchant Transaction New"){
//        $payload['data'][0]['message']['buttons'] =[
//                            "url"=>[
//                                    "display"=> "ITS Food",
//                                    "link"=>$url
//                                ]
//                            ];
//        }
        curl_setopt($curl, CURLOPT_HTTPHEADER,
            array(
                "Authorization: $token",
                "Content-Type: application/json"
            )
        );
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload) );
        curl_setopt($curl, CURLOPT_URL,  "https://solo.wablas.com/api/v2/send-message");
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

        $hasil = curl_exec($curl);
        $err = curl_error($curl);

        if ($err) {
            return json_encode($err);
        } else {
            return json_encode($hasil);
        }
    }
//     public function send($api_key, $number, $text)
//    {
//        $curl = curl_init();
//        curl_setopt_array($curl, array(
//        CURLOPT_URL =>  env('URLS_WA').'send-message?token=' . env('TOKEN_WA') . "&phone=" . $number . "&message=" . urlencode($text),
//        CURLOPT_RETURNTRANSFER => true,
//        CURLOPT_ENCODING => "",
//        CURLOPT_MAXREDIRS => 10,
//        CURLOPT_TIMEOUT => 30,
//        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//        CURLOPT_CUSTOMREQUEST => "GET",
//        ));
//
//        $hasil = curl_exec($curl);
//        $err = curl_error($curl);
//
//        if ($err) {
//            return json_encode($err);
//        } else {
//            return json_encode($hasil);
//        }
//    }
}
