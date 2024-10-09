<?php

namespace Modules\Setting\Http\Controllers;

use App\Http\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Lib\MyHelper;

class ApiSettingWebview extends Controller
{
    public function faqWebviewView(Request $request)
    {
        $bearer = $request->header('Authorization');

        if ($bearer == "") {
            return view('error', ['msg' => 'Unauthenticated']);
        }

        $faqList = MyHelper::postCURLWithBearer('api/setting/faq?log_save=0', null, $bearer);

        if (isset($faqList['result'])) {
            return view('setting::webview.faq', ['faq' => $faqList['result']]);
        } else {
            return view('setting::webview.faq', ['faq' => null]);
        }
    }

    public function aboutWebview($key, Request $request)
    {
        $bearer = $request->header('Authorization');

        if ($bearer == "") {
            return view('error', ['msg' => 'Unauthenticated']);
        }

        $data = MyHelper::postCURLWithBearer('api/setting/webview', ['key' => $key, 'data' => 1], $bearer);

        if (isset($data['status']) && $data['status'] == 'success') {
            if ($data['result']['value_text']) {
                $data['value'] = preg_replace('/face="[^;"]*(")?/', 'div class="seravek-light-font"', $data['result']['value_text']);
                $data['value'] = preg_replace('/face="[^;"]*(")?/', '', $data['value']);
            }

            if ($data['result']['value']) {
                $data['value'] = preg_replace('/<\/font>?/', '</div>', $data['value']);
            }
        } else {
            $data['value'] = null;
        }
        return view('setting::webview.about', $data);
    }

    public function faqDetailView(Request $request)
    {
        $bearer = $request->header('Authorization');

        if ($bearer == "") {
            return view('error', ['msg' => 'Unauthenticated']);
        }

        $faqList = MyHelper::postCURLWithBearer('api/setting/faq?log_save=0', null, $bearer);

        if (isset($faqList['result'])) {
            return response()->json(['status' => 'success', 'result' => $faqList['result']]);
        } else {
            return response()->json(['status' => 'fail', 'message' => 'Failed to open News']);
        }
    }

    // public function aboutDetail($key, Request $request)
    // {
    //     $bearer = $request->header('Authorization');

    //     if ($bearer == "") {
    //         return view('error', ['msg' => 'Unauthenticated']);
    //     }

    //     $data = MyHelper::postCURLWithBearer('api/setting/webview', ['key' => $key, 'data' => 1], $bearer);

    //     if(isset($data['status']) && $data['status'] == 'success'){
    //         if($data['result']['value_text']){
    //             $data['value'] =preg_replace('/face="[^;"]*(")?/', 'div class="seravek-light-font"' , $data['result']['value_text']);
    //             $data['value'] =preg_replace('/face="[^;"]*(")?/', '' , $data['value']);
    //         }

    //         if($data['result']['value']){
    //             $data['value'] =preg_replace('/<\/font>?/', '</div>' , $data['value']);
    //         }

    //         return response()->json(['status' => 'success', 'result' => $data['result']]);
    //     }else{
    //         return response()->json(['status' => 'fail', 'message' => 'Failed to open']);
    //     }
    // }

    public function aboutDetail($key, Request $request)
    {
        $data['result'] = Setting::where('key', $key)->first();
        return response()->json(['status' => 'success', 'result' => $data['result']]);
    }
}
