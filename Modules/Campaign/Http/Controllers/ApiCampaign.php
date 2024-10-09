<?php

namespace Modules\Campaign\Http\Controllers;

use App\Jobs\SendCampaignNow;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Http\Models\User;
use App\Http\Models\UserInbox;
use App\Http\Models\Campaign;
use App\Http\Models\CampaignRule;
use App\Http\Models\CampaignEmailSent;
use App\Http\Models\CampaignSmsSent;
use App\Http\Models\CampaignSmsQueue;
use App\Http\Models\CampaignPushSent;
use App\Http\Models\Outlet;
use App\Http\Models\Product;
use App\Http\Models\Treatment;
use App\Http\Models\Setting;
use App\Http\Models\CampaignRuleParent;
use App\Http\Models\WhatsappContent;
use App\Http\Models\CampaignWhatsappSent;
use App\Http\Models\CampaignWhatsappSentContent;
use App\Http\Models\News;
use App\Http\Models\OauthAccessToken;
use Modules\Doctor\Entities\Doctor;
use Modules\PromoCampaign\Entities\PromoCampaign;
use Modules\RedirectComplex\Entities\RedirectComplexReference;
use App\Http\Models\PromotionSent;
//use Modules\Campaign\Http\Requests\campaign_list;
//use Modules\Campaign\Http\Requests\campaign_create;
//use Modules\Campaign\Http\Requests\campaign_update;
//use Modules\Campaign\Http\Requests\campaign_delete;

use App\Jobs\SendCampaignJob;
use App\Jobs\GenerateCampaignRecipient;
use App\Lib\MyHelper;
use App\Lib\PushNotificationHelper;
use App\Lib\ClassMaskingJson;
use App\Lib\Apiwha;
use Validator;
use Hash;
use DB;
use App\Lib\SendMail as Mail;
use App\Http\Models\TextReplace;

class ApiCampaign extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->user     = "Modules\Users\Http\Controllers\ApiUser";
        $this->autocrm  = "Modules\Autocrm\Http\Controllers\ApiAutoCrm";
        $this->rajasms = new ClassMaskingJson();
        $this->Apiwha = new Apiwha();
    }

    public function campaignList(Request $request)
    {
        $post = $request->json()->all();

        $query = Campaign::orderBy('id_campaign', 'Desc');

        if (isset($post['campaign_title']) && $post['campaign_title'] != "") {
            $query = $query->where('campaign_title', 'like', '%' . $post['campaign_title'] . '%');
        }

        $query->withCount(['campaign_push_sents' => function ($q) {
            $q->whereNotNull('click_at');
        }]);

        $query = $query->paginate(15);
        return response()->json(MyHelper::checkGet($query));
    }
    public function CreateCampaign(Request $request)
    {
        if ($request->hasFile('import_file')) {
            if (!($request->file('import_file')->isValid() && in_array($request->file('import_file')->getMimeType(), array('text/plain','text/csv','application/csv')))) {
                return [
                    'status'  => 'fail',
                    'messages'  => 'Invalid file',
                ];
            }
            $isi = file_get_contents($request->file('import_file')->getRealPath());
            $post = $request->post();
            $post = array_map(function ($x) {
                return json_decode($x, true);
            }, $post);
            $arr = MyHelper::csvToArray($isi);
            $content = isset($post['csv_content']) ? $post['csv_content'] : 'id';
            $param = array_filter(array_map(function ($y) {
                $x = $y[0];
                if (is_numeric($x)) {
                    return $x;
                }
            }, $arr));
            if (empty($param)) {
                $erDa = $content == 'id' ? 'User Id' : 'Phone Number';
                return [
                    'status'  => 'fail',
                    'messages'  => ['No ' . $erDa . ' was found in this file']
                ];
            }
            $content = isset($post['csv_content']) ? $post['csv_content'] : 'id';
            $post['conditions'] = array(0 => array(
                0 => array(
                    'subject' => $content,
                    'operator' => 'WHERE IN',
                    'parameter' => implode(',', $param)
                ),
                'rule' => 'and',
                'rule_next' => 'and'
            ));
        } else {
            $post = $request->json()->all();
            if ($request->has('import_file') && !isset($post['conditions'])) {
                $post = $request->post();
                $post = array_map(function ($x) {
                    return json_decode($x, true);
                }, $post);
                $post['conditions'] = [];
            }
            if (!isset($post['campaign_description'])) {
                $post['campaign_description'] = '';
            }
        }
        if (empty($post['conditions']) && !$request->has('import_file')) {
            return [
                'status'  => 'fail',
                'messages'  => ['Rule must be filled in at least one']
            ];
        }
        $user = $request->user();
        $data                           = [];
        $data['campaign_title']         = $post['campaign_title'];
        $data['campaign_description']   = $post['campaign_description'];
        $data['id_user']                = $user['id'];

        if (!empty($post['campaign_send_at'])) {
//          $datetimearr                = explode(' - ',$post['campaign_send_at']);
//          $datearr                    = explode(' ',$datetimearr[0]);
//          $date                       = date("Y-m-d", strtotime($datearr[2].", ".$datearr[1]." ".$datearr[0]));
//          $data['campaign_send_at']   = $date." ".$datetimearr[1].":00";
            $data['campaign_send_at']   = date('Y-m-d H:i:s', strtotime(str_replace('-', '', $post['campaign_send_at'])));
        } else {
            $data['campaign_send_at'] = null;
        }

        // $data['campaign_rule']       = $post['rule'];

        if (in_array('Email', $post['campaign_media'])) {
            $data['campaign_media_email'] = "Yes";
        } else {
            $data['campaign_media_email'] = "No";
        }

        if (in_array('SMS', $post['campaign_media'])) {
            $data['campaign_media_sms'] = "Yes";
        } else {
            $data['campaign_media_sms'] = "No";
        }

        if (in_array('Push Notification', $post['campaign_media'])) {
            $data['campaign_media_push'] = "Yes";
        } else {
            $data['campaign_media_push'] = "No";
        }

        if (in_array('Inbox', $post['campaign_media'])) {
            $data['campaign_media_inbox'] = "Yes";
        } else {
            $data['campaign_media_inbox'] = "No";
        }

        if (in_array('Whatsapp', $post['campaign_media'])) {
            $data['campaign_media_whatsapp'] = "Yes";
        } else {
            $data['campaign_media_whatsapp'] = "No";
        }

        $data['campaign_generate_receipient'] =  $post['campaign_generate_receipient'];

        DB::beginTransaction();
        if (isset($post['id_campaign'])) {
            $queryCampaign = Campaign::where('id_campaign', '=', $post['id_campaign'])->update($data);
        } else {
            $queryCampaign = Campaign::create($data);
        }

        if ($queryCampaign) {
            $data = [];

            if (isset($post['id_campaign']) && !empty($post['conditions'])) {
                $deleteRuleParent = CampaignRuleParent::where('id_campaign', '=', $post['id_campaign'])->get();
                foreach ($deleteRuleParent as $key => $value) {
                    $value->rules()->delete();
                }
                $deleteRuleParent = CampaignRuleParent::where('id_campaign', '=', $post['id_campaign'])->delete();
            }

            if (isset($post['id_campaign'])) {
                $data['id_campaign'] = $post['id_campaign'];
            } else {
                $data['id_campaign'] = $queryCampaign->id_campaign;
            }
            if (!empty($post['conditions'])) {
                $queryCampaignRule = MyHelper::insertCondition('campaign', $data['id_campaign'], $post['conditions']);
                if (isset($queryCampaignRule['status']) && $queryCampaignRule['status'] == 'success') {
                    $resultrule = $queryCampaignRule['data'];
                } else {
                    DB::rollBack();
                    $result = [
                        'status'  => 'fail',
                        'messages'  => ['Create Campaign Failed']
                    ];
                }
                $result = [
                    'status'  => 'success',
                    'result'  => 'Set Campaign Information & Rule Success',
                    'campaign'  => $queryCampaign,
                    'rule'  => $resultrule
                ];
            } else {
                $result = [
                        'status'  => 'success',
                        'result'  => 'Set Campaign Information',
                        'campaign'  => $queryCampaign,
                    ];
            }
        } else {
            DB::rollBack();
            $result = [
                    'status'  => 'fail',
                    'messages'  => ['Create Campaign Failed']
                ];
        }

        DB::commit();
        return response()->json($result);
    }

    public function ShowCampaignStep1(Request $request)
    {
        $post = $request->json()->all();
        $user = $request->user();

        $campaign = Campaign::with(['user', 'campaign_rule_parents', 'campaign_rule_parents.rules'])->where('id_campaign', '=', $post['id_campaign'])->get()->toArray();
        if ($campaign) {
            $result = [
                    'status'  => 'success',
                    'result'  => $campaign
                ];
        } else {
            $result = [
                    'status'  => 'fail',
                    'messages'  => ['Campaign Not Found']
                ];
        }
        return response()->json($result);
    }

    public function ShowCampaignStep2(Request $request)
    {
        $post = $request->json()->all();
        $user = $request->user();

        $campaign = Campaign::with(['user', 'campaign_rule_parents', 'campaign_rule_parents.rules', 'whatsapp_content'])->where('id_campaign', '=', $post['id_campaign'])->get()->first();
        if ($campaign) {
            $campaign['campaign_push_name_reference'] = "";

            if ($campaign['campaign_media_push'] == "Yes") {
                if ($campaign['campaign_push_clickto'] == "product_detail") {
                    if ($campaign['campaign_push_id_reference'] != 0) {
                        $q = Product::where('id_product', '=', $campaign['campaign_push_id_reference'])->get()->first();
                        $campaign['campaign_push_name_reference'] = $q['product_name'];
                    }
                }

                if ($campaign['campaign_push_clickto'] == "merchant_detail") {
                    if ($campaign['campaign_push_id_reference'] != 0) {
                        $q = Outlet::where('id_outlet', '=', $campaign['campaign_push_id_reference'])->get()->first();
                        $campaign['campaign_push_name_reference'] = $q['outlet_name'];
                    }
                }

                if ($campaign['campaign_push_clickto'] == "doctor_detail") {
                    if ($campaign['campaign_push_id_reference'] != 0) {
                        $q = Doctor::where('id_doctor', '=', $campaign['campaign_push_id_reference'])->get()->first();
                        $campaign['campaign_push_name_reference'] = $q['doctor_name'];
                    }
                }

                if ($campaign['campaign_push_clickto'] == "promo_detail") {
                    if ($campaign['campaign_push_id_reference'] != 0) {
                        $q = PromoCampaign::where('id_promo_campaign', '=', $campaign['campaign_push_id_reference'])->get()->first();
                        $campaign['campaign_push_name_reference'] = $q['promo_title'];
                    }
                }
            }

            if ($campaign['campaign_media_inbox'] == "Yes") {
                if ($campaign['campaign_inbox_clickto'] == "product_detail") {
                    if ($campaign['campaign_inbox_id_reference'] != 0) {
                        $q = Product::where('id_product', '=', $campaign['campaign_inbox_id_reference'])->get()->first();
                        $campaign['campaign_inbox_name_reference'] = $q['product_name'];
                    }
                }

                if ($campaign['campaign_inbox_clickto'] == "merchant_detail") {
                    if ($campaign['campaign_inbox_id_reference'] != 0) {
                        $q = Outlet::where('id_outlet', '=', $campaign['campaign_inbox_id_reference'])->get()->first();
                        $campaign['campaign_inbox_name_reference'] = $q['outlet_name'];
                    }
                }

                if ($campaign['campaign_inbox_clickto'] == "doctor_detail") {
                    if ($campaign['campaign_inbox_id_reference'] != 0) {
                        $q = Doctor::where('id_doctor', '=', $campaign['campaign_inbox_id_reference'])->get()->first();
                        $campaign['campaign_inbox_name_reference'] = $q['doctor_name'];
                    }
                }

                if ($campaign['campaign_inbox_clickto'] == "promo_detail") {
                    if ($campaign['campaign_inbox_id_reference'] != 0) {
                        $q = PromoCampaign::where('id_promo_campaign', '=', $campaign['campaign_inbox_id_reference'])->get()->first();
                        $campaign['campaign_inbox_name_reference'] = $q['promo_title'];
                    }
                }
            }

            $result = [
                'status'  => 'success',
                'result'  => $campaign
            ];
        } else {
            $result = [
                    'status'  => 'fail',
                    'messages'  => ['Campaign Not Found']
                ];
        }

        return response()->json($result);
    }

    public function showRecipient(Request $request)
    {
        $post = $request->json()->all();
        $limiter = [];
        $column = ['id','name','email','phone','gender','city_name','birthday'];
        $limiter = [
            $column[$post['order'][0]['column'] ?? 0] ?? 'id',
            $post['order'][0]['dir'] ?? 'asc',
            $post['start'] ?? 0,
            $post['length'] ?? 99999999,
            $post['search']['value'] ?? null,
        ];
        $cond = Campaign::with(['campaign_rule_parents', 'campaign_rule_parents.rules'])->where('id_campaign', '=', $post['id_campaign'])->first();
        if (!$cond) {
            return [
                    'status'  => 'fail',
                    'messages'  => ['Campaign Not Found']
                ];
        }
        // UserFilter($conditions = null, $order_field='id', $order_method='asc', $skip=0, $take=99999999999,$keyword=null)
        $users = app($this->user)->UserFilter($cond['campaign_rule_parents'], ...$limiter);
        if ($users['status'] == 'success') {
            $cond['users'] = $users['result'];
        }
        $result = [
                'status'  => 'success',
                'result'  => $cond,
                'recordsFiltered' => $users['total'] ?? 0,
                'recordsTotal' => $users['total'] ?? 0
            ];
        return $result;
    }

    public function SendCampaign(Request $request)
    {
        $post = $request->json()->all();
        $user = $request->user();

        $campaign = Campaign::where('id_campaign', '=', $post['id_campaign'])->first();

        if ($campaign) {
            DB::beginTransaction();
            if ($campaign['campaign_is_sent'] == 'Yes') {
                if ($post['resend'] ?? 0 == 1) {
                    unset($campaign['id_campaign']);
                    unset($campaign['created_at']);
                    unset($campaign['updated_at']);
                    $campaign['campaign_is_sent'] = 'No';
                    $campaign['campaign_email_receipient'] = null;
                    $campaign['campaign_sms_receipient'] = null;
                    $campaign['campaign_push_receipient'] = null;
                    $campaign['campaign_inbox_receipient'] = null;
                    $campaign['campaign_whatsapp_receipient'] = null;

                    $campaign['campaign_email_count_all'] = 0;
                    $campaign['campaign_sms_count_all'] = 0;
                    $campaign['campaign_push_count_all'] = 0;
                    $campaign['campaign_whatsapp_count_all'] = 0;

                    $campaign['campaign_email_count_sent'] = 0;
                    $campaign['campaign_sms_count_sent'] = 0;
                    $campaign['campaign_push_count_sent'] = 0;
                    $campaign['campaign_inbox_count'] = 0;
                    $campaign['campaign_whatsapp_count_sent'] = 0;
                    $data = json_decode(json_encode($campaign), true);
                    $c = Campaign::create($data);

                    if ($c) {
                        $id_campaign = $c->id_campaign;
                        $campaign = Campaign::where('id_campaign', '=', $c->id_campaign)->first();
                        $rules = CampaignRuleParent::with('rules')->where('id_campaign', '=', $post['id_campaign'])->get();

                        foreach ($rules as $value) {
                            $rule_parent = CampaignRuleParent::create([
                                "id_campaign" => $c->id_campaign,
                                "campaign_rule" => $value['campaign_rule'],
                                "campaign_rule_next" => $value['campaign_rule_next'],
                                "created_at" => date('Y-m-d H:i:s'),
                                "updated_at" => date('Y-m-d H:i:s')
                            ]);

                            if ($rule_parent) {
                                foreach ($value['rules'] as $val) {
                                    $rule = CampaignRule::create([
                                        "id_campaign_rule_parent" => $rule_parent->id_campaign_rule_parent,
                                        "campaign_rule_subject" => $val['subject'],
                                        "campaign_rule_operator" => $val['operator'],
                                        "campaign_rule_param" => $val['parameter'],
                                        "campaign_rule_param_select" => $val['parameter_select'] ?? null,
                                        "campaign_rule_param_id" => $val['id'],
                                        "created_at" => date('Y-m-d H:i:s'),
                                        "updated_at" => date('Y-m-d H:i:s')
                                    ]);

                                    if (!$rule) {
                                        DB::rollBack();
                                        $result = [
                                            'status'  => 'fail',
                                            'messages'  => ['Failed create Rule']
                                        ];
                                    }
                                }
                            } else {
                                DB::rollBack();
                                $result = [
                                    'status'  => 'fail',
                                    'messages'  => ['Failed create Rule Parent']
                                ];
                            }
                        }
                    } else {
                        DB::rollBack();
                        $result = [
                            'status'  => 'fail',
                            'messages'  => ['Re-create Campaign Failed']
                        ];
                        return response()->json($result);
                    }
                } else {
                    DB::rollBack();
                    $result = [
                        'status'  => 'fail',
                        'messages'  => ['Campaign already sent']
                    ];
                    return response()->json($result);
                }
            }

            if ($campaign['campaign_send_at'] == null && $post['resend'] != 1) {
                //Kirimnya NOW
                if ($campaign['generate_recipient_status'] != 1) {
                    $result = [
                        'status'  => 'fail',
                        'messages'  => ['Recipient has not yet been generated, please wait until recipient already generated.']
                    ];
                } else {
                    Campaign::where('id_campaign', '=', $campaign['id_campaign'])->update(['campaign_is_sent' => 'Yes']);
                    SendCampaignNow::dispatch($campaign)->allOnConnection('campaignqueue');
                    $result = [
                        'status'  => 'success',
                        'result'  => true
                    ];
                }
            } elseif ($campaign['campaign_send_at'] == null && $post['resend'] == 1) {
                $result = [
                    'status'  => 'success',
                    'result'  => $campaign
                ];
            } else {
                DB::rollBack();
                $result = [
                    'status'  => 'fail',
                    'messages'  => ['Campaign Will be automatically sent at ' . date("d F Y - H:i", strtotime($campaign['campaign_send_at']))]
                ];
            }
            DB::commit();

            if ($post['resend'] == 1) {
                $post['id_campaign'] = $id_campaign;
                GenerateCampaignRecipient::dispatch($post)->allOnConnection('campaignqueue');
            }
        } else {
            $result = [
                    'status'  => 'fail',
                    'messages'  => ['Campaign Not Found']
                ];
        }
        return response()->json($result);
    }

    public function insertQueue()
    {
        $log = MyHelper::logCron('Insert Queue');
        try {
            $now = date('Y-m-d H:i:00');
            $now2 = date('Y-m-d H:i:00', strtotime('-5 minutes'));

            $campaigns = Campaign::where('campaign_send_at', '>', $now2)->where('campaign_send_at', '<=', $now)->where('campaign_is_sent', 'No')->where('campaign_complete', '1')->get();
            foreach ($campaigns as $i => $campaign) {
                if ($campaign->campaign_generate_receipient == 'Send At Time') {
                    $post = ['id_campaign' => $campaign->id_campaign];
                    GenerateCampaignRecipient::dispatch($post)->allOnConnection('campaignqueue');
                } else {
                    $this->sendCampaignInternal($campaign->toArray());
                }
            }

            $log->success(count($campaigns) . ' campaign has been insert to queue');
            return response()->json([
                'status' => 'success',
                'result' => count($campaigns) . ' campaign has been insert to queue'
            ]);
        } catch (\Exception $e) {
            $log->fail($e->getMessage());
        }
    }

    public function sendCampaignInternal($campaign)
    {
        $update = Campaign::where('id_campaign', '=', $campaign['id_campaign'])->update(['campaign_is_sent' => 'Yes']);

        if ($campaign['campaign_media_push'] == "Yes") {
            $receipient_push = explode(',', str_replace(' ', ',', str_replace(';', ',', $campaign['campaign_push_receipient'])));

            unset($campaign['campaign_push_receipient']);
            $data['campaign'] = $campaign;
            $data['type'] = 'push';

            $textReplace = TextReplace::pluck('keyword')->toArray();
            $notUseTextReplace = 1;
            $data['not_use_text_replace'] = 1;
            foreach ($textReplace as $text) {
                if (strpos($campaign['campaign_push_subject'], $text) === false && strpos($campaign['campaign_push_content'], $text) === false) {
                    continue;
                }
                $notUseTextReplace = 0;
                $data['not_use_text_replace'] = 0;
                break;
            }

            if ($notUseTextReplace == 1) {
                foreach (array_chunk($receipient_push, 500) as $recipients) {
                    $data['recipient'] = array_filter($recipients, function ($var) {
                        return !empty($var);
                    });
                    SendCampaignJob::dispatch($data)->allOnConnection('campaignqueue');
                }
            } else {
                foreach (array_chunk($receipient_push, 300) as $recipients) {
                    $data['recipient'] = array_filter($recipients, function ($var) {
                        return !empty($var);
                    });
                    SendCampaignJob::dispatch($data)->allOnConnection('campaignqueue');
                }
            }
        }

        if ($campaign['campaign_media_email'] == "Yes") {
            $receipient_email = explode(',', str_replace(' ', ',', str_replace(';', ',', $campaign['campaign_email_receipient'])));
            unset($campaign['campaign_email_receipient']);
            $data['campaign'] = $campaign;
            $data['type'] = 'email';
            foreach (array_chunk($receipient_email, 300) as $recipients) {
                $data['recipient'] = array_filter($recipients, function ($var) {
                    return !empty($var);
                });
                SendCampaignJob::dispatch($data)->allOnConnection('campaignqueue');
            }
        }

        if ($campaign['campaign_media_sms'] == "Yes") {
            $receipient_sms = explode(',', str_replace(' ', ',', str_replace(';', ',', $campaign['campaign_sms_receipient'])));

            unset($campaign['campaign_sms_receipient']);
            $data['campaign'] = $campaign;
            $data['type'] = 'sms';
            foreach (array_chunk($receipient_sms, 300) as $recipients) {
                $data['recipient'] = array_filter($recipients, function ($var) {
                    return !empty($var);
                });
                SendCampaignJob::dispatch($data)->allOnConnection('campaignqueue');
            }
        }

        if ($campaign['campaign_media_inbox'] == "Yes") {
            $receipient_inbox = explode(',', str_replace(' ', ',', str_replace(';', ',', $campaign['campaign_inbox_receipient'])));
            unset($campaign['campaign_inbox_receipient']);
            $data['campaign'] = $campaign;
            $data['type'] = 'inbox';
            foreach (array_chunk($receipient_inbox, 300) as $recipients) {
                $data['recipient'] = array_filter($recipients, function ($var) {
                    return !empty($var);
                });
                SendCampaignJob::dispatch($data)->allOnConnection('campaignqueue');
            }
        }

        if ($campaign['campaign_media_whatsapp'] == "Yes") {
            $sendAt = date('Y-m-d H:i:s', strtotime("+ 5 minutes"));

            $receipient_whatsapp = explode(',', str_replace(' ', ',', str_replace(';', ',', $campaign['campaign_whatsapp_receipient'])));
            unset($campaign['campaign_whatsapp_receipient']);
            $data['campaign'] = $campaign;
            $data['type'] = 'whatsapp';
            foreach (array_chunk($receipient_whatsapp, 300) as $recipients) {
                $data['recipient'] = array_filter($recipients, function ($var) {
                    return !empty($var);
                });
                SendCampaignJob::dispatch($data)->allOnConnection('campaignqueue');
            }
        }
        $update = Campaign::where('id_campaign', '=', $campaign['id_campaign'])->update(['campaign_is_sent' => 'Yes']);
        return $update;
    }


    public function update(Request $request)
    {
        $post = $request->json()->all();

        $id_campaign = $post['id_campaign'];
        if (isset($post['campaign_email_receipient']) && $post['campaign_email_receipient'] != "") {
            $post['campaign_email_count_all'] = count(explode(',', $post['campaign_email_receipient']));
        }
        if (isset($post['campaign_sms_receipient']) && $post['campaign_sms_receipient'] != "") {
            $post['campaign_sms_count_all'] = count(explode(',', $post['campaign_sms_receipient']));
        }
        if (isset($post['campaign_push_receipient']) && $post['campaign_push_receipient'] != "") {
            $post['campaign_push_count_all'] = count(explode(',', $post['campaign_push_receipient']));
        }
        if (isset($post['campaign_inbox_receipient']) && $post['campaign_inbox_receipient'] != "") {
            $post['campaign_inbox_count'] = count(explode(',', $post['campaign_inbox_receipient']));
        }
        if (isset($post['campaign_whatsapp_receipient']) && $post['campaign_whatsapp_receipient'] != "") {
            $post['campaign_whatsapp_count_all'] = count(explode(',', $post['campaign_whatsapp_receipient']));
        }

        if (isset($post['campaign_push_image'])) {
            $upload = MyHelper::uploadPhoto($post['campaign_push_image'], $path = 'img/push/', 600);

            if ($upload['status'] == "success") {
                $post['campaign_push_image'] = $upload['path'];
            } else {
                $result = [
                        'status'    => 'fail',
                        'messages'  => ['Update Push Notification Image failed.']
                    ];
                return response()->json($result);
            }
        }

        $post['campaign_push_id_reference'] = $post['campaign_push_id_reference'] ?? null;

        $campaign = Campaign::where('id_campaign', $id_campaign)->first();
        if (empty($campaign)) {
            $result = [
                'status'    => 'fail',
                'messages'  => ['Campaign not found']
            ];
            return response()->json($result);
        }

        DB::beginTransaction();
        if ($campaign->campaign_generate_receipient == 'Now' || (empty($campaign->campaign_send_at) && $campaign->campaign_generate_receipient == 'Send At Time')) {
            GenerateCampaignRecipient::dispatch($post)->allOnConnection('campaignqueue');
        }
        if ($campaign->campaign_send_at && $campaign->campaign_send_at < date('Y-m-d H:i:s')) {
            $post['campaign_send_at'] = date('Y-m-d H:i:s');
        }
        unset($post['id_campaign']);
        $post['campaign_complete'] = 1;

        $contentWa = null;
        if (isset($post['campaign_whatsapp_content'])) {
            $contentWa = $post['campaign_whatsapp_content'];
            unset($post['campaign_whatsapp_content']);
        }

        $query = Campaign::where('id_campaign', '=', $id_campaign)->update($post);

        if ($query) {
            //whatsapp contents
            if ($contentWa) {
                //delete content
                $idOld = array_filter(array_pluck($contentWa, 'id_whatsapp_content'));
                $contentOld = WhatsappContent::where('source', 'campaign')->where('id_reference', $id_campaign)->whereNotIn('id_whatsapp_content', $idOld)->get();
                if (count($contentOld) > 0) {
                    foreach ($contentOld as $old) {
                        if ($old['content_type'] == 'image' || $old['content_type'] == 'file') {
                            $del = MyHelper::deletePhoto(str_replace(config('url.storage_url_api'), '', $old['content']));
                        }
                    }

                    $delete =  WhatsappContent::where('source', 'campaign')->where('id_reference', $id_campaign)->whereNotIn('id_whatsapp_content', $idOld)->delete();
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
                            if (!file_exists('whatsapp/img/campaign/')) {
                                mkdir('whatsapp/img/campaign/', 0777, true);
                            }

                            //upload file
                            $upload = MyHelper::uploadPhoto($content['content'], $path = 'whatsapp/img/campaign/');
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
                            if (!file_exists('whatsapp/file/campaign/')) {
                                mkdir('whatsapp/file/campaign/', 0777, true);
                            }

                            $i = 1;
                            $filename = $content['content_file_name'];
                            while (file_exists('whatsapp/file/campaign/' . $content['content_file_name'] . '.' . $content['content_file_ext'])) {
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

                        $dataContent['source']       = 'campaign';
                        $dataContent['id_reference'] = $id_campaign;
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
            $result = [
                    'status'  => 'success',
                    'result'  => $query
                ];
        } else {
            DB::rollBack();
            $result = [
                    'status'  => 'fail',
                    'messages'  => ['Campaign Update Failed']
                ];
        }
        return response()->json($result);
    }

    public function campaignEmailOutboxList(Request $request)
    {
        $post = $request->json()->all();

        $query = CampaignEmailSent::join('campaigns', 'campaigns.id_campaign', '=', 'campaign_email_sents.id_campaign')
                                    ->orderBy('id_campaign_email_sent', 'Desc');
        $count = CampaignEmailSent::join('campaigns', 'campaigns.id_campaign', '=', 'campaign_email_sents.id_campaign')->get();

        if (isset($post['email_sent_subject']) && $post['email_sent_subject'] != "") {
            $query = $query->where('email_sent_subject', 'like', '%' . $post['email_sent_subject'] . '%');
            $count = $count->where('email_sent_subject', 'like', '%' . $post['email_sent_subject'] . '%');
        }

        $query = $query->skip($post['skip'])->take($post['take'])->get()->toArray();
        $count = $count->count();

        if (isset($query) && !empty($query)) {
            $result = [
                    'status'  => 'success',
                    'result'  => $query,
                    'count'  => $count
                ];
        } else {
            $result = [
                    'status'  => 'fail',
                    'messages'  => ['No Campaign Email Outbox']
                ];
        }
        return response()->json($result);
    }

    public function campaignEmailOutboxDetail(Request $request)
    {
        $post = $request->json()->all();

        $query = CampaignEmailSent::join('campaigns', 'campaigns.id_campaign', '=', 'campaign_email_sents.id_campaign')
                                ->where('id_campaign_email_sent', $post['id_campaign_email_sent'])
                                ->first();

        if (isset($query) && !empty($query)) {
            $result = [
                    'status'  => 'success',
                    'result'  => $query
                ];
        } else {
            $result = [
                    'status'  => 'fail',
                    'messages'  => ['No Campaign Email Outbox']
                ];
        }
        return response()->json($result);
    }

    public function campaignSmsOutboxList(Request $request)
    {
        $post = $request->json()->all();

        $query = CampaignSmsSent::join('campaigns', 'campaigns.id_campaign', '=', 'campaign_sms_sents.id_campaign')
                                    ->orderBy('id_campaign_sms_sent', 'Desc');
        $count = CampaignSmsSent::join('campaigns', 'campaigns.id_campaign', '=', 'campaign_sms_sents.id_campaign')->get();

        if (isset($post['sms_sent_content']) && $post['sms_sent_content'] != "") {
            $query = $query->where('sms_sent_content', 'like', '%' . $post['sms_sent_content'] . '%');
            $count = $count->where('sms_sent_content', 'like', '%' . $post['sms_sent_content'] . '%');
        }

        $query = $query->skip($post['skip'])->take($post['take'])->get()->toArray();
        $count = $count->count();

        if (isset($query) && !empty($query)) {
            $result = [
                    'status'  => 'success',
                    'result'  => $query,
                    'count'  => $count
                ];
        } else {
            $result = [
                    'status'  => 'fail',
                    'messages'  => ['No Campaign SMS Outbox']
                ];
        }
        return response()->json($result);
    }

    public function campaignSmsOutboxDetail(Request $request)
    {
        $post = $request->json()->all();

        $query = CampaignSmsSent::join('campaigns', 'campaigns.id_campaign', '=', 'campaign_sms_sents.id_campaign')
                                ->where('id_campaign_sms_sent', $post['id_campaign_sms_sent'])
                                ->first();

        if (isset($query) && !empty($query)) {
            $result = [
                    'status'  => 'success',
                    'result'  => $query
                ];
        } else {
            $result = [
                    'status'  => 'fail',
                    'messages'  => ['No Campaign Sms Outbox']
                ];
        }
        return response()->json($result);
    }

    public function campaignPushOutboxList(Request $request)
    {
        $post = $request->json()->all();

        $query = CampaignPushSent::join('campaigns', 'campaigns.id_campaign', '=', 'campaign_push_sents.id_campaign')
                                    ->orderBy('id_campaign_push_sent', 'Desc');
        $count = CampaignPushSent::join('campaigns', 'campaigns.id_campaign', '=', 'campaign_push_sents.id_campaign');

        if (isset($post['push_sent_subject']) && $post['push_sent_subject'] != "") {
            $query = $query->where('push_sent_subject', 'like', '%' . $post['push_sent_subject'] . '%');
            $count = $count->where('push_sent_subject', 'like', '%' . $post['push_sent_subject'] . '%');
        }

        $query = $query->skip($post['skip'])->take($post['take'])->get()->toArray();
        $count = $count->count();

        if (isset($query) && !empty($query)) {
            $result = [
                    'status'  => 'success',
                    'result'  => $query,
                    'count'  => $count
                ];
        } else {
            $result = [
                    'status'  => 'fail',
                    'messages'  => ['No Campaign Push Notification Outbox']
                ];
        }
        return response()->json($result);
    }

    public function campaignPushOutboxDetail(Request $request)
    {
        $post = $request->json()->all();

        $query = CampaignPushSent::join('campaigns', 'campaigns.id_campaign', '=', 'campaign_push_sents.id_campaign')
                                ->where('id_campaign_push_sent', $post['id_campaign_push_sent'])
                                ->first();

        if (isset($query) && !empty($query)) {
            $result = [
                    'status'  => 'success',
                    'result'  => $query
                ];
        } else {
            $result = [
                    'status'  => 'fail',
                    'messages'  => ['No Campaign Push Notification Outbox']
                ];
        }
        return response()->json($result);
    }

    public function campaignWhatsappOutboxList(Request $request)
    {
        $post = $request->json()->all();

        $query = CampaignWhatsappSent::join('campaigns', 'campaigns.id_campaign', '=', 'campaign_whatsapp_sents.id_campaign')
                                    ->orderBy('campaign_whatsapp_sents.id_campaign_whatsapp_sent', 'Desc');
        $count = CampaignWhatsappSent::join('campaigns', 'campaigns.id_campaign', '=', 'campaign_whatsapp_sents.id_campaign')->get();

        if (isset($post['id_campaign_whatsapp_sent'])) {
            $query = $query->where('campaign_whatsapp_sents.id_campaign_whatsapp_sent', $post['id_campaign_whatsapp_sent'])
                           ->with('campaign_whatsapp_sent_content');
        } else {
            $query = $query->skip($post['skip'])->take($post['take']);
        }

        $query = $query->get()->toArray();
        $count = $count->count();

        if (isset($query) && !empty($query)) {
            $result = [
                    'status'  => 'success',
                    'result'  => $query,
                    'count'  => $count
                ];
        } else {
            $result = [
                    'status'  => 'fail',
                    'messages'  => ['No Campaign Whatsapp Outbox']
                ];
        }
        return response()->json($result);
    }

    public function destroy(Request $request)
    {
        $post = $request->json()->all();
        if (isset($post['id_campaign']) && !empty($post['id_campaign'])) {
            $check = Campaign::where('id_campaign', $post['id_campaign'])->first();

            if ($check['campaign_is_sent'] == 'Yes') {
                return response()->json(['status'  => 'fail','messages'  => ['Can not delete this campaign, the campaign has been sent.']]);
            } else {
                $delete = Campaign::where('id_campaign', $post['id_campaign'])->delete();
                if ($delete) {
                    $getId = CampaignRuleParent::where('id_campaign', $post['id_campaign'])->first();
                    CampaignRuleParent::where('id_campaign', $post['id_campaign'])->delete();
                    CampaignRule::where('id_campaign_rule_parent', $getId['id_campaign_rule_parent'])->delete();
                }
                return response()->json(MyHelper::checkDelete($delete));
            }
        } else {
            return response()->json(['status'  => 'fail','messages'  => ['Incompleted data']]);
        }
    }

    public function updatePushClickCount(Request $request)
    {
        $now = date("Y-m-d H:i:s");
        switch ($request->source) {
            case 'campaign':
                $phone = $request->user()->phone ?? null;
                $campaign = CampaignPushSent::where('id_campaign', $request->id_notif)
                        ->where('push_sent_to', $phone)
                        ->whereNull('click_at');

                if (!$campaign) {
                    \Log::error([
                        'campaign click count error' => [
                            'status' => 'fail',
                            'messages' => $request->all()
                        ]
                    ]);
                } else {
                    $campaign->update(['click_at' => $now]);
                }

                break;

            case 'promotion':
                $update = PromotionSent::where('id_promotion_sent', $request->id_notif)->whereNull('push_click_at')->update(['push_click_at' => $now]);
                break;

            default:
                # code...
                break;
        }

        return ['status' => 'success'];
    }

    public function campaignPushOutboxListV2(Request $request)
    {
        $post = $request->json()->all();

        $query = CampaignPushSent::join('campaigns', 'campaigns.id_campaign', '=', 'campaign_push_sents.id_campaign')->orderBy('id_campaign_push_sent', 'Desc');

        if (isset($post['push_sent_subject']) && $post['push_sent_subject'] != "") {
            $query = $query->where('push_sent_subject', 'like', '%' . $post['push_sent_subject'] . '%');
        }

        $query = $query->paginate(15)->toArray();

        if (isset($query) && !empty($query)) {
            $result = [
                    'status'  => 'success',
                    'result'  => $query
                ];
        } else {
            $result = [
                    'status'  => 'fail',
                    'messages'  => ['No Campaign Push Notification Outbox']
                ];
        }
        return response()->json($result);
    }
}
