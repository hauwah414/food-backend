<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Models\User;
use App\Http\Models\UserInbox;
use App\Http\Models\Campaign;
use App\Http\Models\CampaignEmailSent;
use App\Http\Models\CampaignSmsSent;
use App\Http\Models\CampaignPushSent;
use App\Http\Models\Outlet;
use App\Http\Models\News;
use App\Http\Models\Deal;
use App\Http\Models\Setting;
use App\Http\Models\OauthAccessToken;
//use Modules\Campaign\Http\Requests\campaign_list;
//use Modules\Campaign\Http\Requests\campaign_create;
//use Modules\Campaign\Http\Requests\campaign_update;
//use Modules\Campaign\Http\Requests\campaign_delete;

use App\Lib\PushNotificationHelper;
use App\Lib\ClassMaskingJson;
use App\Lib\ClassJatisSMS;
use App\Lib\ValueFirst;
use DB;
use App\Lib\SendMail as Mail;

class SendCampaignJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $data;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        date_default_timezone_set('Asia/Jakarta');
        $userr     = "Modules\Users\Http\Controllers\ApiUser";
        $autocrm  = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        $rajasms = new ClassMaskingJson();
        $this->jatissms = new ClassJatisSMS();
        $campaign = $this->data['campaign'];
        $type = $this->data['type'];
        $recipient = $this->data['recipient'];
        $notUseTextReplace = $this->data['not_use_text_replace'] ?? 0;
        $countPush = 0;
        $countInbox = 0;
        $countEmail = 0;
        $countWhatsApp = 0;
        $countSms = 0;

        switch ($type) {
            case 'email':
                foreach ($recipient as $key => $receipient) {
                    $to = $receipient;
                    $em_arr = explode('@', $receipient);
                    $name = ucwords(str_replace("_", " ", str_replace("-", " ", str_replace(".", " ", $em_arr[0]))));

                    $subject = app($autocrm)->TextReplace($campaign['campaign_email_subject'], $receipient, null, 'email');
                    $content = app($autocrm)->TextReplace($campaign['campaign_email_content'], $receipient, null, 'email');

                    // get setting email
                    $setting = array();
                    $set = Setting::where('key', 'email_from')->first();
                    if (!empty($set)) {
                        $setting['email_from'] = $set['value'];
                    } else {
                        $setting['email_from'] = null;
                    }
                    $set = Setting::where('key', 'email_sender')->first();
                    if (!empty($set)) {
                        $setting['email_sender'] = $set['value'];
                    } else {
                        $setting['email_sender'] = null;
                    }
                    $set = Setting::where('key', 'email_reply_to')->first();
                    if (!empty($set)) {
                        $setting['email_reply_to'] = $set['value'];
                    } else {
                        $setting['email_reply_to'] = null;
                    }
                    $set = Setting::where('key', 'email_reply_to_name')->first();
                    if (!empty($set)) {
                        $setting['email_reply_to_name'] = $set['value'];
                    } else {
                        $setting['email_reply_to_name'] = null;
                    }
                    $set = Setting::where('key', 'email_cc')->first();
                    if (!empty($set)) {
                        $setting['email_cc'] = $set['value'];
                    } else {
                        $setting['email_cc'] = null;
                    }
                    $set = Setting::where('key', 'email_cc_name')->first();
                    if (!empty($set)) {
                        $setting['email_cc_name'] = $set['value'];
                    } else {
                        $setting['email_cc_name'] = null;
                    }
                    $set = Setting::where('key', 'email_bcc')->first();
                    if (!empty($set)) {
                        $setting['email_bcc'] = $set['value'];
                    } else {
                        $setting['email_bcc'] = null;
                    }
                    $set = Setting::where('key', 'email_bcc_name')->first();
                    if (!empty($set)) {
                        $setting['email_bcc_name'] = $set['value'];
                    } else {
                        $setting['email_bcc_name'] = null;
                    }
                    $set = Setting::where('key', 'email_logo')->first();
                    if (!empty($set)) {
                        $setting['email_logo'] = $set['value'];
                    } else {
                        $setting['email_logo'] = null;
                    }
                    $set = Setting::where('key', 'email_logo_position')->first();
                    if (!empty($set)) {
                        $setting['email_logo_position'] = $set['value'];
                    } else {
                        $setting['email_logo_position'] = null;
                    }
                    $set = Setting::where('key', 'email_copyright')->first();
                    if (!empty($set)) {
                        $setting['email_copyright'] = $set['value'];
                    } else {
                        $setting['email_copyright'] = null;
                    }
                    $set = Setting::where('key', 'email_contact')->first();
                    if (!empty($set)) {
                        $setting['email_contact'] = $set['value'];
                    } else {
                        $setting['email_contact'] = null;
                    }

                    $data = array(
                        'customer' => $name,
                        'html_message' => $content,
                        'setting' => $setting
                    );

                    try {
                        Mail::send('emails.test', $data, function ($message) use ($to, $subject, $name, $setting) {

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
                        });
                        $outbox = [];
                        $outbox['id_campaign'] = $campaign['id_campaign'];
                        $outbox['email_sent_to'] = $receipient;
                        $outbox['email_sent_subject'] = $subject;
                        $outbox['email_sent_message'] = $content;
                        $outbox['email_sent_send_at'] = date("Y-m-d H:i:s");

                        $logs = CampaignEmailSent::create($outbox);
                        $countEmail++;
                    } catch (\Exception $e) {
                        \Log::error($e);
                    }
                }
                break;

            case 'sms':
                $senddata = array(
                    'apikey' => env('SMS_KEY'),
                    'callbackurl' => config('url.app_url'),
                    'datapacket' => array()
                );


                foreach ($recipient as $key => $receipient) {
                    $content    = app($autocrm)->TextReplace($campaign['campaign_sms_content'], $receipient);

                    switch (env('SMS_GATEWAY')) {
                        case 'Jatis':
                            $senddata = [
                                'userid'    => env('SMS_USER'),
                                'password'  => env('SMS_PASSWORD'),
                                'msisdn'    => '62' . substr($receipient, 1),
                                'sender'    => env('SMS_SENDER'),
                                'division'  => env('SMS_DIVISION'),
                                'batchname' => env('SMS_BATCHNAME'),
                                'uploadby'  => env('SMS_UPLOADBY'),
                                'channel'   => env('SMS_CHANNEL')
                            ];

                            $senddata['message'] = $content;

                            $this->jatissms->setData($senddata);
                            $send = $this->jatissms->send();

                            break;
                        case 'RajaSMS':
                            $senddata = array(
                                'apikey' => env('SMS_KEY'),
                                'callbackurl' => config('url.app_url'),
                                'datapacket' => array()
                            );

                            array_push($senddata['datapacket'], array(
                                'number' => trim($receipient),
                                'message' => urlencode(stripslashes(utf8_encode($content))),
                                'sendingdatetime' => ""));

                            $this->rajasms->setData($senddata);
                            $send = $this->rajasms->send();
                            break;
                        case 'ValueFirst':
                            $sendData = [
                                'to' => trim($receipient),
                                'text' => $content
                            ];

                            ValueFirst::create()->send($sendData);
                            break;
                        default:
                            $senddata = array(
                                'apikey' => env('SMS_KEY'),
                                'callbackurl' => config('url.app_url'),
                                'datapacket' => array()
                            );

                            array_push($senddata['datapacket'], array(
                                'number' => trim($receipient),
                                'message' => urlencode(stripslashes(utf8_encode($content))),
                                'sendingdatetime' => ""));

                            $this->rajasms->setData($senddata);
                            $send = $this->rajasms->send();
                            break;
                    }

                    $outbox = [];
                    $outbox['id_campaign'] = $campaign['id_campaign'];
                    $outbox['sms_sent_to'] = $receipient;
                    $outbox['sms_sent_content'] = $content;
                    $outbox['sms_sent_send_at'] = date("Y-m-d H:i:s");

                    $logs = CampaignSmsSent::create($outbox);
                    $countSms++;
                }
                break;

            case 'push':
                if ($notUseTextReplace == 1) {
                    $dataOptional          = [];
                    $image = null;
                    if (isset($campaign['campaign_push_image']) && $campaign['campaign_push_image'] != null) {
                        $dataOptional['image'] = config('url.storage_url_api') . $campaign['campaign_push_image'];
                        $image = config('url.storage_url_api') . $campaign['campaign_push_image'];
                    }

                    if (isset($campaign['campaign_push_clickto']) && $campaign['campaign_push_clickto'] != null) {
                        $dataOptional['type'] = $campaign['campaign_push_clickto'];
                    } else {
                        $dataOptional['type'] = 'Home';
                    }

                    if (isset($campaign['campaign_push_link']) && $campaign['campaign_push_link'] != null) {
                        if ($dataOptional['type'] == 'Link') {
                            $dataOptional['link'] = $campaign['campaign_push_link'];
                        } else {
                            $dataOptional['link'] = null;
                        }
                    } else {
                        $dataOptional['link'] = null;
                    }

                    if (isset($campaign['campaign_push_id_reference']) && $campaign['campaign_push_id_reference'] != null) {
                        $dataOptional['id_reference'] = (int)$campaign['campaign_push_id_reference'];
                    } else {
                        $dataOptional['id_reference'] = 0;
                    }

                    if ($campaign['campaign_push_clickto'] == 'News' && $campaign['campaign_push_id_reference'] != null) {
                        $news = News::find($campaign['campaign_push_id_reference']);
                        if ($news) {
                            $dataOptional['news_title'] = $news->news_title;
                            $dataOptional['title'] = $news->news_title;
                        }
                        $dataOptional['url'] = config('url.app_url') . 'news/webview/' . $campaign['campaign_push_id_reference'];
                    } elseif ($campaign['campaign_push_clickto'] == 'Order' && $campaign['campaign_push_id_reference'] != null) {
                        $outlet = Outlet::find($campaign['campaign_push_id_reference']);
                        if ($outlet) {
                            $dataOptional['title'] = $outlet->outlet_name;
                        }
                    } elseif ($campaign['campaign_push_clickto'] == 'Deals' && $campaign['campaign_push_id_reference'] != null) {
                        $deals = Deal::find($campaign['campaign_push_id_reference']);
                        if ($deals) {
                            $dataOptional['title'] = $deals->deals_title;
                        }
                    }

                    //push notif logout
                    if ($campaign['campaign_push_clickto'] == 'Logout') {
                        $user = User::whereIn('phone', $recipient)->pluck('id')->toArray();
                        if ($user) {
                            //delete token
                            $del = OauthAccessToken::join('oauth_access_token_providers', 'oauth_access_tokens.id', 'oauth_access_token_providers.oauth_access_token_id')
                                ->where('oauth_access_tokens.user_id', $user)->where('oauth_access_token_providers.provider', 'users')->delete();
                        }
                    }

                    $subject = app($autocrm)->TextReplace($campaign['campaign_push_subject'], $recipient[0]);
                    $content = app($autocrm)->TextReplace($campaign['campaign_push_content'], $recipient[0]);
                    $deviceToken = PushNotificationHelper::searchDeviceToken("phone", $recipient);
                    if (!empty($deviceToken)) {
                        if (isset($deviceToken['token']) && !empty($deviceToken['token'])) {
                            try {
                                $dataOptional['id_notif'] = $campaign['id_campaign'];
                                $dataOptional['source'] = 'campaign';
                                $push = PushNotificationHelper::sendPush($deviceToken['token'], $subject, $content, $image, $dataOptional, 1);

                                if (isset($push['success']) && $push['success'] > 0) {
                                    $calculationSuccess = [
                                        'error_token' => $push['error_token'] ?? [],
                                        'all_token' => $deviceToken['token'],
                                        'recipient' => $recipient,
                                        'date_send' => date('Y-m-d H:i:s', strtotime("+ 5 minutes")),
                                        'subject' => $subject,
                                        'content' => $content,
                                        'id_campaign' => $campaign['id_campaign']
                                    ];

                                    SendCampaignCalculationSuccessJob::dispatch($calculationSuccess)->allOnConnection('database');
                                }
                            } catch (\Exception $e) {
                                \Log::error($e);
                            }
                        }
                    }
                } else {
                    $dataOptional          = [];
                    $image = null;
                    if (isset($campaign['campaign_push_image']) && $campaign['campaign_push_image'] != null) {
                        $dataOptional['image'] = config('url.storage_url_api') . $campaign['campaign_push_image'];
                        $image = config('url.storage_url_api') . $campaign['campaign_push_image'];
                    }

                    if (isset($campaign['campaign_push_clickto']) && $campaign['campaign_push_clickto'] != null) {
                        $dataOptional['type'] = $campaign['campaign_push_clickto'];
                    } else {
                        $dataOptional['type'] = 'Home';
                    }

                    if (isset($campaign['campaign_push_link']) && $campaign['campaign_push_link'] != null) {
                        if ($dataOptional['type'] == 'Link') {
                            $dataOptional['link'] = $campaign['campaign_push_link'];
                        } else {
                            $dataOptional['link'] = null;
                        }
                    } else {
                        $dataOptional['link'] = null;
                    }

                    if (isset($campaign['campaign_push_id_reference']) && $campaign['campaign_push_id_reference'] != null) {
                        $dataOptional['id_reference'] = (int)$campaign['campaign_push_id_reference'];
                    } else {
                        $dataOptional['id_reference'] = 0;
                    }

                    if ($campaign['campaign_push_clickto'] == 'News' && $campaign['campaign_push_id_reference'] != null) {
                        $news = News::find($campaign['campaign_push_id_reference']);
                        if ($news) {
                            $dataOptional['news_title'] = $news->news_title;
                            $dataOptional['title'] = $news->news_title;
                        }
                        $dataOptional['url'] = config('url.app_url') . 'news/webview/' . $campaign['campaign_push_id_reference'];
                    } elseif ($campaign['campaign_push_clickto'] == 'Order' && $campaign['campaign_push_id_reference'] != null) {
                        $outlet = Outlet::find($campaign['campaign_push_id_reference']);
                        if ($outlet) {
                            $dataOptional['title'] = $outlet->outlet_name;
                        }
                    } elseif ($campaign['campaign_push_clickto'] == 'Deals' && $campaign['campaign_push_id_reference'] != null) {
                        $deals = Deal::find($campaign['campaign_push_id_reference']);
                        if ($deals) {
                            $dataOptional['title'] = $deals->deals_title;
                        }
                    }

                    foreach ($recipient as $key => $receipient) {
                        $dataOptionalInsert = $dataOptional;
                        //push notif logout
                        if ($campaign['campaign_push_clickto'] == 'Logout') {
                            $user = User::where('phone', $receipient)->first();
                            if ($user) {
                                //delete token
                                $del = OauthAccessToken::join('oauth_access_token_providers', 'oauth_access_tokens.id', 'oauth_access_token_providers.oauth_access_token_id')
                                    ->where('oauth_access_tokens.user_id', $user['id'])->where('oauth_access_token_providers.provider', 'users')->delete();
                            }
                        }

                        $subject = app($autocrm)->TextReplace($campaign['campaign_push_subject'], $receipient);
                        $content = app($autocrm)->TextReplace($campaign['campaign_push_content'], $receipient);
                        $deviceToken = PushNotificationHelper::searchDeviceToken("phone", $receipient);

                        if (!empty($deviceToken)) {
                            if (isset($deviceToken['token']) && !empty($deviceToken['token'])) {
                                try {
                                    DB::beginTransaction();
                                    $push = [];
                                    $push['id_campaign'] = $campaign['id_campaign'];
                                    $push['push_sent_to'] = $receipient;
                                    $push['push_sent_subject'] = $subject;
                                    $push['push_sent_content'] = $content;
                                    $push['push_sent_send_at'] = date('Y-m-d H:i:s', strtotime("+ 5 minutes"));

                                    $logs = CampaignPushSent::create($push);

                                    $dataOptionalInsert['id_notif'] = $campaign['id_campaign'];
                                    $dataOptionalInsert['source'] = 'campaign';

                                    $push = PushNotificationHelper::sendPush($deviceToken['token'], $subject, $content, $image, $dataOptionalInsert);

                                    if (isset($push['success']) && $push['success'] > 0) {
                                        DB::commit();
                                        $countPush++;
                                    } else {
                                        DB::rollback();
                                    }
                                } catch (\Exception $e) {
                                    \Log::error($e);
                                }
                            }
                        }
                    }
                }

                break;

            case 'inbox':
                $user = User::whereIn('phone', $recipient)->get()->toArray();
                $inbox = [];
                foreach ($user as $key => $receipient) {
                    $inboxInsert['id_campaign'] = $campaign['id_campaign'];
                    $inboxInsert['id_user']     = $receipient['id'];
                    $inboxInsert['inboxes_subject'] = app($autocrm)->TextReplace($campaign['campaign_inbox_subject'], $receipient['id'], null, 'id');
                    $inboxInsert['inboxes_content'] = app($autocrm)->TextReplace($campaign['campaign_inbox_content'], $receipient['id'], null, 'id');
                    $inboxInsert['inboxes_clickto'] = $campaign['campaign_inbox_clickto'];

                    if ($campaign['campaign_inbox_clickto'] == 'Link') {
                        $inboxInsert['inboxes_link'] = $campaign['campaign_inbox_link'];
                    }

                    if (!empty($campaign['campaign_inbox_id_reference'])) {
                        $inboxInsert['inboxes_id_reference'] = $campaign['campaign_inbox_id_reference'];
                    } else {
                        $inboxInsert['inboxes_id_reference'] = 0;
                    }

                    if ($campaign['campaign_inbox_clickto'] == 'No Action' || empty($campaign['campaign_inbox_clickto'])) {
                        $inboxInsert['inboxes_clickto'] = 'Default';
                    }

                    $inboxInsert['inboxes_send_at'] = date("Y-m-d H:i:s");
                    $inboxInsert['inboxes_promotion_status'] = 1;
                    $inboxInsert['created_at'] = date("Y-m-d H:i:s");
                    $inboxInsert['updated_at'] = date("Y-m-d H:i:s");
                    $inbox[] = $inboxInsert;
                    $countInbox++;
                }
                $inboxQuery = UserInbox::insert($inbox);
                break;

            case 'whatsapp':
                $api_key = Setting::where('key', 'api_key_whatsapp')->first();
                if ($api_key) {
                    if ($api_key->value) {
                        foreach ($recipient as $key => $receipient) {
                            $contentWaSent = [];
                            //send every content whatsapp
                            foreach ($campaign->whatsapp_content as $contentWhatsapp) {
                                if ($contentWhatsapp['content_type'] == 'text') {
                                    $content = app($this->autocrm)->TextReplace($contentWhatsapp['content'], $receipient);
                                } else {
                                    $content = $contentWhatsapp['content'];
                                }
                                // add country code in number
                                $ptn = "/^0/";
                                $rpltxt = "62";
                                $phone = preg_replace($ptn, $rpltxt, $receipient);

                                $send = $this->Apiwha->send($api_key->value, $phone, $content);

                                //api key whatsapp not valid
                                if (isset($send['result_code']) && $send['result_code'] == -1) {
                                    break 2;
                                }

                                $dataContent['content'] = $content;
                                $dataContent['content_type'] = $contentWhatsapp['content_type'];
                                array_push($contentWaSent, $dataContent);
                            }

                            $outbox = [];
                            $outbox['id_campaign'] = $campaign['id_campaign'];
                            $outbox['whatsapp_sent_to'] = $receipient;
                            $outbox['whatsapp_sent_send_at'] = date("Y-m-d H:i:s");

                            $logs = CampaignWhatsappSent::create($outbox);
                            if ($logs) {
                                foreach ($dataContent as $data) {
                                    $data['id_campaign_whatsapp_sent'] = $logs['id_campaign_whatsapp_sent'];
                                    $create = CampaignWhatsappSentContent::create($data);
                                }
                            }

                            $countWhatsApp++;
                        }
                    }
                }
                break;

            default:
                // print("Do nothing\n");
                break;
        }

        /*update Coun */
        DB::table('campaigns')
            ->where('id_campaign', $campaign['id_campaign'])
            ->update([
                'campaign_email_count_sent' => DB::raw('campaign_email_count_sent + ' . $countEmail)
            ]);

        DB::table('campaigns')
            ->where('id_campaign', $campaign['id_campaign'])
            ->update([
                'campaign_sms_count_sent' => DB::raw('campaign_sms_count_sent + ' . $countSms)
            ]);

        DB::table('campaigns')
            ->where('id_campaign', $campaign['id_campaign'])
            ->update([
                'campaign_whatsapp_count_sent' => DB::raw('campaign_whatsapp_count_sent + ' . $countWhatsApp)
            ]);

        DB::table('campaigns')
            ->where('id_campaign', $campaign['id_campaign'])
            ->update([
                'campaign_push_count_sent' => DB::raw('campaign_push_count_sent + ' . $countPush)
            ]);

        return true;
    }
}
