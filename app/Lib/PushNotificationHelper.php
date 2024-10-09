<?php

namespace App\Lib;

use Image;
use File;
use DB;
use App\Http\Models\Notification;
use App\Http\Models\Store;
use App\Http\Models\User;
use App\Http\Models\UserDevice;
use App\Http\Models\Transaction;
use App\Http\Models\ProductVariant;
use App\Http\Requests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use Guzzle\Http\EntityBody;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\Response;
use Guzzle\Http\Exception\ServerErrorResponseException;
use Modules\Doctor\Entities\Doctor;
use Modules\Doctor\Entities\DoctorDevice;
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
// use LaravelFCM\Message\PayloadNotificationBuilder;
use App\Lib\CustomPayloadNotificationBuilder;
use FCM;

class PushNotificationHelper
{
    public $saveImage = "img/push";
    public $endPoint;
    public $autocrm;

    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->autocrm  = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        $this->endPoint  = config('url.storage_url_api');
    }

    public static function saveQueue($id_user, $subject, $message, $inbox = null, $data = null)
    {
        $save = [
            'id_user'    => $id_user,
            'subject'    => $subject,
            'message'    => $message,
            'data'       => serialize($data),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        return $save;
    }

    public static function processImage($image)
    {
        $upload = MyHelper::uploadPhoto($image, $this->saveImage, 500);

        if (isset($upload['status']) && $upload['status'] == "success") {
            $result = $this->endPoint . $upload['path'];
        } else {
            $result = "";
        }

        return $result;
    }

    // based on field Users Table
    public static function searchDeviceToken($type, $value, $recipient_type = null)
    {
        $result = [];

        if ($recipient_type == 'doctor') {
            $devUser = Doctor::leftjoin('doctor_devices', 'doctor_devices.id_doctor', '=', 'doctors.id_doctor')
            ->select('id_doctor_device', 'doctors.id_doctor', 'doctor_devices.device_token', 'doctor_devices.device_id', 'doctor_phone');

            if (is_array($type) && is_array($value)) {
                for ($i = 0; $i < count($type); $i++) {
                    $devUser->where($type[$i], $value[$i]);
                }
            } else {
                if (is_array($value)) {
                    $devUser->whereIn('doctors.doctor_' . $type, $value);
                } else {
                    $devUser->where('doctors.doctor_' . $type, $value);
                }
            }

            $devUser = $devUser->get()->toArray();
            if (!empty($devUser)) {
                // if phone
                if ($type == "phone") {
                    if (is_array($value)) {
                        $phone = implode(",", $value);
                    } else {
                        $phone = $value;
                    }

                    $result['phone'] = $phone;
                }

                $token             = array_values(array_filter(array_unique(array_pluck($devUser, 'device_token'))));
                $id_user           = array_values(array_filter(array_unique(array_pluck($devUser, 'id_doctor'))));
                $result['token']   = $token;
                $result['id_user'] = $id_user;
                $result['mphone']  = array_values(array_filter(array_unique(array_pluck($devUser, 'doctor_phone'))));
            }
        } else {
            $devUser = User::leftjoin('user_devices', 'user_devices.id_user', '=', 'users.id')
                ->select('id_device_user', 'users.id', 'user_devices.device_token', 'user_devices.device_id', 'phone');

            if (is_array($type) && is_array($value)) {
                for ($i = 0; $i < count($type); $i++) {
                    $devUser->where($type[$i], $value[$i]);
                }
            } else {
                if (is_array($value)) {
                    $devUser->whereIn('users.' . $type, $value);
                } else {
                    $devUser->where('users.' . $type, $value);
                }
            }

            $devUser = $devUser->get()->toArray();
            if (!empty($devUser)) {
                // if phone
                if ($type == "phone") {
                    if (is_array($value)) {
                        $phone = implode(",", $value);
                    } else {
                        $phone = $value;
                    }

                    $result['phone'] = $phone;
                }

                $token             = array_values(array_filter(array_unique(array_pluck($devUser, 'device_token'))));
                $id_user           = array_values(array_filter(array_unique(array_pluck($devUser, 'id_user'))));
                $result['token']   = $token;
                $result['id_user'] = $id_user;
                $result['mphone']  = array_values(array_filter(array_unique(array_pluck($devUser, 'phone'))));
            }
        }

        return $result;
    }

    public static function getDeviceTokenAll()
    {
        $device = UserDevice::get()->toArray();

        if (!empty($device)) {
            $device = array_values(array_filter(array_unique(array_pluck($device, 'device_token'))));
        }

        return $device;
    }

    public static function sendPush($tokens, $subject, $messages, $image = null, $dataOptional = [], $return_error = 0)
    {

        $optionBuiler = new OptionsBuilder();
        $optionBuiler->setTimeToLive(60 * 200);
        $optionBuiler->setContentAvailable(true);
        $optionBuiler->setPriority("high");

        // $notificationBuilder = new PayloadNotificationBuilder("");
        $notificationBuilder = new CustomPayloadNotificationBuilder($subject);
        $notificationBuilder->setBody($messages)
                            ->setSound('notif.mp3');
        if ($image) {
            $notificationBuilder->setImage($image);
        }

        $dataBuilder = new PayloadDataBuilder();

        $dataOptional['title']             = $subject;
        $dataOptional['body']              = $messages;
        $dataOptional['push_notif_local']  = $dataOptional['push_notif_local'] ?? 0;
        $dataBuilder->addData($dataOptional);

        // build semua
        $option       = $optionBuiler->build();
        $notification = $notificationBuilder->build();
        $data         = $dataBuilder->build();

        $downstreamResponse = FCM::sendTo($tokens, $option, $notification, $data);
        $success = $downstreamResponse->numberSuccess();
        $fail    = $downstreamResponse->numberFailure();

        if ($fail != 0) {
            $error = $downstreamResponse->tokensWithError();
        }

        $downstreamResponse->tokensToDelete();
        $downstreamResponse->tokensToModify();
        $downstreamResponse->tokensToRetry();

        $result = [
            'success' => $success,
            'fail'    => $fail
        ];


        if ($return_error ==  1) {
            $result['error_token'] = $downstreamResponse->tokensToDelete();
        }
        return $result;
    }

    public static function sendPushOutlet($tokens, $subject, $messages, $image = null, $dataOptional = [])
    {

        $optionBuiler = new OptionsBuilder();
        $optionBuiler->setTimeToLive(60 * 200);
        $optionBuiler->setContentAvailable(true);
        $optionBuiler->setPriority("high");

        // $notificationBuilder = new PayloadNotificationBuilder("");
        $notificationBuilder = new PayloadNotificationBuilder($subject);
        $notificationBuilder->setBody($messages)
                            ->setSound('default')
                            ->setClickAction($dataOptional['type']);

        $dataBuilder = new PayloadDataBuilder();

        $dataOptional['title']             = $subject;
        $dataOptional['body']              = $messages;

        $dataBuilder->addData($dataOptional);

        // build semua
        $option       = $optionBuiler->build();
        $notification = $notificationBuilder->build();
        $data         = $dataBuilder->build();
        return $data;
        $downstreamResponse = FCM::sendTo($tokens, $option, $notification, $data);

        $success = $downstreamResponse->numberSuccess();
        $fail    = $downstreamResponse->numberFailure();

        if ($fail != 0) {
            $error = $downstreamResponse->tokensWithError();
        }

        $downstreamResponse->tokensToDelete();
        $downstreamResponse->tokensToModify();
        $downstreamResponse->tokensToRetry();

        $result = [
            'success' => $success,
            'fail'    => $fail
        ];

        return $result;
    }
}
