<?php

namespace Modules\Autocrm\Http\Controllers;

use App\Http\Models\LogBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Http\Models\Autocrm;
use App\Http\Models\AutocrmRule;
use App\Http\Models\User;
use App\Http\Models\TextReplace;
use App\Http\Models\AutocrmEmailLog;
use App\Http\Models\AutocrmSmsLog;
use App\Http\Models\AutocrmPushLog;
use App\Http\Models\AutocrmWhatsappLog;
use App\Http\Models\AutocrmWhatsappLogContent;
use App\Http\Models\WhatsappContent;
use App\Http\Models\UserInbox;
use App\Http\Models\Setting;
use App\Http\Models\News;
use App\Http\Models\UsersMembership;
use App\Http\Models\OauthAccessToken;
use App\Http\Models\UserOutlet;
use App\Http\Models\Outlet;
use App\Lib\MyHelper;
use App\Lib\PushNotificationHelper;
use App\Lib\ClassTexterSMS;
use App\Lib\ClassMaskingJson;
use App\Lib\ClassJatisSMS;
use App\Lib\Apiwha;
use App\Lib\ValueFirst;
use Modules\Franchise\Entities\UserFranchise;
use Modules\Franchise\Entities\FranchiseEmailLog;
use Modules\Doctor\Entities\Doctor;
use Modules\Doctor\Entities\DoctorInbox;
use Modules\Merchant\Entities\Merchant;
use Modules\Merchant\Entities\MerchantInbox;
use Validator;
use Hash;
use DB;
use App\Http\Models\Notification;
//use App\Lib\SendMail as Mail;
use Illuminate\Support\Facades\Mail;

class ApiAutoCrm extends Controller
{
    public $Sms;
    private $textersms;

    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->textersms = new ClassTexterSMS();
        $this->rajasms = new ClassMaskingJson();
        $this->jatissms = new ClassJatisSMS();
        $this->Apiwha = new Apiwha();
    }

    public function SendAutoCRM($autocrm_title, $receipient, $variables = null, $useragent = null, $forward_only = false, $outlet = false, $recipient_type = null, $franchise = null, $save_log = true, $otp_type = null, $doctor = null)
    {
        $query = Autocrm::where('autocrm_title', '=', $autocrm_title)->with('whatsapp_content')->get()->toArray();
        //get recepient type
        if (!isset($recipient_type)) {
            if ($franchise) {
                $users = UserFranchise::select('id_user_franchise as id', 'user_franchises.*')->where('username', '=', $receipient)->get()->toArray();
            } elseif ($outlet) {
                $users = UserOutlet::select('id_user_outlet as id', 'user_outlets.*')->where('phone', '=', $receipient)->get()->toArray();
            } elseif ($doctor) {
                $users = Doctor::select('id_doctor as id', 'doctors.*')->where('doctor_phone', '=', $receipient)->get()->toArray();
            } else {
                $users = User::where('phone', '=', $receipient)->get()->toArray();
            }
        } else {
            if ($recipient_type == 'outlet' || $recipient_type == 'outlet_franchise') {
                // auto response for outlet is email only, therefore recipient is email
                $users = [[
                    'email' => $receipient,
                    'name'  => ""
                ]];

                $query[0]['autocrm_email_subject'] = MyHelper::simpleReplace($query[0]['autocrm_email_subject'], $variables);
                $query[0]['autocrm_email_content'] = MyHelper::simpleReplace($query[0]['autocrm_email_content'], $variables);
                $query[0]['autocrm_forward_email_subject'] = MyHelper::simpleReplace($query[0]['autocrm_forward_email_subject'], $variables);
                $query[0]['autocrm_forward_email_content'] = MyHelper::simpleReplace($query[0]['autocrm_forward_email_content'], $variables);
            } elseif ($recipient_type == 'franchise') {
                $users = UserFranchise::select('id_user_franchise as id', 'user_franchises.*')->where('username', '=', $receipient)->get()->toArray();
            } elseif ($recipient_type == 'doctor') {
                $users = Doctor::select('id_doctor as id', 'doctors.*')->where('doctor_phone', '=', $receipient)->get()->toArray();
            } elseif ($recipient_type == 'merchant') {
               
                $users = Merchant::join('users', 'users.id', 'merchants.id_user')
                    ->join('outlets', 'outlets.id_outlet', 'merchants.id_outlet')
                    ->where('id_merchant', $receipient)
                    ->select('users.phone as phone', 'merchants.*', 'merchants.id_merchant as id', 'outlets.*', 'outlets.outlet_email as email', 'outlets.outlet_name as name')
                    ->get()->toArray();
            }
        }

        if (empty($users)) {
            return true;
        }
        
        if ($query) {
            $crm    = $query[0];
            $user   = $users[0];
            if ($recipient_type == 'doctor') {
                $user['id'] = $user['id'];
                $user['name'] = $user['doctor_name'];
                $user['phone'] = $user['doctor_phone'];
            }
            $usermessage  = User::where('id','=', $user['id_user']??$user['id'])->first();
            $content = $this->TextReplace($crm['autocrm_email_content'], $receipient, $variables, null, 0, $recipient_type);
            $notif = Notification::triggerCreate($usermessage,$autocrm_title,$content,$usermessage['id']);
            if ($crm['autocrm_email_toogle'] == 1 && !$forward_only) {
                if (!empty($user['email'])) {
                    if ($user['name'] != "") {
                        $name    = "";
                    } else {
                        $name    = $user['name'];
                    }

                    $to      = $user['email'];

                    $subject = $this->TextReplace($crm['autocrm_email_subject'], $receipient, $variables, null, $franchise, $recipient_type);

                    $content = $this->TextReplace($crm['autocrm_email_content'], $receipient, $variables, null, $franchise, $recipient_type);
                    //get setting email
                    $getSetting = Setting::where('key', 'LIKE', 'email%')->get()->toArray();
                    $setting = array();
                    foreach ($getSetting as $key => $value) {
                        if ($value['key'] == 'email_setting_url') {
                            $setting[$value['key']]  = (array)json_decode($value['value_text']);
                        } else {
                            $setting[$value['key']] = $value['value'];
                        }
                    }

                    $data = array(
                        'customer' => $name,
                        'html_message' => $content,
                        'setting' => $setting
                    );

                    if ($autocrm_title == 'Transaction Success') {
                        try {
                            Mail::send('emails.test2', $data, function ($message) use ($to, $subject, $name, $setting, $variables) {

                                if (stristr($to, 'gmail.con')) {
                                    $to = str_replace('gmail.con', 'gmail.com', $to);
                                }

                                $message->to($to, $name)->subject($subject);

                                if (!empty($setting['email_from']) && !empty($setting['email_sender'])) {
                                    $message->from($setting['email_sender'], $setting['email_from']);
                                } elseif (!empty($setting['email_sender'])) {
                                    $message->from($setting['email_sender']);
                                }

                                if (!empty($setting['email_reply_to']) && !empty($setting['email_reply_to_name'])) {
                                    $message->replyTo($setting['email_reply_to'], $setting['email_reply_to_name']);
                                } elseif (!empty($setting['email_reply_to'])) {
                                    $message->replyTo($setting['email_reply_to']);
                                }

                                if (!empty($setting['email_cc']) && !empty($setting['email_cc_name'])) {
                                    $message->cc($setting['email_cc'], $setting['email_cc_name']);
                                }

                                if (!empty($setting['email_bcc']) && !empty($setting['email_bcc_name'])) {
                                    $message->bcc($setting['email_bcc'], $setting['email_bcc_name']);
                                }

                                // attachment
                                if (isset($variables['attachment'])) {
                                    if (is_array($variables['attachment'])) {
                                        foreach ($variables['attachment'] as $attach) {
                                            $message->attach($attach);
                                        }
                                    } else {
                                        $message->attach($variables['attachment']);
                                    }
                                }
                            });
                        } catch (\Exception $e) {
                            \Log::error($e);
                        }
                    } else {
                        try {
                            Mail::send('emails.test', $data, function ($message) use ($to, $subject, $name, $setting, $variables, $autocrm_title, $crm) {
                                $message->to($to, $name)->subject($subject);
                                if (!empty($setting['email_from']) && !empty($setting['email_sender'])) {
                                    $message->from($setting['email_sender'], $setting['email_from']);
                                } elseif (!empty($setting['email_sender'])) {
                                    $message->from($setting['email_sender']);
                                }

                                if (!empty($setting['email_reply_to']) && !empty($setting['email_reply_to_name'])) {
                                    $message->replyTo($setting['email_reply_to'], $setting['email_reply_to_name']);
                                } elseif (!empty($setting['email_reply_to'])) {
                                    $message->replyTo($setting['email_reply_to']);
                                }

                                if (!empty($setting['email_cc']) && !empty($setting['email_cc_name'])) {
                                    $message->cc($setting['email_cc'], $setting['email_cc_name']);
                                }

                                if (!empty($setting['email_bcc']) && !empty($setting['email_bcc_name'])) {
                                    $message->bcc($setting['email_bcc'], $setting['email_bcc_name']);
                                }

                                // attachment
                                if (
                                    (isset($variables['attachment']) && !(stristr($autocrm_title, 'nquiry'))) ||
                                    ((stristr($autocrm_title, 'nquiry') && $crm['attachment_mail'] == 1))
                                ) {
                                    if (is_array($variables['attachment'])) {
                                        foreach ($variables['attachment'] as $attach) {
                                            $message->attach($attach);
                                        }
                                    } else {
                                        $message->attach($variables['attachment']);
                                    }
                                }
                            });
                        } catch (\Exception $e) {
                        }
                    }

                    if ($save_log) {
                        if ($recipient_type == 'outlet_franchise') {
                            $logData = [];
                            $logData['id_outlet'] = $variables['id_outlet'];
                            $logData['email_log_to'] = $user['email'];
                            $logData['email_log_subject'] = $subject;
                            $logData['email_log_message'] = $content;

                            $logs = FranchiseEmailLog::create($logData);
                        } else {
                            $logData = [];
                            $logData['id_user'] = $user['id'];
                            $logData['email_log_to'] = $user['email'];
                            $logData['email_log_subject'] = $subject;
                            $logData['email_log_message'] = $content;

                            if (!empty($recipient_type)) {
                                $logData['user_type'] = $recipient_type;
                            }

                            $logs = AutocrmEmailLog::create($logData);
                        }
                    }
                }
            }

            if ($crm['autocrm_forward_toogle'] == 1) {
                if (!empty($crm['autocrm_forward_email'])) {
                    $exparr = explode(';', str_replace(',', ';', $crm['autocrm_forward_email']));
                    foreach ($exparr as $email) {
                        $n   = explode('@', $email);
                        $name = $n[0];

                        $to      = $email;
                        $subject = $this->TextReplace($crm['autocrm_forward_email_subject'], $receipient, $variables, null, 0, $recipient_type);

                        $content = $this->TextReplace($crm['autocrm_forward_email_content'], $receipient, $variables, null, 0, $recipient_type);

                        // get setting email
                        $getSetting = Setting::where('key', 'LIKE', 'email%')->get()->toArray();
                        $setting = array();
                        foreach ($getSetting as $key => $value) {
                            if ($value['key'] == 'email_setting_url') {
                                $setting[$value['key']]  = (array)json_decode($value['value_text']);
                            } else {
                                $setting[$value['key']] = $value['value'];
                            }
                        }

                        $data = array(
                            'customer' => $name,
                            'html_message' => $content,
                            'setting' => $setting
                        );
                        try {
                            Mail::send('emails.test', $data, function ($message) use ($to, $subject, $name, $setting, $autocrm_title, $variables, $crm) {
                                $message->to($to, $name)->subject($subject);
                                if (!empty($setting['email_from']) && !empty($setting['email_sender'])) {
                                    $message->from($setting['email_sender'], $setting['email_from']);
                                } elseif (!empty($setting['email_sender'])) {
                                    $message->from($setting['email_sender']);
                                }

                                if (!empty($setting['email_reply_to']) && !empty($setting['email_reply_to_name'])) {
                                    $message->replyTo($setting['email_reply_to'], $setting['email_reply_to_name']);
                                } elseif (!empty($setting['email_reply_to'])) {
                                    $message->replyTo($setting['email_reply_to']);
                                }

                                if (!empty($setting['email_cc']) && !empty($setting['email_cc_name'])) {
                                    $message->cc($setting['email_cc'], $setting['email_cc_name']);
                                }

                                if (!empty($setting['email_bcc']) && !empty($setting['email_bcc_name'])) {
                                    $message->bcc($setting['email_bcc'], $setting['email_bcc_name']);
                                }

                                // attachment
                                if ((stristr($autocrm_title, 'nquiry') && $crm['attachment_forward'] == 1) || isset($variables['attachment'])) {
                                    if (is_array($variables['attachment'])) {
                                        foreach ($variables['attachment'] as $attach) {
                                            if (is_array($attach)) {
                                                $message->attach(...$attach);
                                            } else {
                                                $message->attach($attach);
                                            }
                                        }
                                    } else {
                                        $message->attach($variables['attachment']);
                                    }
                                }
                            });
                        } catch (\Exception $e) {
                        }

                        if ($save_log) {
                            if ($recipient_type == 'outlet_franchise') {
                                $logData = [];
                                $logData['id_outlet'] = $variables['id_outlet'];
                                $logData['email_log_to'] = $email;
                                $logData['email_log_subject'] = $subject;
                                $logData['email_log_message'] = $content;

                                $logs = FranchiseEmailLog::create($logData);
                            } else {
                                $logData = [];
                                $logData['id_user'] = $user['id'];
                                $logData['user_type'] = 'user';
                                $logData['email_log_to'] = $email;
                                $logData['email_log_subject'] = $subject;
                                $logData['email_log_message'] = $content;

                                if (!empty($recipient_type)) {
                                    $logData['user_type'] = $recipient_type;
                                }

                                $logs = AutocrmEmailLog::create($logData);
                            }
                        }
                    }
                }
            }

            if ($crm['autocrm_sms_toogle'] == 1 && !$forward_only && (is_null($otp_type) || $otp_type == 'sms')) {
                if (!empty($user['phone'])) {
                    //input env to log
                    $gateway = env('SMS_GATEWAY');

                    if (env('OTP_TYPE') == 'MISSCALL') {
                        $gateway = env('MISSCALL_GATEWAY');
                    } else {
                        if (in_array($autocrm_title, ['Pin Sent', 'Pin Forgot'])) {
                            // if user not 0 and even, send using alternative
                            if ($user['sms_increment'] % 2) {
                                $gateway = env('SMS_GATEWAY_ALT');
                            }
                            User::where('id', $user['id'])->update(['sms_increment' => $user['sms_increment'] + 1]);
                        }

                        if (in_array($autocrm_title, ['Doctor Pin Sent'])) {
                            // if doctor not 0 and even, send using alternative
                            if ($user['sms_increment'] % 2) {
                                $gateway = env('SMS_GATEWAY_ALT');
                            }
                            Doctor::where('id_doctor', $user['id'])->update(['sms_increment' => $user['sms_increment'] + 1]);
                        }
                    }

                    switch ($gateway) {
                        case 'Jatis':
                            $senddata = [
                                'userid'    => env('SMS_USER'),
                                'password'  => env('SMS_PASSWORD'),
                                'msisdn'    => '62' . substr($user['phone'], 1),
                                'sender'    => env('SMS_SENDER'),
                                'division'  => env('SMS_DIVISION'),
                                'batchname' => env('SMS_BATCHNAME'),
                                'uploadby'  => env('SMS_UPLOADBY')
                            ];

                            if ($crm['autocrm_title'] == 'Pin Sent' || $crm['autocrm_title'] == 'Pin Forgot' || $crm['autocrm_title'] == 'Doctor Pin Sent') {
                                if ($useragent && $useragent == "Android") {
                                    $crm['autocrm_sms_content'] = '<#> ' . $crm['autocrm_sms_content'] . ' ' . ENV('HASH_KEY_' . ENV('HASH_KEY_TYPE'));
                                }
                                $senddata['message']    = $this->TextReplace($crm['autocrm_sms_content'], $user['phone'], $variables, null, 0, $recipient_type);
                                $senddata['channel']    = 2;
                            } else {
                                $senddata['message']    = $this->TextReplace($crm['autocrm_sms_content'], $user['phone'], $variables, null, 0, $recipient_type);
                                $senddata['channel']    = env('SMS_CHANNEL');
                            }

                            $this->jatissms->setData($senddata);
                            $send = $this->jatissms->send();

                            break;
                        case 'RajaSMS':
                            $senddata = array(
                                'apikey' => env('SMS_KEY'),
                                'callbackurl' => config('url.app_url'),
                                'datapacket' => array()
                            );

                            //add <#> and Hash Key in pin sms content
                            if ($crm['autocrm_title'] == 'Pin Sent' || $crm['autocrm_title'] == 'Pin Forgot' || $crm['autocrm_title'] == 'Doctor Pin Sent') {
                                if ($useragent && $useragent == "Android") {
                                    $crm['autocrm_sms_content'] = '<#> ' . $crm['autocrm_sms_content'] . ' ' . ENV('HASH_KEY_' . ENV('HASH_KEY_TYPE'));
                                }
                            }

                            $content    = $this->TextReplace($crm['autocrm_sms_content'], $user['phone'], $variables, null, 0, $recipient_type);
                            array_push($senddata['datapacket'], array(
                                    'number' => trim($user['phone']),
                                    'message' => urlencode(stripslashes(utf8_encode($content))),
                                    'sendingdatetime' => ""));

                            $this->rajasms->setData($senddata);
                            $send = $this->rajasms->send();
                            break;
                        case 'ValueFirst':
                            if ($crm['autocrm_title'] == 'Pin Sent' || $crm['autocrm_title'] == 'Pin Forgot' || $crm['autocrm_title'] == 'Doctor Pin Sent') {
                                if ($useragent && $useragent == "Android") {
                                    $crm['autocrm_sms_content'] = '<#> ' . $crm['autocrm_sms_content'] . ' ' . ENV('HASH_KEY_' . ENV('HASH_KEY_TYPE'));
                                }
                                $content    = $this->TextReplace($crm['autocrm_sms_content'], $user['phone'], $variables, null, 0, $recipient_type);
                            } else {
                                $content    = $this->TextReplace($crm['autocrm_sms_content'], $user['phone'], $variables, null, 0, $recipient_type);
                            }

                            $sendData = [
                                'to' => trim($user['phone']),
                                'text' => $content
                            ];

                            ValueFirst::create()->send($sendData);
                            break;
                        case 'SMS114':
                            $senddata = array(
                                'apikey' => env('SMS114_API_KEY'),
                                'callbackurl' => env('SMS114_URL_CALLBACK'),
                                'datapacket' => array()
                            );

                            //add <#> and Hash Key in pin sms content
                            if ($crm['autocrm_title'] == 'Pin Sent' || $crm['autocrm_title'] == 'Pin Forgot' || $crm['autocrm_title'] == 'Doctor Pin Sent') {
                                if ($useragent && $useragent == "Android") {
                                    $crm['autocrm_sms_content'] = '<#> ' . $crm['autocrm_sms_content'] . ' ' . ENV('HASH_KEY_' . ENV('HASH_KEY_TYPE'));
                                }
                            }
                            $content    = $this->TextReplace($crm['autocrm_sms_content'], $user['phone'], $variables, null, 0, $recipient_type);
                            array_push($senddata['datapacket'], array(
                                'number' => trim($user['phone']),
                                'otp' => $variables['pin'],
                                'message' => urlencode(stripslashes(utf8_encode($content))),
                                'sendingdatetime' => ""));

                            $this->rajasms->setData($senddata);
                            $send = $this->rajasms->sendSMS();
                            break;
                        default:
                            $senddata = array(
                                'apikey' => env('SMS_KEY'),
                                'callbackurl' => config('url.app_url'),
                                'datapacket' => array()
                            );

                            //add <#> and Hash Key in pin sms content
                            if ($crm['autocrm_title'] == 'Pin Sent' || $crm['autocrm_title'] == 'Pin Forgot' || $crm['autocrm_title'] == 'Doctor Pin Sent') {
                                if ($useragent && $useragent == "Android") {
                                    $crm['autocrm_sms_content'] = '<#> ' . $crm['autocrm_sms_content'] . ' ' . ENV('HASH_KEY_' . ENV('HASH_KEY_TYPE'));
                                }
                            }

                            $content    = $this->TextReplace($crm['autocrm_sms_content'], $user['phone'], $variables, null, 0, $recipient_type);

                            array_push($senddata['datapacket'], array(
                                    'number' => trim($user['phone']),
                                    'message' => urlencode(stripslashes(utf8_encode($content))),
                                    'sendingdatetime' => ""));

                            $this->rajasms->setData($senddata);
                            $send = $this->rajasms->send();
                            break;
                    }
                    $content    = $this->TextReplace($crm['autocrm_sms_content'], $user['phone'], $variables, null, 0, $recipient_type);
                    $logData = [];
                    $logData['id_user'] = $user['id'];
                    $logData['sms_log_to'] = $user['phone'];
                    $logData['sms_log_content'] = $content;
                    $logData['user_type'] = 'user';

                    if (!empty($recipient_type)) {
                        $logData['user_type'] = $recipient_type;
                    }

                    $logs = AutocrmSmsLog::create($logData);
                }
            }

            if ($crm['autocrm_whatsapp_toogle'] == 1 && !$forward_only && (is_null($otp_type) || $otp_type == 'whatsapp')) {
                if (!empty($user['phone'])) {
                    //cek api key whatsapp
                    $api_key = env('TOKEN_WA');
                    if ($api_key) {
                        if ($api_key) {
                            $contentWaSent = [];
                            //send every content whatsapp
                            foreach ($crm['whatsapp_content'] as $contentWhatsapp) {
                            $content = $this->TextReplace($contentWhatsapp['content'], $user['phone'], $variables, null, 0, null);
                                // add country code in number
                                $ptn = "/^0/";
                                $rpltxt = "62";
                                $phone = preg_replace($ptn, $rpltxt, $user['phone']);
                                $url = '';
                                if($autocrm_title == "Merchant Transaction New"){
                                    $url = ENV('APP_API_URL_MITRA').'transaction/detail/'.$variables['receipt_number']??null;
                                }
//                                $send = $this->Apiwha->send($api_key, $phone, $content);
                               return $send = $this->Apiwha->send($api_key, $phone, $content,$url,$autocrm_title);

                                //api key whatsapp not valid
                                if (isset($send['result_code']) && $send['result_code'] == -1) {
                                    break 1;
                                }

                                $dataContent['content'] = $content;
                                $dataContent['content_type'] = $contentWhatsapp['content_type'];
                                array_push($contentWaSent, $dataContent);
                            }

                            if ($save_log) {
                                // insert to whatsapp log
                                $outbox = [];
                                if (isset($user['id'])) {
                                    $outbox['id_user'] = $user['id'];
                                }
                                $outbox['whatsapp_log_to'] = $user['phone'];
                                $outbox['user_type'] = 'user';

                                if (!empty($recipient_type)) {
                                    $logData['user_type'] = $recipient_type;
                                }
                                $logs = AutocrmWhatsappLog::create($outbox);

                                // insert to whatsapp log content
                                foreach ($contentWaSent as $data) {
                                    $dataContentWhatsapp['content'] = $data['content'];
                                    $dataContentWhatsapp['content_type'] = $data['content_type'];
                                    $dataContentWhatsapp['id_autocrm_whatsapp_log'] =  $logs['id_autocrm_whatsapp_log'];
                                    $create = AutocrmWhatsappLogContent::create($dataContentWhatsapp);
                                }
                            }
                        }
                    }
                }
            }

            if ($crm['autocrm_push_toogle'] == 1 && !$forward_only) {
                if ($recipient_type == 'merchant') {
                    $user['phone'] = $user['user_phone'] ?? null;
                }

                if (!empty($user['phone'])) {
                    try {
                        $dataOptional          = $variables['data_optional'] ?? [];
                        $image = null;
                        if (isset($crm['autocrm_push_image']) && $crm['autocrm_push_image'] != null) {
                            $dataOptional['image'] = config('url.storage_url_api') . $crm['autocrm_push_image'];
                            $image = config('url.storage_url_api') . $crm['autocrm_push_image'];
                        }

                        //======set id reference and type
                        switch ($crm['autocrm_push_clickto']) {
                            case 'No Action':
                                $dataOptional['type'] = 'Default';
                                $dataOptional['id_reference'] = 0;
                                break;
                            case 'News':
                                if (isset($variables['id_news'])) {
                                    $dataOptional['id_reference'] = $variables['id_news'];
                                } else {
                                    $dataOptional['id_reference'] = 0;
                                }
                                break;
                            case 'history_order_detail':
                            case 'history_consultation_detail ':
                            case 'history_order':
                            case 'History Transaction':
                                if ($crm['autocrm_push_clickto'] == 'History Transaction' && !empty($inboxFrom)) {
                                    $dataOptional['type'] = 'history_' . str_replace('-', '_', $inboxFrom);
                                }

                                $dataOptional['id_reference'] = (!empty($variables['id_transaction']) ? $variables['id_transaction'] : 0);
                                break;
                            case 'History Point':
                                if (isset($variables['id_log_balance'])) {
                                    $dataOptional['id_reference'] = $variables['id_log_balance'];
                                } else {
                                    $dataOptional['id_reference'] = 0;
                                }
                                break;
                            case 'Voucher':
                                if (isset($variables['id_deals_user'])) {
                                    $dataOptional['id_reference'] = $variables['id_deals_user'];
                                } else {
                                    $dataOptional['id_reference'] = 0;
                                }
                                break;
                            case 'History Point Quest':
                                if (isset($variables['id_log_balance'])) {
                                    $dataOptional['id_reference'] = $variables['id_log_balance'];
                                } else {
                                    $dataOptional['id_reference'] = 0;
                                }
                                break;
                            case 'Voucher Quest':
                                if (isset($variables['id_deals_user'])) {
                                    $dataOptional['id_reference'] = $variables['id_deals_user'];
                                } else {
                                    $dataOptional['id_reference'] = 0;
                                }
                                break;
                            case 'Deals':
                                if (isset($variables['id_deals'])) {
                                    $dataOptional['id_reference'] = $variables['id_deals'];
                                } else {
                                    $dataOptional['id_reference'] = 0;
                                }
                                break;
                            case 'Outlet':
                                if (isset($variables['id_outlet'])) {
                                    $dataOptional['id_reference'] = $variables['id_outlet'];
                                } else {
                                    $dataOptional['id_reference'] = 0;
                                }
                                break;
                            case 'Order':
                                if (isset($variables['id_outlet'])) {
                                    $dataOptional['id_reference'] = $variables['id_outlet'];
                                } else {
                                    $dataOptional['id_reference'] = 0;
                                }
                                break;
                            case 'Subscription':
                                if (isset($variables['id_subscription'])) {
                                    $dataOptional['id_reference'] = $variables['id_subscription'];
                                } else {
                                    $dataOptional['id_reference'] = 0;
                                }
                                break;
                            case 'Quest':
                                if (isset($variables['id_quest'])) {
                                    $dataOptional['id_reference'] = $variables['id_quest'];
                                } else {
                                    $dataOptional['id_reference'] = 0;
                                }
                                break;
                            case 'Home':
                                 $dataOptional['id_reference'] = 0;
                                break;
                            case 'Logout':
                                if (!empty($user['id'])) {
                                    OauthAccessToken::join('oauth_access_token_providers', 'oauth_access_tokens.id', 'oauth_access_token_providers.oauth_access_token_id')
                                        ->where('oauth_access_tokens.user_id', $user['id'])->where('oauth_access_token_providers.provider', 'users')->delete();
                                }
                                break;
                            case 'home_service_history':
                                $dataOptional['type'] = $variables['mitra_get_order_clickto'] ?? 'home_service_history';
                                break;
                            case 'doctor_home':
                                $dataOptional['type'] = $crm['autocrm_push_clickto'];
                                $dataOptional['id_reference'] = 0;
                                break;
                            case 'consultation_list_online':
                                $dataOptional['type'] = $crm['autocrm_push_clickto'];
                                $dataOptional['id_reference'] = 0;
                                break;
                            case 'consultation_list_scheduled':
                                $dataOptional['type'] = $crm['autocrm_push_clickto'];
                                $dataOptional['id_reference'] = 0;
                                break;
                            case 'consultation_list_completed':
                                $dataOptional['type'] = $crm['autocrm_push_clickto'];
                                $dataOptional['id_reference'] = 0;
                                break;
                            case 'doctor_profile':
                                $dataOptional['type'] = $crm['autocrm_push_clickto'];
                                $dataOptional['id_reference'] = 0;
                                break;
                            case 'doctor_profile_update_request':
                                $dataOptional['type'] = $crm['autocrm_push_clickto'];
                                $dataOptional['id_reference'] = 0;
                                break;
                            case 'doctor_rating_detail':
                                $dataOptional['type'] = $crm['autocrm_push_clickto'];
                                $dataOptional['id_reference'] = 0;
                                break;
                            case 'setting_detail':
                                $dataOptional['type'] = $crm['autocrm_push_clickto'];
                                $dataOptional['id_reference'] = 0;
                                break;
                            case 'doctor_privacy_policy':
                                $dataOptional['type'] = $crm['autocrm_push_clickto'];
                                $dataOptional['id_reference'] = 0;
                                break;
                            case 'doctor_faq':
                                $dataOptional['type'] = $crm['autocrm_push_clickto'];
                                $dataOptional['id_reference'] = 0;
                                break;
                            case 'doctor_contact_cs':
                                $dataOptional['type'] = $crm['autocrm_push_clickto'];
                                $dataOptional['id_reference'] = 0;
                                break;
                            case 'doctor_received_chat':
                                $dataOptional['type'] = $crm['autocrm_push_clickto'];
                                $dataOptional['id_reference'] = $variables['id_transaction'];
                                break;
                            case 'consultation_detail':
                                $dataOptional['type'] = $crm['autocrm_push_clickto'];
                                $dataOptional['id_reference'] = $variables['id_transaction'];
                                break;
                            case 'consultation_done':
                                $dataOptional['type'] = $crm['autocrm_push_clickto'];
                                $dataOptional['id_reference'] = $variables['id_transaction'];
                                break;
                            case 'consultation_chat':
                                $dataOptional['type'] = $crm['autocrm_push_clickto'];
                                $dataOptional['id_reference'] = $variables['id_transaction'];
                                break;
                            case 'consultation_summary':
                                $dataOptional['type'] = $crm['autocrm_push_clickto'];
                                $dataOptional['id_reference'] = $variables['id_transaction'];
                                break;
                            case 'consultation_product_recomendation':
                                $dataOptional['type'] = $crm['autocrm_push_clickto'];
                                $dataOptional['id_reference'] = $variables['id_transaction'];
                                break;
                            case 'consultation_prescription':
                                $dataOptional['type'] = $crm['autocrm_push_clickto'];
                                $dataOptional['id_reference'] = $variables['id_transaction'];
                                break;
                            default:
                                $dataOptional['type'] = $crm['autocrm_push_clickto'];
                                $dataOptional['id_reference'] = 0;
                                break;
                        }

                        if (isset($crm['autocrm_push_link']) && $crm['autocrm_push_link'] != null) {
                            if ($dataOptional['type'] == 'Link') {
                                $dataOptional['link'] = $crm['autocrm_push_link'];
                            } else {
                                $dataOptional['link'] = null;
                            }
                        } else {
                            $dataOptional['link'] = null;
                        }

                        if (isset($crm['autocrm_push_id_reference']) && $crm['autocrm_push_id_reference'] != null) {
                            $dataOptional['id_reference'] = (int)$crm['autocrm_push_id_reference'];
                        }

                        if (isset($variables['notif_type'])) {
                            $dataOptional['notif_type'] = $variables['notif_type'];
                        }

                        if (isset($variables['total_payment'])) {
                            $dataOptional['push_type'] = 'Payment Success';
                            $dataOptional['total_revenue'] = $variables['total_payment'];
                        }

                        if (isset($variables['header_label'])) {
                            $dataOptional['header_label'] = $variables['header_label'];
                        }

                        $deviceToken = PushNotificationHelper::searchDeviceToken("phone", $user['phone'], $recipient_type);
                        // print_r($deviceToken);exit;
                        $subject = $this->TextReplace($crm['autocrm_push_subject'], $receipient, $variables, null, 0, $recipient_type);
                        $content = $this->TextReplace($crm['autocrm_push_content'], $receipient, $variables, null, 0, $recipient_type);
                        $deviceToken = PushNotificationHelper::searchDeviceToken("phone", $user['phone'], $recipient_type);

                        if (!empty($deviceToken)) {
                            if (isset($deviceToken['token']) && !empty($deviceToken['token'])) {
                                $push = PushNotificationHelper::sendPush($deviceToken['token'], $subject, $content, $image, $dataOptional);

                                if (isset($push['success']) && $push['success'] > 0) {
                                    $logData = [];
                                    $logData['id_user'] = $user['id'];
                                    $logData['push_log_to'] = $user['phone'];
                                    $logData['push_log_subject'] = $subject;
                                    $logData['push_log_content'] = $content;
                                    $logData['user_type'] = 'user';

                                    if (!empty($recipient_type)) {
                                        $logData['user_type'] = $recipient_type;
                                    }

                                    $logs = AutocrmPushLog::create($logData);
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        return response()->json(MyHelper::throwError($e));
                    }
                }
            }

            if ($crm['autocrm_inbox_toogle'] == 1 && !$forward_only) {
                if (!empty($user['id'])) {
                    if ($recipient_type == 'merchant') {
                        $inboxTable = new MerchantInbox();
                        $inboxRecipient = $receipient;
                        $inboxWherefield = null;

                        $inbox['id_merchant'] = $user['id'];
                    } elseif ($recipient_type == 'doctor') {
                        $inboxTable = new DoctorInbox();
                        $inboxRecipient = $receipient;
                        $inboxWherefield = null;

                        $inbox['id_doctor'] = $user['id'];
                    } else {
                        $inboxTable = new UserInbox();
                        $inboxRecipient = $user['id'];
                        $inboxWherefield = 'id';

                        $inbox['id_user'] = $user['id'];
                    }

                    $inbox['inboxes_subject'] = $this->TextReplace($crm['autocrm_inbox_subject'], $inboxRecipient, $variables, $inboxWherefield, 0, $recipient_type);
                    $inbox['inboxes_clickto'] = $crm['autocrm_inbox_clickto'];
                    $inbox['inboxes_content'] = $this->TextReplace($crm['autocrm_inbox_content'], $inboxRecipient, $variables, $inboxWherefield, 0, $recipient_type);

                    if ($crm['autocrm_inbox_clickto'] == 'Link') {
                        $inbox['inboxes_link'] = $crm['autocrm_inbox_link'];
                    }

                    //===== set id reference and click to
                    switch ($crm['autocrm_inbox_clickto']) {
                        case "News":
                            if (isset($variables['id_news'])) {
                                $inbox['inboxes_id_reference'] = $variables['id_news'];
                            } else {
                                $inbox['inboxes_id_reference'] = 0;
                            }
                            break;
                        case 'history_order_detail':
                        case 'history_consultation_detail ':
                        case 'history_order':
                        case 'History Transaction':
                            if ($crm['autocrm_inbox_clickto'] == 'History Transaction' && !empty($inboxFrom)) {
                                $inbox['inboxes_clickto'] = 'history_' . str_replace('-', '_', $inboxFrom);
                            }

                            $inbox['inboxes_id_reference'] = (!empty($variables['id_transaction']) ? $variables['id_transaction'] : 0);
                            break;
                        case 'History Point':
                            if (isset($variables['id_log_balance'])) {
                                $inbox['inboxes_id_reference'] = $variables['id_log_balance'];
                            } else {
                                $inbox['inboxes_id_reference'] = 0;
                            }
                            break;
                        case 'Voucher':
                            if (isset($variables['id_deals_user'])) {
                                $inbox['inboxes_id_reference'] = $variables['id_deals_user'];
                            } else {
                                $inbox['inboxes_id_reference'] = 0;
                            }
                            break;
                        case 'Deals':
                            if (isset($variables['id_deals'])) {
                                $inbox['inboxes_id_reference'] = $variables['id_deals'];
                            } else {
                                $inbox['inboxes_id_reference'] = 0;
                            }
                            break;
                        case 'Subscription':
                            if (isset($variables['id_subscription'])) {
                                $inbox['inboxes_id_reference'] = $variables['id_subscription'];
                            } else {
                                $inbox['inboxes_id_reference'] = 0;
                            }
                            break;
                        case 'Quest':
                            if (isset($variables['id_quest'])) {
                                $inbox['inboxes_id_reference'] = $variables['id_quest'];
                            } else {
                                $inbox['inboxes_id_reference'] = 0;
                            }
                            break;
                        case 'Home':
                            $inbox['inboxes_id_reference'] = 0;
                            break;
                        case 'home_service_history':
                            $inbox['inboxes_clickto'] = $variables['mitra_get_order_clickto'] ?? 'home_service_history';
                            break;
                        default:
                            $inbox['inboxes_clickto'] = 'Default';
                            $inbox['inboxes_id_reference'] = 0;
                            break;
                    }

                    if (isset($crm['autocrm_inbox_id_reference']) && $crm['autocrm_inbox_id_reference'] != null) {
                        $inbox['inboxes_id_reference'] = (int)$crm['autocrm_inbox_id_reference'];
                    }

                    $inbox['inboxes_send_at'] = date("Y-m-d H:i:s");
                    $inbox['created_at'] = date("Y-m-d H:i:s");
                    $inbox['updated_at'] = date("Y-m-d H:i:s");

                    $inboxQuery = $inboxTable::insert($inbox);
                }
            }
            return "true";
        } else {
            return "false";
        }
    }

    public function listTextReplace(Request $request, $var = null)
    {
        if ($var != null) {
            $query = TextReplace::get()->toArray();
        } else {
            $query = TextReplace::where('reference', '!=', 'variables')->where('status', '=', 'Activated')->get()->toArray();
        }
        return response()->json(MyHelper::checkGet($query));
    }

    public function updateTextReplace(Request $request)
    {
        $post = $request->json()->all();
        $id_text_replace = $post['id_text_replace'];
        unset($post['id_text_replace']);
        $query = TextReplace::where('id_text_replace', '=', $id_text_replace)->update($post);

        return response()->json(MyHelper::checkUpdate($query));
    }

    public function TextReplace($text, $receipient, $variables = null, $wherefield = null, $franchise = 0, $r_type = null)
    {
        $query = TextReplace::where('status', '=', 'Activated')->get()->toArray();

        if ($franchise) {
            $user = UserFranchise::select('id_user_franchise as id', 'user_franchises.*')->where('username', '=', $receipient)->get()->first();
        } elseif ($r_type == 'merchant') {
            $user = Merchant::join('outlets', 'outlets.id_outlet', 'merchants.id_outlet')
                ->where('id_merchant', $receipient)
                ->select('merchants.*', 'merchants.id_merchant as id', 'outlets.*', 'outlets.outlet_phone as phone', 'outlets.outlet_email as email', 'outlets.outlet_name as name')
                ->get()->first();
        } elseif ($r_type == 'doctor') {
            $user = Doctor::select('id_doctor as id', 'doctors.*')->where('doctor_phone', '=', $receipient)->get()->first();
        } else {
            if ($wherefield != null) {
                $user = User::leftJoin('cities', 'cities.id_city', '=', 'users.id_city')
                    ->leftJoin('provinces', 'cities.id_province', '=', 'provinces.id_province')
                    ->where($wherefield, '=', $receipient)
                    ->get()
                    ->first();
            } else {
                $user = User::leftJoin('cities', 'cities.id_city', '=', 'users.id_city')
                    ->leftJoin('provinces', 'cities.id_province', '=', 'provinces.id_province')
                    ->where('phone', '=', $receipient)
                    ->get()
                    ->first();
            }
        }

        if ($user) {
            //add - to pin
            if (isset($variables['pin'])) {
                $variables['pin'] = substr($variables['pin'], 0, 3) . '-' . substr($variables['pin'], 3, 3);
            }

            if (isset($variables['password'])) {
                $variables['pin'] = $variables['password'];
            }

            //add numeric separator to point
            if (isset($variables['received_point'])) {
                $variables['received_point'] = MyHelper::requestNumber($variables['received_point'], '_POINT');
            }

            foreach ($query as $replace) {
                $replaced = "";
                if ($replace['type'] == 'String') {
                    if ($replace['reference'] == 'variables') {
                        if (isset($variables[str_replace('%', '', $replace['keyword'])])) {
                            $replaced = $variables[str_replace('%', '', $replace['keyword'])];
                        } else {
                            $replaced = $replace['default_value'];
                        }
                    } else {
                        if ($user[$replace['reference']] != "") {
                            if ($replace['reference'] == 'name') {
                                $replaced = ucwords($user[$replace['reference']]);
                            } else {
                                $replaced = $user[$replace['reference']];
                            }
                        } else {
                            $replaced = $replace['default_value'];
                        }
                    }
                }

                if ($replace['type'] == 'Alias') {
                    if ($replace['reference'] == 'variables') {
                        if (isset($variables[$replace['reference']])) {
                            if ($replace['custom_rule'] != "") {
                                $ruleexp = explode(";", $replace['custom_rule']);
                                if ($ruleexp) {
                                    foreach ($ruleexp as $exp) {
                                        $customruleexp = explode("=", $exp);
                                        if ($customruleexp[0] == $variables[$replace['reference']]) {
                                            $replaced = $customruleexp[1];
                                        }
                                    }
                                } else {
                                    $replaced = $variables[$replace['reference']];
                                }
                            } else {
                                $replaced = $variables[$replace['reference']];
                            }
                        } else {
                            if ($replace['custom_rule'] != "") {
                                $ruleexp = explode(";", $replace['custom_rule']);
                                if ($ruleexp) {
                                    foreach ($ruleexp as $exp) {
                                        $customruleexp = explode("=", $exp);
                                        if ($customruleexp[0] == $replace['default_value']) {
                                            $replaced = $customruleexp[1];
                                        }
                                    }
                                } else {
                                    $replaced = $replace['default_value'];
                                }
                            } else {
                                $replaced = $replace['default_value'];
                            }
                        }
                    } else {
                        if ($user[$replace['reference']] != "") {
                            if ($replace['custom_rule'] != "") {
                                $ruleexp = explode(";", $replace['custom_rule']);
                                if ($ruleexp) {
                                    foreach ($ruleexp as $exp) {
                                        $customruleexp = explode("=", $exp);
                                        if ($customruleexp[0] == $user[$replace['reference']]) {
                                            $replaced = $customruleexp[1];
                                        }
                                    }
                                } else {
                                    $replaced = $user[$replace['reference']];
                                }
                            } else {
                                $replaced = $user[$replace['reference']];
                            }
                        } else {
                            if ($replace['custom_rule'] != "") {
                                $ruleexp = explode(";", $replace['custom_rule']);
                                if ($ruleexp) {
                                    foreach ($ruleexp as $exp) {
                                        $customruleexp = explode("=", $exp);
                                        if ($customruleexp[0] == $user[$replace['reference']]) {
                                            $replaced = $customruleexp[1];
                                        }
                                    }
                                } else {
                                    $replaced = $replace['default_value'];
                                }
                            } else {
                                $replaced = $replace['default_value'];
                            }
                        }
                    }
                }

                if ($replace['type'] == 'Date') {
                    if ($replace['reference'] == 'variables') {
                        if (isset($variables[$replace['reference']])) {
                            if ($replace['custom_rule'] != "") {
                                $replaced = date($replace['custom_rule'], strtotime($variables[$replace['reference']]));
                            } else {
                                $replaced = date('Y-m-d', strtotime($variables[$replace['reference']]));
                            }
                        } else {
                            if ($replace['custom_rule'] != "") {
                                $replaced = date($replace['custom_rule'], strtotime($replace['default_value']));
                            } else {
                                $replaced = date('Y-m-d', strtotime($replace['default_value']));
                            }
                        }
                    } else {
                        if ($user[$replace['reference']] != "") {
                            if ($replace['custom_rule'] != "") {
                                $replaced = date($replace['custom_rule'], strtotime($user[$replace['reference']]));
                            } else {
                                $replaced = date('Y-m-d', strtotime($user[$replace['reference']]));
                            }
                        } else {
                            if ($replace['custom_rule'] != "") {
                                $replaced = date($replace['custom_rule'], strtotime(date('Y-m-d')));
                            } else {
                                $replaced = date('Y-m-d', strtotime($replace['default_value']));
                            }
                        }
                    }
                }

                if ($replace['type'] == 'DateTime') {
                    if ($replace['reference'] == 'variables') {
                        if (isset($variables[$replace['reference']])) {
                            if ($replace['custom_rule'] != "") {
                                $replaced = date($replace['custom_rule'], strtotime($variables[$replace['reference']]));
                            } else {
                                $replaced = date('Y-m-d H:i', strtotime($variables[$replace['reference']]));
                            }
                        } else {
                            if ($replace['custom_rule'] != "") {
                                $replaced = date($replace['custom_rule'], strtotime(date('Y-m-d H:i:s')));
                            } else {
                                $replaced = date('Y-m-d H:i', strtotime($replace['default_value']));
                            }
                        }
                    } else {
                        if ($user[$replace['reference']] != "") {
                            if ($replace['custom_rule'] != "") {
                                $replaced = date($replace['custom_rule'], strtotime($user[$replace['reference']]));
                            } else {
                                $replaced = date('Y-m-d H:i', strtotime($user[$replace['reference']]));
                            }
                        } else {
                            if ($replace['custom_rule'] != "") {
                                $replaced = date($replace['custom_rule'], strtotime(date('Y-m-d H:i:s')));
                            } else {
                                $replaced = date('Y-m-d H:i', strtotime($replace['default_value']));
                            }
                        }
                    }
                }

                if ($replace['type'] == 'Currency') {
                    if ($replace['reference'] == 'variables') {
                        if (isset($variables[$replace['reference']])) {
                            if ($replace['custom_rule'] != "") {
                                $replaced = $replace['custom_rule'] . " " . number_format($variables[$replace['reference']], 0, ',', '.');
                            } else {
                                $replaced = number_format($replace['reference'], 0, ',', '.');
                            }
                        } else {
                            if ($replace['custom_rule'] != "") {
                                $replaced = $replace['custom_rule'] . " " . number_format($replace['default_value'], 0, ',', '.');
                            } else {
                                $replaced = number_format($replace['default_value'], 0, ',', '.');
                            }
                        }
                    } else {
                        if ($user[$replace['reference']] != "") {
                            if ($replace['custom_rule'] != "") {
                                $replaced = $replace['custom_rule'] . " " . number_format($user[$replace['reference']], 0, ',', '.');
                            } else {
                                $replaced = number_format($user[$replace['reference']], 0, ',', '.');
                            }
                        } else {
                            if ($replace['custom_rule'] != "") {
                                $replaced = $replace['custom_rule'] . " " . number_format($replace['default_value'], 0, ',', '.');
                            } else {
                                $replaced = number_format($replace['default_value'], 0, ',', '.');
                            }
                        }
                    }
                }

                if ($replace['keyword'] == '%level%') {
                    $usermembership = UsersMembership::where('id_user', $user->id)->orderBy('id_log_membership', 'DESC')->first();
                    if ($usermembership) {
                        $replaced = $usermembership->membership_name;
                    }
                }

                if ($replace['keyword'] == "%points%") {
                    $points = $user['balance'] ?? 0;
                    $points = number_format($points, 0, ',', '.');

                    $text = str_replace("%point%", $points, $text);
                    $text = str_replace("%points%", $points, $text);
                    $text = str_replace($replace['keyword'], $points, $text);
                } else {
                    $text = str_replace($replace['keyword'], $replaced, $text);
                }
            }

            if (!empty($variables)) {
                foreach ($variables as $key => $var) {
                    if (is_string($var)) {
                        $text = str_replace('%' . $key . '%', $var, $text);
                    }
                }
            }
        }

        return $text;
    }

    public function listPushNotif()
    {
        $query = Setting::where('key', 'push_notification_list')->get()->first();

        if (!$query) {
            $data = [
                'key'           => 'push_notification_list',
                'value_text'    => json_encode([
                    'flexible'  => [
                        'Home',
                        'News List',
                        'News Detail',
                        'Inbox List',
                        'Outlet List',
                        'Outlet Detail',
                        'Voucher List',
                        'Deals List',
                        'Deals Detail',
                        'History Transaction List',
                        'History Point List',
                        'Profile',
                        'Delivery Service',
                        'FAQ',
                        'TOS',
                        'Contact US',
                        'Link',
                        'Logout',
                        'Custom Page'
                    ],
                    'voucher'   => [
                        'Voucher Detail'
                    ],
                    'history_trx'   => [
                        'History Transaction Detail'
                    ],
                    'history_point' => [
                        'History Point Detail'
                    ]
                ])
            ];
            $query = Setting::create($data);
        }
        $result = json_decode($query['value_text']);
        return response()->json(MyHelper::checkGet($result));
    }

    public function listAutoCrm(Request $request)
    {
        $query = Autocrm::with('whatsapp_content');
        $post = $request->json()->all();
        if (isset($post['autocrm_title'])) {
            $query = $query->where('autocrm_title', $post['autocrm_title'])->first();
        } else {
            $query = $query->get()->toArray();
        }
        return response()->json(MyHelper::checkGet($query));
    }

    public function updateAutoCrm(Request $request)
    {
        $post = $request->json()->all();

        $id_autocrm = $post['id_autocrm'];
        unset($post['id_autocrm']);

        if (isset($post['autocrm_push_image'])) {
            $query = Autocrm::where('id_autocrm', '=', $id_autocrm)->first();
            if ($query) {
                //delete photo
                if ($query['autocrm_push_image']) {
                    $del = MyHelper::deletePhoto($query['autocrm_push_image']);
                }
            }

            $upload = MyHelper::uploadPhoto($post['autocrm_push_image'], $path = 'img/push/', 600);

            if ($upload['status'] == "success") {
                $post['autocrm_push_image'] = $upload['path'];
            } else {
                $result = [
                        'status'    => 'fail',
                        'messages'  => ['Update Push Notification Image failed.']
                    ];
                return response()->json($result);
            }
        }

        if (isset($post['whatsapp_content'])) {
            $contentWa = $post['whatsapp_content'];
            unset($post['whatsapp_content']);
        }

        DB::beginTransaction();
        $query = Autocrm::where('id_autocrm', '=', $id_autocrm)->update($post);
        if (!$query) {
            DB::rollBack();
            $result = [
                    'status'    => 'fail',
                    'messages'  => ['Update Autocrm Failed.']
                ];
            return response()->json($result);
        }

        //whatsapp contents
        if (isset($contentWa)) {
            //delete content
            $idOld = array_filter(array_pluck($contentWa, 'id_whatsapp_content'));
            $contentOld = WhatsappContent::where('source', 'autocrm')->where('id_reference', $id_autocrm)->whereNotIn('id_whatsapp_content', $idOld)->get();
            if (count($contentOld) > 0) {
                foreach ($contentOld as $old) {
                    if ($old['content_type'] == 'image' || $old['content_type'] == 'file') {
                        $del = MyHelper::deletePhoto(str_replace(config('url.storage_url_api'), '', $old['content']));
                    }
                }

                $delete =  WhatsappContent::where('source', 'autocrm')->where('id_reference', $id_autocrm)->whereNotIn('id_whatsapp_content', $idOld)->delete();
                if (!$delete) {
                    DB::rollBack();
                    $result = [
                            'status'    => 'fail',
                            'messages'  => ['Update WhatsApp Content Failed.']
                        ];
                    return response()->json($result);
                }
            }

            //create or update content
            foreach ($contentWa as $content) {
                if ($content['content']) {
                    //delete file if update
                    if ($content['id_whatsapp_content']) {
                        $whatsappContent = WhatsappContent::find($content['id_whatsapp_content']);
                        if ($whatsappContent && ($whatsappContent->content_type == 'image' || $whatsappContent->content_type == 'file')) {
                            MyHelper::deletePhoto($whatsappContent->content);
                        }
                    }

                    if ($content['content_type'] == 'image') {
                        if (!file_exists('whatsapp/img/autocrm/')) {
                            mkdir('whatsapp/img/autocrm/', 0777, true);
                        }

                        //upload file
                        $upload = MyHelper::uploadPhoto($content['content'], $path = 'whatsapp/img/autocrm/');
                        if ($upload['status'] == "success") {
                            $content['content'] = config('url.storage_url_api') . $upload['path'];
                        } else {
                            DB::rollBack();
                            $result = [
                                    'status'    => 'fail',
                                    'messages'  => ['Update WhatsApp Content Image Failed.']
                                ];
                            return response()->json($result);
                        }
                    } elseif ($content['content_type'] == 'file') {
                        if (!file_exists('whatsapp/file/autocrm/')) {
                            mkdir('whatsapp/file/autocrm/', 0777, true);
                        }

                        $i = 1;
                        $filename = $content['content_file_name'];
                        while (file_exists('whatsapp/file/autocrm/' . $content['content_file_name'] . '.' . $content['content_file_ext'])) {
                            $content['content_file_name'] = $filename . '_' . $i;
                            $i++;
                        }

                        $upload = MyHelper::uploadFile($content['content'], $path = 'whatsapp/file/campaign/', $content['content_file_ext'], $content['content_file_name']);
                        if ($upload['status'] == "success") {
                            $content['content'] = config('url.storage_url_api') . $upload['path'];
                        } else {
                            DB::rollBack();
                            $result = [
                                    'status'    => 'fail',
                                    'messages'  => ['Update WhatsApp Content File Failed.']
                                ];
                            return response()->json($result);
                        }
                    }

                    $dataContent['source']       = 'autocrm';
                    $dataContent['id_reference'] = $id_autocrm;
                    $dataContent['content_type'] = $content['content_type'];
                    $dataContent['content']      = $content['content'];

                    //for update
                    if ($content['id_whatsapp_content']) {
                        $whatsappContent = WhatsappContent::where('id_whatsapp_content', $content['id_whatsapp_content'])->update($dataContent);
                    } else {
                    //for create
                        $whatsappContent = WhatsappContent::create($dataContent);
                    }

                    if (!$whatsappContent) {
                        DB::rollBack();
                        $result = [
                                'status'    => 'fail',
                                'messages'  => ['Update WhatsApp Content Failed.']
                            ];
                        return response()->json($result);
                    }
                }
            }
        }

        DB::commit();
        return response()->json(MyHelper::checkUpdate($query));
    }

    public function sendForwardEmail($autocrm_title, $subject, $content)
    {
        $getAutocrm = Autocrm::where('autocrm_title', '=', $autocrm_title)->with('whatsapp_content')->first();
        if ($getAutocrm) {
            if ($getAutocrm['autocrm_forward_toogle'] == 1 && !is_null($getAutocrm['autocrm_forward_email'])) {
                $recipient_email = explode(',', str_replace(' ', ',', str_replace(';', ',', $getAutocrm['autocrm_forward_email'])));

                foreach ($recipient_email as $key => $recipient) {
                    if ($recipient != ' ' && $recipient != "") {
                        $to      = $recipient;
                        //get setting email
                        $getSetting = Setting::where('key', 'LIKE', 'email%')->get()->toArray();
                        $setting = array();
                        foreach ($getSetting as $key => $value) {
                            $setting[$value['key']] = $value['value'];
                        }

                        $data = array(
                            'html_message' => $content,
                            'setting' => $setting
                        );

                        try {
                            $send = Mail::send('emails.test', $data, function ($message) use ($to, $subject, $setting) {
                                $message->to($to)->subject($subject);
                                if (env('MAIL_DRIVER') == 'mailgun') {
                                    $message->trackClicks(true)
                                        ->trackOpens(true);
                                }

                                if (!empty($setting['email_from']) && !empty($setting['email_sender'])) {
                                    $message->from($setting['email_sender'], $setting['email_from']);
                                } elseif (!empty($setting['email_sender'])) {
                                    $message->from($setting['email_sender']);
                                }

                                if (!empty($setting['email_reply_to']) && !empty($setting['email_reply_to_name'])) {
                                    $message->replyTo($setting['email_reply_to'], $setting['email_reply_to_name']);
                                } elseif (!empty($setting['email_reply_to'])) {
                                    $message->replyTo($setting['email_reply_to']);
                                }

                                if (!empty($setting['email_cc']) && !empty($setting['email_cc_name'])) {
                                    $message->cc($setting['email_cc'], $setting['email_cc_name']);
                                }

                                if (!empty($setting['email_bcc']) && !empty($setting['email_bcc_name'])) {
                                    $message->bcc($setting['email_bcc'], $setting['email_bcc_name']);
                                }
                            });
                        } catch (\Exception $e) {
                            return false;
                        }
                    }
                }
            }
        }

        return true;
    }
}
