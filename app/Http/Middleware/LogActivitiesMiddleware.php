<?php

namespace App\Http\Middleware;

use Closure;
use App\Http\Models\LogActivitiesApps;
use App\Http\Models\LogActivitiesBE;
use App\Lib\MyHelper;
use Auth;

class LogActivitiesMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $arrReq = $request->except('_token');
        if (!isset($arrReq['log_save']) || ($arrReq['log_save'] == true && $arrReq['log_save'] == 'true')) {
            if (!isset($arrReq['page']) || (int)$arrReq['page'] <= 1) {
                $ruser = $request->user();
                if (!$ruser) {
                    $ruser = auth('api')->user();
                }
                $url = $request->url();
                $user = json_decode(json_encode($ruser), true);
                $st = stristr(json_encode($response), 'success');
                $status = 'fail';
                if ($st) {
                    $status = 'success';
                }
                $reqnya = $request->json()->all();
                if (isset($reqnya['pin'])) {
                    $reqnya['pin'] = "******";
                }
                if (isset($reqnya['pin_old'])) {
                    $reqnya['pin'] = "******";
                }
                if (isset($reqnya['pin_new'])) {
                    $reqnya['pin'] = "******";
                }
                $requestnya = json_encode($reqnya);
                $requeste = json_decode($requestnya, true);

                $phone = null;
                if (isset($requeste['phone'])) {
                    $phone = $requeste['phone'];
                }

                if (isset($user['phone'])) {
                    $phone = $user['phone'];
                }

                if (Auth::user()) {
                    $datauser = Auth::user();
                    if (isset($datauser['phone'])) {
                        $phone = $datauser['phone'];
                    }
                }

                $id_store = null;

                if ($requestnya == '[]') {
                    $requestnya = null;
                }
                $urlexp = explode('/', $url);

                if (isset($urlexp[6])) {
                    $module = $urlexp[6];
                } elseif (isset($urlexp[4])) {
                    $module = $urlexp[4];
                }
                if (stristr($url, 'transaction')) {
                    $module = 'Transaction';
                }
                if (stristr($url, 'outlet/filter')) {
                    $module = 'Outlet';
                }
                if (stristr($url, 'gofood')) {
                    $module = 'Banner Go-Food';
                }
                if (stristr($url, 'users')) {
                    $module = 'User';
                }
                if (stristr($url, 'v1/pos')) {
                    $module = 'POS';
                }
                if (stristr($url, 'subscription')) {
                    $module = 'Subscription';
                }

                $subject = "Unknown";

                //subscription
                if (stristr($url, 'subscription')) {
                    $subject = 'Subscription';
                }

                //autocrm
                if (stristr($url, 'autocrm')) {
                    $subject = 'Autocrm';
                }

                //balance
                if (stristr($url, 'balance')) {
                    $subject = 'Balance';
                }

                //campaign
                if (stristr($url, 'campaign')) {
                    $subject = 'Campaign';
                }

                //deals
                if (stristr($url, 'deals')) {
                    $subject = 'Deals';
                }

                //enquiry
                if (stristr($url, 'enquiries')) {
                    $subject = 'Enquiry';
                }

                //membership
                if (stristr($url, 'membership')) {
                    $subject = 'Membership';
                }

                //news
                if (stristr($url, 'news')) {
                    $subject = 'News';
                }

                //outlet
                if (stristr($url, 'outlet')) {
                    $subject = 'Outlet';
                }
                if (stristr($url, 'outlet/nearme')) {
                    $subject = 'Outlet Near Me';
                }

                //product
                if (stristr($url, 'product')) {
                    $subject = 'Product';
                }

                //promotion
                if (stristr($url, 'promotion')) {
                    $subject = 'Promotion';
                }

                //report
                if (stristr($url, 'report')) {
                    $subject = 'Report';
                }

                //reward
                if (stristr($url, 'reward')) {
                    $subject = 'Reward';
                }

                //setting
                if (stristr($url, 'setting')) {
                    if (isset($requeste['key'])) {
                        $subject = ucfirst($requeste['key']);
                    } else {
                        $subject = 'Setting';
                    }
                }
                if (stristr($url, 'faq')) {
                    $subject = 'FAQ';
                }

                //fraud
                if (stristr($url, 'fraud')) {
                    $subject = 'Fraud';
                }

                //spin the wheel
                if (stristr($url, 'spinthewheel')) {
                    $subject = 'Spin The Wheel';
                }

                //transaction
                if (stristr($url, 'transaction')) {
                    $subject = 'Transaction';
                }
                if (stristr($url, 'balance')) {
                    $subject = 'Point';
                }

                //user
                if (stristr($url, 'user')) {
                    $subject = 'User';
                }

                //inbox
                if (stristr($url, 'inbox')) {
                    $subject = 'Inbox';
                }

                //home
                if (stristr($url, 'home')) {
                    $subject = 'Home';
                }
                if (stristr($url, 'home/refresh-point-balance')) {
                    $subject = 'Refresh Home';
                }

                //profile
                if (stristr($url, 'profile')) {
                    $subject = 'Profile';
                }
                if (stristr($url, 'complete-profile')) {
                    $subject = 'Complete Profile';
                }

                //voucher
                if (stristr($url, 'voucher')) {
                    $subject = 'Voucher';
                }
                if (stristr($url, 'voucher/me')) {
                    $subject = 'My Voucher';
                }
                if (stristr($url, 'invalidate')) {
                    $subject = 'Invalidate Voucher';
                }

                //Order
                if (stristr($url, 'pos/order')) {
                    $subject = 'POS Order';
                }
                if (stristr($url, 'pos/order/detail')) {
                    $subject = 'POS Order Detail';
                }
                if (stristr($url, 'pos/order/accept')) {
                    $subject = 'POS Order Accept';
                }
                if (stristr($url, 'pos/order/ready')) {
                    $subject = 'POS Order Ready';
                }
                if (stristr($url, 'pos/order/taken')) {
                    $subject = 'POS Order taken';
                }
                if (stristr($url, 'pos/order/reject')) {
                    $subject = 'POS Order reject';
                }
                if (stristr($url, 'pos/product')) {
                    $subject = 'POS Order Product';
                }
                if (stristr($url, 'pos/product/sold-out')) {
                    $subject = 'POS Order Sold-out';
                }

                if (stristr($url, 'quest')) {
                    $subject = 'Quest';
                }
                if (stristr($url, 'quest/list')) {
                    $subject = 'Quest List';
                }
                if (stristr($url, 'quest/me')) {
                    $subject = 'My Quest';
                }
                if (stristr($url, 'quest/take')) {
                    $subject = 'Claim Quest';
                }
                if (stristr($url, 'quest/status')) {
                    $subject = 'Quest Status';
                }
                if (stristr($url, 'quest/detail-apps')) {
                    $subject = 'Detail Quest';
                }
                if (stristr($url, 'quest/claim-benefit')) {
                    $subject = 'Claim Quest Benefit';
                }

                //CRUD
                if (stristr($url, 'create') || (stristr($url, 'new') && !stristr($url, 'news') )) {
                    if ($subject) {
                        $subject = 'Create ' . $subject;
                    }
                }
                if (stristr($url, 'update')) {
                    if ($subject) {
                        $subject = 'Update ' . $subject;
                    }
                }
                if (stristr($url, 'delete')) {
                    if ($subject) {
                        $subject = 'Delete ' . $subject;
                    }
                }

                if (stristr($url, 'detail')) {
                    if ($subject) {
                        $subject = $subject . ' Detail';
                    }
                }

                if (stristr($url, 'webview')) {
                    if ($subject) {
                        $subject = 'Webview ' . $subject;
                    }
                }

                if (stristr($url, 'list')) {
                    if (stristr($url, 'webview')) {
                        if ($subject) {
                            $subject = 'Webview ' . $subject;
                        }
                    } else {
                        if ($subject) {
                            $subject = $subject . ' List';
                        }
                    }
                }
                if (stristr($url, 'filter')) {
                    if ($subject) {
                        $subject = $subject . ' Filter';
                    }
                }

                if (stristr($url, 'history-balance')) {
                    $subject = 'History Point';
                }
                if (stristr($url, 'history-trx')) {
                    $subject = 'History Transaction';
                }
                if (stristr($url, 'history-ongoing')) {
                    $subject = 'On Going History';
                }

                if (stristr($url, 'users/pin/create')) {
                    $subject = 'User Register';
                }
                if (stristr($url, 'users/pin/check')) {
                    $subject = 'User Login Attempt';
                }
                if (stristr($url, 'users/phone/check')) {
                    $subject = 'Phone Check';
                }
                if (stristr($url, 'users/pin/resend')) {
                    $subject = 'Resend PIN';
                }
                if (stristr($url, 'users/pin/forgot')) {
                    $subject = 'Forgot PIN';
                }
                if (stristr($url, 'users/pin/change')) {
                    $subject = 'Change PIN';
                }
                if (stristr($url, 'users/pin/verify')) {
                    $subject = 'Verify PIN';
                }
                if (stristr($url, 'product/delete')) {
                    $subject = 'Delete Product';
                    $module = $urlexp[4];
                }

                if (stristr($url, 'ready')) {
                    $subject = 'Order Ready';
                }
                if (stristr($url, 'taken')) {
                    $subject = 'Order Taken';
                }
                if (stristr($url, 'reject')) {
                    $subject = 'Order Reject';
                }

                if (!empty($request->header('ip-address-view'))) {
                    $ip = $request->header('ip-address-view');
                } else {
                    $ip = $request->ip();
                }

                $beStatusUserAgent = 0;
                if (!empty($request->header('user-agent-view'))) {
                    $userAgent = $request->header('user-agent-view');
                    if (stristr($userAgent, 'iOS') || stristr($userAgent, 'okhttp')) {
                    } else {
                        if (!stristr($url, 'complete-profile')) {
                            $beStatusUserAgent = 1;
                        }
                    }
                } else {
                    $userAgent = $request->header('user-agent');
                }

                if (!empty($user) && $user != "") {
                    $data = [
                    'module'            => ucwords($module),
                    'url'           => $url,
                    'subject'           => $subject,
                    'phone'             => $phone,
                    'user'          => MyHelper::encrypt2019(json_encode($ruser)),
                    // 'user'           => json_encode($ruser),
                    'request'       => MyHelper::encrypt2019($requestnya),
                    // 'request'            => $requestnya,
                    'response_status'           => $status,
                    'response'               => MyHelper::encrypt2019(json_encode($response)),
                    // 'response'                  => json_encode($response),
                    'ip'                        => $ip,
                    'useragent'                 => $userAgent
                    ];
                } else {
                    $data = [
                        'module'        => ucwords($module),
                        'url'           => $url,
                        'subject'       => $subject,
                        'phone'         => $phone,
                        'user'          => null,
                        // 'request'        => $requestnya,
                        'request'       => MyHelper::encrypt2019($requestnya),
                        'response_status'       => $status,
                        'response'      => MyHelper::encrypt2019(json_encode($response)),
                        // 'response'       => json_encode($response),
                        'ip'            => $ip,
                        'useragent'         => $userAgent
                      ];
                }

                if ($beStatusUserAgent == 1) {
                    if (stristr($url, 'activity')) {
                        $data = [
                        'module'            => ucwords($module),
                        'url'           => $url,
                        'subject'           => $subject,
                        'phone'             => $phone,
                        'user'          => MyHelper::encrypt2019(json_encode($ruser)),
                        // 'user'           => json_encode($ruser),
                        'request'       => MyHelper::encrypt2019($requestnya),
                        // 'request'            => $requestnya,
                        'response_status'           => $status,
                        'response'               => MyHelper::encrypt2019(json_encode('success')),
                        // 'response'                  => json_encode($response),
                        'ip'                        => $ip,
                        'useragent'                 => $userAgent
                        ];
                    }
                    $log = LogActivitiesBE::create($data);
                } else {
                    $log = LogActivitiesApps::create($data);
                }
            }
        }
        return $response;
    }
}
