<?php

namespace Modules\SettingFraud\Http\Controllers;

use App\Http\Models\Configs;
use Modules\SettingFraud\Entities\FraudDetectionLogTransactionInBetween;
use Modules\SettingFraud\Entities\FraudDetectionLogTransactionPoint;
use App\Http\Models\OauthAccessToken;
use App\Http\Models\Transaction;
use App\Http\Models\User;
use App\Http\Models\UserDevice;
use App\Http\Models\UsersDeviceLogin;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\SettingFraud\Entities\FraudSetting;
use Modules\SettingFraud\Entities\FraudDetectionLog;
use Modules\SettingFraud\Entities\FraudDetectionLogDevice;
use Modules\SettingFraud\Entities\FraudDetectionLogTransactionDay;
use Modules\SettingFraud\Entities\FraudDetectionLogTransactionWeek;
use App\Http\Models\Setting;
use Illuminate\Support\Facades\DB;
use App\Lib\MyHelper;
use App\Lib\ClassMaskingJson;
use App\Lib\Apiwha;
use DateTime;
use Mail;

use function GuzzleHttp\Psr7\str;

class ApiSettingFraud extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->autocrm  = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        $this->rajasms = new ClassMaskingJson();
        $this->Apiwha = new Apiwha();
    }

    public function listSettingFraud(Request $request)
    {
        $post = $request->json()->all();

        if (isset($post['id_fraud_setting'])) {
            $data = FraudSetting::find($post['id_fraud_setting']);
        } else {
            $data = FraudSetting::get();
        }

        return response()->json(MyHelper::checkGet($data));
    }

    public function updateSettingFraud(Request $request)
    {
        $post = $request->json()->all();
        unset($post['type']);
        unset($post['fraud_settings_status']);

        if (isset($post['auto_suspend_status'])) {
            $post['auto_suspend_status'] = 1;
        } else {
            $post['auto_suspend_status'] = 0;
        }

        if (isset($post['forward_admin_status'])) {
            $post['forward_admin_status'] = 1;
        } else {
            $post['forward_admin_status'] = 0;
        }

        $update = FraudSetting::where('id_fraud_setting', $post['id_fraud_setting'])->update($post);

        return response()->json(MyHelper::checkUpdate($update));
    }

    public function updateStatus(Request $request)
    {
        $post = $request->json()->all();
        if (isset($post['id_fraud_setting']) && isset($post['fraud_settings_status'])) {
            $save = FraudSetting::where('id_fraud_setting', $post['id_fraud_setting'])->update($post);

            return response()->json(MyHelper::checkUpdate($save));
        } else {
            return response()->json(['status' => 'fail','messages' => ['incomplete data']]);
        }
    }

    public function fraudConfig(Request $request)
    {
        $post = $request->json()->all();
        $get = Configs::where('config_name', 'fraud transaction point')->first()->is_active ?? 0;

        return response()->json(['status' => 'success', 'result' => ['is_active' => $get]]);
    }
}
